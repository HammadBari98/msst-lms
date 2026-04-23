<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Not needed for static version

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- START: DUMMY DATA FOR WEEKLY CLASS SCHEDULE ---
// The data is grouped by the day of the week.
$schedule = [
    'Monday' => [
        ['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Calculus II', 'teacher_name' => 'Mr. Farhan'],
        ['start_time' => '10:00', 'end_time' => '11:00', 'course_name' => 'English Literature', 'teacher_name' => 'Ms. Ayesha'],
        ['start_time' => '11:00', 'end_time' => '12:00', 'course_name' => 'Introduction to Physics', 'teacher_name' => 'Mr. Ahmed Khan'],
        ['start_time' => '12:00', 'end_time' => '13:00', 'course_name' => 'Break', 'teacher_name' => ''],
        ['start_time' => '13:00', 'end_time' => '14:00', 'course_name' => 'Islamic Studies', 'teacher_name' => 'Mr. Qari'],
    ],
    'Tuesday' => [
        ['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Introduction to Physics', 'teacher_name' => 'Mr. Ahmed Khan'],
        ['start_time' => '10:00', 'end_time' => '11:00', 'course_name' => 'Calculus II', 'teacher_name' => 'Mr. Farhan'],
        ['start_time' => '11:00', 'end_time' => '12:00', 'course_name' => 'Pakistan Studies', 'teacher_name' => 'Mr. Ali'],
    ],
    'Wednesday' => [
        ['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'English Literature', 'teacher_name' => 'Ms. Ayesha'],
        ['start_time' => '10:00', 'end_time' => '11:00', 'course_name' => 'Calculus II', 'teacher_name' => 'Mr. Farhan'],
        ['start_time' => '11:00', 'end_time' => '12:00', 'course_name' => 'Introduction to Physics (Lab)', 'teacher_name' => 'Mr. Ahmed Khan'],
    ],
    'Thursday' => [
        ['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Introduction to Physics', 'teacher_name' => 'Mr. Ahmed Khan'],
        ['start_time' => '10:00', 'end_time' => '11:00', 'course_name' => 'English Literature', 'teacher_name' => 'Ms. Ayesha'],
        ['start_time' => '11:00', 'end_time' => '12:00', 'course_name' => 'Pakistan Studies', 'teacher_name' => 'Mr. Ali'],
    ],
    'Friday' => [
        ['start_time' => '09:00', 'end_time' => '10:00', 'course_name' => 'Calculus II', 'teacher_name' => 'Mr. Farhan'],
        ['start_time' => '10:00', 'end_time' => '11:00', 'course_name' => 'Islamic Studies', 'teacher_name' => 'Mr. Qari'],
    ],
    'Saturday' => [], // Example of a day with no classes
];

// Get the current day of the week to make its tab active
$current_day = date('l'); // e.g., "Tuesday"
if (!array_key_exists($current_day, $schedule)) {
    $current_day = 'Monday'; // Default to Monday if it's Sunday or a day not in the schedule
}

// --- END OF DUMMY DATA ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4e73df; --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .nav-tabs .nav-link { color: #6e707e; }
        .nav-tabs .nav-link.active { color: var(--primary); font-weight: bold; border-color: #dee2e6 #dee2e6 #fff; }
        .schedule-item { border-left: 4px solid var(--primary); }
        .schedule-item .time { font-weight: bold; }
        .schedule-item .course { font-size: 1.1rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                <h1 class="h3 mb-4">Weekly Class Schedule</h1>

                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0">
                        <ul class="nav nav-tabs card-header-tabs" id="scheduleTab" role="tablist">
                            <?php foreach ($schedule as $day => $classes): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= ($day == $current_day) ? 'active' : '' ?>" 
                                            id="<?= strtolower($day) ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#<?= strtolower($day) ?>-pane" 
                                            type="button"><?= $day ?></button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="scheduleTabContent">
                            <?php foreach ($schedule as $day => $classes): ?>
                                <div class="tab-pane fade <?= ($day == $current_day) ? 'show active' : '' ?>" 
                                     id="<?= strtolower($day) ?>-pane" 
                                     role="tabpanel">
                                     
                                    <?php if (empty($classes)): ?>
                                        <div class="text-center p-5">
                                            <i class="fas fa-couch fa-3x text-muted"></i>
                                            <p class="mt-3 text-muted">No classes scheduled for today.</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($classes as $class_item): ?>
                                                <?php if ($class_item['course_name'] == 'Break'): ?>
                                                    <li class="list-group-item text-center bg-light">
                                                        <strong class="text-muted"><i class="fas fa-mug-hot me-2"></i><?= htmlspecialchars($class_item['course_name']) ?> (<?= date('g:i A', strtotime($class_item['start_time'])) ?> - <?= date('g:i A', strtotime($class_item['end_time'])) ?>)</strong>
                                                    </li>
                                                <?php else: ?>
                                                    <li class="list-group-item p-3 schedule-item mb-2">
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <h5 class="mb-1 course"><?= htmlspecialchars($class_item['course_name']) ?></h5>
                                                            <span class="time text-primary"><?= date('g:i A', strtotime($class_item['start_time'])) ?> - <?= date('g:i A', strtotime($class_item['end_time'])) ?></span>
                                                        </div>
                                                        <p class="mb-1 text-muted">
                                                            <i class="fas fa-user-tie fa-fw me-2"></i><?= htmlspecialchars($class_item['teacher_name']) ?>
                                                        </p>
                                                    </li>
                                                <?php endif; ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // The final, correct sidebar toggle script
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