<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // No longer needed for this dummy version

// Authenticate the user
if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- NEW: Dummy Data for Enrolled Courses (Replaces Database Query) ---
$enrolled_courses = [
    [
        'id' => 1,
        'course_code' => 'ENG-101',
        'course_name' => 'English Literature',
        'course_description' => 'An introduction to classic and modern English literature, exploring various genres and historical periods.'
    ],
    [
        'id' => 2,
        'course_code' => 'MTH-202',
        'course_name' => 'Calculus II',
        'course_description' => 'Advanced topics in differential and integral calculus, including sequences, series, and polar coordinates.'
    ],
    [
        'id' => 3,
        'course_code' => 'PHY-101',
        'course_name' => 'Introduction to Physics',
        'course_description' => 'A comprehensive overview of the fundamental principles of mechanics, heat, and sound.'
    ],
    [
        'id' => 4,
        'course_code' => 'CS-101',
        'course_name' => 'Introduction to Programming',
        'course_description' => 'Learn the fundamentals of programming logic using modern software development techniques.'
    ]
];
// --- END OF DUMMY DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrolled Subjects | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Basic styles from your dashboard for consistency */
        :root {
            --primary: #4e73df; --secondary: #1cc88a; --accent: #36b9cc;
            --light-bg: #f8f9fc; --dark-text: #5a5c69;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .card-course {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 0;
            border-left: 5px solid var(--primary);
        }
        .card-course:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <h1 class="h3 mb-4 text-dark-text">My Enrolled Subjects</h1>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <?php if (empty($enrolled_courses) && !isset($error_message)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>You are not currently enrolled in any subjects.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($enrolled_courses as $course): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card card-course h-100 shadow-sm">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title text-primary"><?= htmlspecialchars($course['course_name']) ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($course['course_code']) ?></h6>
                                        <p class="card-text flex-grow-1">
                                            <?= htmlspecialchars($course['course_description'] ?? 'No description available.') ?>
                                        </p>
                                        <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-primary mt-auto">
                                            View Materials <i class="fas fa-arrow-right ms-2"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

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