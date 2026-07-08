<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
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

// Fetch ALL assessments for the Admin view
$stmt = $pdo->query("
    SELECT a.*, c.class_name, u.full_name as teacher_name,
           (SELECT COUNT(*) FROM student_scores WHERE assessment_id = a.id) as graded_count,
           (SELECT COUNT(*) FROM student_details sd JOIN users us ON sd.user_id = us.id WHERE sd.class_id = a.class_id AND us.status = 'Active') as total_students
    FROM assessments a
    JOIN classes c ON a.class_id = c.id
    JOIN users u ON a.teacher_id = u.id
    ORDER BY a.assessment_date DESC
");
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Scores | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    
    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>
        
        <div class="content-wrapper">
            <div class="container-fluid">
                
                <h1 class="h3 mb-4 text-gray-800">School-Wide Assessments & Scores</h1>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white">
                        <h6 class="m-0 font-weight-bold text-primary">All Assessments Overview</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="adminScoresTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Teacher</th>
                                        <th>Class & Subject</th>
                                        <th>Title & Type</th>
                                        <th>Grading Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assessments as $asm): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($asm['assessment_date'])) ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($asm['teacher_name']) ?></td>
                                        <td>
                                            Class <?= htmlspecialchars($asm['class_name']) ?><br>
                                            <span class="text-muted small"><?= htmlspecialchars($asm['subject_name']) ?></span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($asm['title']) ?><br>
                                            <span class="badge bg-secondary"><?= $asm['assessment_type'] ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2 small"><?= $asm['graded_count'] ?>/<?= $asm['total_students'] ?> Graded</span>
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <?php $pct = $asm['total_students'] > 0 ? ($asm['graded_count'] / $asm['total_students']) * 100 : 0; ?>
                                                    <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info text-white" onclick="viewClassScores(<?= $asm['id'] ?>, '<?= htmlspecialchars($asm['title'], ENT_QUOTES) ?>')">
                                                <i class="fas fa-eye me-1"></i> View Results
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'footer.php' ?>
    </div>

    <div class="modal fade" id="viewScoresModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Results: <span id="viewTitleDisplay"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="table-responsive">
                       <table class="table table-bordered bg-white table-hover" id="modalScoresTable" width="100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Marks Obtained</th>
                                    <th>Percentage</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="adminScoresList">
                                <tr><td colspan="4" class="text-center">Loading scores...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#adminScoresTable').DataTable({
                "order": [[0, 'desc']],
                "pageLength": 15
            });
        });

        // Toggle Script 
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

        // Fetch Data for Admin Modal & Initialize DataTables
        function viewClassScores(assessmentId, title) {
            $('#viewTitleDisplay').text(title);
            
            // Safely destroy existing DataTable if the modal was opened previously
            if ($.fn.DataTable.isDataTable('#modalScoresTable')) {
                $('#modalScoresTable').DataTable().destroy();
            }

            // Show loading spinner
            $('#adminScoresList').html('<tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading scores...</p></td></tr>');
            
            // Open Modal
            new bootstrap.Modal(document.getElementById('viewScoresModal')).show();

            // Fetch AJAX data
            $.post('ajax/admin_get_scores.php', { assessment_id: assessmentId }, function(response) {
                $('#adminScoresList').html(response);
                
                // Initialize DataTables ON THE MODAL TABLE
                // We only initialize if real data is returned (not the "No scores" message)
                if(response.indexOf('colspan="4"') === -1) {
                    $('#modalScoresTable').DataTable({
                        "pageLength": 10,
                        "lengthMenu": [5, 10, 25, 50],
                        "order": [], // Keeps the natural ranking order from the database
                        "destroy": true,
                        "scrollY": "45vh",       // Limits table height to 45% of screen
                        "scrollCollapse": true   // Enables smooth internal scrolling
                    });
                }
            }).fail(function() {
                $('#adminScoresList').html('<tr><td colspan="4" class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Failed to load scores. Check file path.</td></tr>');
            });
        }
    </script>
</body>
</html>