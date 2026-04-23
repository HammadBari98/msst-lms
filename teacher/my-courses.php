<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Kept commented for static preview

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// Dummy data for assigned subjects
$assigned_subjects = [
    ['course_id' => 'CS301', 'course_name' => 'Database Systems', 'class_name' => 'BSCS 4th Semester', 'schedule_info' => 'Mon, Wed, Fri 09:00 - 10:00 in Room C-305', 'existing_materials' => [['title' => 'Lecture 1.pdf', 'file_path' => '#'],['title' => 'Assignment 1.docx', 'file_path' => '#']]],
    ['course_id' => 'MATH202', 'course_name' => 'Linear Algebra', 'class_name' => 'BS Math 2nd Semester', 'schedule_info' => 'Tue, Thu 13:00 - 14:30 in Room B-112', 'existing_materials' => []]
];

// Dummy data for the timetable modal
$teacher_weekly_schedule = [
    'Monday' => [['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Database Systems', 'class_name' => 'BSCS 4th', 'room' => 'C-305'],['start_time' => '10:00', 'end_time' => '11:30', 'course_name' => 'Intro to CS', 'class_name' => 'BSCS 1st', 'room' => 'A-205']],
    'Tuesday' => [['start_time' => '13:00', 'end_time' => '14:30', 'course_name' => 'Linear Algebra', 'class_name' => 'BS Math 2nd', 'room' => 'B-112']],
    'Wednesday' => [['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Database Systems', 'class_name' => 'BSCS 4th', 'room' => 'C-305'],['start_time' => '10:00', 'end_time' => '11:30', 'course_name' => 'Intro to CS', 'class_name' => 'BSCS 1st', 'room' => 'A-205']],
    'Thursday' => [['start_time' => '13:00', 'end_time' => '14:30', 'course_name' => 'Linear Algebra', 'class_name' => 'BS Math 2nd', 'room' => 'B-112']],
    'Friday' => [['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Database Systems', 'class_name' => 'BSCS 4th', 'room' => 'C-305']],
    'Saturday' => [],
];

$current_day = date('l');
if (!array_key_exists($current_day, $teacher_weekly_schedule)) {
    $current_day = 'Monday';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses | <?= htmlspecialchars($teacher_name) ?></title>
    
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
        .nav-pills .nav-link.active { background-color: var(--primary); }
        .nav-tabs .nav-link.active { background-color: var(--primary); color: white; }
        .schedule-item { border-left: 4px solid var(--primary); }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    
    <div class="content-wrapper">
        <div class="container-fluid">
            <h1 class="h3 mb-4">My Assigned Subjects</h1>
            <?php foreach ($assigned_subjects as $subject): ?>
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="m-0"><?= htmlspecialchars($subject['course_name']) ?> <span class="badge bg-secondary"><?= htmlspecialchars($subject['course_id']) ?></span></h5>
                        <span class="text-muted"><?= htmlspecialchars($subject['class_name']) ?></span>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#materials-<?= $subject['course_id'] ?>">Materials</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#upload-<?= $subject['course_id'] ?>">Upload</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#schedule-<?= $subject['course_id'] ?>">Schedule</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="materials-<?= $subject['course_id'] ?>">
                                <?php if (empty($subject['existing_materials'])): ?>
                                    <p class="text-muted">No materials uploaded yet.</p>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($subject['existing_materials'] as $material): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-file-alt me-2 text-primary"></i><?= htmlspecialchars($material['title']) ?></span>
                                                <a href="#" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            <div class="tab-pane fade" id="upload-<?= $subject['course_id'] ?>">
                                <form onsubmit="alert('File upload is disabled in this static preview.'); return false;">
                                    <div class="mb-3"><label class="form-label">Material Title</label><input type="text" class="form-control" placeholder="e.g., Lecture 1 Notes"></div>
                                    <div class="mb-3"><label class="form-label">File</label><input type="file" class="form-control"></div>
                                    <button type="submit" class="btn btn-primary">Upload Material</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="schedule-<?= $subject['course_id'] ?>">
                                <p><strong>Class Timing:</strong> <?= htmlspecialchars($subject['schedule_info']) ?></p>
                                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#timetableModal">View Full Timetable</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
   <?php include 'footer.php'; ?>
</div>

<div class="modal fade" id="timetableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">My Weekly Timetable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs">
                    <?php foreach ($teacher_weekly_schedule as $day => $classes): ?>
                        <li class="nav-item"><button class="nav-link <?= ($day == $current_day) ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#modal-<?= strtolower($day) ?>-pane"><?= $day ?></button></li>
                    <?php endforeach; ?>
                </ul>
                <div class="tab-content p-2">
                    <?php foreach ($teacher_weekly_schedule as $day => $classes): ?>
                        <div class="tab-pane fade <?= ($day == $current_day) ? 'show active' : '' ?>" id="modal-<?= strtolower($day) ?>-pane">
                            <?php if (empty($classes)): ?>
                                <div class="text-center p-5"><i class="fas fa-coffee fa-3x text-muted"></i><p class="mt-3 text-muted">No classes scheduled.</p></div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($classes as $class_item): ?>
                                        <li class="list-group-item p-3 schedule-item mb-2">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?= htmlspecialchars($class_item['course_name']) ?></h5>
                                                <span class="text-primary fw-bold"><?= date('g:i A', strtotime($class_item['start_time'])) ?> - <?= date('g:i A', strtotime($class_item['end_time'])) ?></span>
                                            </div>
                                            <p class="mb-1 text-muted">
                                                <i class="fas fa-users fa-fw me-2"></i>Class: <?= htmlspecialchars($class_item['class_name']) ?> | 
                                                <i class="fas fa-map-marker-alt fa-fw mx-2"></i>Room: <?= htmlspecialchars($class_item['room']) ?>
                                            </p>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- FINAL, FULLY-FEATURED SIDEBAR SCRIPT (FOR DESKTOP & MOBILE) ---
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle'); // Your toggle button in header.php

    // 1. Logic for the Toggle Button Click
    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', (event) => {
            // Stop this click from being caught by the document listener below
            event.stopPropagation();
            
            // Check screen size to decide which class to toggle
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    }

    // 2. Logic to close the sidebar when clicking outside on mobile
    document.addEventListener('click', function (event) {
        if (sidebar && toggleBtn && !sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            // If the mobile sidebar is open (.show class is present), close it
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        }
    });
</script>
</body>
</html>