<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Kept commented for static preview

// --- Authentication Check ---
if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// --- START: DUMMY DATA FOR THE TEACHER'S TIMETABLE ---
// This data represents the teacher's full weekly schedule.
$teacher_weekly_schedule = [
    'Monday' => [
        ['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Database Systems', 'class_name' => 'BSCS 4th Semester', 'room' => 'C-305'],
        ['start_time' => '10:00', 'end_time' => '11:30', 'course_name' => 'Intro to Computer Science', 'class_name' => 'BSCS 1st Semester', 'room' => 'A-205'],
    ],
    'Tuesday' => [
        ['start_time' => '11:00', 'end_time' => '12:00', 'course_name' => 'Physics Practical', 'class_name' => '1st Year Pre-Eng', 'room' => 'Physics Lab'],
        ['start_time' => '13:00', 'end_time' => '14:30', 'course_name' => 'Linear Algebra', 'class_name' => 'BS Math 2nd Semester', 'room' => 'B-112'],
    ],
    'Wednesday' => [
        ['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Database Systems', 'class_name' => 'BSCS 4th Semester', 'room' => 'C-305'],
        ['start_time' => '10:00', 'end_time' => '11:30', 'course_name' => 'Intro to Computer Science', 'class_name' => 'BSCS 1st Semester', 'room' => 'A-205'],
    ],
    'Thursday' => [
        ['start_time' => '13:00', 'end_time' => '14:30', 'course_name' => 'Linear Algebra', 'class_name' => 'BS Math 2nd Semester', 'room' => 'B-112'],
    ],
    'Friday' => [
        ['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Database Systems', 'class_name' => 'BSCS 4th Semester', 'room' => 'C-305'],
        ['start_time' => '11:00', 'end_time' => '12:00', 'course_name' => 'Physics - 1st Year', 'class_name' => '1st Year Pre-Eng', 'room' => 'A-201'],
    ],
    'Saturday' => [], // Day with no classes
];

// Get the current day of the week to make its tab active.
$current_day = date('l');
if (!array_key_exists($current_day, $teacher_weekly_schedule)) {
    $current_day = 'Monday'; // Default to Monday if today is Sunday or not in the schedule
}
// --- END OF DUMMY DATA ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable | <?= htmlspecialchars($teacher_name) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #007bff; --secondary: #6c757d; --light-bg: #f8f9fc; --dark-text: #343a40; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        #main-content { margin-left: 250px; transition: all 0.3s; }
        #main-content.collapsed { margin-left: 70px; }
        @media (max-width: 768px) { .sidebar { left: -250px; } .sidebar.show { left: 0; } #main-content, #main-content.collapsed { margin-left: 0; } }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1.5rem; border: none;}
        .nav-tabs .nav-link { color: var(--dark-text); }
        .nav-tabs .nav-link.active { background-color: var(--primary); color: white; border-color: var(--primary); }
        .schedule-item { border-left: 4px solid var(--primary); border-radius: 0.25rem; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    
    <div class="content-wrapper p-3 p-md-4">
        <div class="container-fluid">
            <h1 class="h3 mb-4">My Weekly Teaching Schedule</h1>

            <div class="card shadow-sm">
                <div class="card-header bg-white border-0">
                    <ul class="nav nav-tabs card-header-tabs" id="scheduleTab" role="tablist">
                        <?php foreach ($teacher_weekly_schedule as $day => $classes): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= ($day == $current_day) ? 'active' : '' ?>" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#<?= strtolower($day) ?>-pane" 
                                        type="button"><?= $day ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="scheduleTabContent">
                        <?php foreach ($teacher_weekly_schedule as $day => $classes): ?>
                            <div class="tab-pane fade <?= ($day == $current_day) ? 'show active' : '' ?>" 
                                 id="<?= strtolower($day) ?>-pane" 
                                 role="tabpanel">
                                 
                                <?php if (empty($classes)): ?>
                                    <div class="text-center p-5">
                                        <i class="fas fa-coffee fa-3x text-muted"></i>
                                        <p class="mt-3 text-muted">No classes scheduled for today.</p>
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($classes as $class_item): ?>
                                            <li class="list-group-item p-3 schedule-item mb-2">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h5 class="mb-1 text-primary"><?= htmlspecialchars($class_item['course_name']) ?></h5>
                                                    <span class="fw-bold"><?= date('g:i A', strtotime($class_item['start_time'])) ?> - <?= date('g:i A', strtotime($class_item['end_time'])) ?></span>
                                                </div>
                                                <p class="mb-1 text-muted">
                                                    <i class="fas fa-users fa-fw me-2"></i> Class: <strong><?= htmlspecialchars($class_item['class_name']) ?></strong>
                                                </p>
                                                <p class="mb-0 text-muted">
                                                    <i class="fas fa-map-marker-alt fa-fw me-2"></i> Room: <strong><?= htmlspecialchars($class_item['room']) ?></strong>
                                                </p>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
   <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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