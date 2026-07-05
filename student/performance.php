<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

$student_name = $_SESSION['student_name'] ?? 'Student';

// --- Safe Student ID Translator ---
$current_user_db_id = 0;
if (!empty($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_id']]);
    $current_user_db_id = $stmt->fetchColumn();
}
if (!$current_user_db_id && !empty($_SESSION['student_email'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_email']]);
    $current_user_db_id = $stmt->fetchColumn();
}
if (!$current_user_db_id && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $current_user_db_id = (int)$_SESSION['user_id'];
}

// =======================================================
// FETCH STUDENT EXAM REPORTS
// =======================================================
$student_class_id = 0;
$class_name = '';

// Pre-define our groups for the UX Layout
$grouped_reports = [
    'Monthly Test' => [],
    'Mid Term' => [],
    'Final Exam' => [],
    'Other' => []
];

if ($current_user_db_id > 0) {
    // 1. Get Class + Section
    $stmt_class = $pdo->prepare("SELECT sd.class_id, sd.section_id, c.class_name FROM student_details sd JOIN classes c ON sd.class_id = c.id WHERE sd.user_id = ? LIMIT 1");
    $stmt_class->execute([$current_user_db_id]);
    $class_info = $stmt_class->fetch(PDO::FETCH_ASSOC);

    if ($class_info) {
        $student_class_id = $class_info['class_id'];
        $student_section_id = (int)($class_info['section_id'] ?? 0);
        $class_name = $class_info['class_name'];

        // 2. Find all Official Exams for this class + section
        $stmt_exams = $pdo->prepare("
            SELECT DISTINCT assessment_type, DATE_FORMAT(assessment_date, '%Y-%m') as exam_month
            FROM assessments
            WHERE class_id = ? AND IFNULL(section_id, 0) = ?
            ORDER BY exam_month DESC
        ");
        $stmt_exams->execute([$student_class_id, $student_section_id]);
        $available_exams = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);

        // 3. Process each exam
        foreach ($available_exams as $exam) {
            $type = $exam['assessment_type'];
            $month = $exam['exam_month'];

            // Determine Grouping Category
            $category = in_array($type, ['Monthly Test', 'Mid Term', 'Final Exam']) ? $type : 'Other';

            // Get Subjects & Max Marks (scoped to this student's own section, so rank is computed against section-mates only)
            $stmt_assm = $pdo->prepare("SELECT id, subject_name, total_marks FROM assessments WHERE class_id = ? AND IFNULL(section_id, 0) = ? AND assessment_type = ? AND assessment_date LIKE ? ORDER BY subject_name ASC");
            $stmt_assm->execute([$student_class_id, $student_section_id, $type, $month . '%']);
            $assessments = $stmt_assm->fetchAll(PDO::FETCH_ASSOC);

            if (empty($assessments)) continue;

            $subjects = [];
            $grand_total_max = 0;
            $asm_ids = [];
            foreach ($assessments as $asm) {
                $subjects[$asm['id']] = ['name' => $asm['subject_name'], 'max' => $asm['total_marks']];
                $grand_total_max += $asm['total_marks'];
                $asm_ids[] = $asm['id'];
            }

            // Get All Scores for Rank Calculation
            $placeholders = str_repeat('?,', count($asm_ids) - 1) . '?';
            $stmt_all_scores = $pdo->prepare("SELECT student_id, assessment_id, marks_obtained FROM student_scores WHERE assessment_id IN ($placeholders)");
            $stmt_all_scores->execute($asm_ids);
            
            $class_totals = [];
            $my_scores = [];
            
            while ($row = $stmt_all_scores->fetch(PDO::FETCH_ASSOC)) {
                $s_id = $row['student_id'];
                $val = is_numeric($row['marks_obtained']) ? floatval($row['marks_obtained']) : 0;
                
                if (!isset($class_totals[$s_id])) $class_totals[$s_id] = 0;
                $class_totals[$s_id] += $val;

                if ($s_id == $current_user_db_id) {
                    $my_scores[$row['assessment_id']] = $row['marks_obtained'];
                }
            }

            // Calculate My Rank (Dense Ranking)
            rsort($class_totals); 
            $class_totals = array_values(array_unique($class_totals)); 
            
            $my_total = 0;
            foreach ($my_scores as $score) { if(is_numeric($score)) $my_total += floatval($score); }
            
            $my_rank = array_search($my_total, $class_totals) !== false ? (array_search($my_total, $class_totals) + 1) : '-';
            $my_percentage = $grand_total_max > 0 ? round(($my_total / $grand_total_max) * 100, 2) : 0;

            // Save report into the correct category
            $grouped_reports[$category][] = [
                'title' => $type,
                'date' => date('F Y', strtotime($month)),
                'subjects' => $subjects,
                'my_scores' => $my_scores,
                'my_total' => $my_total,
                'max_total' => $grand_total_max,
                'percentage' => $my_percentage,
                'rank' => $my_rank
            ];
        }
    }
}

// Check if there are absolutely zero records anywhere
$has_any_records = false;
foreach ($grouped_reports as $group) {
    if (!empty($group)) { $has_any_records = true; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Performance | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --light-bg: #f8f9fc; --primary: #4e73df; --warning: #f6c23e; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
        
        /* Custom Tabs Styling */
        .nav-pills .nav-link { color: #5a5c69; font-weight: 600; border-radius: 0.5rem; transition: all 0.2s ease; }
        .nav-pills .nav-link.active { background-color: var(--primary); color: white; box-shadow: 0 4px 6px rgba(78, 115, 223, 0.2); }
        
        /* Custom Accordion Styling */
        .accordion-item { border: none; border-radius: 0.75rem !important; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1rem; overflow: hidden; }
        .accordion-button { background-color: white; padding: 1.25rem; font-weight: bold; color: #343a40; transition: all 0.3s; }
        .accordion-button:not(.collapsed) { background-color: #f8f9fc; color: var(--primary); box-shadow: none; border-bottom: 1px solid #e3e6f0; }
        .accordion-button::after { filter: invert(0.5); }
        
        .stat-badge { display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 70px; padding: 5px; border-radius: 8px; line-height: 1.2; }
        .stat-badge .label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8; }
        .stat-badge .value { font-size: 1.1rem; font-weight: 800; }
        .rank-bg { background: linear-gradient(135deg, #f6c23e, #f4a200); color: white; }
        .pct-bg { background-color: #e7f3ff; color: var(--primary); border: 1px solid #b6d4fe; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content" class="d-flex flex-column min-vh-100">
        <?php include 'header.php' ?>

        <div class="content-wrapper flex-grow-1 p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-chart-line me-2 text-primary"></i>My Exam Performance</h1>
                    <span class="badge bg-secondary fs-6 px-3 py-2 shadow-sm">Class <?= htmlspecialchars($class_name) ?></span>
                </div>

                <?php if (!$student_class_id): ?>
                    <div class="text-center p-5 bg-white rounded border border-light shadow-sm">
                        <i class="fas fa-user-slash text-muted fa-4x mb-3 opacity-25"></i>
                        <h5 class="fw-bold text-dark">Class Not Assigned</h5>
                        <p class="text-muted mb-0">Please contact the administration to assign you to a class.</p>
                    </div>
                <?php elseif (!$has_any_records): ?>
                    <div class="text-center p-5 bg-white rounded border border-light shadow-sm">
                        <i class="fas fa-folder-open text-muted fa-4x mb-3 opacity-25"></i>
                        <h5 class="fw-bold text-dark">No Exam Results Yet</h5>
                        <p class="text-muted mb-0">Your official Exam Award Lists will appear here once the teachers publish them.</p>
                    </div>
                <?php else: ?>
                    
                    <div class="bg-white p-2 rounded shadow-sm mb-4">
                        <ul class="nav nav-pills nav-fill" id="examTabs" role="tablist">
                            <?php $first_tab = true; ?>
                            <?php foreach (['Monthly Test', 'Mid Term', 'Final Exam', 'Other'] as $tab_id): ?>
                                <?php if (!empty($grouped_reports[$tab_id])): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?= $first_tab ? 'active' : '' ?>" id="tab-<?= str_replace(' ', '', $tab_id) ?>" data-bs-toggle="pill" data-bs-target="#content-<?= str_replace(' ', '', $tab_id) ?>" type="button" role="tab">
                                            <?= htmlspecialchars($tab_id) ?> 
                                            <span class="badge bg-light text-dark ms-1 rounded-pill"><?= count($grouped_reports[$tab_id]) ?></span>
                                        </button>
                                    </li>
                                    <?php $first_tab = false; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="tab-content" id="examTabsContent">
                        <?php $first_pane = true; $collapse_id_counter = 1; ?>
                        <?php foreach (['Monthly Test', 'Mid Term', 'Final Exam', 'Other'] as $tab_id): ?>
                            <?php if (!empty($grouped_reports[$tab_id])): ?>
                                <div class="tab-pane fade <?= $first_pane ? 'show active' : '' ?>" id="content-<?= str_replace(' ', '', $tab_id) ?>" role="tabpanel">
                                    
                                    <div class="accordion" id="accordion-<?= str_replace(' ', '', $tab_id) ?>">
                                        <?php foreach ($grouped_reports[$tab_id] as $index => $report): ?>
                                            <?php $is_first_item = ($index === 0); // Only auto-open the very first exam in the list ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="heading-<?= $collapse_id_counter ?>">
                                                    <button class="accordion-button <?= $is_first_item ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $collapse_id_counter ?>" aria-expanded="<?= $is_first_item ? 'true' : 'false' ?>">
                                                        <div class="d-flex w-100 justify-content-between align-items-center me-3 flex-wrap gap-2">
                                                            <div>
                                                                <h5 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($report['title']) ?></h5>
                                                                <div class="text-muted small"><i class="far fa-calendar-alt me-1"></i><?= $report['date'] ?></div>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <div class="stat-badge pct-bg">
                                                                    <span class="label">Score</span>
                                                                    <span class="value"><?= $report['percentage'] ?>%</span>
                                                                </div>
                                                                <div class="stat-badge rank-bg shadow-sm">
                                                                    <span class="label text-white">Rank</span>
                                                                    <span class="value text-white">#<?= $report['rank'] ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapse-<?= $collapse_id_counter ?>" class="accordion-collapse collapse <?= $is_first_item ? 'show' : '' ?>" data-bs-parent="#accordion-<?= str_replace(' ', '', $tab_id) ?>">
                                                    <div class="accordion-body p-0">
                                                        <div class="table-responsive">
                                                            <table class="table table-hover table-striped mb-0 text-center align-middle">
                                                                <thead class="table-light text-muted small">
                                                                    <tr>
                                                                        <th class="text-start ps-4">Subject</th>
                                                                        <th>Max Marks</th>
                                                                        <th>Marks Obtained</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($report['subjects'] as $asm_id => $sub): ?>
                                                                        <?php $score = $report['my_scores'][$asm_id] ?? null; ?>
                                                                        <tr>
                                                                            <td class="text-start ps-4 fw-bold text-dark"><?= htmlspecialchars($sub['name']) ?></td>
                                                                            <td class="text-muted"><?= $sub['max'] ?></td>
                                                                            <td class="fw-bold <?= ($score === null || $score === '') ? 'text-danger' : 'text-primary' ?>">
                                                                                <?= ($score === null || $score === '') ? 'Absent' : floatval($score) ?>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                                <tfoot class="table-light border-top border-2">
                                                                    <tr>
                                                                        <td class="text-start ps-4 fw-bold text-dark fs-6">GRAND TOTAL</td>
                                                                        <td class="fw-bold text-dark fs-6"><?= $report['max_total'] ?></td>
                                                                        <td class="fw-bold text-success fs-5"><?= floatval($report['my_total']) ?></td>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                        <div class="bg-white p-3 text-center border-top d-flex justify-content-between align-items-center px-4">
                                                            <div class="text-muted fw-bold small text-uppercase">Result Status</div>
                                                            <?php if($report['percentage'] >= 40): ?>
                                                                <div class="fs-5 fw-bold text-success"><i class="fas fa-check-circle me-1"></i> PASS</div>
                                                            <?php else: ?>
                                                                <div class="fs-5 fw-bold text-danger"><i class="fas fa-times-circle me-1"></i> FAIL</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php $collapse_id_counter++; ?>
                                        <?php endforeach; ?>
                                    </div>

                                </div>
                                <?php $first_pane = false; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        
        <footer class="footer mt-auto py-3 bg-white border-top">
            <div class="container text-center text-muted small">
                 &copy; MSST LMS <?= date('Y') ?> | Student Portal
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    }
    </script>
</body>
</html>