<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// ==========================================
// 1. AUTO-PATCHER: Create Notices Table
// ==========================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notices (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            target_audience VARCHAR(50) DEFAULT 'All Students',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) { /* Ignore if already exists */ }

// ==========================================
// 2. HANDLE ACTIONS (Add, Edit, Delete)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_notice') {
            $stmt = $pdo->prepare("INSERT INTO notices (title, message, target_audience, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                trim($_POST['title']),
                trim($_POST['message']),
                $_POST['target_audience'] ?? 'All Students',
                isset($_POST['is_active']) ? 1 : 0
            ]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Notice published successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } 
        elseif ($_POST['action'] === 'edit_notice') {
            $stmt = $pdo->prepare("UPDATE notices SET title = ?, message = ?, target_audience = ?, is_active = ? WHERE id = ?");
            $stmt->execute([
                trim($_POST['title']),
                trim($_POST['message']),
                $_POST['target_audience'] ?? 'All Students',
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['notice_id']
            ]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Notice updated successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
        elseif ($_POST['action'] === 'delete_notice') {
            $pdo->prepare("DELETE FROM notices WHERE id = ?")->execute([$_POST['notice_id']]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-trash me-2"></i>Notice deleted!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    } catch (Exception $e) {
        $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch session messages
$action_msg = '';
if (isset($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// Fetch all notices
try {
    $notices = $pdo->query("SELECT * FROM notices ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice Board | MSST Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .notice-content-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .notice-card { border-left: 4px solid var(--primary); }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper p-4">
        <div class="container-fluid">
            
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-bullhorn text-primary me-2"></i>Notice Board</h1>
                <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                    <i class="fas fa-plus me-1"></i> Publish New Notice
                </button>
            </div>

            <?= $action_msg ?>

            <div class="card shadow mb-4 border-0">
                <div class="card-header py-3 bg-white border-bottom">
                    <h6 class="m-0 font-weight-bold text-primary">All Announcements</h6>
                </div>
                <div class="card-body bg-light">
                    <div class="table-responsive bg-white p-3 rounded border">
                        <table class="table table-hover align-middle" id="noticesTable" width="100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Date Posted</th>
                                    <th>Title</th>
                                    <th>Message Preview</th>
                                    <th>Audience</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notices as $notice): ?>
                                    <tr>
                                        <td data-sort="<?= $notice['created_at'] ?>">
                                            <span class="fw-bold"><?= date('d M, Y', strtotime($notice['created_at'])) ?></span><br>
                                            <small class="text-muted"><?= date('h:i A', strtotime($notice['created_at'])) ?></small>
                                        </td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($notice['title']) ?></td>
                                        <td class="text-muted notice-content-preview"><?= htmlspecialchars(strip_tags($notice['message'])) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($notice['target_audience']) ?></span></td>
                                        <td>
                                            <?php if ($notice['is_active']): ?>
                                                <span class="badge bg-success rounded-pill"><i class="fas fa-check-circle me-1"></i>Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-pill"><i class="fas fa-eye-slash me-1"></i>Hidden</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-light border text-primary me-1" title="Edit" onclick="editNotice(<?= htmlspecialchars(json_encode($notice)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this notice?');">
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
                </div>
            </div>

        </div>
    </div>
    <footer class="footer mt-auto py-3 bg-white border-top"><div class="container-fluid"><p class="text-center mb-0 small text-muted">© <?= date('Y') ?> MSST Admin Portal.</p></div></footer>
</div>

<div class="modal fade" id="addNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-pen me-2"></i>Draft New Notice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_notice">
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notice Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control fw-bold" name="title" placeholder="e.g. Eid Holidays Announcement" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Target Audience</label>
                        <select class="form-select" name="target_audience">
                            <option value="All Students">All Students</option>
                            <option value="Teachers">Teachers</option>
                            <option value="Everyone">Everyone (Students & Teachers)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="message" rows="5" placeholder="Write your announcement here..." required></textarea>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActiveAdd" checked>
                        <label class="form-check-label fw-bold text-success" for="isActiveAdd">Publish Immediately (Visible to students)</label>
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
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2"></i>Edit Notice</h5>
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
                        <label class="form-label fw-bold">Target Audience</label>
                        <select class="form-select" name="target_audience" id="editAudience">
                            <option value="All Students">All Students</option>
                            <option value="Teachers">Teachers</option>
                            <option value="Everyone">Everyone (Students & Teachers)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Content</label>
                        <textarea class="form-control" name="message" id="editMessage" rows="5" required></textarea>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
                        <label class="form-check-label fw-bold" for="editIsActive">Notice is Active (Visible)</label>
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
            if (window.innerWidth <= 768) { sidebar.classList.toggle('show'); mainContent.classList.toggle('show'); } 
            else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('collapsed'); }
        });
    }

    $(document).ready(function() {
        $('#noticesTable').DataTable({
            "order": [[0, "desc"]],
            "pageLength": 10,
            "columnDefs": [ { "orderable": false, "targets": 5 } ]
        });
        setTimeout(() => $('.alert').alert('close'), 5000);
    });

    function editNotice(notice) {
        document.getElementById('editNoticeId').value = notice.id;
        document.getElementById('editTitle').value = notice.title;
        document.getElementById('editMessage').value = notice.message;
        document.getElementById('editAudience').value = notice.target_audience;
        document.getElementById('editIsActive').checked = notice.is_active == 1;
        new bootstrap.Modal(document.getElementById('editNoticeModal')).show();
    }
</script>
</body>
</html>