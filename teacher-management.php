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
    $class_ids = $_POST['class_ids'] ?? []; // Array of selected classes

    try {
        $pdo->beginTransaction();
        
        // 1. Delete all existing assignments for this teacher
        $stmt = $pdo->prepare("DELETE FROM teacher_class_assignments WHERE teacher_user_id = ?");
        $stmt->execute([$teacher_id]);

        // 2. Insert the newly selected assignments
        if (!empty($class_ids)) {
            $insert_stmt = $pdo->prepare("INSERT INTO teacher_class_assignments (teacher_user_id, class_id) VALUES (?, ?)");
            foreach ($class_ids as $cid) {
                $insert_stmt->execute([$teacher_id, $cid]);
            }
        }
        
        $pdo->commit();
        $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Classes (and their students) successfully assigned to the teacher!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
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

// Fetch all available classes for the modal checkboxes
$classes = $pdo->query("SELECT * FROM classes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all teachers and their currently assigned classes
$stmt = $pdo->query("
    SELECT u.id, u.user_id_string, u.full_name, u.email, u.status,
           GROUP_CONCAT(c.id) as assigned_class_ids,
           GROUP_CONCAT(c.class_name SEPARATOR ', ') as assigned_class_names
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN teacher_class_assignments tca ON u.id = tca.teacher_user_id
    LEFT JOIN classes c ON tca.class_id = c.id
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
                                                if (!empty($teacher['assigned_class_names'])) {
                                                    $class_names = explode(', ', $teacher['assigned_class_names']);
                                                    foreach ($class_names as $cname) {
                                                        echo '<span class="class-badge">Class ' . htmlspecialchars($cname) . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted small">No classes assigned</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-primary" onclick="openAssignModal(
                                                    <?= $teacher['id'] ?>, 
                                                    '<?= htmlspecialchars($teacher['full_name'], ENT_QUOTES) ?>', 
                                                    '<?= $teacher['assigned_class_ids'] ?>'
                                                )">
                                                    <i class="fas fa-users-cog me-1"></i> Assign Classes
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
                        <h5 class="modal-title"><i class="fas fa-user-tie me-2"></i>Assign Classes to <span id="assignTeacherName"></span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body bg-light">
                        <p class="text-muted small mb-3">Check the classes you want to assign to this teacher. The teacher will automatically be assigned all active students within these classes for grading and scoring.</p>
                        
                        <div class="class-checkbox-container">
                            <?php foreach ($classes as $class): ?>
                                <div class="form-check mb-2 p-2 border-bottom">
                                    <input class="form-check-input class-checkbox ms-1" type="checkbox" name="class_ids[]" value="<?= $class['id'] ?>" id="class_<?= $class['id'] ?>">
                                    <label class="form-check-label w-100 ms-2 fw-bold" for="class_<?= $class['id'] ?>" style="cursor: pointer;">
                                        Class <?= htmlspecialchars($class['class_name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($classes)): ?>
                                <div class="text-center text-muted p-3">No classes found in the database.</div>
                            <?php endif; ?>
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

        // Function to handle opening the Assign Modal and pre-checking existing classes
        function openAssignModal(teacherId, teacherName, assignedClassIds) {
            // Set Hidden ID and Title Name
            document.getElementById('assignTeacherId').value = teacherId;
            document.getElementById('assignTeacherName').textContent = teacherName;
            
            // Uncheck all checkboxes first to reset the modal
            document.querySelectorAll('.class-checkbox').forEach(cb => cb.checked = false);
            
            // Check the boxes for the classes this teacher is already assigned to
            if (assignedClassIds) {
                const ids = assignedClassIds.split(',');
                ids.forEach(id => {
                    const checkbox = document.getElementById('class_' + id);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
            
            // Show the modal
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