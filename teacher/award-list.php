<?php
session_start();
require_once '../config/db_config.php';

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: ../login.php');
    exit();
}

$teacher_session_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];

// --- Convert String ID to Integer ID ---
$stmt_get_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
$stmt_get_id->execute([$teacher_session_id, $teacher_session_id]);
$teacher_id = $stmt_get_id->fetchColumn();

// Get Teacher's Assigned Classes
$stmt_classes = $pdo->prepare("SELECT c.id, c.class_name FROM classes c JOIN teacher_class_assignments tca ON c.id = tca.class_id WHERE tca.teacher_user_id = ?");
$stmt_classes->execute([$teacher_id]);
$assigned_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Handle Filter Request
$class_id = $_GET['class_id'] ?? '';
$exam_type = $_GET['exam_type'] ?? 'Monthly Test';
$exam_month = $_GET['exam_month'] ?? date('Y-m');

$award_list = [];
$subjects = [];
$class_name = '';
$grand_total_max_marks = 0;

if ($class_id && $exam_type && $exam_month) {
    // 1. Get Class Name
    $stmt_cname = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
    $stmt_cname->execute([$class_id]);
    $class_name = $stmt_cname->fetchColumn();

    // 2. Get Assessments (Subjects) for this specific Exam
    $stmt_assessments = $pdo->prepare("
        SELECT id, subject_name, total_marks 
        FROM assessments 
        WHERE class_id = ? AND assessment_type = ? AND assessment_date LIKE ?
        ORDER BY subject_name ASC
    ");
    // Append '%' to match the year-month (e.g. '2026-05%')
    $stmt_assessments->execute([$class_id, $exam_type, $exam_month . '%']);
    $assessments = $stmt_assessments->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($assessments)) {
        // Prepare Subject Headers and calculate Grand Total Max Marks
        foreach ($assessments as $asm) {
            $subjects[$asm['id']] = [
                'name' => $asm['subject_name'],
                'max_marks' => $asm['total_marks']
            ];
            $grand_total_max_marks += $asm['total_marks'];
        }

        // 3. Get all active students in this class
        $stmt_students = $pdo->prepare("
            SELECT u.id, u.full_name, sd.father_name 
            FROM users u 
            JOIN student_details sd ON u.id = sd.user_id 
            WHERE sd.class_id = ? AND u.status = 'Active'
        ");
        $stmt_students->execute([$class_id]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        // 4. Get all scores for these assessments
        $asm_ids = array_keys($subjects);
        $placeholders = str_repeat('?,', count($asm_ids) - 1) . '?';
        $stmt_scores = $pdo->prepare("SELECT student_id, assessment_id, marks_obtained FROM student_scores WHERE assessment_id IN ($placeholders)");
        $stmt_scores->execute($asm_ids);
        
        // Map scores for quick lookup
        $scores_map = [];
        while ($row = $stmt_scores->fetch(PDO::FETCH_ASSOC)) {
            $scores_map[$row['student_id']][$row['assessment_id']] = $row['marks_obtained'];
        }

        // 5. Compile the Matrix
        foreach ($students as $stu) {
            $stu_row = [
                'id' => $stu['id'],
                'name' => $stu['full_name'],
                'father_name' => $stu['father_name'],
                'scores' => [],
                'total_obtained' => 0
            ];

            foreach ($subjects as $asm_id => $sub) {
                $score = $scores_map[$stu['id']][$asm_id] ?? null;
                $stu_row['scores'][$asm_id] = $score;
                if (is_numeric($score)) {
                    $stu_row['total_obtained'] += $score;
                }
            }

            // Calculate Percentage
            $stu_row['percentage'] = $grand_total_max_marks > 0 ? round(($stu_row['total_obtained'] / $grand_total_max_marks) * 100, 2) : 0;
            
            $award_list[] = $stu_row;
        }

        // 6. Rank Students (Dense Ranking Algorithm)
        // Sort by total marks descending
        usort($award_list, function($a, $b) {
            return $b['total_obtained'] <=> $a['total_obtained'];
        });

        $rank = 1;
        $prev_marks = null;
        foreach ($award_list as &$sr) {
            if ($prev_marks !== null && $sr['total_obtained'] < $prev_marks) {
                $rank++;
            }
            $sr['position'] = $rank;
            $prev_marks = $sr['total_obtained'];
        }
        unset($sr);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award List | Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        :root { --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
        
        /* Award List Specific Print CSS */
        .award-table th, .award-table td { vertical-align: middle; text-align: center; border: 1px solid #dee2e6; }
        .award-table th { background-color: #f1f3f5 !important; font-weight: bold; }
        .text-absent { color: #dc3545; font-weight: bold; }
        
        .print-header { display: none; text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .print-header h2 { font-weight: 800; font-family: "Times New Roman", Times, serif; color: #1a365d; }
        .print-subheader { display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-top: 10px; }

        @media print {
            body { background-color: white; font-size: 12pt; }
            .sidebar, .topbar, .footer, .no-print { display: none !important; }
            #main-content, .content-wrapper { margin: 0 !important; padding: 0 !important; width: 100%; }
            .card { box-shadow: none !important; border: none !important; }
            .print-header { display: block; }
            .award-table th { background-color: #e9ecef !important; -webkit-print-color-adjust: exact; }
            .table-responsive { overflow: visible !important; }
            @page { size: landscape; margin: 1cm; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h1 class="h3 text-gray-800 fw-bold"><i class="fas fa-award text-warning me-2"></i>Examination Award List</h1>
                    <a href="manage-scores.php" class="btn btn-outline-secondary fw-bold shadow-sm"><i class="fas fa-arrow-left me-1"></i> Back to Scores</a>
                </div>

                <!-- Filter Card -->
                <div class="card shadow-sm border-0 mb-4 no-print">
                    <div class="card-body bg-white rounded">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-secondary">Target Class</label>
                                <select name="class_id" class="form-select border-primary" required>
                                    <option value="">Select Class...</option>
                                    <?php foreach($assigned_classes as $cls): ?>
                                        <option value="<?= $cls['id'] ?>" <?= $class_id == $cls['id'] ? 'selected' : '' ?>>Class <?= htmlspecialchars($cls['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-secondary">Exam Type</label>
                                <select name="exam_type" class="form-select border-primary" required>
                                    <option value="Monthly Test" <?= $exam_type == 'Monthly Test' ? 'selected' : '' ?>>Monthly Test</option>
                                    <option value="Mid Term" <?= $exam_type == 'Mid Term' ? 'selected' : '' ?>>Mid Term Result</option>
                                    <option value="Final Exam" <?= $exam_type == 'Final Exam' ? 'selected' : '' ?>>Final Exam</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-secondary">Exam Month & Year</label>
                                <input type="month" name="exam_month" class="form-control border-primary" value="<?= htmlspecialchars($exam_month) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm"><i class="fas fa-filter me-2"></i> Generate Award List</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Award List Display Area -->
                <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['class_id'])): ?>
                    
                    <?php if (empty($assessments)): ?>
                        <div class="alert alert-warning shadow-sm border-0 text-center p-5 no-print">
                            <i class="fas fa-folder-open fa-4x mb-3 text-warning opacity-50"></i>
                            <h4 class="fw-bold text-dark">No Records Found</h4>
                            <p class="mb-0">There are no subject assessments matching "<?= htmlspecialchars($exam_type) ?>" for <?= date('F Y', strtotime($exam_month)) ?> in this class.<br>Please ensure teachers have created their assessments under `Manage Scores` using this exact Month and Type.</p>
                        </div>
                    <?php else: ?>
                        <div class="card shadow border-0">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center no-print">
                                <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-file-invoice me-2"></i> Result Compiled Successfully</h6>
                                <button onclick="window.print()" class="btn btn-sm btn-dark fw-bold"><i class="fas fa-print me-1"></i> Print Award List</button>
                            </div>
                            <div class="card-body p-0 p-md-4 bg-white">
                                
                                <!-- Official Print Header -->
                                <div class="print-header">
                                    <h2><i class="fas fa-graduation-cap me-2 text-dark"></i>Muhaddisa School of Science & Technology</h2>
                                    <div class="print-subheader">
                                        <span><?= date('F Y', strtotime($exam_month)) ?></span>
                                        <span class="text-dark"><?= htmlspecialchars($exam_type) ?> Result</span>
                                        <span style="color: #dc3545;">Class <?= htmlspecialchars($class_name) ?></span>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm award-table mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 5%;">S.NO</th>
                                                <th class="text-start ps-3" style="width: 15%;">Name</th>
                                                <th class="text-start ps-3" style="width: 15%;">Father's Name</th>
                                                
                                                <!-- Dynamic Subject Headers -->
                                                <?php foreach ($subjects as $sub): ?>
                                                    <th>
                                                        <?= htmlspecialchars($sub['name']) ?><br>
                                                        <span class="small fw-normal text-muted">(<?= $sub['max_marks'] ?>)</span>
                                                    </th>
                                                <?php endforeach; ?>
                                                
                                                <th>Total<br>Marks</th>
                                                <th>%age</th>
                                                <th>Position</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $sno = 1; foreach ($award_list as $row): ?>
                                                <tr>
                                                    <td class="fw-bold"><?= $sno++ ?></td>
                                                    <td class="text-start ps-3 fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></td>
                                                    <td class="text-start ps-3"><?= htmlspecialchars($row['father_name'] ?? '-') ?></td>
                                                    
                                                    <!-- Dynamic Subject Scores -->
                                                    <?php foreach ($subjects as $asm_id => $sub): ?>
                                                        <?php $score = $row['scores'][$asm_id]; ?>
                                                        <td>
                                                            <?php if ($score === null || $score === ''): ?>
                                                                <span class="text-absent">A</span>
                                                            <?php else: ?>
                                                                <?= floatval($score) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    
                                                    <td class="fw-bold bg-light"><?= floatval($row['total_obtained']) ?> <small class="text-muted fw-normal">/ <?= $grand_total_max_marks ?></small></td>
                                                    <td class="fw-bold text-primary"><?= number_format($row['percentage'], 2) ?>%</td>
                                                    <td class="fw-bold text-success fs-6"><?= $row['position'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.getElementById('main-content');
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show-sidebar');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    </script>
</body>
</html>