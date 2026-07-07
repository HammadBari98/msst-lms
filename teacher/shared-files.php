<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['teacher_user_db_id'])) {
    header('Location: login.php');
    exit;
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$current_type = 'user';
$current_ref_id = (int)$_SESSION['teacher_user_db_id'];

// =======================================================
// AUTO-PATCHER: Shared Files tables
// =======================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shared_files (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            sender_type ENUM('admin','user') NOT NULL,
            sender_ref_id INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shared_file_recipients (
            id INT PRIMARY KEY AUTO_INCREMENT,
            shared_file_id INT NOT NULL,
            recipient_type ENUM('admin','user') NOT NULL,
            recipient_ref_id INT NOT NULL
        )
    ");
} catch (PDOException $e) { /* Ignore if already exists */ }

$upload_dir = __DIR__ . '/../uploads/shared_files/';
if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

// =======================================================
// POST HANDLERS
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_shared_file') {
        try {
            $title = trim($_POST['title'] ?? '');
            $recipients = $_POST['recipients'] ?? [];
            if ($title === '' || empty($recipients)) {
                throw new Exception("Title and at least one recipient are required.");
            }

            $allowed_keys = [];
            foreach ($pdo->query("SELECT id FROM admin")->fetchAll(PDO::FETCH_ASSOC) as $a) {
                if ($current_type === 'admin' && (int)$a['id'] === $current_ref_id) continue;
                $allowed_keys['admin|' . $a['id']] = true;
            }
            $stmt_valid_users = $pdo->query("
                SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id
                WHERE r.role_name IN ('Teacher','Staff') AND u.status = 'Active'
            ");
            foreach ($stmt_valid_users->fetchAll(PDO::FETCH_ASSOC) as $u) {
                if ($current_type === 'user' && (int)$u['id'] === $current_ref_id) continue;
                $allowed_keys['user|' . $u['id']] = true;
            }
            $recipients = array_filter($recipients, fn($r) => isset($allowed_keys[$r]));
            if (empty($recipients)) {
                throw new Exception("No valid recipients selected.");
            }
            if (!isset($_FILES['shared_file']) || $_FILES['shared_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Please select a valid file to upload.");
            }

            $max_file_size = 100 * 1024 * 1024;
            if ($_FILES['shared_file']['size'] > $max_file_size) {
                throw new Exception("The selected file is larger than the 100MB limit.");
            }

            $file_tmp = $_FILES['shared_file']['tmp_name'];
            $original_name = basename($_FILES['shared_file']['name']);
            $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'mp4', 'webm', 'avi', 'mkv'];
            if (!in_array($file_ext, $allowed_exts)) {
                throw new Exception("Invalid file type. Please upload a valid document, image, or video format.");
            }

            $new_file_name = uniqid('sf_') . '_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;
            if (!move_uploaded_file($file_tmp, $destination)) {
                throw new Exception("Failed to save the file. Please contact your system administrator.");
            }
            $file_path = 'uploads/shared_files/' . $new_file_name;

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO shared_files (title, file_name, file_path, sender_type, sender_ref_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $original_name, $file_path, $current_type, $current_ref_id]);
            $shared_file_id = $pdo->lastInsertId();

            $stmt_recip = $pdo->prepare("INSERT INTO shared_file_recipients (shared_file_id, recipient_type, recipient_ref_id) VALUES (?, ?, ?)");
            foreach ($recipients as $recipient) {
                [$r_type, $r_id] = array_pad(explode('|', $recipient), 2, null);
                if (!$r_type || !$r_id) continue;
                $stmt_recip->execute([$shared_file_id, $r_type, (int)$r_id]);
            }
            $pdo->commit();

            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm">File shared successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible shadow-sm">Error: ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
        header('Location: shared-files.php');
        exit;
    } elseif ($_POST['action'] === 'delete_shared_file') {
        $file_id = (int)($_POST['file_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT file_path FROM shared_files WHERE id = ? AND sender_type = ? AND sender_ref_id = ?");
        $stmt->execute([$file_id, $current_type, $current_ref_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($file) {
            $full_path = __DIR__ . '/../' . $file['file_path'];
            if (file_exists($full_path)) unlink($full_path);
            $pdo->prepare("DELETE FROM shared_file_recipients WHERE shared_file_id = ?")->execute([$file_id]);
            $pdo->prepare("DELETE FROM shared_files WHERE id = ?")->execute([$file_id]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm">Shared file deleted.<button type="button" data-bs-dismiss="alert" class="btn-close"></button></div>';
        }
        header('Location: shared-files.php');
        exit;
    }
}

$action_msg = '';
if (isset($_SESSION['action_msg'])) { $action_msg = $_SESSION['action_msg']; unset($_SESSION['action_msg']); }

// =======================================================
// FETCH DATA
// =======================================================
$recipients_list = [];
foreach ($pdo->query("SELECT id, full_name FROM admin")->fetchAll(PDO::FETCH_ASSOC) as $a) {
    if ($current_type === 'admin' && (int)$a['id'] === $current_ref_id) continue;
    $recipients_list[] = ['type' => 'admin', 'id' => $a['id'], 'full_name' => $a['full_name'] . ' (Admin)'];
}
$stmt_users = $pdo->query("
    SELECT u.id, u.full_name, r.role_name
    FROM users u JOIN roles r ON u.role_id = r.id
    WHERE r.role_name IN ('Teacher','Staff') AND u.status = 'Active'
    ORDER BY r.role_name, u.full_name
");
foreach ($stmt_users->fetchAll(PDO::FETCH_ASSOC) as $u) {
    if ($current_type === 'user' && (int)$u['id'] === $current_ref_id) continue;
    $recipients_list[] = ['type' => 'user', 'id' => $u['id'], 'full_name' => $u['full_name'] . ' (' . $u['role_name'] . ')'];
}

function resolveSharedFileName($pdo, $type, $ref_id) {
    $table = $type === 'admin' ? 'admin' : 'users';
    $stmt = $pdo->prepare("SELECT full_name FROM $table WHERE id = ?");
    $stmt->execute([$ref_id]);
    return $stmt->fetchColumn() ?: 'Unknown';
}

$stmt_received = $pdo->prepare("
    SELECT sf.id, sf.title, sf.file_name, sf.file_path, sf.uploaded_at, sf.sender_type, sf.sender_ref_id
    FROM shared_file_recipients sfr
    JOIN shared_files sf ON sf.id = sfr.shared_file_id
    WHERE sfr.recipient_type = ? AND sfr.recipient_ref_id = ?
    ORDER BY sf.uploaded_at DESC
");
$stmt_received->execute([$current_type, $current_ref_id]);
$received_files = $stmt_received->fetchAll(PDO::FETCH_ASSOC);
foreach ($received_files as &$rf) {
    $rf['sender_name'] = resolveSharedFileName($pdo, $rf['sender_type'], $rf['sender_ref_id']);
}
unset($rf);

$stmt_sent = $pdo->prepare("SELECT id, title, file_name, file_path, uploaded_at FROM shared_files WHERE sender_type = ? AND sender_ref_id = ? ORDER BY uploaded_at DESC");
$stmt_sent->execute([$current_type, $current_ref_id]);
$sent_files = $stmt_sent->fetchAll(PDO::FETCH_ASSOC);
foreach ($sent_files as &$sf) {
    $stmt_r = $pdo->prepare("SELECT recipient_type, recipient_ref_id FROM shared_file_recipients WHERE shared_file_id = ?");
    $stmt_r->execute([$sf['id']]);
    $names = [];
    foreach ($stmt_r->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $names[] = resolveSharedFileName($pdo, $r['recipient_type'], $r['recipient_ref_id']);
    }
    $sf['recipient_names'] = implode(', ', $names);
}
unset($sf);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Files | <?= htmlspecialchars($teacher_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <?= $action_msg ?>
            <h1 class="h3 mb-4">Shared Files</h1>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Share a New File</h6></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_shared_file">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">File</label>
                            <input type="file" class="form-control" name="shared_file" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Recipients</label>
                            <div class="border rounded p-2" style="max-height:200px; overflow-y:auto;">
                                <?php foreach ($recipients_list as $r): $key = $r['type'] . '|' . $r['id']; ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recipients[]" value="<?= htmlspecialchars($key) ?>" id="recip_<?= htmlspecialchars($key) ?>">
                                        <label class="form-check-label" for="recip_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($r['full_name']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Share File</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Received</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Title</th><th>From</th><th>File</th><th>Date</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($received_files as $rf): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rf['title']) ?></td>
                                    <td><?= htmlspecialchars($rf['sender_name']) ?></td>
                                    <td><?= htmlspecialchars($rf['file_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($rf['uploaded_at'])) ?></td>
                                    <td><a href="shared-file-download.php?file_id=<?= (int)$rf['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($received_files)): ?><tr><td colspan="5" class="text-center text-muted py-4">No files received yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3"><h6 class="m-0 fw-bold text-primary">Sent</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Title</th><th>To</th><th>File</th><th>Date</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($sent_files as $sf): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sf['title']) ?></td>
                                    <td><?= htmlspecialchars($sf['recipient_names']) ?></td>
                                    <td><?= htmlspecialchars($sf['file_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($sf['uploaded_at'])) ?></td>
                                    <td>
                                        <a href="shared-file-download.php?file_id=<?= (int)$sf['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this shared file?');">
                                            <input type="hidden" name="action" value="delete_shared_file">
                                            <input type="hidden" name="file_id" value="<?= (int)$sf['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sent_files)): ?><tr><td colspan="5" class="text-center text-muted py-4">No files sent yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
