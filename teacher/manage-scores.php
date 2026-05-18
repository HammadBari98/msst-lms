<?php
session_start();
require_once '../config/db_config.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: ../login.php');
    exit();
}

$teacher_session_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$is_admin = $_SESSION['is_admin'] ?? false;

// --- BUG FIX: Convert String ID (e.g. TEA-001) to Integer ID ---
$stmt_get_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
$stmt_get_id->execute([$teacher_session_id, $teacher_session_id]);
$teacher_id = $stmt_get_id->fetchColumn();
// ---------------------------------------------------------------

// Handle Form Submissions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // CREATE NEW ASSESSMENT
        if ($_POST['action'] === 'create_assessment') {
            try {
                $stmt = $pdo->prepare("INSERT INTO assessments (teacher_id, class_id, subject_name, assessment_type, title, total_marks, assessment_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $teacher_id,
                    $_POST['class_id'],
                    $_POST['subject_name'],
                    $_POST['assessment_type'],
                    $_POST['title'],
                    $_POST['total_marks'],
                    $_POST['assessment_date']
                ]);
                $msg = '<div class="alert alert-success">Assessment created successfully!</div>';
            } catch (Exception $e) {
                $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
        }
        
        // SAVE SCORES
        if ($_POST['action'] === 'save_scores') {
            try {
                $assessment_id = $_POST['assessment_id'];
                $pdo->beginTransaction();
                
                // Delete existing scores for this assessment to replace them (or update)
                $stmt = $pdo->prepare("DELETE FROM student_scores WHERE assessment_id = ?");
                $stmt->execute([$assessment_id]);
                
                $insert_stmt = $pdo->prepare("INSERT INTO student_scores (assessment_id, student_id, marks_obtained, remarks) VALUES (?, ?, ?, ?)");
                
                foreach ($_POST['marks'] as $student_id => $marks) {
                    if ($marks !== '') { // Only save if marks are entered
                        $remarks = $_POST['remarks'][$student_id] ?? '';
                        $insert_stmt->execute([$assessment_id, $student_id, $marks, $remarks]);
                    }
                }
                
                $pdo->commit();
                $msg = '<div class="alert alert-success">Scores saved successfully!</div>';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = '<div class="alert alert-danger">Error saving scores: ' . $e->getMessage() . '</div>';
            }
        }
        
        // DELETE ASSESSMENT
        if ($_POST['action'] === 'delete_assessment') {
            try {
                $stmt = $pdo->prepare("DELETE FROM assessments WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$_POST['assessment_id'], $teacher_id]);
                $msg = '<div class="alert alert-success">Assessment deleted successfully!</div>';
            } catch (Exception $e) {
                $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// Get Teacher's Assigned Classes
$stmt_classes = $pdo->prepare("
    SELECT c.id, c.class_name 
    FROM classes c
    JOIN teacher_class_assignments tca ON c.id = tca.class_id
    WHERE tca.teacher_user_id = ?
");
$stmt_classes->execute([$teacher_id]);
$assigned_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Get Teacher's Assessments
$stmt_assessments = $pdo->prepare("
    SELECT a.*, c.class_name, 
           (SELECT COUNT(*) FROM student_scores WHERE assessment_id = a.id) as graded_count,
           (SELECT COUNT(*) FROM student_details sd JOIN users u ON sd.user_id = u.id WHERE sd.class_id = a.class_id AND u.status = 'Active') as total_students
    FROM assessments a
    JOIN classes c ON a.class_id = c.id
    WHERE a.teacher_id = ?
    ORDER BY a.assessment_date DESC
");
$stmt_assessments->execute([$teacher_id]);
$assessments = $stmt_assessments->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Scores | Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #007bff;
            --light-bg: #f8f9fc;
            --dark-text: #343a40;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        .sidebar { width: 250px; height: 100vh; position: fixed; top: 0; left: 0; background: var(--primary); color: white; transition: all 0.3s; z-index: 1000; }
        .sidebar.collapsed { width: 70px; }
        .sidebar .brand { padding: 1rem; font-size: 1.2rem; font-weight: bold; text-align: center; }
        .sidebar.collapsed .brand span { display: none; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar ul li a { color: rgba(255, 255, 255, 0.8); padding: 0.75rem 1rem; display: flex; align-items: center; text-decoration: none; transition: background 0.2s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255, 255, 255, 0.1); color: white; }
        .sidebar ul li a i { width: 20px; text-align: center; margin-right: 0.75rem; }
        .sidebar.collapsed ul li a span { display: none; }
        #main-content { margin-left: 250px; transition: all 0.3s; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { height: 70px; background: white; box-shadow: 0 1px 4px rgba(0,0,0,0.1); display: flex; align-items: center; padding: 0 1rem; }
        .content-wrapper { flex: 1; padding: 1.5rem; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1.5rem; }
        @media (max-width: 768px) {
            .sidebar { left: -250px; }
            .sidebar.show { left: 0; }
            #main-content { margin-left: 0; }
            #main-content.show-sidebar { margin-left: 250px; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <nav class="topbar">
            <button class="btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div class="ms-auto"><i class="fas fa-user-circle me-2"></i><span><?= htmlspecialchars($teacher_name) ?></span></div>
        </nav>

        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-gray-800">Manage Scores & Assessments</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
                        <i class="fas fa-plus me-2"></i>New Assessment
                    </button>
                </div>

                <?= $msg ?>

                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Your Assessments</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Title & Subject</th>
                                        <th>Class</th>
                                        <th>Type</th>
                                        <th>Grading Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($assessments) > 0): ?>
                                        <?php foreach ($assessments as $asm): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($asm['assessment_date'])) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($asm['title']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($asm['subject_name']) ?> (Max: <?= $asm['total_marks'] ?>)</small>
                                            </td>
                                            <td>Class <?= htmlspecialchars($asm['class_name']) ?></td>
                                            <td><span class="badge bg-info"><?= $asm['assessment_type'] ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-2"><?= $asm['graded_count'] ?> / <?= $asm['total_students'] ?></span>
                                                    <div class="progress flex-grow-1" style="height: 8px;">
                                                        <?php $pct = $asm['total_students'] > 0 ? ($asm['graded_count'] / $asm['total_students']) * 100 : 0; ?>
                                                        <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success mb-1" onclick="gradeAssessment(<?= $asm['id'] ?>, <?= $asm['class_id'] ?>, '<?= htmlspecialchars($asm['title'], ENT_QUOTES) ?>', <?= $asm['total_marks'] ?>)">
                                                    <i class="fas fa-edit"></i> Grade
                                                </button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this assessment and ALL associated scores?');">
                                                    <input type="hidden" name="action" value="delete_assessment">
                                                    <input type="hidden" name="assessment_id" value="<?= $asm['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger mb-1"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted">No assessments created yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createAssessmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="create_assessment">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Create New Assessment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Class</label>
                            <select name="class_id" class="form-select" required>
                                <option value="">Choose your assigned class...</option>
                                <?php foreach($assigned_classes as $cls): ?>
                                    <option value="<?= $cls['id'] ?>">Class <?= htmlspecialchars($cls['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject_name" class="form-control" placeholder="e.g., Mathematics" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type</label>
                                <select name="assessment_type" class="form-select" required>
                                    <option value="Test">Class Test</option>
                                    <option value="Assignment">Assignment</option>
                                    <option value="Exam">Term Exam</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assessment Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Chapter 4 Quiz" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Total Marks</label>
                                <input type="number" name="total_marks" class="form-control" min="1" step="0.5" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="assessment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100">Create Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gradingModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="save_scores">
                    <input type="hidden" name="assessment_id" id="gradeAssessmentId">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Grade: <span id="gradeTitleDisplay"></span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <div class="alert alert-info">Total Marks possible: <strong id="gradeTotalMarks"></strong></div>
                        <div class="table-responsive">
                            <table class="table table-bordered bg-white">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Marks Obtained</th>
                                        <th>Remarks (Optional)</th>
                                    </tr>
                                </thead>
                                <tbody id="studentGradingList">
                                    <tr><td colspan="3" class="text-center">Loading students...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Save All Scores</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle Script
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

        // Load students for grading via AJAX
        function gradeAssessment(assessmentId, classId, title, totalMarks) {
            $('#gradeAssessmentId').val(assessmentId);
            $('#gradeTitleDisplay').text(title);
            $('#gradeTotalMarks').text(totalMarks);
            $('#studentGradingList').html('<tr><td colspan="3" class="text-center"><div class="spinner-border text-primary"></div></td></tr>');
            
            new bootstrap.Modal(document.getElementById('gradingModal')).show();

            // We need a small AJAX file to fetch students in this class and their existing scores
            $.post('../ajax/get_students_for_grading.php', { assessment_id: assessmentId, class_id: classId }, function(response) {
                $('#studentGradingList').html(response);
            }).fail(function() {
                $('#studentGradingList').html('<tr><td colspan="3" class="text-danger text-center">Failed to load students. Ensure AJAX endpoint exists.</td></tr>');
            });
        }
    </script>
</body>
</html>