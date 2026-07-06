<?php
session_start();
require_once __DIR__ . '/../config/db_config.php'; 

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// 1. ROBUST TEACHER ID FETCH (Converts String ID to Numeric ID)
$raw_session_id = $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? $_SESSION['id'] ?? null;
$teacher_user_id = 0;

if ($raw_session_id) {
    if (is_numeric($raw_session_id)) {
        $teacher_user_id = (int)$raw_session_id;
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
        $stmt->execute([$raw_session_id]);
        $teacher_user_id = $stmt->fetchColumn() ?: 0;
    }
}

// Fallback to email if session ID fails
if (!$teacher_user_id) {
    $email = $_SESSION['teacher_email'] ?? $_SESSION['email'] ?? '';
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $teacher_user_id = $stmt->fetchColumn() ?: 0;
    }
}

// 2. CROSS-REFERENCE FIX: Fetch corresponding teacher_details ID 
$teacher_details_id = 0;
if ($teacher_user_id > 0) {
    try {
        $stmt_td = $pdo->prepare("SELECT id FROM teacher_details WHERE user_id = ? LIMIT 1");
        $stmt_td->execute([$teacher_user_id]);
        $teacher_details_id = $stmt_td->fetchColumn() ?: 0;
    } catch (Exception $e) {
        // Fallback if table doesn't match
    }
}

$raw_students = [];
$error_message = null;
$assigned_classes = [];

