<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Kept commented for static preview

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// --- UPDATED: Dummy data now includes more fields for the modal ---
$courses_with_students = [
    [
        'course_name' => 'Maths',
        'course_id' => 'CS301',
        'class_name' => '9',
        'student_count' => 3,
        'students' => [
            ['student_id' => 'CS-001', 'name' => 'Ali Khan', 'email' => 'ali.khan@example.com', 'phone' => '0300-1234567', 'department' => 'Computer Science', 'class' => '10', 'section' => 'A'],
            ['student_id' => 'CS-002', 'name' => 'Fatima Ahmed', 'email' => 'fatima.a@example.com', 'phone' => '0333-7654321', 'department' => 'Computer Science', 'class' => '10', 'section' => 'A'],
            ['student_id' => 'CS-003', 'name' => 'Bilal Raza', 'email' => 'b.raza@example.com', 'phone' => '0321-9876543', 'department' => 'Computer Science', 'class' => '10', 'section' => 'B'],
        ]
    ],
    [
        'course_name' => 'Physics',
        'course_id' => 'MATH202',
        'class_name' => '10',
        'student_count' => 2,
        'students' => [
            ['student_id' => 'MTH-011', 'name' => 'Sana Javed', 'email' => 'sana.j@example.com', 'phone' => '0317-1122334', 'department' => 'Mathematics', 'class' => '11', 'section' => 'A'],
            ['student_id' => 'MTH-012', 'name' => 'Usman Tariq', 'email' => 'usman.t@example.com', 'phone' => '0345-5566778', 'department' => 'Mathematics', 'class' => '11', 'section' => 'A'],
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List | <?= htmlspecialchars($teacher_name) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        .accordion-button:not(.collapsed) { color: white; background-color: var(--primary); }
        .accordion-button:not(.collapsed)::after { filter: brightness(0) invert(1); }
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
            <h1 class="h3 mb-4">Student Lists by Course</h1>
            <div class="accordion" id="studentListAccordion">
                <?php foreach ($courses_with_students as $index => $course): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $index == 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $course['course_id'] ?>">
                                <span class="fw-bold me-2"><?= htmlspecialchars($course['course_name']) ?></span> 
                                (<?= htmlspecialchars($course['class_name']) ?>)
                                <span class="badge bg-secondary ms-auto me-2"><?= $course['student_count'] ?> Students</span>
                            </button>
                        </h2>
                        <div id="collapse-<?= $course['course_id'] ?>" class="accordion-collapse collapse <?= $index == 0 ? 'show' : '' ?>" data-bs-parent="#studentListAccordion">
                            <div class="accordion-body">
                                <?php if (empty($course['students'])): ?>
                                    <p class="text-center text-muted m-3">No students are currently enrolled in this course.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead><tr><th>Student ID</th><th>Name</th><th>Email</th><th>Action</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($course['students'] as $student): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($student['student_id']) ?></td>
                                                        <td><?= htmlspecialchars($student['name']) ?></td>
                                                        <td><?= htmlspecialchars($student['email']) ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary view-profile-btn"
                                                                data-bs-toggle="modal" data-bs-target="#studentProfileModal"
                                                                data-name="<?= htmlspecialchars($student['name']) ?>"
                                                                data-email="<?= htmlspecialchars($student['email']) ?>"
                                                                data-phone="<?= htmlspecialchars($student['phone']) ?>"
                                                                data-department="<?= htmlspecialchars($student['department']) ?>"
                                                                data-class="<?= htmlspecialchars($student['class']) ?>"
                                                                data-section="<?= htmlspecialchars($student['section']) ?>">
                                                                <i class="fas fa-user me-1"></i>View Profile
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
   <?php include 'footer.php'; ?>
</div>

<div class="modal fade" id="studentProfileModal" tabindex="-1" aria-labelledby="studentProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentProfileModalLabel">Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <p class="profile-modal-label mb-0">Full Name</p>
                        <p class="profile-modal-value" id="modal-name"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <p class="profile-modal-label mb-0">Email</p>
                        <p class="profile-modal-value" id="modal-email"></p>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // --- NEW: JAVASCRIPT FOR STUDENT PROFILE MODAL ---
    $('#studentProfileModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal
        
        // Extract info from data-* attributes
        var name = button.data('name');
        var email = button.data('email');
        var phone = button.data('phone');
        var department = button.data('department');
        var studentClass = button.data('class');
        var section = button.data('section');
        
        // Update the modal's content.
        var modal = $(this);
        modal.find('.modal-title').text(name + "'s Profile");
        modal.find('#modal-name').text(name);
        modal.find('#modal-email').text(email);
        modal.find('#modal-phone').text(phone || 'N/A');
        modal.find('#modal-department').text(department);
        modal.find('#modal-class').text(studentClass);
        modal.find('#modal-section').text(section);
    });
});

// The final, correct sidebar toggle script for desktop and mobile
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