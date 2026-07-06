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

// =======================================================
// AUTO-PATCHER: Dynamic Assessment Types (Core Protected)
// =======================================================
$core_types = ['Monthly Test', 'Mid Term', 'Final Exam'];
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS assessment_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            teacher_id INT NOT NULL,
            is_core TINYINT(1) DEFAULT 0
        )
    ");
    // Seed core types if table is empty for this teacher
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM assessment_types WHERE teacher_id = ?");
    $stmt_check->execute([$teacher_id]);
    if ($stmt_check->fetchColumn() == 0) {
        $stmt_insert = $pdo->prepare("INSERT INTO assessment_types (name, teacher_id, is_core) VALUES (?, ?, 1)");
        foreach ($core_types as $core) { $stmt_insert->execute([$core, $teacher_id]); }
        // Add a default custom one
        $pdo->prepare("INSERT INTO assessment_types (name, teacher_id, is_core) VALUES ('Class Assignment', ?, 0)")->execute([$teacher_id]);
    }
} catch (PDOException $e) { /* Ignore */ }

// Handle Form Submissions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- MANAGE DYNAMIC TYPES ---
    if ($_POST['action'] === 'add_type') {
        try {
            $pdo->prepare("INSERT INTO assessment_types (name, teacher_id, is_core) VALUES (?, ?, 0)")->execute([trim($_POST['type_name']), $teacher_id]);
            $msg = '<div class="alert alert-success">Assessment type added!</div>';
        } catch (Exception $e) { $msg = '<div class="alert alert-danger">Error adding type.</div>'; }
    }
    elseif ($_POST['action'] === 'delete_type') {
        try {
            // Extra security: Only allow deleting if is_core = 0
            $pdo->prepare("DELETE FROM assessment_types WHERE id = ? AND teacher_id = ? AND is_core = 0")->execute([$_POST['type_id'], $teacher_id]);
            $msg = '<div class="alert alert-success">Assessment type deleted!</div>';
        } catch (Exception $e) { $msg = '<div class="alert alert-danger">Cannot delete core types.</div>'; }
    }
    
    // --- CREATE NEW ASSESSMENT ---
    elseif ($_POST['action'] === 'create_assessment') {
        try {
            [$class_id, $section_id] = array_pad(explode('|', $_POST['class_id'] ?? ''), 2, null);
            $stmt = $pdo->prepare("INSERT INTO assessments (teacher_id, class_id, section_id, subject_name, assessment_type, title, total_marks, assessment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $teacher_id, (int)$class_id, $section_id ? (int)$section_id : null, $_POST['subject_name'],
                $_POST['assessment_type'], $_POST['title'], $_POST['total_marks'], $_POST['assessment_date']
            ]);
            $msg = '<div class="alert alert-success">Assessment created successfully!</div>';
        } catch (Exception $e) { $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>'; }
    }

    // --- UPDATE ASSESSMENT DETAILS ---
    elseif ($_POST['action'] === 'update_assessment') {
        try {
            [$class_id, $section_id] = array_pad(explode('|', $_POST['class_id'] ?? ''), 2, null);
            $stmt = $pdo->prepare("UPDATE assessments SET class_id = ?, section_id = ?, subject_name = ?, assessment_type = ?, title = ?, total_marks = ?, assessment_date = ? WHERE id = ? AND teacher_id = ?");
            $stmt->execute([
                (int)$class_id, $section_id ? (int)$section_id : null, $_POST['subject_name'], $_POST['assessment_type'],
                $_POST['title'], $_POST['total_marks'], $_POST['assessment_date'],
                $_POST['assessment_id'], $teacher_id
            ]);
            $msg = '<div class="alert alert-success alert-dismissible shadow-sm">Assessment details updated successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (Exception $e) { $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>'; }
    }
    
    // --- SAVE SCORES ---
    elseif ($_POST['action'] === 'save_scores') {
        try {
            $assessment_id = $_POST['assessment_id'];
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM student_scores WHERE assessment_id = ?");
            $stmt->execute([$assessment_id]);
            
            $insert_stmt = $pdo->prepare("INSERT INTO student_scores (assessment_id, student_id, marks_obtained, remarks) VALUES (?, ?, ?, ?)");
            if(isset($_POST['marks']) && is_array($_POST['marks'])) {
                foreach ($_POST['marks'] as $student_id => $marks) {
                    if ($marks !== '') { 
                        $remarks = $_POST['remarks'][$student_id] ?? '';
                        $insert_stmt->execute([$assessment_id, $student_id, $marks, $remarks]);
                    }
                }
            }
            $pdo->commit();
            $msg = '<div class="alert alert-success">Scores saved successfully!</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '<div class="alert alert-danger">Error saving scores: ' . $e->getMessage() . '</div>';
        }
    }
    
    // --- DELETE ASSESSMENT ---
    elseif ($_POST['action'] === 'delete_assessment') {
        try {
            $pdo->prepare("DELETE FROM assessments WHERE id = ? AND teacher_id = ?")->execute([$_POST['assessment_id'], $teacher_id]);
            $msg = '<div class="alert alert-success">Assessment deleted successfully!</div>';
        } catch (Exception $e) { $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>'; }
    }
}

