<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Authenticate the user
if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// =======================================================
// 1. ROBUST TEACHER ID FETCH (Converts String to Numeric)
// =======================================================
$raw_session_id = $_SESSION['teacher_user_db_id'] ?? $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? $_SESSION['id'] ?? null;
$teacher_user_id = 0;

if ($raw_session_id) {
    if (is_numeric($raw_session_id)) { 
        $teacher_user_id = (int)$raw_session_id; 
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
        $stmt->execute([$raw_session_id]);
        $teacher_user_id = $stmt->fetchColumn() ?: 0;
    }
}
if (!$teacher_user_id) {
    $email = $_SESSION['teacher_email'] ?? $_SESSION['email'] ?? '';
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $teacher_user_id = $stmt->fetchColumn() ?: 0;
    }
}

// =======================================================
// 2. CROSS-REFERENCE FIX: Fetch corresponding teacher_details ID
// =======================================================
$teacher_details_id = 0;
if ($teacher_user_id > 0) {
    try {
        $stmt_td = $pdo->prepare("SELECT id FROM teacher_details WHERE user_id = ? LIMIT 1");
        $stmt_td->execute([$teacher_user_id]);
        $teacher_details_id = $stmt_td->fetchColumn() ?: 0;
    } catch (Exception $e) {}
}

