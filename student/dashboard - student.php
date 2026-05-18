<?php
session_start();
if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$is_admin = $_SESSION['is_admin'] ?? false;

// Using your original example data to prevent errors
$courses_count = 5;
$assignments_due = 3;
$attendance_percentage = 92;
$fees_status = "Paid";

// Upcoming Classes & Deadlines (Example Data)
$upcoming_events = [
    [
        'type' => 'class',
        'title' => 'Mathematics 101',
        'time' => 'Tomorrow at 10:00 AM',
        'details' => 'Topic: Introduction to Calculus',
        'icon' => 'fa-chalkboard-teacher',
        'color' => 'text-primary'
    ],
    [
        'type' => 'assignment',
        'title' => 'History Essay Submission',
        'time' => 'Due in 3 days',
        'details' => 'Submit the essay on the Industrial Revolution.',
        'icon' => 'fa-file-alt',
        'color' => 'text-danger'
    ],
    [
        'type' => 'fee',
        'title' => 'Semester Fee Deadline',
        'time' => 'Due in 1 week',
        'details' => 'Please ensure all dues are cleared to avoid penalties.',
        'icon' => 'fa-money-check-alt',
        'color' => 'text-warning'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($student_name) ?> | Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
      
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .welcome-message {
            background: linear-gradient(45deg, var(--primary), var(--accent));
            color: white; padding: 2.5rem; border-radius: 0.75rem;
            margin-bottom: 2rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
        .stat-card {
            border-left: 5px solid var(--primary); transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px); box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.1);
        }
        .upcoming-list .list-group-item {
            border: 0; border-left: 5px solid #e9ecef;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">

                <div class="welcome-message">
                    <h1 class="h2 font-weight-bold">Welcome Back, <?= htmlspecialchars($student_name) ?>!</h1>
                    <p class="lead mb-0">Here’s a quick look at your academic progress.</p>
                </div>

                <h2 class="h4 mb-4 text-gray-800">Quick Stats</h2>
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--primary);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Enrolled Courses</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $courses_count ?></div>
                                </div>
                                <i class="fas fa-book-open fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--warning);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Assignments</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $assignments_due ?></div>
                                </div>
                                <i class="fas fa-clipboard-list fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--accent);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Attendance</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $attendance_percentage ?>%</div>
                                </div>
                                <i class="fas fa-user-check fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--secondary);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Fees Status</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($fees_status) ?></div>
                                </div>
                                <i class="fas fa-dollar-sign fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Upcoming Deadlines</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush upcoming-list">
                            <?php if (!empty($upcoming_events)): ?>
                                <?php foreach ($upcoming_events as $event): ?>
                                    <div class="list-group-item px-3">
                                        <div class="d-flex w-100 align-items-center">
                                            <div class="me-3">
                                                <i class="fas <?= $event['icon'] ?> fa-2x <?= $event['color'] ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1 font-weight-bold"><?= htmlspecialchars($event['title']) ?></h6>
                                                    <small class="fw-bold <?= $event['color'] ?>"><?= htmlspecialchars($event['time']) ?></small>
                                                </div>
                                                <p class="mb-0 text-muted small"><?= htmlspecialchars($event['details']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-calendar-check fa-3x text-gray-300 mb-3"></i>
                                    <p class="mb-0">No upcoming deadlines. Great job!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <footer class="footer mt-auto py-3 bg-light">
            <div class="container text-center">
                 &copy; LMS <?= date('Y') ?>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // --- THIS IS THE ONLY CHANGE FROM YOUR ORIGINAL FILE ---
    // This smart script works on both desktop and mobile.
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle'); // Your toggle button in header.php

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', () => {
            // Check if the window width is for mobile (matches your CSS @media query)
            if (window.innerWidth <= 768) {
                // Mobile view: toggle the '.show' class
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show');
            } else {
                // Desktop view: toggle the '.collapsed' class
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    }
    </script>
</body>
</html>