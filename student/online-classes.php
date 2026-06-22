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
// 1. GUARANTEED SAFE STUDENT ID TRANSLATOR
// =======================================================
$current_user_db_id = 0;

if (!empty($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_id']]);
    $current_user_db_id = $stmt->fetchColumn();
}
if (!$current_user_db_id && !empty($_SESSION['student_email'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_email']]);
    $current_user_db_id = $stmt->fetchColumn();
}
if (!$current_user_db_id && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $current_user_db_id = (int)$_SESSION['user_id'];
}

// =======================================================
// 2. FETCH STUDENT'S SCHEDULED ONLINE CLASSES
// =======================================================
$student_class_id = 0;
$scheduled_classes = [];
$error_message = null;

if ($current_user_db_id > 0) {
    try {
        // Find which class this student belongs to
        $stmt_class = $pdo->prepare("SELECT class_id FROM student_details WHERE user_id = ? LIMIT 1");
        $stmt_class->execute([$current_user_db_id]);
        $student_class_id = $stmt_class->fetchColumn();

        if ($student_class_id) {
            // Fetch ONLY the online classes assigned to this specific class
            // Only fetch classes from TODAY onwards (don't show old expired links)
            $stmt_plans = $pdo->prepare("
                SELECT oc.*, u.full_name as teacher_name 
                FROM online_classes oc
                LEFT JOIN users u ON oc.teacher_id = u.id
                WHERE oc.class_id = ? AND oc.class_date >= CURRENT_DATE()
                ORDER BY oc.class_date ASC, oc.start_time ASC
            ");
            $stmt_plans->execute([$student_class_id]);
            $scheduled_classes = $stmt_plans->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_message = "A database error occurred while fetching your live class schedule.";
    }
}

// Helper function for platform badges
function getPlatformBadge($platform) {
    if ($platform == 'Zoom') return '<span class="badge bg-primary px-3 py-2"><i class="fas fa-video me-1"></i>Zoom</span>';
    if ($platform == 'Google Meet') return '<span class="badge bg-success px-3 py-2"><i class="fas fa-chalkboard-teacher me-1"></i>Meet</span>';
    if ($platform == 'MS Teams') return '<span class="badge px-3 py-2" style="background-color: #6264A7;"><i class="fas fa-users me-1"></i>Teams</span>';
    return '<span class="badge bg-secondary px-3 py-2"><i class="fas fa-link me-1"></i>Other</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Classes | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
        
        .class-card {
            border: 0;
            border-left: 5px solid #4e73df;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .class-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1) !important;
        }
        .class-card.today {
            border-left-color: #1cc88a; /* Green for today */
            background-color: #f8fff9;
        }
        .teacher-badge {
            background-color: #e7f3ff;
            color: #0d6efd;
            border: 1px solid #b6d4fe;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content" class="d-flex flex-column min-vh-100">
        <?php include 'header.php' ?>

        <div class="content-wrapper flex-grow-1 p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-video me-2 text-primary"></i>My Live Classes</h1>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger shadow-sm border-0">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$student_class_id): ?>
                    <div class="text-center p-5 bg-white rounded border border-light shadow-sm">
                        <i class="fas fa-user-slash text-muted fa-4x mb-3 opacity-25"></i>
                        <h5 class="fw-bold text-dark">Class Not Assigned</h5>
                        <p class="text-muted mb-0">You have not been assigned to a specific class yet. Please contact the administration.</p>
                    </div>
                <?php elseif (empty($scheduled_classes)): ?>
                    <div class="text-center p-5 bg-white rounded border border-light shadow-sm">
                        <i class="fas fa-coffee text-muted fa-4x mb-3 opacity-25"></i>
                        <h5 class="fw-bold text-dark">No Upcoming Classes</h5>
                        <p class="text-muted mb-0">Your teachers haven't scheduled any upcoming live video classes. Enjoy your day!</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php 
                        $today = date('Y-m-d');
                        foreach ($scheduled_classes as $cls): 
                            $is_today = ($cls['class_date'] == $today);
                        ?>
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card class-card <?= $is_today ? 'today' : '' ?> shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <?= getPlatformBadge($cls['platform']) ?>
                                                <?php if($is_today): ?>
                                                    <span class="badge bg-danger ms-1 animate__animated animate__pulse animate__infinite">TODAY</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge teacher-badge px-2 py-1 rounded">
                                                <i class="fas fa-chalkboard-teacher me-1"></i> <?= htmlspecialchars($cls['teacher_name'] ?? 'Teacher') ?>
                                            </span>
                                        </div>
                                        
                                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($cls['subject_topic']) ?></h5>
                                        
                                        <div class="text-muted small mb-3">
                                            <div class="mb-1"><i class="fas fa-calendar-alt me-2 text-primary"></i><strong class="text-dark"><?= date('l, d M Y', strtotime($cls['class_date'])) ?></strong></div>
                                            <div><i class="fas fa-clock me-2 text-danger"></i><strong class="text-dark"><?= date('h:i A', strtotime($cls['start_time'])) ?></strong> <span class="ms-1 opacity-75">(<?= $cls['duration_minutes'] ?> Mins)</span></div>
                                        </div>
                                        
                                        <?php if(!empty($cls['meeting_password'])): ?>
                                            <div class="bg-light border rounded p-2 mb-3 text-center small">
                                                <span class="text-muted">Meeting Password:</span> 
                                                <strong class="text-dark font-monospace"><?= htmlspecialchars($cls['meeting_password']) ?></strong>
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-3"></div> <?php endif; ?>
                                        
                                        <a href="<?= htmlspecialchars($cls['meeting_link']) ?>" target="_blank" class="btn btn-primary w-100 fw-bold shadow-sm rounded-pill">
                                            <i class="fas fa-external-link-alt me-2"></i> Join Live Class
                                        </a>
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