// =======================================================
// 3. DYNAMIC DATA FETCHING (Using Real Assignment Tables)
// =======================================================
try {
    // A. Fetch Detailed List of Assigned (Class, Section) Pairs
    $stmt_my_classes_list = $pdo->prepare("
        SELECT DISTINCT c.id as class_id, c.class_name, IFNULL(tca.section_id,0) as section_id, sec.section_name
        FROM (SELECT DISTINCT class_id, section_id FROM teacher_class_assignments WHERE teacher_user_id = ? OR teacher_user_id = ?) tca
        JOIN classes c ON tca.class_id = c.id
        LEFT JOIN sections sec ON tca.section_id = sec.id
        ORDER BY c.class_name, sec.section_name
    ");
    $stmt_my_classes_list->execute([$teacher_user_id, $teacher_details_id]);
    $my_assigned_classes = $stmt_my_classes_list->fetchAll(PDO::FETCH_ASSOC);
    $total_my_classes = count($my_assigned_classes);

    // B. Total Active Students in THIS Teacher's assigned sections
    $stmt_students = $pdo->prepare("
        SELECT COUNT(DISTINCT sd.user_id)
        FROM student_details sd
        JOIN (SELECT DISTINCT class_id, section_id FROM teacher_class_assignments WHERE teacher_user_id = ? OR teacher_user_id = ?) tca
          ON sd.class_id = tca.class_id AND IFNULL(sd.section_id,0) = IFNULL(tca.section_id,0)
        JOIN users u ON sd.user_id = u.id
        WHERE u.status = 'Active'
    ");
    $stmt_students->execute([$teacher_user_id, $teacher_details_id]);
    $total_my_students = $stmt_students->fetchColumn() ?: 0;

    // C. Upcoming Assessments Created for THIS Teacher's assigned sections
    $stmt_assess_count = $pdo->prepare("
        SELECT COUNT(DISTINCT a.id)
        FROM assessments a
        JOIN (SELECT DISTINCT class_id, section_id FROM teacher_class_assignments WHERE teacher_user_id = ? OR teacher_user_id = ?) tca
          ON a.class_id = tca.class_id AND IFNULL(a.section_id,0) = IFNULL(tca.section_id,0)
        WHERE a.assessment_date >= CURRENT_DATE
    ");
    $stmt_assess_count->execute([$teacher_user_id, $teacher_details_id]);
    $pending_assessments_count = $stmt_assess_count->fetchColumn() ?: 0;

    // D. Fetch Upcoming Assessments Timeline (Only for their assigned sections)
    $stmt_assessments = $pdo->prepare("
        SELECT DISTINCT a.*, c.class_name
        FROM assessments a
        JOIN (SELECT DISTINCT class_id, section_id FROM teacher_class_assignments WHERE teacher_user_id = ? OR teacher_user_id = ?) tca
          ON a.class_id = tca.class_id AND IFNULL(a.section_id,0) = IFNULL(tca.section_id,0)
        LEFT JOIN classes c ON a.class_id = c.id
        WHERE a.assessment_date >= CURRENT_DATE
        ORDER BY a.assessment_date ASC LIMIT 5
    ");
    $stmt_assessments->execute([$teacher_user_id, $teacher_details_id]);
    $upcoming_assessments = $stmt_assessments->fetchAll(PDO::FETCH_ASSOC);

    // E. Fetch Notices strictly for Teachers or Everyone
    $stmt_notices = $pdo->query("
        SELECT title, message, created_at 
        FROM notices 
        WHERE is_active = 1 AND target_audience IN ('Teachers', 'Everyone') 
        ORDER BY created_at DESC LIMIT 5
    ");
    $notices = $stmt_notices->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Graceful Fallback
    $total_my_classes = 0; $total_my_students = 0; $pending_assessments_count = 0;
    $my_assigned_classes = []; $upcoming_assessments = []; $notices = [];
}

// Helper to format Notice Dates nicely
function formatNoticeDate($datetime) {
    $time = strtotime($datetime);
    $date = date('Y-m-d', $time);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($date === $today) return "Today at " . date('h:i A', $time);
    if ($date === $yesterday) return "Yesterday at " . date('h:i A', $time);
    return date('d M Y, h:i A', $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($teacher_name) ?> | My Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        :root {
            --primary: #4e73df; --secondary: #858796; --success: #1cc88a; 
            --info: #36b9cc; --warning: #f6c23e; --danger: #e74a3b; 
            --light-bg: #f8f9fc; --accent: #4e73df;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .welcome-message {
            background: linear-gradient(45deg, #1cc88a, #13855c);
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
            border: 0; border-left: 5px solid #e9ecef; margin-bottom: 5px; border-radius: 4px;
        }
        .upcoming-list .list-group-item:hover {
            background-color: #f8f9fa;
        }
        .notice-icon-wrapper {
            width: 40px; height: 40px; border-radius: 50%;
            background-color: rgba(246, 194, 62, 0.15); color: var(--warning);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 1.2rem;
        }
        .class-badge {
            background-color: #e7f3ff;
            color: #0d6efd;
            border: 1px solid #b6d4fe;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            margin: 4px;
            font-size: 0.95rem;
        }
        /* Sticky Footer Fix for Sidebar Layouts */
        #main-content {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .content-wrapper {
            flex-grow: 1; /* Pushes the footer to the bottom */
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content" class="d-flex flex-column min-vh-100">
        <?php include 'header.php' ?>

        <div class="content-wrapper flex-grow-1 p-4">
            <div class="container-fluid">

                <div class="welcome-message">
                    <h1 class="h2 font-weight-bold mb-1">Welcome Back, <?= htmlspecialchars($teacher_name) ?>!</h1>
                    <p class="lead mb-0"><i class="fas fa-chalkboard-teacher me-2"></i> Here is a summary of your assigned classes and tasks.</p>
                </div>

                <h2 class="h4 mb-4 text-gray-800"><i class="fas fa-chart-line me-2 text-primary"></i> My Overview</h2>
                <div class="row">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--success);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">My Assigned Classes</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $total_my_classes ?></div>
                                </div>
                                <i class="fas fa-layer-group fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--primary);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">My Total Students</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $total_my_students ?></div>
                                </div>
                                <i class="fas fa-users fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--warning);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Assessments</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $pending_assessments_count ?></div>
                                </div>
                                <i class="fas fa-clipboard-list fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-7 mb-4">
                        
                        <div class="card shadow-sm mb-4 border-0">
                            <div class="card-header py-3 bg-white border-bottom">
                                <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-book-reader me-2"></i>Classes I Teach</h6>
                            </div>
                            <div class="card-body bg-light">
                                <?php if (empty($my_assigned_classes)): ?>
                                    <div class="alert alert-info border-0 shadow-sm mb-0">
                                        <i class="fas fa-info-circle me-2"></i> You have not been assigned to any specific classes yet. Please contact the administrator.
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach ($my_assigned_classes as $cls): ?>
                                            <div class="class-badge shadow-sm">
                                                Class <?= htmlspecialchars($cls['class_name']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card shadow-sm h-100 border-0">
                            <div class="card-header py-3 bg-white border-bottom">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-check me-2"></i>My Scheduled Assessments</h6>
                            </div>
                            <div class="card-body bg-light">
                                <div class="list-group list-group-flush upcoming-list">
                                    <?php if (!empty($upcoming_assessments)): ?>
                                        <?php foreach ($upcoming_assessments as $ass): 
                                            $icon = 'fa-file-alt'; $color = 'text-primary';
                                            if ($ass['assessment_type'] === 'Exam') { $icon = 'fa-laptop-code'; $color = 'text-danger'; }
                                            elseif ($ass['assessment_type'] === 'Test') { $icon = 'fa-edit'; $color = 'text-info'; }
                                        ?>
                                            <div class="list-group-item px-4 py-3 shadow-sm bg-white border-left-<?= explode('-', $color)[1] ?>">
                                                <div class="d-flex w-100 align-items-center">
                                                    <div class="me-4 text-center" style="width: 40px;">
                                                        <i class="fas <?= $icon ?> fa-2x <?= $color ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                                            <h6 class="mb-1 font-weight-bold text-dark"><?= htmlspecialchars($ass['subject_name'] ?? 'Assessment') ?> <?= htmlspecialchars($ass['assessment_type']) ?></h6>
                                                            <span class="badge bg-light text-dark border <?= $color ?> border-opacity-25 px-2 py-1">
                                                                <i class="far fa-clock me-1"></i><?= date('d M Y', strtotime($ass['assessment_date'])) ?>
                                                            </span>
                                                        </div>
                                                        <p class="mb-0 text-muted small mt-1">Class: <?= htmlspecialchars($ass['class_name'] ?? 'N/A') ?> | Title: <?= htmlspecialchars($ass['title']) ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center p-4 bg-white rounded shadow-sm border border-light h-100 d-flex flex-column justify-content-center">
                                            <i class="fas fa-mug-hot fa-3x text-muted mb-3 opacity-50"></i>
                                            <h6 class="fw-bold text-dark">Schedule Clear!</h6>
                                            <p class="text-muted small mb-0">You have no upcoming assessments or tests scheduled for your classes.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 mb-4">
                        <div class="card shadow-sm h-100 border-0">
                            <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-bullhorn me-2"></i>Notice Board</h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($notices)): ?>
                                    <div class="text-center p-5 text-muted">
                                        <i class="fas fa-bell-slash fa-3x mb-3 opacity-25"></i>
                                        <p class="mb-0">No new announcements for teachers.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($notices as $notice): ?>
                                            <div class="list-group-item p-3 border-bottom">
                                                <div class="d-flex align-items-start">
                                                    <div class="notice-icon-wrapper me-3">
                                                        <i class="fas fa-bell"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($notice['title']) ?></h6>
                                                        <p class="mb-1 small text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                            <?= htmlspecialchars(strip_tags($notice['message'])) ?>
                                                        </p>
                                                        <small class="text-primary fw-semibold"><i class="far fa-clock me-1"></i><?= formatNoticeDate($notice['created_at']) ?></small>
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

            </div>
        </div>
        
        <footer class="footer mt-auto py-3 bg-white border-top">
            <div class="container text-center text-muted small">
                 &copy; MSST LMS <?= date('Y') ?> | Teacher Portal
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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