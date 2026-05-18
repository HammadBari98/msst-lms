<?php
session_start();
require_once '../config/db_config.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: ../student_login.php');
    exit();
}

$student_session_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- BUG FIX: Convert String ID (e.g. STD-001) to Integer ID ---
$stmt_get_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
$stmt_get_id->execute([$student_session_id, $student_session_id]);
$student_id = $stmt_get_id->fetchColumn();
// ---------------------------------------------------------------

// Fetch the student's scores along with assessment details
$stmt = $pdo->prepare("
    SELECT a.title, a.subject_name, a.assessment_type, a.assessment_date, a.total_marks,
           ss.marks_obtained, ss.remarks, u.full_name as teacher_name
    FROM student_scores ss
    JOIN assessments a ON ss.assessment_id = a.id
    JOIN users u ON a.teacher_id = u.id
    WHERE ss.student_id = ?
    ORDER BY a.assessment_date DESC
");
$stmt->execute([$student_id]);
$scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall percentage for a summary card
$total_possible = 0;
$total_obtained = 0;
foreach ($scores as $s) {
    $total_possible += $s['total_marks'];
    $total_obtained += $s['marks_obtained'];
}
$overall_percentage = $total_possible > 0 ? round(($total_obtained / $total_possible) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Scores | Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .score-card { border-left: 5px solid var(--primary); }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-gray-800">My Assessment Scores</h1>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6">
                        <div class="card score-card shadow-sm h-100" style="border-left-color: <?= $overall_percentage >= 50 ? 'var(--success)' : 'var(--danger)' ?>;">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Overall Performance</div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800"><?= $overall_percentage ?>%</div>
                                    <small class="text-muted">Total Marks: <?= $total_obtained ?> / <?= $total_possible ?></small>
                                </div>
                                <i class="fas fa-chart-line fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 bg-white">
                        <h6 class="m-0 font-weight-bold text-primary">Detailed Score Report</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="scoresTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Title & Type</th>
                                        <th>Teacher</th>
                                        <th class="text-center">Marks</th>
                                        <th class="text-center">Percentage</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scores as $score): 
                                        $pct = round(($score['marks_obtained'] / $score['total_marks']) * 100, 1);
                                        $badge_color = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning text-dark' : 'danger');
                                    ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($score['assessment_date'])) ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($score['subject_name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($score['title']) ?><br>
                                            <span class="badge bg-secondary" style="font-size: 0.7rem;"><?= $score['assessment_type'] ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($score['teacher_name']) ?></td>
                                        <td class="text-center fw-bold">
                                            <?= $score['marks_obtained'] ?> / <?= $score['total_marks'] ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $badge_color ?> fs-6"><?= $pct ?>%</span>
                                        </td>
                                        <td class="text-muted small"><?= htmlspecialchars($score['remarks']) ?: '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <footer class="footer mt-auto py-3 bg-light text-center">
             &copy; LMS <?= date('Y') ?>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Sidebar Toggle Script
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');

        if (toggleBtn && sidebar && mainContent) {
            toggleBtn.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show');
                } else {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                }
            });
        }

        $(document).ready(function() {
            $('#scoresTable').DataTable({
                "order": [[0, 'desc']],
                "pageLength": 15
            });
        });
    </script>
</body>
</html>