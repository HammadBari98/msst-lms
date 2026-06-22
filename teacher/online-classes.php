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
// 1. ROBUST TEACHER ID FETCH
// =======================================================
$raw_session_id = $_SESSION['user_db_id'] ?? $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? $_SESSION['id'] ?? null;
$teacher_user_id = 0;

if ($raw_session_id) {
    if (is_numeric($raw_session_id)) { $teacher_user_id = (int)$raw_session_id; } 
    else {
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

// Fetch corresponding teacher_details ID
$teacher_details_id = 0;
if ($teacher_user_id > 0) {
    try {
        $stmt_td = $pdo->prepare("SELECT id FROM teacher_details WHERE user_id = ? LIMIT 1");
        $stmt_td->execute([$teacher_user_id]);
        $teacher_details_id = $stmt_td->fetchColumn() ?: 0;
    } catch (Exception $e) {}
}

// =======================================================
// 2. AUTO-PATCHER (Create Online Classes Table)
// =======================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS online_classes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            subject_topic VARCHAR(255) NOT NULL,
            platform VARCHAR(50) NOT NULL,
            meeting_link VARCHAR(500) NOT NULL,
            meeting_password VARCHAR(100) DEFAULT '',
            class_date DATE NOT NULL,
            start_time TIME NOT NULL,
            duration_minutes INT DEFAULT 45,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) { /* Ignore if exists */ }

// =======================================================
// 3. HANDLE ACTIONS (Add, Edit, Delete)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_class') {
            $stmt = $pdo->prepare("INSERT INTO online_classes (teacher_id, class_id, subject_topic, platform, meeting_link, meeting_password, class_date, start_time, duration_minutes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $teacher_user_id,
                intval($_POST['class_id']),
                trim($_POST['subject_topic']),
                trim($_POST['platform']),
                trim($_POST['meeting_link']),
                trim($_POST['meeting_password']),
                $_POST['class_date'],
                $_POST['start_time'],
                intval($_POST['duration_minutes'])
            ]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i>Online class scheduled successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } elseif ($_POST['action'] === 'delete_class') {
            $pdo->prepare("DELETE FROM online_classes WHERE id = ? AND teacher_id = ?")->execute([$_POST['class_id'], $teacher_user_id]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-trash me-2"></i>Class deleted successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    } catch (Exception $e) {
        $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible shadow-sm"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$action_msg = '';
if (isset($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// =======================================================
// 4. FETCH DATA
// =======================================================
$my_assigned_classes = [];
try {
    $stmt_classes = $pdo->prepare("
        SELECT DISTINCT c.id as class_id, c.class_name 
        FROM teacher_class_assignments tca
        JOIN classes c ON tca.class_id = c.id
        WHERE tca.teacher_user_id = ? OR tca.teacher_user_id = ?
        ORDER BY c.class_name
    ");
    $stmt_classes->execute([$teacher_user_id, $teacher_details_id]);
    $my_assigned_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$scheduled_classes = [];
try {
    $stmt_classes = $pdo->prepare("
        SELECT oc.*, c.class_name 
        FROM online_classes oc
        JOIN classes c ON oc.class_id = c.id
        WHERE oc.teacher_id = ?
        ORDER BY oc.class_date DESC, oc.start_time DESC
    ");
    $stmt_classes->execute([$teacher_user_id]);
    $scheduled_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Helper function for platform badges
function getPlatformBadge($platform) {
    if ($platform == 'Zoom') return '<span class="badge bg-primary"><i class="fas fa-video me-1"></i>Zoom</span>';
    if ($platform == 'Google Meet') return '<span class="badge bg-success"><i class="fas fa-chalkboard-teacher me-1"></i>Meet</span>';
    if ($platform == 'MS Teams') return '<span class="badge" style="background-color: #6264A7;"><i class="fas fa-users me-1"></i>Teams</span>';
    return '<span class="badge bg-secondary"><i class="fas fa-link me-1"></i>Other</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Classes | <?= htmlspecialchars($teacher_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        :root { --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content" class="d-flex flex-column min-vh-100">
        <?php include 'header.php' ?>

        <div class="content-wrapper flex-grow-1 p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-laptop-house me-2 text-primary"></i>Live Classes</h1>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#scheduleClassModal">
                        <i class="fas fa-plus-circle me-1"></i> Schedule New Class
                    </button>
                </div>

                <?= $action_msg ?>

                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-alt me-2"></i>My Scheduled Live Classes</h6>
                    </div>
                    <div class="card-body bg-light p-3">
                        <?php if (empty($scheduled_classes)): ?>
                            <div class="text-center p-5 bg-white rounded border border-light">
                                <i class="fas fa-video-slash text-muted fa-4x mb-3 opacity-25"></i>
                                <h5 class="fw-bold text-dark">No Live Classes Scheduled</h5>
                                <p class="text-muted mb-0">You have not scheduled any online classes yet. Use the button above to create one.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive bg-white rounded border p-3">
                                <table class="table table-hover align-middle mb-0" id="onlineClassesTable">
                                    <thead class="table-light text-muted small">
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Subject / Topic</th>
                                            <th>Target Class</th>
                                            <th>Platform</th>
                                            <th>Meeting Link</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scheduled_classes as $cls): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= date('d M Y', strtotime($cls['class_date'])) ?></div>
                                                    <div class="small text-danger fw-semibold"><i class="far fa-clock me-1"></i><?= date('h:i A', strtotime($cls['start_time'])) ?> (<?= $cls['duration_minutes'] ?> mins)</div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($cls['subject_topic']) ?></div>
                                                </td>
                                                <td><span class="badge bg-secondary">Class <?= htmlspecialchars($cls['class_name']) ?></span></td>
                                                <td><?= getPlatformBadge($cls['platform']) ?></td>
                                                <td>
                                                    <a href="<?= htmlspecialchars($cls['meeting_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm rounded-pill px-3">
                                                        <i class="fas fa-external-link-alt me-1"></i> Join
                                                    </a>
                                                    <?php if(!empty($cls['meeting_password'])): ?>
                                                        <div class="small text-muted mt-1">Pass: <?= htmlspecialchars($cls['meeting_password']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this class?');">
                                                        <input type="hidden" name="action" value="delete_class">
                                                        <input type="hidden" name="class_id" value="<?= $cls['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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

    <div class="modal fade" id="scheduleClassModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fas fa-video me-2"></i>Schedule Online Class</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="add_class">
                    <div class="modal-body p-4 bg-light">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select Class <span class="text-danger">*</span></label>
                                <select class="form-select" name="class_id" required>
                                    <option value="" disabled selected>Choose a class...</option>
                                    <?php foreach ($my_assigned_classes as $ac): ?>
                                        <option value="<?= htmlspecialchars($ac['class_id']) ?>">Class <?= htmlspecialchars($ac['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Subject / Topic <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="subject_topic" placeholder="e.g. Physics - Chapter 3" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="class_date" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Duration (Mins) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="duration_minutes" value="45" min="15" max="180" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Platform <span class="text-danger">*</span></label>
                                <select class="form-select" name="platform" required>
                                    <option value="Zoom">Zoom</option>
                                    <option value="Google Meet">Google Meet</option>
                                    <option value="MS Teams">Microsoft Teams</option>
                                    <option value="Other">Other Platform</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Meeting URL Link <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" name="meeting_link" placeholder="https://zoom.us/j/..." required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Meeting Password / Passcode <small class="text-muted">(Optional)</small></label>
                                <input type="text" class="form-control" name="meeting_password" placeholder="Leave blank if not required">
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fas fa-calendar-check me-2"></i> Schedule Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

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

    $(document).ready(function() {
        if ($('#onlineClassesTable').length) {
            $('#onlineClassesTable').DataTable({
                "order": [[0, "desc"]], 
                "pageLength": 10,
                "columnDefs": [ { "orderable": false, "targets": [4, 5] } ]
            });
        }
        setTimeout(() => $('.alert').alert('close'), 5000);
    });
    </script>
</body>
</html>