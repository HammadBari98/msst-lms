<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Fetch all classes and sections for the Edit Modal dropdowns
try {
    $classes_stmt = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC");
    $all_classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

    $sections_stmt = $pdo->query("SELECT * FROM sections ORDER BY section_name ASC");
    $all_sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: Could not fetch classes and sections. " . $e->getMessage());
}

// Handle AJAX update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password']; // Get the password directly
        $department = $_POST['department'];
        $joining_year = $_POST['joining_year'];
        $class_id = $_POST['class_id'];
        $section_id = $_POST['section_id'];

        // If password field is empty, do NOT update it. Otherwise, update it.
        if (!empty($password)) {
            $sql = "UPDATE students SET full_name = :name, email = :email, phone = :phone, password = :password, department = :department, joining_year = :joining_year, class_id = :class_id, section_id = :section_id WHERE id = :id";
            $params = [
                'name' => $name, 'email' => $email, 'phone' => $phone, 'password' => $password, 'department' => $department,
                'joining_year' => $joining_year, 'class_id' => $class_id, 'section_id' => $section_id, 'id' => $id
            ];
        } else {
            $sql = "UPDATE students SET full_name = :name, email = :email, phone = :phone, department = :department, joining_year = :joining_year, class_id = :class_id, section_id = :section_id WHERE id = :id";
            $params = [
                'name' => $name, 'email' => $email, 'phone' => $phone, 'department' => $department,
                'joining_year' => $joining_year, 'class_id' => $class_id, 'section_id' => $section_id, 'id' => $id
            ];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Student updated successfully!']);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}

// Handle delete action
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'];
    $stmtDel = $pdo->prepare("DELETE FROM students WHERE id = :id");
    $stmtDel->execute(['id' => $id]);
    header('Location: view_students.php');
    exit();
}

// Fetch all students with their class and section names
try {
    $sql = "SELECT s.*, c.class_name, sec.section_name 
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            ORDER BY s.id DESC";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $error = '';
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $students = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>LMS - View Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container-scroller">
        <?php include 'header.php' ?>
        <div class="container-fluid page-body-wrapper">
            <?php include 'sidebar.php' ?>
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">Students List</h4>
                            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                            <div id="updateAlert" class="alert alert-success d-none"></div>
                            <div class="table-responsive">
                                <table id="studentsTable" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Department</th>
                                            <th>Class</th>
                                            <th>Section</th>
                                            <th>Address</th>
                                            <th>Joining Year</th>
                                            <th>Password</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $stu): ?>
                                            <tr id="student-<?= $stu['id'] ?>">
                                                <td><?= htmlspecialchars($stu['student_id']) ?></td>
                                                <td><?= htmlspecialchars($stu['full_name']) ?></td>
                                                <td><?= htmlspecialchars($stu['email']) ?></td>
                                                <td><?= htmlspecialchars($stu['phone']) ?></td>
                                                <td><?= htmlspecialchars($stu['department']) ?></td>
                                                <td><?= htmlspecialchars($stu['class_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($stu['section_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($stu['address']) ?></td>
                                                <td><?= htmlspecialchars($stu['joining_year']) ?></td>
                                                <td><?= htmlspecialchars($stu['password']) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning edit-student" data-bs-toggle="modal" data-bs-target="#editStudentModal" 
                                                        data-id="<?= $stu['id'] ?>"
                                                        data-name="<?= htmlspecialchars($stu['full_name']) ?>"
                                                        data-email="<?= htmlspecialchars($stu['email']) ?>"
                                                        data-phone="<?= htmlspecialchars($stu['phone']) ?>"
                                                        data-department="<?= htmlspecialchars($stu['department']) ?>"
                                                        data-class-id="<?= htmlspecialchars($stu['class_id']) ?>" 
                                                        data-section-id="<?= htmlspecialchars($stu['section_id']) ?>"
                                                        data-address="<?= htmlspecialchars($stu['address']) ?>"
                                                        data-joining-year="<?= htmlspecialchars($stu['joining_year']) ?>"
                                                        data-password="<?= htmlspecialchars($stu['password']) ?>">
                                                        <i class="mdi mdi-pencil"></i> Edit
                                                    </button>
                                                    <a href="?action=delete&id=<?= $stu['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">
                                                        <i class="mdi mdi-delete"></i> Delete
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Edit Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <form id="editStudentForm">
                                <input type="hidden" name="ajax" value="1">
                                <input type="hidden" id="edit_id" name="id">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" id="edit_name" name="name" required></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" id="edit_email" name="email" required></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" class="form-control" id="edit_phone" name="phone"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Department</label>
                                            <select class="form-select" id="edit_department" name="department" required>
                                                <option value="F.Sc. Pre-Medical">F.Sc. Pre-Medical</option>
                                                <option value="F.Sc. Pre-Engineering">F.Sc. Pre-Engineering</option>
                                                <option value="ICS (Intermediate in Computer Science)">ICS</option>
                                                <option value="General Science Group">General Science</option>
                                                <option value="Humanities Group">Humanities</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Class</label>
                                            <select class="form-select" id="edit_class_id" name="class_id" required>
                                                <option value="">Select Class</option>
                                                <?php foreach ($all_classes as $class): ?><option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Section</label>
                                            <select class="form-select" id="edit_section_id" name="section_id" required>
                                                <option value="">Select Section</option>
                                                <?php foreach ($all_sections as $section): ?><option value="<?= $section['id'] ?>"><?= htmlspecialchars($section['section_name']) ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Address</label><input type="text" class="form-control" id="edit_address" name="address"></div>
                                        <div class="col-md-6 mb-3"><label class="form-label">Joining Year</label><input type="text" class="form-control" id="edit_joining_year" name="joining_year"></div>
                                        <div class="col-md-12 mb-3"><label class="form-label">Password (leave blank to keep current)</label><input type="text" class="form-control" id="edit_password" name="password"></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php include 'footer.php' ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function () {
        $('#studentsTable').DataTable({ responsive: true });

        // Handle edit modal population
        $('#editStudentModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var modal = $(this);
            modal.find('#edit_id').val(button.data('id'));
            modal.find('#edit_name').val(button.data('name'));
            modal.find('#edit_email').val(button.data('email'));
            modal.find('#edit_phone').val(button.data('phone'));
            modal.find('#edit_department').val(button.data('department'));
            modal.find('#edit_class_id').val(button.data('class-id'));
            modal.find('#edit_section_id').val(button.data('section-id'));
            modal.find('#edit_address').val(button.data('address'));
            modal.find('#edit_joining_year').val(button.data('joining-year'));
            modal.find('#edit_password').val(''); // Clear password field by default
        });

        // Handle EDIT form submission
        $('#editStudentForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'view_students.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload(); // Simple reload is easiest to show all changes
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() { alert('An AJAX error occurred.'); }
            });
        });
    });
    </script>
</body>
</html>