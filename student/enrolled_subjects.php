<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Authenticate the user
if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

$student_name = $_SESSION['student_name'] ?? 'Student';

// =======================================================
// 1. ROBUST STUDENT ID TRANSLATOR
// =======================================================
$raw_session_id = $_SESSION['student_user_db_id'] ?? $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? $_SESSION['id'] ?? null;
$current_user_db_id = 0;

if ($raw_session_id) {
    if (is_numeric($raw_session_id)) { 
        $current_user_db_id = (int)$raw_session_id; 
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
        $stmt->execute([$raw_session_id]);
        $current_user_db_id = $stmt->fetchColumn() ?: 0;
    }
}
if (!$current_user_db_id) {
    $email = $_SESSION['student_email'] ?? $_SESSION['email'] ?? '';
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $current_user_db_id = $stmt->fetchColumn() ?: 0;
    }
}

// =======================================================
// 2. FETCH DYNAMIC SUBJECTS BASED ON PROGRAM
// =======================================================
$enrolled_subjects = [];
$program_name = "Not Assigned";
$class_name = "Not Assigned";
$error_message = null;

if ($current_user_db_id > 0) {
    try {
        // First, find the student's assigned program (section)
        $stmt_details = $pdo->prepare("
            SELECT sd.section_id, c.class_name, sec.section_name 
            FROM student_details sd
            LEFT JOIN classes c ON sd.class_id = c.id
            LEFT JOIN sections sec ON sd.section_id = sec.id
            WHERE sd.user_id = ?
        ");
        $stmt_details->execute([$current_user_db_id]);
        $details = $stmt_details->fetch(PDO::FETCH_ASSOC);

        if ($details && $details['section_id']) {
            $program_id = $details['section_id'];
            $program_name = $details['section_name'] ?? 'Unknown Program';
            $class_name = $details['class_name'] ?? 'Unknown Class';

            // Now, fetch the subjects perfectly mapped to this program
            $stmt_subs = $pdo->prepare("
                SELECT s.id, s.subject_name 
                FROM subjects s
                JOIN program_subjects ps ON s.id = ps.subject_id
                WHERE ps.program_id = ?
                ORDER BY s.subject_name ASC
            ");
            $stmt_subs->execute([$program_id]);
            $enrolled_subjects = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $error_message = "A database error occurred while fetching your subjects.";
    }
} else {
    $error_message = "Session error: Could not verify student identity.";
}

// Helper to assign cool icons based on subject name
function getSubjectIconAndColor($subjectName) {
    $sub = strtolower($subjectName);
    if (strpos($sub, 'math') !== false) return ['icon' => 'fa-calculator', 'color' => 'text-primary'];
    if (strpos($sub, 'computer') !== false || strpos($sub, 'it') !== false) return ['icon' => 'fa-laptop-code', 'color' => 'text-dark'];
    if (strpos($sub, 'physic') !== false) return ['icon' => 'fa-atom', 'color' => 'text-info'];
    if (strpos($sub, 'chemist') !== false || strpos($sub, 'bio') !== false || strpos($sub, 'sci') !== false) return ['icon' => 'fa-flask', 'color' => 'text-success'];
    if (strpos($sub, 'english') !== false || strpos($sub, 'urdu') !== false) return ['icon' => 'fa-book-reader', 'color' => 'text-warning'];
    if (strpos($sub, 'islam') !== false) return ['icon' => 'fa-mosque', 'color' => 'text-success'];
    if (strpos($sub, 'geo') !== false || strpos($sub, 'hist') !== false) return ['icon' => 'fa-globe-asia', 'color' => 'text-danger'];
    return ['icon' => 'fa-book', 'color' => 'text-secondary'];
}
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
        :root {
            --primary: #4e73df; --secondary: #1cc88a; --accent: #36b9cc;
            --light-bg: #f8f9fc; --dark-text: #5a5c69;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .card-course {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 0;
            border-radius: 10px;
            overflow: hidden;
        }
        .card-course:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.1) !important;
        }
        .course-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(78, 115, 223, 0.1);
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h1 class="h3 mb-1 text-gray-800 fw-bold"><i class="fas fa-book-open me-2 text-primary"></i>My Enrolled Subjects</h1>
                        <p class="text-muted mb-0">Class: <strong><?= htmlspecialchars($class_name) ?></strong> | Program: <strong><?= htmlspecialchars($program_name) ?></strong></p>
                    </div>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger shadow-sm border-0"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <?php if (empty($enrolled_subjects) && !$error_message): ?>
                    <div class="text-center p-5 bg-white rounded shadow-sm border border-light mt-4">
                        <i class="fas fa-folder-open fa-4x text-muted mb-3 opacity-50"></i>
                        <h5 class="fw-bold text-dark">No Subjects Found</h5>
                        <p class="text-muted mb-0">You are not currently enrolled in any subjects. Please contact the administration if you believe this is a mistake.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($enrolled_subjects as $sub): 
                            $ui = getSubjectIconAndColor($sub['subject_name']);
                        ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                                <div class="card card-course h-100 shadow-sm border-0">
                                    <div class="card-body p-4 d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="course-icon-wrapper shadow-sm">
                                                <i class="fas <?= $ui['icon'] ?> fa-2x <?= $ui['color'] ?>"></i>
                                            </div>
                                        </div>
                                        <h5 class="card-title fw-bold text-dark mb-1"><?= htmlspecialchars($sub['subject_name']) ?></h5>
                                        <p class="text-muted small mb-0">Core Subject &bull; <?= htmlspecialchars($class_name) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        
        <footer class="footer mt-auto py-3 bg-white border-top">
            <div class="container text-center text-muted small">
                 &copy; MSST LMS <?= date('Y') ?> | Student Portal
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
                mainContent.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (window.innerWidth <= 768 && sidebar && toggleBtn && !sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                mainContent.classList.remove('show');
            }
        }
    });
    </script>
</body>
</html>