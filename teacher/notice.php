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
$raw_session_id = $_SESSION['teacher_user_db_id'] ?? $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? $_SESSION['id'] ?? null;
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
    $email = $_SESSION['teacher_email'] ?? $_SESSION['email'] ?? '';
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $current_user_db_id = $stmt->fetchColumn() ?: 0;
    }
}

// =======================================================
// 2. AUTO-PATCHER: Add Teacher Tracking to Notices Table
// =======================================================
try {
    $pdo->exec("ALTER TABLE notices ADD COLUMN IF NOT EXISTS teacher_id INT DEFAULT 0");
} catch (PDOException $e) { /* Ignore if already exists */ }

// =======================================================
// 3. HANDLE ACTIONS (Add, Edit, Delete for THIS Teacher)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_notice') {
            $stmt = $pdo->prepare("INSERT INTO notices (title, message, target_audience, is_active, teacher_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['title']),
                trim($_POST['message']),
                'All Students', // Hardcoded so it perfectly matches the Student Panel's fetch logic
                isset($_POST['is_active']) ? 1 : 0,
                $current_user_db_id
            ]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i>Notice published to students successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } 
        elseif ($_POST['action'] === 'edit_notice') {
            // Include teacher_id in WHERE clause so they can't edit admin notices
            $stmt = $pdo->prepare("UPDATE notices SET title = ?, message = ?, is_active = ? WHERE id = ? AND teacher_id = ?");
            $stmt->execute([
                trim($_POST['title']),
                trim($_POST['message']),
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['notice_id'],
                $current_user_db_id
            ]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i>Notice updated successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
        elseif ($_POST['action'] === 'delete_notice') {
            $pdo->prepare("DELETE FROM notices WHERE id = ? AND teacher_id = ?")->execute([$_POST['notice_id'], $current_user_db_id]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-trash me-2"></i>Notice deleted!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
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
try {
    // Admin Notices meant for Teachers
    $stmt_admin = $pdo->prepare("SELECT * FROM notices WHERE target_audience IN ('Teachers', 'Everyone') AND teacher_id = 0 ORDER BY created_at DESC");
    $stmt_admin->execute();
    $admin_notices = $stmt_admin->fetchAll(PDO::FETCH_ASSOC);

    // Notices published by THIS specific teacher
    $stmt_mine = $pdo->prepare("SELECT * FROM notices WHERE teacher_id = ? ORDER BY created_at DESC");
    $stmt_mine->execute([$current_user_db_id]);
    $my_notices = $stmt_mine->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $admin_notices = [];
    $my_notices = [];
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
    <title>Notice Board | <?= htmlspecialchars($teacher_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary: #4e73df; --secondary: #858796; --success: #1cc88a; 
            --warning: #f6c23e; --danger: #e74a3b; --light-bg: #f8f9fc;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .notice-card { border-left: 4px solid var(--primary); transition: transform 0.2s ease; margin-bottom: 1rem;}
        .notice-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.1)!important; }
        .notice-card.admin { border-left-color: var(--warning); }
        .notice-icon { width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .notice-icon.admin { background-color: rgba(246, 194, 62, 0.15); color: var(--warning); }
        .notice-icon.mine { background-color: rgba(78, 115, 223, 0.15); color: var(--primary); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-bullhorn me-2 text-primary"></i>Notice Board</h1>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                        <i class="fas fa-plus me-1"></i> Post Notice to Students
                    </button>
                </div>

                <?= $action_msg ?>

                <div class="row">
                    <div class="col-lg-5 mb-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header py-3 bg-white border-bottom">
                                <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-university me-2"></i>School Announcements</h6>
                            </div>
                            <div class="card-body bg-light p-3">
                                <?php if (empty($admin_notices)): ?>
                                    <div class="text-center p-4 text-muted">
                                        <i class="fas fa-bell-slash fa-3x mb-3 opacity-25"></i>
                                        <p class="mb-0">No new school announcements.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($admin_notices as $notice): ?>
                                        <div class="card notice-card admin shadow-sm border-0">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start mb-2">
                                                    <div class="notice-icon admin me-3"><i class="fas fa-star"></i></div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($notice['title']) ?></h6>
                                                        <div class="small text-muted fw-semibold"><i class="far fa-clock me-1"></i><?= formatNoticeDate($notice['created_at']) ?></div>
                                                    </div>
                                                </div>
                                                <div class="text-dark small" style="white-space: pre-wrap;"><?= htmlspecialchars($notice['message']) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7 mb-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-paper-plane me-2"></i>My Published Notices</h6>
                            </div>
                            <div class="card-body bg-light p-3">
                                <?php if (empty($my_notices)): ?>
                                    <div class="text-center p-5 bg-white rounded border border-light">
                                        <i class="fas fa-clipboard text-muted fa-3x mb-3 opacity-25"></i>
                                        <h6 class="fw-bold text-dark">You haven't posted anything yet.</h6>
                                        <p class="text-muted small mb-0">Use the button above to publish an announcement for your students.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive bg-white rounded border p-2">
                                        <table class="table table-hover align-middle mb-0" id="teacherNoticesTable">
                                            <thead class="table-light small text-muted">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Title</th>
                                                    <th>Status</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($my_notices as $notice): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold text-dark small"><?= date('d M Y', strtotime($notice['created_at'])) ?></div>
                                                            <div class="text-muted" style="font-size: 0.75rem;"><?= date('h:i A', strtotime($notice['created_at'])) ?></div>
                                                        </td>
                                                        <td class="fw-bold text-primary"><?= htmlspecialchars($notice['title']) ?></td>
                                                        <td>
                                                            <?php if ($notice['is_active']): ?>
                                                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary"><i class="fas fa-eye-slash me-1"></i>Hidden</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <button class="btn btn-sm btn-light border text-primary me-1" title="Edit" onclick="editNotice(<?= htmlspecialchars(json_encode($notice)) ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this notice? Students will no longer see it.');">
                                                                <input type="hidden" name="action" value="delete_notice">
                                                                <input type="hidden" name="notice_id" value="<?= $notice['id'] ?>">
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

            </div>
        </div>
        
        <footer class="footer mt-auto py-3 bg-white border-top">
            <div class="container text-center text-muted small">
                 &copy; MSST LMS <?= date('Y') ?> | Teacher Portal
            </div>
        </footer>
    </div>

    <div class="modal fade" id="addNoticeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-pen me-2"></i>Post New Notice</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="add_notice">
                    <div class="modal-body p-4 bg-light">
                        <div class="alert alert-info border-0 shadow-sm small">
                            <i class="fas fa-info-circle me-2"></i> Notices published here will be visible on the <strong>Student Notice Board</strong>.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Notice Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold" name="title" placeholder="e.g. Assignment Deadline Extended" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Message Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="message" rows="5" placeholder="Write your announcement to the students here..." required></textarea>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActiveAdd" checked>
                            <label class="form-check-label fw-bold text-success" for="isActiveAdd">Make Active Immediately (Students can see it)</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-paper-plane me-1"></i> Publish Notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editNoticeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning border-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>Edit My Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="edit_notice">
                    <input type="hidden" name="notice_id" id="editNoticeId">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Notice Title</label>
                            <input type="text" class="form-control fw-bold" name="title" id="editTitle" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Message Content</label>
                            <textarea class="form-control" name="message" id="editMessage" rows="5" required></textarea>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
                            <label class="form-check-label fw-bold" for="editIsActive">Notice is Active (Visible to students)</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fas fa-save me-1"></i> Update Notice</button>
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
        if ($('#teacherNoticesTable').length) {
            $('#teacherNoticesTable').DataTable({
                "order": [[0, "desc"]],
                "pageLength": 5,
                "lengthChange": false,
                "columnDefs": [ { "orderable": false, "targets": 3 } ]
            });
        }
        setTimeout(() => $('.alert').alert('close'), 5000);
    });

    function editNotice(notice) {
        document.getElementById('editNoticeId').value = notice.id;
        document.getElementById('editTitle').value = notice.title;
        document.getElementById('editMessage').value = notice.message;
        document.getElementById('editIsActive').checked = notice.is_active == 1;
        new bootstrap.Modal(document.getElementById('editNoticeModal')).show();
    }
    </script>
</body>
</html>