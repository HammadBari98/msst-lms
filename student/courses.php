<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

try {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.course_name, c.course_description
         FROM courses c
         JOIN student_courses sc ON c.id = sc.course_id
         WHERE sc.student_id = :student_id
         ORDER BY c.course_name ASC"
    );
    $stmt->execute(['student_id' => $student_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    $courses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        <div id="main-content" class="w-100">
            <?php include 'header.php'; ?>
            <div class="content-wrapper p-4">
                <div class="container-fluid">
                    <h1 class="h3 mb-4">My Enrolled Courses</h1>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php elseif (empty($courses)): ?>
                        <div class="alert alert-info">You are not currently enrolled in any courses.</div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($courses as $course): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-body d-flex flex-column">
                                            <h5 class="card-title"><?= htmlspecialchars($course['course_name']) ?></h5>
                                            <p class="card-text flex-grow-1"><?= htmlspecialchars($course['course_description']) ?></p>
                                            <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-primary mt-auto">
                                                View Course <i class="fas fa-arrow-right ms-2"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Your Smart Sidebar Toggle Script
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    }
    </script>
</body>
</html>