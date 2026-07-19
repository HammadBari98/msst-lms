<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// =======================================================
// AUTO-PATCHER: assessments schema drift (section_id/subject_id)
// =======================================================
try {
    $existing_cols = $pdo->query("SHOW COLUMNS FROM assessments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('section_id', $existing_cols)) {
        $pdo->exec("ALTER TABLE assessments ADD COLUMN section_id INT DEFAULT 0 AFTER class_id");
    }
    if (!in_array('subject_id', $existing_cols)) {
        $pdo->exec("ALTER TABLE assessments ADD COLUMN subject_id INT DEFAULT 0 AFTER section_id");
    }
} catch (PDOException $e) { /* Ignore if already up to date */ }

// Fetch all active students for the Directory View
$stmt_students = $pdo->query("
    SELECT u.id, u.full_name, u.user_id_string, c.class_name, sec.section_name,
           (SELECT COUNT(*) FROM student_scores WHERE student_id = u.id) as total_assessments
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN student_details sd ON u.id = sd.user_id
    LEFT JOIN classes c ON sd.class_id = c.id
    LEFT JOIN sections sec ON sd.section_id = sec.id
    WHERE r.role_name = 'Student' AND u.status = 'Active'
    ORDER BY c.class_name, sec.section_name, u.full_name
");
$all_students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

$selected_student_id = $_GET['student_id'] ?? null;
$student_scores = [];
$student_info = null;
$overall_percentage = 0;
$total_possible = 0;
$total_obtained = 0;

if ($selected_student_id) {
    // Get the selected student's basic info
    $stmt_info = $pdo->prepare("
        SELECT u.full_name, u.user_id_string, c.class_name, sec.section_name
        FROM users u
        LEFT JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN classes c ON sd.class_id = c.id
        LEFT JOIN sections sec ON sd.section_id = sec.id
        WHERE u.id = ?
    ");
    $stmt_info->execute([$selected_student_id]);
    $student_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    // Get ALL individual scores for this student across all subjects/teachers
    $stmt_scores = $pdo->prepare("
        SELECT a.title, a.subject_name, a.assessment_type, a.assessment_date, a.total_marks, 
               ss.marks_obtained, ss.remarks, tu.full_name as teacher_name
        FROM student_scores ss
        JOIN assessments a ON ss.assessment_id = a.id
        JOIN users tu ON a.teacher_id = tu.id
        WHERE ss.student_id = ?
        ORDER BY a.assessment_date DESC
    ");
    $stmt_scores->execute([$selected_student_id]);
    $student_scores = $stmt_scores->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals for the summary card
    foreach($student_scores as $s) {
        $total_possible += $s['total_marks'];
        $total_obtained += $s['marks_obtained'];
    }
    if($total_possible > 0) {
        $overall_percentage = round(($total_obtained / $total_possible) * 100, 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Reports | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .print-header { display: none; text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .print-header h2 { font-weight: 800; font-family: "Times New Roman", Times, serif; color: #1a365d; }
        .print-subheader { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: bold; margin-top: 10px; }

        @media print {
            body { background-color: white; font-size: 12pt; }
            .sidebar, .topbar, .footer, .no-print { display: none !important; }
            #main-content, .content-wrapper { margin: 0 !important; padding: 0 !important; width: 100%; }
            .card { box-shadow: none !important; border: none !important; }
            .print-header { display: block; }
            table th { background-color: #e9ecef !important; -webkit-print-color-adjust: exact; }
            .table-responsive { overflow: visible !important; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div id="main-content">
        <?php include 'header.php'; ?>

        <div class="content-wrapper">
            <div class="container-fluid">

                <?php if (!$selected_student_id): ?>
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Student Scoring Directory</h1>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white">
                        <h6 class="m-0 font-weight-bold text-primary">Select a Student to View Their Report</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="studentDirectoryTable" width="100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Full Name</th>
                                        <th>Class</th>
                                        <th class="text-center">Total Assessments Taken</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_students as $st): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($st['user_id_string']) ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($st['full_name']) ?></td>
                                        <td>Class <?= htmlspecialchars($st['class_name']) . ($st['section_name'] ? ' (' . htmlspecialchars($st['section_name']) . ')' : '') ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $st['total_assessments'] > 0 ? 'info' : 'secondary' ?> fs-6">
                                                <?= $st['total_assessments'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="?student_id=<?= $st['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-chart-bar me-1"></i> View Report
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
                    <h1 class="h3 mb-0 text-gray-800">Individual Student Report Card</h1>
                    <div>
                        <?php if ($student_info): ?>
                        <button onclick="window.print()" class="btn btn-dark shadow-sm me-2">
                            <i class="fas fa-print me-1"></i> Print Report Card
                        </button>
                        <?php endif; ?>
                        <a href="student-report.php" class="btn btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Directory
                        </a>
                    </div>
                </div>

                <?php if ($student_info): ?>

                <!-- Official Print Header -->
                <div class="print-header">
                    <h2><i class="fas fa-graduation-cap me-2 text-dark"></i>Muhaddisa School of Science & Technology</h2>
                    <div class="print-subheader">
                        <span><?= htmlspecialchars($student_info['full_name']) ?> (ID: <?= htmlspecialchars($student_info['user_id_string']) ?>)</span>
                        <span class="text-dark">Individual Report Card</span>
                        <span style="color: #dc3545;">Class <?= htmlspecialchars($student_info['class_name']) . ($student_info['section_name'] ? ' (' . htmlspecialchars($student_info['section_name']) . ')' : '') ?></span>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6">
                        <div class="card shadow h-100 py-2" style="border-left: 4px solid <?= $overall_percentage >= 50 ? '#1cc88a' : '#e74a3b' ?>;">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: <?= $overall_percentage >= 50 ? '#1cc88a' : '#e74a3b' ?>;">
                                            Overall Performance (All Subjects)
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $overall_percentage ?>%</div>
                                        <small class="text-muted">Marks: <?= $total_obtained ?> / <?= $total_possible ?></small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Scores for: <?= htmlspecialchars($student_info['full_name']) ?> (ID: <?= htmlspecialchars($student_info['user_id_string']) ?>)
                        </h6>
                        <span class="badge bg-dark">Class <?= htmlspecialchars($student_info['class_name']) . ($student_info['section_name'] ? ' (' . htmlspecialchars($student_info['section_name']) . ')' : '') ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (count($student_scores) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="individualScoresTable" width="100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Assessment Title</th>
                                        <th>Teacher</th>
                                        <th class="text-center">Marks Obtained</th>
                                        <th class="text-center">Percentage</th>
                                        <th>Teacher's Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student_scores as $score): 
                                        $pct = $score['total_marks'] > 0 ? round(($score['marks_obtained'] / $score['total_marks']) * 100, 1) : 0;
                                        $badge_color = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning text-dark' : 'danger');
                                    ?>
                                    <tr>
                                        <td><span class="d-none"><?= strtotime($score['assessment_date']) ?></span><?= date('d M Y', strtotime($score['assessment_date'])) ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($score['subject_name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($score['title']) ?><br>
                                            <span class="badge bg-secondary" style="font-size: 0.7rem;"><?= $score['assessment_type'] ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($score['teacher_name']) ?></td>
                                        <td class="text-center fw-bold">
                                            <?= floatval($score['marks_obtained']) ?> / <?= floatval($score['total_marks']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $badge_color ?>"><?= $pct ?>%</span>
                                        </td>
                                        <td class="text-muted small"><?= htmlspecialchars($score['remarks']) ?: '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3 text-gray-300"></i>
                                <h5>No Individual Markings Found</h5>
                                <p>Teachers have not submitted any graded assessments for this student yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>

        <?php include 'footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            if ($('#studentDirectoryTable').length) {
                $('#studentDirectoryTable').DataTable({
                    "pageLength": 15,
                    "language": { "search": "Search Student:" }
                });
            }
            if ($('#individualScoresTable').length) {
                $('#individualScoresTable').DataTable({
                    "pageLength": 10,
                    "order": [[0, 'desc']] // Sort by Date descending
                });
            }
        });

        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');
        
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show-sidebar');
                }
            });
        }
    </script>
</body>
</html>