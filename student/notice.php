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
// FETCH DYNAMIC NOTICES
// =======================================================
$notices = [];
$error_message = null;

try {
    // Fetch active notices, joining with users table to get the author's name
    $stmt = $pdo->prepare("
        SELECT n.title, n.message, n.created_at, n.teacher_id, u.full_name as teacher_name
        FROM notices n
        LEFT JOIN users u ON n.teacher_id = u.id
        WHERE n.is_active = 1 
        AND n.target_audience IN ('All Students', 'Everyone') 
        ORDER BY n.created_at DESC
    ");
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "A database error occurred while loading the notice board.";
}

// Helper to format date relative to today (e.g., "Today", "Yesterday", or exact date)
function formatNoticeDate($datetime) {
    $time = strtotime($datetime);
    $date = date('Y-m-d', $time);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($date === $today) {
        return "Today at " . date('h:i A', $time);
    } elseif ($date === $yesterday) {
        return "Yesterday at " . date('h:i A', $time);
    } else {
        return date('d M Y, h:i A', $time);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice Board | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df; --secondary: #858796; --success: #1cc88a; 
            --info: #36b9cc; --warning: #f6c23e; --danger: #e74a3b; 
            --light-bg: #f8f9fc;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        
        .notice-card {
            border: 0;
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 1.5rem;
        }
        .notice-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1) !important;
        }
        .notice-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(78, 115, 223, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .notice-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        .notice-message {
            white-space: pre-wrap; /* Preserves line breaks from admin textarea */
            color: #495057;
            font-size: 0.95rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid max-w-4xl mx-auto" style="max-width: 900px;">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold">
                        <i class="fas fa-clipboard-list me-2 text-primary"></i>Notice Board
                    </h1>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger shadow-sm border-0">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($notices) && !$error_message): ?>
                    <div class="text-center p-5 bg-white rounded shadow-sm border border-light mt-4">
                        <i class="fas fa-bell-slash fa-4x text-muted mb-3 opacity-25"></i>
                        <h5 class="fw-bold text-dark">No New Announcements</h5>
                        <p class="text-muted mb-0">Check back later for updates from the administration.</p>
                    </div>
                <?php else: ?>
                    <div class="notice-feed">
                        <?php foreach ($notices as $notice): ?>
                            <div class="card notice-card shadow-sm">
                                <div class="card-body p-4">
                                    <?php
                                        $is_admin = empty($notice['teacher_id']) || $notice['teacher_id'] == 0;
                                        $author_name = $is_admin ? 'School Administration' : htmlspecialchars($notice['teacher_name'] ?? 'Teacher');
                                        $badge_class = $is_admin ? 'bg-warning text-dark' : 'bg-primary text-white';
                                        $icon_class = $is_admin ? 'fa-university' : 'fa-chalkboard-teacher';
                                    ?>
                                    <div class="notice-header mb-3">
                                        <div class="notice-icon-wrapper shadow-sm <?= $is_admin ? 'text-warning bg-warning bg-opacity-10' : 'text-primary bg-primary bg-opacity-10' ?>">
                                            <i class="fas <?= $icon_class ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($notice['title']) ?></h5>
                                                <span class="badge <?= $badge_class ?> shadow-sm px-3 py-2 rounded-pill">
                                                    <i class="fas <?= $icon_class ?> me-1"></i> <?= $author_name ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted fw-semibold mt-2">
                                                <i class="far fa-clock me-1"></i> Posted: <?= formatNoticeDate($notice['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="notice-message border-top pt-3 mt-2">
                                        <?= htmlspecialchars($notice['message']) ?>
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