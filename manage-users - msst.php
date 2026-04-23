<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

// Check if the admin is logged in, otherwise redirect to the login page.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    // Fetch all users with their related details
    $stmt_users = $pdo->query(
        "SELECT 
            u.id, u.user_id_string, u.full_name, u.email, u.status, r.id as role_id, r.role_name,
            sd.class_id, sd.section_id,
            td.phone, td.cnic, td.address,
            GROUP_CONCAT(tca.class_id) as assigned_class_ids
         FROM users u
         JOIN roles r ON u.role_id = r.id
         LEFT JOIN student_details sd ON u.id = sd.user_id
         LEFT JOIN teacher_details td ON u.id = td.user_id
         LEFT JOIN teacher_class_assignments tca ON u.id = tca.teacher_user_id
         GROUP BY u.id
         ORDER BY u.created_at DESC"
    );
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all roles, classes, and sections for modals and for display
    $roles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
    $classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name")->fetchAll(PDO::FETCH_ASSOC);
    $sections = $pdo->query("SELECT id, section_name FROM sections ORDER BY section_name")->fetchAll(PDO::FETCH_ASSOC);

    // Create maps for quick lookup of class and section names
    $class_map = array_column($classes, 'class_name', 'id');
    $section_map = array_column($sections, 'section_name', 'id');

    // Separate users into role-based arrays
    $students = [];
    $teachers = [];
    $staff = [];

    foreach ($users as $user) {
        switch ($user['role_name']) {
            case 'Student':
                $students[] = $user;
                break;
            case 'Teacher':
                $teachers[] = $user;
                break;
            default: // Catches Admin, Staff, etc.
                $staff[] = $user;
                break;
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">User Management</h1>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="prepareAddModal()">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Add New User
                </button>
            </div>
            
            <div id="alert-placeholder"></div>

            <!-- Students Table Section -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Students List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="studentTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['user_id_string']) ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($class_map[$user['class_id']] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($section_map[$user['section_id']] ?? 'N/A') ?></td>
                                    <td><span class="badge <?= $user['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($user['status']) ?></span></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" title="Edit" data-bs-toggle="modal" data-bs-target="#userModal" onclick='prepareEditModal(<?= json_encode($user) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick='prepareDeleteModal(<?= $user["id"] ?>, "<?= htmlspecialchars($user["full_name"]) ?>")'>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Teachers Table Section -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Teachers List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="teacherTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['user_id_string']) ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                    <td><span class="badge <?= $user['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($user['status']) ?></span></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" title="Edit" data-bs-toggle="modal" data-bs-target="#userModal" onclick='prepareEditModal(<?= json_encode($user) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick='prepareDeleteModal(<?= $user["id"] ?>, "<?= htmlspecialchars($user["full_name"]) ?>")'>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Staff Table Section -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Staff List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="staffTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['user_id_string']) ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['role_name']) ?></td>
                                    <td><span class="badge <?= $user['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($user['status']) ?></span></td>
                                    <td>
                                        <button class="btn btn-info btn-sm" title="Edit" data-bs-toggle="modal" data-bs-target="#userModal" onclick='prepareEditModal(<?= json_encode($user) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick='prepareDeleteModal(<?= $user["id"] ?>, "<?= htmlspecialchars($user["full_name"]) ?>")'>
                                            <i class="fas fa-trash"></i>
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

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="userForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="userId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" id="userName" name="name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Email Address</label><input type="email" class="form-control" id="userEmail" name="email" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Role</label>
                            <select class="form-select" id="userRole" name="role" required>
                                <option value="" disabled selected>Select a role</option>
                                <?php foreach($roles as $role): ?><option value="<?= $role['id'] ?>" data-role-name="<?= $role['role_name'] ?>"><?= htmlspecialchars($role['role_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Status</label>
                            <select class="form-select" id="userStatus" name="status" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select>
                        </div>
                    </div>

                    <!-- Student Specific Fields -->
                    <div id="student-fields" class="row border-top pt-3 mt-2" style="display: none;">
                        <h6 class="mb-3 text-primary">Student Academic Details</h6>
                        <div class="col-md-6 mb-3"><label for="class_id" class="form-label">Assign Class</label>
                            <select class="form-select" id="class_id" name="class_id">
                                <option value="">Select Class</option>
                                <?php foreach($classes as $class): ?><option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3"><label for="section_id" class="form-label">Assign Section</label>
                            <select class="form-select" id="section_id" name="section_id">
                                <option value="">Select Section</option>
                                <?php foreach($sections as $section): ?><option value="<?= $section['id'] ?>"><?= htmlspecialchars($section['section_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Teacher Specific Fields -->
                    <div id="teacher-fields" class="row border-top pt-3 mt-2" style="display: none;">
                        <h6 class="mb-3 text-primary">Teacher Details</h6>
                        <div class="col-md-6 mb-3"><label for="teacher_phone" class="form-label">Phone Number</label><input type="tel" class="form-control" id="teacher_phone" name="phone"></div>
                        <div class="col-md-6 mb-3"><label for="teacher_cnic" class="form-label">CNIC</label><input type="text" class="form-control" id="teacher_cnic" name="cnic"></div>
                        <div class="col-12 mb-3"><label for="teacher_address" class="form-label">Address</label><textarea class="form-control" id="teacher_address" name="address" rows="2"></textarea></div>
                        <div class="col-12 mb-3"><label class="form-label">Assign Classes</label>
                            <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                                <?php foreach($classes as $class): ?>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="assigned_classes[]" value="<?= $class['id'] ?>" id="class_<?= $class['id'] ?>"><label class="form-check-label" for="class_<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></label></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-2">
                        <label for="userPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="userPassword" name="password">
                        <div id="passwordHelp" class="form-text">For new users, this is required. For editing, leave blank to keep the same password.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="saveUserBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">Are you sure you want to delete <strong id="userToDeleteName"></strong>? This cannot be undone.<input type="hidden" id="userToDeleteId"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete User</button></div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables for each table
    $('#studentTable').DataTable();
    $('#teacherTable').DataTable();
    $('#staffTable').DataTable();
});

// All your existing vanilla JS functions remain here
const userModal = new bootstrap.Modal(document.getElementById('userModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
const userRoleSelect = document.getElementById('userRole');
const studentFields = document.getElementById('student-fields');
const teacherFields = document.getElementById('teacher-fields');

userRoleSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const roleName = selectedOption.getAttribute('data-role-name');
    
    studentFields.style.display = (roleName === 'Student') ? 'flex' : 'none';
    teacherFields.style.display = (roleName === 'Teacher') ? 'flex' : 'none';
});

function prepareAddModal() {
    document.getElementById('userForm').reset();
    document.getElementById('userModalLabel').textContent = 'Add New User';
    document.getElementById('action').value = 'add';
    document.getElementById('userPassword').required = true;
    studentFields.style.display = 'none';
    teacherFields.style.display = 'none';
}
    
function prepareEditModal(user) {
    document.getElementById('userForm').reset();
    document.getElementById('userModalLabel').textContent = 'Edit User: ' + user.full_name;
    document.getElementById('action').value = 'update';
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.full_name;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userStatus').value = user.status;
    document.getElementById('userRole').value = user.role_id;
    document.getElementById('userPassword').required = false;
    
    // Populate student fields
    document.getElementById('class_id').value = user.class_id || "";
    document.getElementById('section_id').value = user.section_id || "";

    // Populate teacher fields
    document.getElementById('teacher_phone').value = user.phone || "";
    document.getElementById('teacher_cnic').value = user.cnic || "";
    document.getElementById('teacher_address').value = user.address || "";
    
    // Pre-check assigned classes for a teacher
    document.querySelectorAll('input[name="assigned_classes[]"]').forEach(c => c.checked = false);
    const assigned_ids = user.assigned_class_ids ? user.assigned_class_ids.split(',') : [];
    assigned_ids.forEach(id => {
        const checkbox = document.getElementById('class_' + id);
        if (checkbox) checkbox.checked = true;
    });

    // Trigger the change event to show/hide role-specific fields
    userRoleSelect.dispatchEvent(new Event('change'));
}

function prepareDeleteModal(id, name) {
    document.getElementById('userToDeleteId').value = id;
    document.getElementById('userToDeleteName').textContent = name;
}

document.getElementById('userForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const saveBtn = document.getElementById('saveUserBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    try {
        const response = await fetch('api_user_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (response.ok) {
            userModal.hide();
            showAlert(result.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(result.message || 'An error occurred.', 'danger');
        }
    } catch (error) {
        showAlert('A network error occurred. Please check the console.', 'danger');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save Changes';
    }
});

document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
    const id = document.getElementById('userToDeleteId').value;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    try {
        const response = await fetch('api_user_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (response.ok) {
            deleteModal.hide();
            showAlert(result.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(result.message || 'An error occurred.', 'danger');
        }
    } catch (error) {
        showAlert('A network error occurred.', 'danger');
    }
});

function showAlert(message, type = 'success') {
    const placeholder = document.getElementById('alert-placeholder');
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `<div class="alert alert-${type} alert-dismissible" role="alert"><div>${message}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    placeholder.append(wrapper);
}
</script>
</body>
</html>
