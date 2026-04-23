<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Not needed for static version

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- START: DUMMY DATA FOR ATTENDANCE ---

// 1. Dummy data for the monthly summary cards.
// The "percentage" will be used to style the progress circles.
$monthly_summary = [
    ['month_name' => 'June', 'year' => 2025, 'percentage' => 95, 'total_present' => 19, 'total_classes' => 20],
    ['month_name' => 'May',  'year' => 2025, 'percentage' => 88, 'total_present' => 22, 'total_classes' => 25],
    ['month_name' => 'April','year' => 2025, 'percentage' => 75, 'total_present' => 18, 'total_classes' => 24]
];

// 2. Dummy data for the detailed day-by-day log, grouped by month.
$detailed_attendance = [
    'June 2025' => [
        ['date' => '2025-06-09', 'day_name' => 'Monday',    'status' => 'Present', 'course_name' => 'English Literature'],
        ['date' => '2025-06-06', 'day_name' => 'Friday',    'status' => 'Present', 'course_name' => 'Introduction to Physics'],
        ['date' => '2025-06-05', 'day_name' => 'Thursday',  'status' => 'Absent',  'course_name' => 'Calculus II'],
        ['date' => '2025-06-04', 'day_name' => 'Wednesday', 'status' => 'Present', 'course_name' => 'English Literature'],
        ['date' => '2025-06-03', 'day_name' => 'Tuesday',   'status' => 'Leave',   'course_name' => 'Introduction to Physics'],
        ['date' => '2025-06-02', 'day_name' => 'Monday',    'status' => 'Present', 'course_name' => 'Calculus II'],
    ],
    'May 2025' => [
        ['date' => '2025-05-30', 'day_name' => 'Friday', 'status' => 'Present', 'course_name' => 'English Literature'],
        ['date' => '2025-05-29', 'day_name' => 'Thursday', 'status' => 'Present', 'course_name' => 'Introduction to Physics'],
    ]
];

// --- END OF DUMMY DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df; --secondary: #858796; --success: #1cc88a; 
            --warning: #f6c23e; --danger: #e74a3b; --light-bg: #f8f9fc;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .accordion-button:not(.collapsed) { color: #fff; background-color: var(--primary); }
        
        /* --- CSS for the Progress Circle --- */
        .progress-circle {
            width: 120px; height: 120px; border-radius: 50%;
            display: grid; place-items: center;
            background: conic-gradient(var(--primary) calc(var(--p) * 1%), var(--light-bg) 0);
            transition: background 0.5s;
        }
        .progress-circle::before {
            counter-reset: percentage var(--p);
            content: counter(percentage) '%';
            font-size: 1.5rem; font-weight: 700; color: var(--primary);
            background: #fff; width: 85%; height: 85%; border-radius: 50%;
            display: grid; place-items: center;
        }
        .progress-circle.over-75 { background: conic-gradient(var(--success) calc(var(--p) * 1%), var(--light-bg) 0); }
        .progress-circle.over-75::before { color: var(--success); }
        .progress-circle.under-80 { background: conic-gradient(var(--warning) calc(var(--p) * 1%), var(--light-bg) 0); }
        .progress-circle.under-80::before { color: var(--warning); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                <h1 class="h3 mb-4">My Attendance</h1>

                <h4 class="h5 mb-3">Monthly Summary</h4>
                <div class="row">
                    <?php if(empty($monthly_summary)): ?>
                        <div class="col-12"><div class="alert alert-info">No attendance summary available yet.</div></div>
                    <?php else: ?>
                        <?php foreach($monthly_summary as $summary): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-body text-center">
                                        <h5 class="card-title"><?= htmlspecialchars($summary['month_name']) . ' ' . $summary['year'] ?></h5>
                                        <?php 
                                            $progress_class = '';
                                            if ($summary['percentage'] >= 75) $progress_class = 'over-75';
                                            if ($summary['percentage'] < 80) $progress_class = 'under-80';
                                        ?>
                                        <div class="progress-circle my-3 mx-auto <?= $progress_class ?>" style="--p:<?= $summary['percentage'] ?>;"></div>
                                        <p class="card-text text-muted">
                                            Attended <strong><?= $summary['total_present'] ?></strong> out of <strong><?= $summary['total_classes'] ?></strong> classes.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h4 class="h5 mb-3 mt-4">Detailed Log</h4>
                <div class="accordion" id="attendanceAccordion">
                    <?php if (empty($detailed_attendance)): ?>
                        <div class="card"><div class="card-body text-center text-muted">No detailed attendance records found.</div></div>
                    <?php else: ?>
                        <?php $is_first = true; foreach ($detailed_attendance as $month => $records): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?= $is_first ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= str_replace(' ', '', $month) ?>">
                                        <?= htmlspecialchars($month) ?>
                                    </button>
                                </h2>
                                <div id="collapse<?= str_replace(' ', '', $month) ?>" class="accordion-collapse collapse <?= $is_first ? 'show' : '' ?>" data-bs-parent="#attendanceAccordion">
                                    <div class="accordion-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover mb-0">
                                                <thead>
                                                    <tr class="table-light"><th>Date</th><th>Day</th><th>Course</th><th>Status</th></tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($records as $record): ?>
                                                        <tr>
                                                            <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                                            <td><?= htmlspecialchars($record['day_name']) ?></td>
                                                            <td><?= htmlspecialchars($record['course_name']) ?></td>
                                                            <td>
                                                                <?php 
                                                                    $status_class = 'bg-secondary';
                                                                    if ($record['status'] == 'Present') $status_class = 'bg-success';
                                                                    if ($record['status'] == 'Absent') $status_class = 'bg-danger';
                                                                    if ($record['status'] == 'Leave') $status_class = 'bg-warning text-dark';
                                                                ?>
                                                                <span class="badge <?= $status_class ?>"><?= htmlspecialchars($record['status']) ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php $is_first = false; endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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