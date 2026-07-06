<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Check for session messages
$action_msg = '';
if (isset($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// ---------------------------------------------------------
// HANDLE FORM SUBMISSION: ASSIGN CLASSES TO TEACHER
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'assign_classes') {
    $teacher_id = $_POST['teacher_id'];
    $assignments = $_POST['assignments'] ?? []; // Array of "class_id|section_id|subject_id"

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM teacher_class_assignments WHERE teacher_user_id = ?");
        $stmt->execute([$teacher_id]);

        if (!empty($assignments)) {
            $insert_stmt = $pdo->prepare("INSERT INTO teacher_class_assignments (teacher_user_id, class_id, section_id, subject_id) VALUES (?, ?, ?, ?)");
            foreach ($assignments as $triple) {
                [$cid, $sid, $subid] = array_pad(explode('|', $triple), 3, null);
                if (!$cid) continue;
                $insert_stmt->execute([$teacher_id, (int)$cid, $sid ? (int)$sid : null, $subid ? (int)$subid : null]);
            }
        }

        $pdo->commit();
        $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Assignments (and their students) successfully saved!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }

    header("Location: teacher-management.php");
    exit;
}

// ---------------------------------------------------------
// FETCH DATA FOR THE UI
// ---------------------------------------------------------

// Build nested Class -> Section -> Subject structure for the assignment picker
$class_structure = [];
$structure_rows = $pdo->query("
    SELECT c.id AS class_id, c.class_name, sec.id AS section_id, sec.section_name,
           s.id AS subject_id, s.subject_name
    FROM classes c
    JOIN sections sec ON sec.class_id = c.id
    JOIN program_subjects ps ON ps.program_id = sec.id
    JOIN subjects s ON s.id = ps.subject_id
    ORDER BY c.class_name, sec.section_name, s.subject_name
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($structure_rows as $row) {
    $cid = $row['class_id']; $sid = $row['section_id'];
    if (!isset($class_structure[$cid])) {
        $class_structure[$cid] = ['class_name' => $row['class_name'], 'sections' => []];
    }
    if (!isset($class_structure[$cid]['sections'][$sid])) {
        $class_structure[$cid]['sections'][$sid] = ['section_name' => $row['section_name'], 'subjects' => []];
    }
    $class_structure[$cid]['sections'][$sid]['subjects'][] = ['subject_id' => $row['subject_id'], 'subject_name' => $row['subject_name']];
}

// Fetch all teachers and their currently assigned class/section/subject triples
$stmt = $pdo->query("
    SELECT u.id, u.user_id_string, u.full_name, u.email, u.status,
           GROUP_CONCAT(DISTINCT CONCAT(tca.class_id, '|', IFNULL(tca.section_id,0), '|', IFNULL(tca.subject_id,0))) as assigned_keys,
           GROUP_CONCAT(DISTINCT CONCAT(c.class_name, IF(sec.section_name IS NOT NULL, CONCAT(' - ', sec.section_name), ''), IF(s.subject_name IS NOT NULL, CONCAT(' (', s.subject_name, ')'), '')) SEPARATOR '||') as assigned_labels
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN teacher_class_assignments tca ON u.id = tca.teacher_user_id
    LEFT JOIN classes c ON tca.class_id = c.id
    LEFT JOIN sections sec ON tca.section_id = sec.id
    LEFT JOIN subjects s ON tca.subject_id = s.id
    WHERE r.role_name = 'Teacher'
    GROUP BY u.id
    ORDER BY u.full_name
");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .class-badge {
            background-color: #0d6efd;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-right: 4px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .class-checkbox-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 10px;
            background: #fff;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div id="main-content">
        <?php include 'header.php'; ?>

        <div class="content-wrapper">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Teacher Management</h1>
                </div>

                <?= $action_msg ?>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">All Teachers & Assignments</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="teachersTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Teacher ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Assigned Classes (Students)</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($teacher['user_id_string']) ?></td>
                                            <td><?= htmlspecialchars($teacher['full_name']) ?></td>
                                            <td><?= htmlspecialchars($teacher['email']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $teacher['status'] === 'Active' ? 'success' : 'danger' ?>">
                                                    <?= htmlspecialchars($teacher['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                if (!empty($teacher['assigned_labels'])) {
                                                    foreach (explode('||', $teacher['assigned_labels']) as $label) {
                                                        echo '<span class="class-badge">' . htmlspecialchars($label) . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted small">No assignments</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-primary" onclick="openAssignModal(
                                                    <?= $teacher['id'] ?>,
                                                    '<?= htmlspecialchars($teacher['full_name'], ENT_QUOTES) ?>',
                                                    '<?= htmlspecialchars($teacher['assigned_keys'] ?? '', ENT_QUOTES) ?>'
                                                )">
                                                    <i class="fas fa-users-cog me-1"></i> Assign Class/Section/Subject
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

        <?php include 'footer.php'; ?>
    </div>

    <div class="modal fade" id="assignClassesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="post">
                    <input type="hidden" name="action" value="assign_classes">
                    <input type="hidden" name="teacher_id" id="assignTeacherId">
                    
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>Assign Class/Section/Subject to <span id="assignTeacherName"></span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body bg-light">
                        <p class="text-muted small mb-3">Check the class / section / subject combinations you want to assign to this teacher. The teacher will automatically be assigned all active students within those specific sections for grading and scoring.</p>
                        
                        <div class="class-checkbox-container">
                            <?php if (empty($class_structure)): ?>
                                <div class="text-center text-muted p-3">No subjects found. Add classes, programs, and subjects in Class Management first.</div>
                            <?php else: foreach ($class_structure as $cid => $cls): ?>
                                <div class="mb-3">
                                    <div class="fw-bold text-primary border-bottom pb-1 mb-2">Class <?= htmlspecialchars($cls['class_name']) ?></div>
                                    <?php foreach ($cls['sections'] as $sid => $sec): ?>
                                        <div class="ms-2 mb-2">
                                            <div class="fw-semibold text-secondary small mb-1"><?= htmlspecialchars($sec['section_name']) ?></div>
                                            <?php foreach ($sec['subjects'] as $sub): $key = $cid . '|' . $sid . '|' . $sub['subject_id']; ?>
                                                <div class="form-check ms-3">
                                                    <input class="form-check-input assignment-checkbox" type="checkbox" name="assignments[]" value="<?= htmlspecialchars($key) ?>" id="asg_<?= $cid ?>_<?= $sid ?>_<?= $sub['subject_id'] ?>">
                                                    <label class="form-check-label" for="asg_<?= $cid ?>_<?= $sid ?>_<?= $sub['subject_id'] ?>"><?= htmlspecialchars($sub['subject_name']) ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                    
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Assignments</button>
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
        $(document).ready(function() {
            $('#teachersTable').DataTable({
                "pageLength": 10,
                "columnDefs": [
                    { "orderable": false, "targets": 5 }
                ]
            });
        });

        // Function to handle opening the Assign Modal and pre-checking existing assignments
        function openAssignModal(teacherId, teacherName, assignedKeys) {
            document.getElementById('assignTeacherId').value = teacherId;
            document.getElementById('assignTeacherName').textContent = teacherName;

            document.querySelectorAll('.assignment-checkbox').forEach(cb => cb.checked = false);

            if (assignedKeys) {
                assignedKeys.split(',').forEach(key => {
                    const cb = document.querySelector(`.assignment-checkbox[value="${key}"]`);
                    if (cb) cb.checked = true;
                });
            }

            new bootstrap.Modal(document.getElementById('assignClassesModal')).show();
        }

        // Standard Sidebar Toggle
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