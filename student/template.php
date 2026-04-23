<?php
session_start();
if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$is_admin = $_SESSION['is_admin'] ?? false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($student_name) ?> | student Dashboard</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #4e73df;
            --secondary: #1cc88a;
            --accent: #36b9cc;
            --light-bg: #f8f9fc;
            --dark-text: #5a5c69;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            overflow-x: hidden;
        }

        /* Additional student-specific styles can be added here */
        .admin-badge {
            background-color: #f39c12;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <?php include 'sidebar.php' ?>

    <!-- Main Content -->
    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                       Template page name
                        <?php if ($is_admin_page_specific): // Use the page-specific $is_admin variable ?>
                            <span class="admin-badge">ADMIN</span>
                        <?php endif; ?>
                    </h1>
                </div>

              
               
            </div>
        </div>

        <footer class="footer">
            &copy; LMS <?= date('Y') ?>
        </footer>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });

        // Optional: Show sidebar on small screens when toggle clicked
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth < 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show');
            }
        });
    </script>

</body>
</html>