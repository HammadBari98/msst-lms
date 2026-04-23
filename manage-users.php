<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    // --- Auto-Seed MSST Programs (Kept because Programs/Tracks don't have a UI yet) ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_categories (
        id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50) NOT NULL,
        tuition_amount DECIMAL(10,2) NOT NULL DEFAULT 0, description VARCHAR(255), is_active TINYINT(1) DEFAULT 1
    )");
    
    // Clean up old mixed data from previous versions
    $pdo->exec("DELETE FROM fee_categories WHERE name IN ('Normal', 'Orphan', 'Needy')");

    $stmt_f = $pdo->query("SELECT COUNT(*) FROM fee_categories");
    if ($stmt_f->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO fee_categories (name, tuition_amount, description) VALUES 
            ('Science', 4000, 'Matric'),
            ('Arts', 5000, 'Intermediate'),
            ('Medical', 6000, 'Intermediate'),
            ('ICS', 5500, 'Intermediate'),
            ('Pre Eng.', 6000, 'Intermediate')");
    }

    // Fetch all users with their related details
    $stmt_users = $pdo->query(
        "SELECT 
            u.id, u.user_id_string, u.full_name, u.email, u.status, u.profile_picture, r.id as role_id, r.role_name,
            sd.gender, sd.father_name, sd.date_of_birth, sd.mother_name, sd.family_monthly_income, 
            sd.guardian_name, sd.father_cnic, sd.cell_no, sd.mother_cnic, sd.phone_no, 
            sd.domicile_district, sd.father_occupation, sd.address, sd.postal_address, 
            sd.previous_school, sd.physical_deformity, sd.awards, sd.extracurricular_expertise, 
            sd.special_care_areas, sd.fee_category, sd.class_id, sd.section_id,
            td.phone as teacher_phone, td.cnic, td.address as teacher_address,
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

    // Fetch roles, programs, and dynamic CLASSES
    $roles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
    $programs = $pdo->query("SELECT * FROM fee_categories WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $sections = $pdo->query("SELECT id, section_name FROM sections ORDER BY section_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch classes smartly sorted just like the class-management page
    $classes = $pdo->query("
        SELECT id, class_name FROM classes 
        ORDER BY 
            CASE 
                WHEN class_name REGEXP '^[0-9]+$' THEN CAST(class_name AS UNSIGNED)
                WHEN class_name LIKE 'Class%' AND class_name REGEXP 'Class [0-9]+' THEN CAST(SUBSTRING(class_name, 7) AS UNSIGNED)
                WHEN class_name LIKE 'First Year%' THEN 11
                WHEN class_name LIKE 'Second Year%' THEN 12
                ELSE 999
            END, class_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $class_map = array_column($classes, 'class_name', 'id');
    $section_map = array_column($sections, 'section_name', 'id');

    $students = []; $teachers = []; $staff = [];
    foreach ($users as $user) {
        switch ($user['role_name']) {
            case 'Student': $students[] = $user; break;
            case 'Teacher': $teachers[] = $user; break;
            default: $staff[] = $user; break;
        }
    }
} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | MSST Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .program-badge { font-size: 0.8rem; padding: 4px 10px; background-color: #e9ecef; color: #212529; border: 1px solid #ced4da; border-radius: 6px; font-weight: 500;}
        .class-badge { font-size: 0.8rem; padding: 4px 10px; background-color: #0d6efd; color: white; border-radius: 6px; }
    </style>
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

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Students List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="studentTable" width="100%" cellspacing="0">
                            <thead class="table-light">
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Class & Section</th>
                                    <th>Program</th>
                                    <th>Cell No.</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $user): 
                                    $program_name = !empty($user['fee_category']) ? $user['fee_category'] : 'N/A'; 
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['user_id_string']) ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td>
                                        <span class="class-badge shadow-sm">
                                            <?= htmlspecialchars($class_map[$user['class_id']] ?? 'N/A') ?> 
                                            <?= !empty($section_map[$user['section_id']]) ? '- ' . htmlspecialchars($section_map[$user['section_id']]) : '' ?>
                                        </span>
                                    </td>
                                    <td><span class="program-badge"><?= htmlspecialchars($program_name) ?></span></td>
                                    <td><?= htmlspecialchars($user['cell_no'] ?? 'N/A') ?></td>
                                    <td><span class="badge <?= $user['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($user['status']) ?></span></td>
                                    <td>
                                        <button class="btn btn-info btn-sm text-white" title="Edit" data-bs-toggle="modal" data-bs-target="#userModal" onclick='prepareEditModal(<?= json_encode($user) ?>)'><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger btn-sm" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick='prepareDeleteModal(<?= $user["id"] ?>, "<?= htmlspecialchars(addslashes($user["full_name"])) ?>")'><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Teachers List</h6></div>
                <div class="card-body"><div class="table-responsive"><table class="table table-bordered align-middle" id="teacherTable" width="100%">
                    <thead class="table-light"><tr><th>User ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><?php foreach ($teachers as $user): ?><tr><td><?= htmlspecialchars($user['user_id_string']) ?></td><td class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></td><td><?= htmlspecialchars($user['email']) ?></td><td><?= htmlspecialchars($user['teacher_phone'] ?? 'N/A') ?></td><td><span class="badge <?= $user['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($user['status']) ?></span></td><td><button class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#userModal" onclick='prepareEditModal(<?= json_encode($user) ?>)'><i class="fas fa-edit"></i></button> <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick='prepareDeleteModal(<?= $user["id"] ?>, "<?= htmlspecialchars(addslashes($user["full_name"])) ?>")'><i class="fas fa-trash"></i></button></td></tr><?php endforeach; ?></tbody>
                </table></div></div>
            </div>
            
            <div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Staff List</h6></div>
                <div class="card-body"><div class="table-responsive"><table class="table table-bordered align-middle" id="staffTable" width="100%">
                    <thead class="table-light"><tr><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody><?php foreach ($staff as $user): ?><tr><td><?= htmlspecialchars($user['user_id_string']) ?></td><td class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></td><td><?= htmlspecialchars($user['email']) ?></td><td><?= htmlspecialchars($user['role_name']) ?></td><td><span class="badge <?= $user['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($user['status']) ?></span></td><td><button class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#userModal" onclick='prepareEditModal(<?= json_encode($user) ?>)'><i class="fas fa-edit"></i></button> <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick='prepareDeleteModal(<?= $user["id"] ?>, "<?= htmlspecialchars(addslashes($user["full_name"])) ?>")'><i class="fas fa-trash"></i></button></td></tr><?php endforeach; ?></tbody>
                </table></div></div>
            </div>

        </div>
    </div>
    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> Minjhaj School of Science and Technology.</p></div></footer>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <form id="userForm" novalidate>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="userModalLabel">Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="userId" name="id">
                    
                    <div class="row bg-white p-3 rounded shadow-sm mb-3">
                        <h6 class="mb-3 text-dark fw-bold border-bottom pb-2">Account Information</h6>
                        <div class="col-md-6 mb-3"><label class="form-label">Full Name *</label><input type="text" class="form-control" id="userName" name="name" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Email Address</label><input type="email" class="form-control" id="userEmail" name="email"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Role *</label><select class="form-select" id="userRole" name="role" required><option value="" disabled selected>Select a role</option><?php foreach($roles as $role): ?><option value="<?= $role['id'] ?>" data-role-name="<?= $role['role_name'] ?>"><?= htmlspecialchars($role['role_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Status *</label><select class="form-select" id="userStatus" name="status" required><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Password</label><input type="password" class="form-control" id="userPassword" name="password"><div class="form-text" style="font-size:0.75rem;">Required for new users.</div></div>
                    </div>

                    <div id="student-fields" class="row bg-white p-3 rounded shadow-sm mb-3" style="display: none;">
                        <h6 class="mb-3 text-primary fw-bold border-bottom pb-2"><i class="fas fa-user-graduate me-2"></i>Academic Details</h6>
                        
                        <div class="col-md-4 mb-3">
                            <label for="class_id" class="form-label fw-bold">Class *</label>
                            <select class="form-select" id="class_id" name="class_id" onchange="loadSections(this.value)">
                                <option value="" disabled selected>Select Class...</option>
                                <?php foreach($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($classes)): ?>
                                <div class="form-text text-danger">No classes found. Add them in Class Management.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="section_id" class="form-label fw-bold">Section *</label>
                            <select class="form-select" id="section_id" name="section_id">
                                <option value="">First select a class</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="fee_category" class="form-label fw-bold">Program / Track *</label>
                            <select class="form-select" id="fee_category" name="fee_category">
                                <option value="" disabled selected>Select Program...</option>
                                <?php foreach($programs as $prog): ?>
                                    <option value="<?= htmlspecialchars($prog['name']) ?>"><?= htmlspecialchars($prog['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <h6 class="mt-3 mb-3 text-secondary fw-bold border-bottom pb-2">Personal Information</h6>
                        <div class="col-md-4 mb-3"><label class="form-label">Gender</label><select class="form-select" id="gender" name="gender"><option value="">Select Gender</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Father Name</label><input type="text" class="form-control" id="father_name" name="father_name"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Mother Name</label><input type="text" class="form-control" id="mother_name" name="mother_name"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Date of Birth</label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Father CNIC</label><input type="text" class="form-control" id="father_cnic" name="father_cnic"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Cell No.</label><input type="tel" class="form-control" id="cell_no" name="cell_no"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Guardian's Name</label><input type="text" class="form-control" id="guardian_name" name="guardian_name"></div>
                        <div class="col-12 mb-3"><label class="form-label">Postal Address</label><textarea class="form-control" id="postal_address" name="postal_address" rows="2"></textarea></div>
                    </div>
                    
                    <div id="teacher-fields" class="row bg-white p-3 rounded shadow-sm mb-3" style="display: none;">
                        <h6 class="mb-3 text-info fw-bold border-bottom pb-2"><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Details</h6>
                        <div class="col-md-6 mb-3"><label class="form-label">Phone Number</label><input type="tel" class="form-control" id="teacher_phone" name="phone"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">CNIC</label><input type="text" class="form-control" id="teacher_cnic" name="cnic"></div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Assign Classes (Optional)</label>
                            <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;">
                                <?php if (empty($classes)): ?>
                                    <span class="text-muted small">No classes found. Please add classes in the Class Management page first.</span>
                                <?php else: ?>
                                    <?php foreach($classes as $class): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="assigned_classes[]" value="<?= $class['id'] ?>" id="class_<?= $class['id'] ?>">
                                            <label class="form-check-label" for="class_<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    </div>

                </div>
                <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="saveUserBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">Are you sure you want to delete <strong id="userToDeleteName"></strong>?<input type="hidden" id="userToDeleteId"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete User</button></div></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() { $('#studentTable').DataTable(); $('#teacherTable').DataTable(); $('#staffTable').DataTable(); });

// Sidebar logic
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (sidebar && mainContent && toggleBtn) {
        if (localStorage.getItem('sidebarCollapsed') === 'true') { sidebar.classList.add('collapsed'); mainContent.classList.add('collapsed'); }
        toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('collapsed'); localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed')); });
    }
});

// Load Sections via AJAX dynamically based on selected class
function loadSections(classId) {
    const secSelect = document.getElementById('section_id');
    if (!classId) { 
        secSelect.innerHTML = '<option value="">First select a class</option>'; 
        return; 
    }
    secSelect.innerHTML = '<option value="">Loading sections...</option>';
    fetch(`ajax/get_sections_by_class.php?class_id=${classId}`)
        .then(r => r.json())
        .then(data => {
            let opts = '<option value="" disabled selected>Select Section...</option>'; 
            if(data.length === 0) {
                opts = '<option value="" disabled>No sections available for this class</option>';
            } else {
                data.forEach(s => opts += `<option value="${s.id}">${s.section_name}</option>`); 
            }
            secSelect.innerHTML = opts;
        })
        .catch(() => secSelect.innerHTML = '<option value="">Error loading sections</option>');
}

function prepareAddModal() {
    document.getElementById('userForm').reset();
    document.getElementById('userModalLabel').textContent = 'Add New User';
    document.getElementById('action').value = 'add';
    document.getElementById('userPassword').required = true;
    
    document.getElementById('section_id').innerHTML = '<option value="">First select a class</option>';
    
    document.getElementById('student-fields').style.display = 'none';
    document.getElementById('teacher-fields').style.display = 'none';
}

function prepareEditModal(user) {
    document.getElementById('userForm').reset();
    document.getElementById('userModalLabel').textContent = 'Edit User: ' + user.full_name;
    document.getElementById('action').value = 'update';
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.full_name;
    document.getElementById('userEmail').value = user.email || '';
    document.getElementById('userStatus').value = user.status;
    document.getElementById('userRole').value = user.role_id;
    document.getElementById('userPassword').required = false;
    
    const roleName = document.querySelector(`#userRole option[value="${user.role_id}"]`)?.getAttribute('data-role-name') || '';
    
    if (roleName === 'Student') {
        if (user.class_id) { 
            document.getElementById('class_id').value = user.class_id; 
            loadSections(user.class_id); 
            // Small delay to let the AJAX load the sections before selecting the right one
            setTimeout(() => { document.getElementById('section_id').value = user.section_id || ""; }, 400); 
        }
        
        ['gender','father_name','date_of_birth','mother_name','cell_no','guardian_name','postal_address','father_cnic'].forEach(f => { if(document.getElementById(f)) document.getElementById(f).value = user[f] || ""; });
        
        if (user.fee_category) {
            document.getElementById('fee_category').value = user.fee_category;
        }

    } else if (roleName === 'Teacher') {
        document.getElementById('teacher_phone').value = user.teacher_phone || "";
        document.getElementById('teacher_cnic').value = user.cnic || "";
        document.querySelectorAll('input[name="assigned_classes[]"]').forEach(c => c.checked = false);
        const assigned_ids = user.assigned_class_ids ? user.assigned_class_ids.split(',') : [];
        assigned_ids.forEach(id => { const cb = document.querySelector(`input[name="assigned_classes[]"][value="${id}"]`); if (cb) cb.checked = true; });
    }
    document.getElementById('userRole').dispatchEvent(new Event('change'));
}

document.getElementById('userRole').addEventListener('change', function() {
    const roleName = this.options[this.selectedIndex].getAttribute('data-role-name');
    document.getElementById('student-fields').style.display = (roleName === 'Student') ? 'flex' : 'none';
    document.getElementById('teacher-fields').style.display = (roleName === 'Teacher') ? 'flex' : 'none';
    
    document.getElementById('class_id').required = (roleName === 'Student');
    document.getElementById('section_id').required = (roleName === 'Student');
    document.getElementById('fee_category').required = (roleName === 'Student');
});

function prepareDeleteModal(id, name) { document.getElementById('userToDeleteId').value = id; document.getElementById('userToDeleteName').textContent = name; }

document.getElementById('userForm').addEventListener('submit', async function(e) {
    e.preventDefault(); 
    const formData = new FormData(this);
    
    const saveBtn = document.getElementById('saveUserBtn'); 
    saveBtn.disabled = true; saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    
    try {
        const response = await fetch('api_user_handler.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') { 
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide(); 
            showAlert(result.message, 'success'); 
            setTimeout(() => location.reload(), 1500); 
        } else { showAlert(result.message || 'Error occurred.', 'danger'); }
    } catch (err) { showAlert('Network error.', 'danger'); } 
    finally { saveBtn.disabled = false; saveBtn.innerHTML = 'Save Changes'; }
});

document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
    const btn = this; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Deleting...';
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', document.getElementById('userToDeleteId').value);
    try {
        const response = await fetch('api_user_handler.php', { method: 'POST', body: fd });
        const result = await response.json();
        if(result.status === 'success') location.reload();
        else { showAlert(result.message, 'danger'); btn.disabled = false; btn.innerHTML = 'Delete User'; }
    } catch (err) { showAlert('Network error.', 'danger'); btn.disabled = false; btn.innerHTML = 'Delete User'; }
});

function showAlert(msg, type) { 
    const p = document.getElementById('alert-placeholder'); 
    p.innerHTML = `<div class="alert alert-${type} alert-dismissible shadow-sm"><strong>System:</strong> ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`; 
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => p.innerHTML='', 5000); 
}
</script>
</body>
</html>