try {
    // 3. Check exact class assignments matching EITHER the user_id or detail_id
    $stmt_assignments = $pdo->prepare("
        SELECT DISTINCT c.id as class_id, c.class_name
        FROM (SELECT DISTINCT class_id, section_id FROM teacher_class_assignments WHERE teacher_user_id = ? OR teacher_user_id = ?) tca
        JOIN classes c ON tca.class_id = c.id
    ");
    $stmt_assignments->execute([$teacher_user_id, $teacher_details_id]);
    $assigned_classes = $stmt_assignments->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($assigned_classes)) {
        // 4. Fetch flat list of students for DataTables
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                c.id as class_id,
                c.class_name,
                s.id as section_id,
                s.section_name,
                u.user_id_string as student_id,
                u.full_name as name,
                u.email,
                sd.cell_no as phone,
                sd.father_name
            FROM (SELECT DISTINCT class_id, section_id FROM teacher_class_assignments WHERE teacher_user_id = ? OR teacher_user_id = ?) tca
            JOIN classes c ON tca.class_id = c.id
            JOIN student_details sd ON sd.class_id = tca.class_id AND IFNULL(sd.section_id,0) = IFNULL(tca.section_id,0)
            JOIN users u ON sd.user_id = u.id
            LEFT JOIN sections s ON sd.section_id = s.id
            ORDER BY c.class_name, s.section_name, u.full_name
        ");
        $stmt->execute([$teacher_user_id, $teacher_details_id]);
        $raw_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students | <?= htmlspecialchars($teacher_name) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        :root { --primary: #007bff; --secondary: #6c757d; --light-bg: #f8f9fc; --dark-text: #343a40; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        .sidebar { width: 250px; height: 100vh; position: fixed; top: 0; left: 0; background: var(--primary); color: white; transition: all 0.3s; z-index: 1000; }
        .sidebar.collapsed { width: 70px; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar ul li a { color: rgba(255, 255, 255, 0.8); padding: 0.75rem 1rem; display: flex; align-items: center; text-decoration: none; transition: background 0.2s, color 0.2s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255, 255, 255, 0.1); color: white; }
        .sidebar ul li a i { width: 20px; text-align: center; margin-right: 0.75rem; }
        .sidebar.collapsed ul li a span { display: none; }
        #main-content { margin-left: 250px; transition: all 0.3s; min-height: 100vh; display: flex; flex-direction: column; }
        #main-content.collapsed { margin-left: 70px; }
        .topbar { height: 70px; background: white; box-shadow: 0 1px 4px rgba(0,0,0,0.1); display: flex; align-items: center; padding: 0 1rem; }
        .content-wrapper { flex: 1; padding: 1.5rem; }
        .footer { text-align: center; padding: 1rem; background: white; font-size: 0.9rem; color: var(--secondary); border-top: 1px solid #e3e6f0; }
        @media (max-width: 768px) { .sidebar { left: -250px; } .sidebar.show { left: 0; } #main-content, #main-content.collapsed { margin-left: 0; } }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1.5rem; border: none;}
        .profile-modal-label { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; margin-bottom: 0; }
        .profile-modal-value { font-size: 1.1rem; font-weight: 500; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            <h1 class="h3 mb-4 text-gray-800">My Assigned Students</h1>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger border-0 shadow-sm p-4">
                    <h5 class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>SQL Error Detected</h5>
                    <p class="mb-0"><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php elseif ($teacher_user_id == 0): ?>
                <div class="alert alert-danger border-0 shadow-sm p-4">
                    <h5 class="fw-bold"><i class="fas fa-user-slash me-2"></i>Session Error</h5>
                    <p class="mb-0">Your Teacher Account ID could not be matched with the database. Please completely log out and log back in.</p>
                </div>
            <?php elseif (empty($assigned_classes)): ?>
                <div class="alert alert-warning border-0 shadow-sm p-4 text-center">
                    <i class="fas fa-clipboard-list fa-3x text-warning mb-3 opacity-50"></i>
                    <h4 class="fw-bold text-dark">No Classes Assigned</h4>
                    <p class="mb-0 text-muted">You are currently <strong>not assigned to any classes</strong> in the system. The admin must officially assign you to a class (like "First Year") in the Admin Panel before you can view students.</p>
                </div>
            <?php elseif (empty($raw_students)): ?>
                <div class="alert alert-info border-0 shadow-sm p-4 text-center">
                    <i class="fas fa-users-slash fa-3x text-info mb-3 opacity-50"></i>
                    <h4 class="fw-bold text-dark">No Students Enrolled</h4>
                    <p class="mb-2 text-muted">You are successfully assigned to the following classes, but the database says <strong>there are no students enrolled</strong> in them:</p>
                    <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                        <?php foreach ($assigned_classes as $ac): ?>
                            <span class="badge bg-primary fs-6"><?= htmlspecialchars($ac['class_name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow mb-4 border-0">
                    <div class="card-header py-3 bg-white">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users me-2"></i>Student Directory</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="teacherStudentsTable" width="100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>S/D Of</th>
                                        <th>Class</th>
                                        <th>Section</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($raw_students as $student): ?>
                                        <tr>
                                            <td class="fw-bold text-secondary"><?= htmlspecialchars($student['student_id'] ?: 'N/A') ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($student['name']) ?></td>
                                            <td><?= htmlspecialchars($student['father_name'] ?: 'N/A') ?></td>
                                            <td><span class="badge bg-primary px-3 py-2">Class <?= htmlspecialchars($student['class_name']) ?></span></td>
                                            <td class="text-center"><?= htmlspecialchars($student['section_name'] ?? '-') ?></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary shadow-sm view-profile-btn"
                                                    data-bs-toggle="modal" data-bs-target="#studentProfileModal"
                                                    data-name="<?= htmlspecialchars($student['name']) ?>"
                                                    data-email="<?= htmlspecialchars($student['email']) ?>"
                                                    data-phone="<?= htmlspecialchars($student['phone']) ?>"
                                                    data-department="<?= htmlspecialchars('S/D of: ' . ($student['father_name'] ?: 'N/A')) ?>"
                                                    data-class="<?= htmlspecialchars('Class ' . $student['class_name']) ?>"
                                                    data-section="<?= htmlspecialchars($student['section_name'] ?? 'N/A') ?>">
                                                    <i class="fas fa-user me-1"></i> View Profile
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
   <?php include 'footer.php'; ?>
</div>

<div class="modal fade" id="studentProfileModal" tabindex="-1" aria-labelledby="studentProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="studentProfileModalLabel">Student Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row bg-white p-3 rounded shadow-sm m-1">
                    <div class="col-md-6 mb-3">
                        <p class="profile-modal-label mb-0">Full Name</p>
                        <p class="profile-modal-value text-dark" id="modal-name"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="profile-modal-label mb-0">Email</p>
                        <p class="profile-modal-value text-primary" id="modal-email"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="profile-modal-label mb-0">Phone Number</p>
                        <p class="profile-modal-value" id="modal-phone"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="profile-modal-label mb-0">Department</p>
                        <p class="profile-modal-value" id="modal-department"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="profile-modal-label mb-0">Class</p>
                        <p class="profile-modal-value" id="modal-class"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="profile-modal-label mb-0">Section</p>
                        <p class="profile-modal-value" id="modal-section"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-white">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables
    if ($('#teacherStudentsTable').length) {
        $('#teacherStudentsTable').DataTable({
            "pageLength": 10,
            "order": [[3, "asc"], [1, "asc"]], // Sort by Class first, then Name
            "columnDefs": [
                { "orderable": false, "targets": 5 } // Disable sorting on action button
            ]
        });
    }

    // Modal Trigger Logic
    $('#studentProfileModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var name = button.data('name');
        var email = button.data('email');
        var phone = button.data('phone');
        var department = button.data('department');
        var studentClass = button.data('class');
        var section = button.data('section');
        
        var modal = $(this);
        modal.find('.modal-title').text(name + "'s Profile");
        modal.find('#modal-name').text(name);
        modal.find('#modal-email').text(email || 'N/A');
        modal.find('#modal-phone').text(phone || 'N/A');
        modal.find('#modal-department').text(department);
        modal.find('#modal-class').text(studentClass);
        modal.find('#modal-section').text(section);
    });
});

// Sidebar toggle script
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('main-content');
const toggleBtn = document.getElementById('sidebarToggle');
if (toggleBtn && sidebar && mainContent) {
    toggleBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('show');
        } else {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        }
    });
}
document.addEventListener('click', function (event) {
    if (sidebar && toggleBtn && !sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    }
});
</script>
</body>
</html>