// Fetch dynamic assessment types for the dropdown
$stmt_types = $pdo->prepare("SELECT * FROM assessment_types WHERE teacher_id = ? ORDER BY is_core DESC, name ASC");
$stmt_types->execute([$teacher_id]);
$assessment_types = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

// Get Teacher's Assigned Classes
$stmt_classes = $pdo->prepare("
    SELECT DISTINCT c.id AS class_id, c.class_name, IFNULL(tca.section_id, 0) AS section_id, sec.section_name
    FROM teacher_class_assignments tca
    JOIN classes c ON tca.class_id = c.id
    LEFT JOIN sections sec ON tca.section_id = sec.id
    WHERE tca.teacher_user_id = ?
    ORDER BY c.class_name, sec.section_name
");
$stmt_classes->execute([$teacher_id]);
$assigned_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Get Teacher's Assessments
$stmt_assessments = $pdo->prepare("
    SELECT a.*, c.class_name,
           (SELECT COUNT(*) FROM student_scores WHERE assessment_id = a.id) as graded_count,
           (SELECT COUNT(*) FROM student_details sd JOIN users u ON sd.user_id = u.id WHERE sd.class_id = a.class_id AND IFNULL(sd.section_id,0) = IFNULL(a.section_id,0) AND u.status = 'Active') as total_students
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        :root { --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1.5rem; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-gray-800 fw-bold"><i class="fas fa-edit text-primary me-2"></i>Manage Scores & Assessments</h1>
                    <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#createAssessmentModal">
                        <i class="fas fa-plus me-2"></i>New Assessment
                    </button>
                </div>

                <?= $msg ?>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Your Assessments</h6>
                        <a href="award-list.php" class="btn btn-sm btn-success fw-bold"><i class="fas fa-clipboard-list me-1"></i> View Award Lists</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                           <table class="table table-bordered table-hover align-middle" id="assessmentsTable" width="100%">
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
                                    <?php foreach ($assessments as $asm): ?>
                                    <tr>
                                        <td><div class="fw-bold"><?= date('d M Y', strtotime($asm['assessment_date'])) ?></div></td>
                                        <td>
                                            <strong class="text-primary"><?= htmlspecialchars($asm['title']) ?></strong><br>
                                            <small class="text-muted"><i class="fas fa-book me-1"></i><?= htmlspecialchars($asm['subject_name']) ?> (Max: <?= $asm['total_marks'] ?>)</small>
                                        </td>
                                        <td><span class="badge bg-secondary">Class <?= htmlspecialchars($asm['class_name']) ?></span></td>
                                        <td>
                                            <?php $is_core = in_array($asm['assessment_type'], $core_types); ?>
                                            <span class="badge <?= $is_core ? 'bg-danger' : 'bg-info text-dark' ?>"><?= htmlspecialchars($asm['assessment_type']) ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="me-2 fw-bold"><?= $asm['graded_count'] ?> / <?= $asm['total_students'] ?></span>
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <?php $pct = $asm['total_students'] > 0 ? ($asm['graded_count'] / $asm['total_students']) * 100 : 0; ?>
                                                    <div class="progress-bar <?= $pct == 100 ? 'bg-success' : 'bg-warning' ?>" style="width: <?= $pct ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success me-1" onclick="gradeAssessment(<?= $asm['id'] ?>, <?= $asm['class_id'] ?>, '<?= htmlspecialchars($asm['title'], ENT_QUOTES) ?>', <?= $asm['total_marks'] ?>)">
                                                <i class="fas fa-edit"></i> Grade
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary me-1 mb-1 shadow-sm" onclick="openEditModal(<?= $asm['id'] ?>, '<?= $asm['class_id'] ?>|<?= (int)($asm['section_id'] ?? 0) ?>', '<?= htmlspecialchars($asm['subject_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($asm['assessment_type'], ENT_QUOTES) ?>', '<?= htmlspecialchars($asm['title'], ENT_QUOTES) ?>', <?= $asm['total_marks'] ?>, '<?= $asm['assessment_date'] ?>')" title="Edit Assessment Info">
                                                <i class="fas fa-pen"></i> Edit
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this assessment and ALL associated scores?');">
                                                <input type="hidden" name="action" value="delete_assessment">
                                                <input type="hidden" name="assessment_id" value="<?= $asm['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger border"><i class="fas fa-trash"></i></button>
                                            </form>
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
    </div>

    <!-- Create Assessment Modal -->
    <div class="modal fade" id="createAssessmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <input type="hidden" name="action" value="create_assessment">
                    <div class="modal-header bg-primary text-white border-0">
                        <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Create New Assessment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Class <span class="text-danger">*</span></label>
                            <select name="class_id" class="form-select" required>
                                <option value="" disabled selected>Choose your assigned class...</option>
                                <?php foreach($assigned_classes as $cls): ?>
                                    <option value="<?= $cls['class_id'] ?>|<?= $cls['section_id'] ?>">Class <?= htmlspecialchars($cls['class_name']) ?><?= $cls['section_name'] ? ' (' . htmlspecialchars($cls['section_name']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                                <select name="subject_name" id="dynamicSubjectSelect" class="form-select" required>
                                    <option value="">First select a class...</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold d-flex justify-content-between align-items-center">
                                    <span>Type <span class="text-danger">*</span></span>
                                    <a href="#" class="small text-decoration-none text-primary" data-bs-toggle="modal" data-bs-target="#manageTypesModal" data-bs-dismiss="modal"><i class="fas fa-cog"></i> Manage</a>
                                </label>
                                <select name="assessment_type" class="form-select" required>
                                    <option value="" disabled selected>Select a type...</option>
                                    <?php foreach($assessment_types as $type): ?>
                                        <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?> <?= $type['is_core'] ? '⭐' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Assessment Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Chapter 4 Quiz, or May 2026 Monthly" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Total Max Marks <span class="text-danger">*</span></label>
                                <input type="number" name="total_marks" class="form-control" min="1" step="0.5" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                                <input type="date" name="assessment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Create Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Assessment Types Modal -->
    <div class="modal fade" id="manageTypesModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title fw-bold"><i class="fas fa-tags me-2"></i>Assessment Types</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-3">
                    <form method="post" class="mb-3 d-flex gap-2">
                        <input type="hidden" name="action" value="add_type">
                        <input type="text" name="type_name" class="form-control form-control-sm border-primary" placeholder="New Type (e.g. Project)" required>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i></button>
                    </form>
                    
                    <ul class="list-group list-group-flush border rounded shadow-sm">
                        <?php foreach($assessment_types as $type): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <span class="small fw-bold text-dark"><?= htmlspecialchars($type['name']) ?> <?= $type['is_core'] ? '<i class="fas fa-star text-warning" title="Core Exam"></i>' : '' ?></span>
                                <?php if (!$type['is_core']): ?>
                                    <form method="post" class="m-0 p-0" onsubmit="return confirm('Delete this custom assessment type?');">
                                        <input type="hidden" name="action" value="delete_type">
                                        <input type="hidden" name="type_id" value="<?= $type['id'] ?>">
                                        <button type="submit" class="btn btn-sm text-danger p-0 m-0"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-secondary small" style="font-size: 0.65rem;">LOCKED</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="modal-footer p-2 bg-white">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#createAssessmentModal" data-bs-dismiss="modal"><i class="fas fa-arrow-left me-1"></i> Back to Assessment</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAssessmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
                <form method="post">
                    <input type="hidden" name="action" value="update_assessment">
                    <input type="hidden" name="assessment_id" id="edit_assessment_id">
                    <div class="modal-header bg-primary text-white border-0">
                        <h5 class="modal-title fw-bold"><i class="fas fa-pen me-2"></i>Edit Assessment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Class <span class="text-danger">*</span></label>
                            <select name="class_id" id="edit_class_id" class="form-select" required>
                                <option value="" disabled>Choose your assigned class...</option>
                                <?php foreach($assigned_classes as $cls): ?>
                                    <option value="<?= $cls['class_id'] ?>|<?= $cls['section_id'] ?>">Class <?= htmlspecialchars($cls['class_name']) ?><?= $cls['section_name'] ? ' (' . htmlspecialchars($cls['section_name']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                                <select name="subject_name" id="edit_subject_name" class="form-select" required>
                                    <option value="">First select a class...</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Type <span class="text-danger">*</span></label>
                                <select name="assessment_type" id="edit_assessment_type" class="form-select" required>
                                    <option value="" disabled>Select a type...</option>
                                    <?php foreach($assessment_types as $type): ?>
                                        <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?> <?= $type['is_core'] ? '⭐' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Assessment Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Total Max Marks <span class="text-danger">*</span></label>
                                <input type="number" name="total_marks" id="edit_total_marks" class="form-control" min="1" step="0.5" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                                <input type="date" name="assessment_date" id="edit_assessment_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Grading Modal -->
    <div class="modal fade" id="gradingModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <form method="post" id="gradingForm">
                    <input type="hidden" name="action" value="save_scores">
                    <input type="hidden" name="assessment_id" id="gradeAssessmentId">
                    <div class="modal-header bg-success text-white border-0 py-3">
                        <h5 class="modal-title fw-bold"><i class="fas fa-clipboard-check me-2"></i>Grade: <span id="gradeTitleDisplay"></span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light p-4">
                        <div class="alert alert-success bg-success bg-opacity-10 border-success border-opacity-25 shadow-sm text-success fw-bold">
                            <i class="fas fa-info-circle me-2"></i>Total Marks possible for this assessment: <span id="gradeTotalMarks" class="fs-5"></span>
                        </div>
                        <div class="table-responsive bg-white rounded border p-2">
                            <table class="table table-hover align-middle mb-0" id="gradingTable" width="100%">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th>Student Name</th>
                                        <th style="width: 25%;">Marks Obtained</th>
                                        <th>Remarks (Optional)</th>
                                    </tr>
                                </thead>
                                <tbody id="studentGradingList">
                                    <tr><td colspan="3" class="text-center">Loading students...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success fw-bold px-4"><i class="fas fa-save me-2"></i>Save All Scores</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });

        $(document).ready(function() {
            if ($('#assessmentsTable').length) {
                $('#assessmentsTable').DataTable({
                    "pageLength": 10,
                    "order": [[0, 'desc']], 
                    "language": { "emptyTable": "No assessments created yet." },
                    "columnDefs": [ { "orderable": false, "targets": 5 } ]
                });
            }
        });

        $('#gradingForm').on('submit', function(e) {
            var form = this;
            if ($.fn.DataTable.isDataTable('#gradingTable')) {
                var dt = $('#gradingTable').DataTable();
                dt.$('input').each(function() {
                    if (!$.contains(document, this)) {
                        $(form).append($('<input>').attr('type', 'hidden').attr('name', this.name).val(this.value));
                    }
                });
            }
        });

        function gradeAssessment(assessmentId, classId, title, totalMarks) {
            $('#gradeAssessmentId').val(assessmentId);
            $('#gradeTitleDisplay').text(title);
            $('#gradeTotalMarks').text(totalMarks);
            
            if ($.fn.DataTable.isDataTable('#gradingTable')) {
                $('#gradingTable').DataTable().destroy();
            }

            $('#studentGradingList').html('<tr><td colspan="3" class="text-center py-5"><div class="spinner-border text-success"></div><p class="mt-2 text-muted fw-bold">Fetching Student Register...</p></td></tr>');
            new bootstrap.Modal(document.getElementById('gradingModal')).show();

            $.post('../ajax/get_students_for_grading.php', { assessment_id: assessmentId, class_id: classId }, function(response) {
                $('#studentGradingList').html(response);
                if(response.indexOf('colspan="3"') === -1) { 
                    $('#gradingTable').DataTable({
                        "pageLength": 10,
                        "lengthMenu": [10, 25, 50, 100],
                        "order": [], 
                        "destroy": true,
                        "scrollY": "45vh",
                        "scrollCollapse": true,
                        "language": { "search": "Search Student:" },
                        "columnDefs": [ { "orderable": false, "targets": [1, 2] } ]
                    });
                }
            }).fail(function() {
                $('#studentGradingList').html('<tr><td colspan="3" class="text-danger text-center py-4"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Failed to load students.</td></tr>');
            });
        }

        $('select[name="class_id"]').on('change', function() {
            var subjectSelect = $('#dynamicSubjectSelect');
            if (!$(this).val()) {
                subjectSelect.html('<option value="">First select a class...</option>');
                return;
            }
            subjectSelect.html('<option value="">Loading subjects...</option>');

            $.ajax({
                url: '../ajax/get_subjects.php',
                type: 'GET',
                data: { class_id: $(this).val().split('|')[0] },
                dataType: 'json',
                success: function(subjects) {
                    var options = '<option value="">Choose Subject...</option>';
                    if (subjects && subjects.length > 0) {
                        subjects.forEach(function(sub) { options += `<option value="${sub}">${sub}</option>`; });
                    } else {
                        subjectSelect.replaceWith('<input type="text" name="subject_name" id="dynamicSubjectSelect" class="form-control" placeholder="e.g., Mathematics" required>');
                        return;
                    }
                    if(subjectSelect.is("input")) {
                        subjectSelect.replaceWith('<select name="subject_name" id="dynamicSubjectSelect" class="form-select" required></select>');
                        subjectSelect = $('#dynamicSubjectSelect');
                    }
                    subjectSelect.html(options);
                },
                error: function() { subjectSelect.html('<option value="">Error loading subjects</option>'); }
            });
        });


        // Open Edit Modal & Populate Data
        function openEditModal(id, classId, subject, type, title, marks, date) {
            $('#edit_assessment_id').val(id);
            $('#edit_class_id').val(classId);
            $('#edit_assessment_type').val(type);
            $('#edit_title').val(title);
            $('#edit_total_marks').val(marks);
            $('#edit_assessment_date').val(date);

            // Dynamically load the subjects for this class and select the correct one
            var subjectSelect = $('#edit_subject_name');
            subjectSelect.html('<option value="">Loading...</option>');
            
            $.ajax({
                url: '../ajax/get_subjects.php',
                type: 'GET',
                data: { class_id: classId.split('|')[0] },
                dataType: 'json',
                success: function(subjects) {
                    var options = '<option value="">Choose Subject...</option>';
                    if (subjects && subjects.length > 0) {
                        subjects.forEach(function(sub) {
                            options += `<option value="${sub}" ${sub === subject ? 'selected' : ''}>${sub}</option>`;
                        });
                        if(subjectSelect.is("input")) {
                            subjectSelect.replaceWith('<select name="subject_name" id="edit_subject_name" class="form-select" required></select>');
                            subjectSelect = $('#edit_subject_name');
                        }
                    } else {
                        // Fallback to text input if no mapping found
                        subjectSelect.replaceWith(`<input type="text" name="subject_name" id="edit_subject_name" class="form-control" value="${subject}" required>`);
                        return;
                    }
                    subjectSelect.html(options);
                }
            });

            new bootstrap.Modal(document.getElementById('editAssessmentModal')).show();
        }

        // If the teacher changes the class INSIDE the edit modal, update the subjects dynamically
        $('#edit_class_id').on('change', function() {
            var subjectSelect = $('#edit_subject_name');
            if (!$(this).val()) {
                subjectSelect.html('<option value="">First select a class...</option>');
                return;
            }
            subjectSelect.html('<option value="">Loading subjects...</option>');

            $.ajax({
                url: '../ajax/get_subjects.php',
                type: 'GET',
                data: { class_id: $(this).val().split('|')[0] },
                dataType: 'json',
                success: function(subjects) {
                    var options = '<option value="">Choose Subject...</option>';
                    if (subjects && subjects.length > 0) {
                        subjects.forEach(function(sub) { options += `<option value="${sub}">${sub}</option>`; });
                    } else {
                        subjectSelect.replaceWith('<input type="text" name="subject_name" id="edit_subject_name" class="form-control" placeholder="e.g., Mathematics" required>');
                        return;
                    }
                    if(subjectSelect.is("input")) {
                        subjectSelect.replaceWith('<select name="subject_name" id="edit_subject_name" class="form-select" required></select>');
                        subjectSelect = $('#edit_subject_name');
                    }
                    subjectSelect.html(options);
                }
            });
        });
    </script>

</body>
</html>