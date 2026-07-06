# Staff Panel + Cross-Role File Sharing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admin, teacher, and staff share files with any specific individual(s) among those three roles, and build the minimal staff panel needed to support this (staff has no panel at all today).

**Architecture:** Two new tables (`shared_files`, `shared_file_recipients`) using a polymorphic `type`+`ref_id` pair on both sender and recipient, because admin identity lives in a separate `admin` table (no `role_id`) while teacher/staff both live in `users` distinguished by `role_id`. Three near-identical `shared-files.php` pages (admin root, `teacher/`, `staff/`) each expose the same upload/list/delete UI scoped to that role's own identity — this mirrors how this codebase already triplicates per-role pages rather than sharing one file across role directories. The staff panel itself is a minimal copy of the existing `teacher/` panel's login/session/sidebar/header shape, trimmed to only Dashboard, Shared Files, Profile, Logout.

**Tech Stack:** PHP 8 / PDO / MySQL (MariaDB via XAMPP), no ORM, no automated test framework — verification is manual (curl against XAMPP Apache + direct MySQL queries), same as the rest of this codebase.

## Global Constraints

- **Polymorphic identity convention:** every reference to "who sent/received this" is a `(type, ref_id)` pair where `type` is `'admin'` (→ `admin.id`) or `'user'` (→ `users.id`, further distinguished by that row's `role_id`). Never assume a single `users.id` FK is enough — admin has no row in `users` at all.
- **Combo value convention:** the recipient checkbox list uses pipe-joined `type|ref_id` values (e.g. `admin|1`, `user|184`), parsed server-side via `array_pad(explode('|', $value), 2, null)` — same convention already used throughout this codebase for combo values.
- **File upload convention (reused verbatim from `teacher/study-materials.php`):** 100MB max size, allowed extensions `pdf, doc, docx, xls, xlsx, txt, ppt, pptx, jpg, jpeg, png, mp4, webm, avi, mkv`, stored at root-relative path `uploads/shared_files/sf_<uniqid>_<time>.<ext>` (matching `study_materials`'s `'uploads/study_materials/' . $new_file_name` root-relative storage convention) regardless of which role's page performed the upload.
- **Auto-patcher convention:** both new tables are created via `try { $pdo->exec("CREATE TABLE IF NOT EXISTS ...") } catch (PDOException $e) {}` blocks. Because three different entry points (admin, teacher, staff pages) could each be the first one visited, the identical auto-patcher block appears in all three `shared-files.php` files (matching this codebase's existing pattern of duplicating an auto-patcher across every file that might be the first to need a shared table).
- **Delete scope:** only the original sender (matched on `sender_type`+`sender_ref_id`) can delete a shared file. Deleting removes the physical file, all `shared_file_recipients` rows, and the `shared_files` row itself — mirrors `study-materials.php`'s `delete_material` handler exactly.
- **Students excluded:** the recipient picker query only ever selects `admin` rows and `users` rows with `role_name IN ('Teacher','Staff')` — students never appear as possible recipients or senders anywhere in this feature.
- **Legacy `employee/`/`employees` system:** not touched, not fixed, not referenced by any file in this plan.
- **Staff auth:** built entirely on the existing `users`/`roles` system (`role_name = 'Staff'`), following the exact same login/session pattern as `teacher/login.php` (`$_SESSION['staff_logged_in']`, `$_SESSION['user_db_id']`, `$_SESSION['staff_id']` for the `user_id_string`, `$_SESSION['staff_name']`).

---

### Task 1: Schema + admin shared-files page

**Files:**
- Create: `shared-files.php`
- Modify: `sidebar.php` (admin sidebar link)

**Interfaces:**
- Produces: `shared_files` table (`id, title, file_name, file_path, sender_type, sender_ref_id, uploaded_at`), `shared_file_recipients` table (`id, shared_file_id, recipient_type, recipient_ref_id`) — Tasks 2 and 4 read/write these same tables from their own role's page.
- Produces: the `type|ref_id` combo-value convention for the recipient checkbox list — Tasks 2 and 4 reuse this exact parsing.

- [ ] **Step 1: Create `shared-files.php` with schema, upload/delete handlers, and data fetch**

Create `shared-files.php`:

```php
<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$current_type = 'admin';
$current_ref_id = (int)($_SESSION['admin_id'] ?? 0);

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

$upload_dir = __DIR__ . '/uploads/shared_files/';
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
            $full_path = __DIR__ . '/' . $file['file_path'];
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
    <title>Shared Files | MSST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                                    <td><a href="<?= htmlspecialchars($rf['file_path']) ?>" class="btn btn-sm btn-outline-primary" download><i class="fas fa-download"></i></a></td>
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
                                        <a href="<?= htmlspecialchars($sf['file_path']) ?>" class="btn btn-sm btn-outline-primary" download><i class="fas fa-download"></i></a>
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
```

- [ ] **Step 2: Add the admin sidebar link**

In `sidebar.php`, after the line `<li><a href="notice-board.php">...Notice Board</span></a></li>` (line 23), add:

```php
        <li><a href="shared-files.php"><i class="fas fa-share-alt fa-fw me-2"></i> <span>Shared Files</span></a></li>
```

- [ ] **Step 3: Verify schema creation and full upload/receive/delete cycle**

The real admin account in this project's DB is `admin.id = 1` ("System Administrator") — the disposable admin-session bootstrap script used throughout this project's manual testing must set `$_SESSION['admin_id']` to this real numeric id (not a placeholder string), since this feature's polymorphic sender/recipient resolution depends on it being correct:

```bash
cat > _t_admin.php <<'EOF'
<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_name'] = 'System Administrator';
echo "ok";
EOF
curl -s -c /tmp/c_admin.txt http://localhost/msst/lms/_t_admin.php
curl -s -b /tmp/c_admin.txt -o /dev/null -w "HTTP %{http_code}\n" http://localhost/msst/lms/shared-files.php
mysql -u root msst_db -e "SHOW TABLES LIKE 'shared_file%';"
```

Expected: HTTP 200, both tables exist.

Create a small disposable test file and upload it addressed to the real teacher account (`user_id=184`, `TEA-38371`):

```bash
echo "test shared file content" > /tmp/zz_shared_test.txt
curl -s -b /tmp/c_admin.txt -X POST \
  -F "action=upload_shared_file" \
  -F "title=ZZ Test Share" \
  -F "recipients[]=user|184" \
  -F "shared_file=@/tmp/zz_shared_test.txt" \
  http://localhost/msst/lms/shared-files.php -o /tmp/upload_out.html -w "HTTP %{http_code}\n"
mysql -u root msst_db -e "SELECT sf.id, sf.title, sf.sender_type, sf.sender_ref_id, sfr.recipient_type, sfr.recipient_ref_id FROM shared_files sf JOIN shared_file_recipients sfr ON sfr.shared_file_id = sf.id WHERE sf.title = 'ZZ Test Share';"
```

Expected: HTTP 302, one row with `sender_type='admin', sender_ref_id=1, recipient_type='user', recipient_ref_id=184`.

Verify it appears in admin's own Sent view, then delete it and confirm cleanup:

```bash
curl -s -b /tmp/c_admin.txt http://localhost/msst/lms/shared-files.php -o /tmp/admin_page.html
grep -c "ZZ Test Share" /tmp/admin_page.html
FILE_ID=$(mysql -u root msst_db -N -e "SELECT id FROM shared_files WHERE title='ZZ Test Share';")
FILE_PATH=$(mysql -u root msst_db -N -e "SELECT file_path FROM shared_files WHERE id=$FILE_ID;")
curl -s -b /tmp/c_admin.txt -X POST -d "action=delete_shared_file&file_id=$FILE_ID" http://localhost/msst/lms/shared-files.php
mysql -u root msst_db -e "SELECT COUNT(*) FROM shared_files WHERE id=$FILE_ID; SELECT COUNT(*) FROM shared_file_recipients WHERE shared_file_id=$FILE_ID;"
ls "uploads/shared_files/" 2>&1
```

Expected: grep count ≥ 1 (appears at least in title column), both counts 0 after delete, and the physical file no longer listed in `uploads/shared_files/`.

Clean up the temp file and leave `_t_admin.php`/`/tmp/c_admin.txt` in place for later tasks:

```bash
rm -f /tmp/zz_shared_test.txt /tmp/upload_out.html /tmp/admin_page.html
```

- [ ] **Step 4: Commit**

```bash
git add shared-files.php sidebar.php
git commit -m "Add shared-files schema and admin file-sharing page"
```

---

### Task 2: Teacher shared-files page

**Files:**
- Create: `teacher/shared-files.php`
- Modify: `teacher/sidebar.php`

**Interfaces:**
- Consumes: `shared_files`/`shared_file_recipients` tables from Task 1.

- [ ] **Step 1: Create `teacher/shared-files.php`**

This is the same page as Task 1's `shared-files.php`, adapted for the teacher role: auth check, `$current_type`/`$current_ref_id` derivation, and filesystem paths (one directory level down, matching `teacher/study-materials.php`'s `__DIR__ . '/../uploads/study_materials/'` convention) all change; the SQL, upload validation, and HTML structure are otherwise identical.

Create `teacher/shared-files.php`:

```php
<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['user_db_id'])) {
    header('Location: login.php');
    exit;
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$current_type = 'user';
$current_ref_id = (int)$_SESSION['user_db_id'];

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
                                    <td><a href="../<?= htmlspecialchars($rf['file_path']) ?>" class="btn btn-sm btn-outline-primary" download><i class="fas fa-download"></i></a></td>
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
                                        <a href="../<?= htmlspecialchars($sf['file_path']) ?>" class="btn btn-sm btn-outline-primary" download><i class="fas fa-download"></i></a>
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
```

- [ ] **Step 2: Add the teacher sidebar link**

In `teacher/sidebar.php`, after the line `<li><a href="notice.php">...Notices</span></a></li>` (line 196), add:

```php
    <li><a href="shared-files.php"><i class="fas fa-share-alt"></i> <span>Shared Files</span></a></li>
```

- [ ] **Step 3: Verify via curl, including the teacher-as-sender path**

The real teacher account (`user_id=184`, `TEA-38371`) sends a file to the real admin (`admin.id=1`):

```bash
cat > _t_teacher.php <<'EOF'
<?php
session_start();
$_SESSION['teacher_logged_in'] = true;
$_SESSION['teacher_id'] = 'TEA-38371';
$_SESSION['teacher_name'] = 'test teacher ics 11 class';
$_SESSION['user_db_id'] = 184;
echo "ok";
EOF
curl -s -c /tmp/c_teacher.txt http://localhost/msst/lms/_t_teacher.php
echo "test shared file from teacher" > /tmp/zz_teacher_share.txt
curl -s -b /tmp/c_teacher.txt -X POST \
  -F "action=upload_shared_file" \
  -F "title=ZZ Teacher To Admin" \
  -F "recipients[]=admin|1" \
  -F "shared_file=@/tmp/zz_teacher_share.txt" \
  http://localhost/msst/lms/teacher/shared-files.php -o /tmp/teacher_upload.html -w "HTTP %{http_code}\n"
mysql -u root msst_db -e "SELECT sender_type, sender_ref_id FROM shared_files WHERE title='ZZ Teacher To Admin';"
```

Expected: HTTP 302, `sender_type='user', sender_ref_id=184`.

Confirm it appears in the real admin's Received view (reusing `_t_admin.php`/`/tmp/c_admin.txt` from Task 1 — recreate if missing):

```bash
curl -s -b /tmp/c_admin.txt http://localhost/msst/lms/shared-files.php -o /tmp/admin_received.html
grep -c "ZZ Teacher To Admin" /tmp/admin_received.html
```

Expected: count ≥ 1.

Clean up:

```bash
FILE_ID=$(mysql -u root msst_db -N -e "SELECT id FROM shared_files WHERE title='ZZ Teacher To Admin';")
curl -s -b /tmp/c_teacher.txt -X POST -d "action=delete_shared_file&file_id=$FILE_ID" http://localhost/msst/lms/teacher/shared-files.php
mysql -u root msst_db -e "SELECT COUNT(*) FROM shared_files WHERE id=$FILE_ID;"
rm -f /tmp/zz_teacher_share.txt /tmp/teacher_upload.html /tmp/admin_received.html
```

Expected: count 0.

- [ ] **Step 4: Commit**

```bash
git add "teacher/shared-files.php" "teacher/sidebar.php"
git commit -m "Add teacher file-sharing page"
```

---

### Task 3: Staff panel scaffold (login, session, dashboard, profile, logout)

**Files:**
- Create: `staff/login.php`
- Create: `staff/logout.php`
- Create: `staff/header.php`
- Create: `staff/sidebar.php`
- Create: `staff/dashboard.php`
- Create: `staff/profile.php`

**Interfaces:**
- Produces: the `$_SESSION['staff_logged_in']`, `$_SESSION['user_db_id']`, `$_SESSION['staff_id']`, `$_SESSION['staff_name']` session-variable convention — Task 4's `staff/shared-files.php` consumes these exact names.

- [ ] **Step 1: Create the `staff/` directory and `staff/login.php`**

```bash
mkdir -p staff
```

Create `staff/login.php` (mirrors `teacher/login.php` exactly, with `role_name == 'Staff'` and staff-prefixed session vars):

```php
<?php
session_start();
require_once '../config/db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staff_id_string = $_POST['staff_id'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($staff_id_string) || empty($password)) {
        $error = '❌ Staff ID and Password are required!';
    } else {
        $sql = "SELECT u.*, r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.user_id_string = :user_id_string";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id_string' => $staff_id_string]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['role_name'] == 'Staff' && password_verify($password, $user['password'])) {
            if ($user['status'] == 'Inactive') {
                $error = '❌ Your account is inactive. Please contact the administration.';
            } else {
                $_SESSION['staff_logged_in'] = true;
                $_SESSION['user_db_id'] = $user['id'];
                $_SESSION['staff_id'] = $user['user_id_string'];
                $_SESSION['staff_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role_name'];

                header('Location: dashboard.php');
                exit();
            }
        } else {
            $error = '❌ Invalid Staff ID or Password!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Staff Login | LMS</title>
    <style>
        :root { --primary: #5a3e85; --secondary: #6c757d; --light-bg: #f8f9fc; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%); height: 100vh; display: flex; justify-content: center; align-items: center; }
        .login-container { width: 100%; max-width: 400px; background: white; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); overflow: hidden; }
        .login-header { background: var(--primary); color: white; padding: 1.5rem; text-align: center; }
        .login-header h2 { margin: 0; }
        .login-body { padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; font-size: 1rem; border: 1px solid #d1d3e2; border-radius: 0.35rem; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 0.75rem; font-weight: 600; color: #fff; background: var(--primary); border: none; border-radius: 0.35rem; cursor: pointer; }
        .alert-danger { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 0.75rem 1.25rem; margin-bottom: 1.5rem; border-radius: 0.35rem; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header"><h2>Staff Portal</h2></div>
        <div class="login-body">
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="staff_id">Staff ID</label>
                    <input type="text" class="form-control" id="staff_id" name="staff_id" placeholder="e.g., STF-12345" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 2: Create `staff/logout.php`**

```php
<?php
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit();
?>
```

- [ ] **Step 3: Create `staff/header.php`**

```php
   <nav class="topbar">
            <button class="btn" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
          <div class="ms-auto">
    <i class="fas fa-user-circle"></i>
  <span id="header-staff-name"><?= htmlspecialchars($staff_name) ?></span>
</div>
    </nav>
```

- [ ] **Step 4: Copy `teacher/sidebar.php` as a base for `staff/sidebar.php`, then trim the nav list**

```bash
cp teacher/sidebar.php staff/sidebar.php
```

In the newly created `staff/sidebar.php`, find this block (the full `<ul>...</ul>` nav list — it will be at the same line numbers as in `teacher/sidebar.php` since the file was just copied, approximately lines 182-200):

```php
<ul>
    <li><a href="dashboard.php" class=""><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
    <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
    <!-- <li><a href="my-courses.php"><i class="fas fa-book"></i> <span>My Courses</span></a></li> -->
    <li><a href="student_list.php"><i class="fas fa-clipboard-list"></i> <span>Student List</span></a></li>
    <!-- <li><a href="assignments.php"><i class="fas fa-chart-bar"></i> <span>Assignments</span></a></li> -->
    <li><a href="lesson-plans.php"><i class="fas fa-chart-bar"></i> <span>Lesson Plans</span></a></li>
    <!-- <li><a href="grades.php"><i class="fas fa-receipt"></i> <span>Grades</span></a></li> -->
    <li><a href="manage-scores.php"><i class="fas fa-receipt"></i> <span>Manage Scores</span></a></li>
    <li><a href="attendance-management.php"><i class="fas fa-calendar-alt"></i> <span>Attendance Management</span></a></li>
    <li><a href="attendance-report.php"><i class="fas fa-calendar-check"></i> <span>Attendance Report</span></a></li>
    <li><a href="online-classes.php"><i class="fas fa-chart-bar"></i> <span>Online Classes</span></a></li>
    <li><a href="study-materials.php"><i class="fas fa-chart-bar"></i> <span>Study Materials</span></a></li>
    <li><a href="award-list.php"><i class="fas fa-trophy"></i> <span>Award List</span></a></li>
    <li><a href="notice.php"><i class="fas fa-bullhorn"></i> <span>Notices</span></a></li>
    <li><a href="timetable.php"><i class="fas fa-calendar-alt"></i> <span>Timetable</span></a></li>
    <!-- <li><a href="performance-reports.php"><i class="fas fa-life-ring"></i> <span>Performance Reports</span></a></li> -->
    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
</ul>
```

Replace with:

```php
<ul>
    <li><a href="dashboard.php" class=""><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
    <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
    <li><a href="shared-files.php"><i class="fas fa-share-alt"></i> <span>Shared Files</span></a></li>
    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
</ul>
```

- [ ] **Step 5: Create `staff/dashboard.php`**

```php
<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['staff_logged_in']) || !isset($_SESSION['user_db_id'])) {
    header('Location: login.php');
    exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Staff';
$current_user_db_id = (int)$_SESSION['user_db_id'];

$received_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shared_file_recipients WHERE recipient_type = 'user' AND recipient_ref_id = ?");
    $stmt->execute([$current_user_db_id]);
    $received_count = (int)$stmt->fetchColumn();
} catch (PDOException $e) { /* Table may not exist yet until shared-files.php has been visited once */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($staff_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <h1 class="h3 mb-4">Welcome, <?= htmlspecialchars($staff_name) ?></h1>
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-uppercase text-muted mb-1">Files Received</div>
                            <div class="h5 mb-0 fw-bold"><?= $received_count ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

- [ ] **Step 6: Create `staff/profile.php`**

```php
<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['staff_logged_in']) || !isset($_SESSION['user_db_id'])) {
    header('Location: login.php');
    exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Staff';
$current_user_db_id = (int)$_SESSION['user_db_id'];

$stmt = $pdo->prepare("SELECT user_id_string, full_name, email, status FROM users WHERE id = ?");
$stmt->execute([$current_user_db_id]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | <?= htmlspecialchars($staff_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <h1 class="h3 mb-4">My Profile</h1>
            <div class="card shadow-sm border-0" style="max-width:500px;">
                <div class="card-body">
                    <p><strong>Staff ID:</strong> <?= htmlspecialchars($staff['user_id_string'] ?? '') ?></p>
                    <p><strong>Full Name:</strong> <?= htmlspecialchars($staff['full_name'] ?? '') ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($staff['email'] ?? '') ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($staff['status'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

- [ ] **Step 7: Verify the full login → dashboard → profile → logout cycle with the real staff account**

The real staff account in this project's DB is `STF-67767` ("usaama", `users.id=12`). This plan does not know that account's real password (it was created before this feature existed), so verification uses this project's established disposable-session-bootstrap pattern instead of a real login POST:

```bash
cat > _t_staff.php <<'EOF'
<?php
session_start();
$_SESSION['staff_logged_in'] = true;
$_SESSION['user_db_id'] = 12;
$_SESSION['staff_id'] = 'STF-67767';
$_SESSION['staff_name'] = 'usaama';
echo "ok";
EOF
curl -s -c /tmp/c_staff.txt http://localhost/msst/lms/_t_staff.php
curl -s -b /tmp/c_staff.txt -o /dev/null -w "dashboard HTTP %{http_code}\n" http://localhost/msst/lms/staff/dashboard.php
curl -s -b /tmp/c_staff.txt -o /tmp/staff_profile.html -w "profile HTTP %{http_code}\n" http://localhost/msst/lms/staff/profile.php
grep -c "usaama" /tmp/staff_profile.html
```

Expected: both HTTP 200, profile page shows the name at least once.

Verify the login page itself renders (without submitting the unknown real password):

```bash
curl -s -o /dev/null -w "login page HTTP %{http_code}\n" http://localhost/msst/lms/staff/login.php
```

Expected: HTTP 200.

Verify logout invalidates the session:

```bash
curl -s -b /tmp/c_staff.txt -c /tmp/c_staff.txt http://localhost/msst/lms/staff/logout.php -o /dev/null -w "HTTP %{http_code}\n"
curl -s -b /tmp/c_staff.txt -o /dev/null -w "HTTP %{http_code}\n" -D - http://localhost/msst/lms/staff/dashboard.php | grep -i location
```

Expected: logout HTTP 302, then dashboard redirects to `login.php`.

Re-establish the staff session for later tasks (Task 4 needs it):

```bash
curl -s -c /tmp/c_staff.txt http://localhost/msst/lms/_t_staff.php
rm -f /tmp/staff_profile.html
```

- [ ] **Step 8: Commit**

```bash
git add staff/
git commit -m "Add minimal staff panel (login, dashboard, profile, logout)"
```

---

### Task 4: Staff shared-files page

**Files:**
- Create: `staff/shared-files.php`

**Interfaces:**
- Consumes: `shared_files`/`shared_file_recipients` tables (Task 1), `staff_logged_in`/`user_db_id`/`staff_name` session convention (Task 3).

- [ ] **Step 1: Create `staff/shared-files.php`**

Identical structure to Task 2's `teacher/shared-files.php`, with the auth check and title changed to the staff session variables. Create `staff/shared-files.php`:

```php
<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['staff_logged_in']) || !isset($_SESSION['user_db_id'])) {
    header('Location: login.php');
    exit;
}

$staff_name = $_SESSION['staff_name'] ?? 'Staff';
$current_type = 'user';
$current_ref_id = (int)$_SESSION['user_db_id'];

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
    <title>Shared Files | <?= htmlspecialchars($staff_name) ?></title>
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
                                    <td><a href="../<?= htmlspecialchars($rf['file_path']) ?>" class="btn btn-sm btn-outline-primary" download><i class="fas fa-download"></i></a></td>
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
                                        <a href="../<?= htmlspecialchars($sf['file_path']) ?>" class="btn btn-sm btn-outline-primary" download><i class="fas fa-download"></i></a>
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
```

- [ ] **Step 2: Verify staff-as-sender and staff-as-recipient paths via curl**

Reuse `/tmp/c_staff.txt` from Task 3 (recreate via `_t_staff.php` if missing) and `/tmp/c_admin.txt` from Task 1. Staff sends a file to admin:

```bash
curl -s -c /tmp/c_staff.txt http://localhost/msst/lms/_t_staff.php
echo "test shared file from staff" > /tmp/zz_staff_share.txt
curl -s -b /tmp/c_staff.txt -X POST \
  -F "action=upload_shared_file" \
  -F "title=ZZ Staff To Admin" \
  -F "recipients[]=admin|1" \
  -F "shared_file=@/tmp/zz_staff_share.txt" \
  http://localhost/msst/lms/staff/shared-files.php -o /tmp/staff_upload.html -w "HTTP %{http_code}\n"
mysql -u root msst_db -e "SELECT sender_type, sender_ref_id FROM shared_files WHERE title='ZZ Staff To Admin';"
curl -s -c /tmp/c_admin.txt http://localhost/msst/lms/_t_admin.php
curl -s -b /tmp/c_admin.txt http://localhost/msst/lms/shared-files.php -o /tmp/admin_check2.html
grep -c "ZZ Staff To Admin" /tmp/admin_check2.html
```

Expected: `sender_type='user', sender_ref_id=12`; the file appears in admin's Received view (count ≥ 1).

Clean up:

```bash
FILE_ID=$(mysql -u root msst_db -N -e "SELECT id FROM shared_files WHERE title='ZZ Staff To Admin';")
curl -s -b /tmp/c_staff.txt -X POST -d "action=delete_shared_file&file_id=$FILE_ID" http://localhost/msst/lms/staff/shared-files.php
mysql -u root msst_db -e "SELECT COUNT(*) FROM shared_files WHERE id=$FILE_ID;"
rm -f /tmp/zz_staff_share.txt /tmp/staff_upload.html /tmp/admin_check2.html
```

Expected: count 0.

- [ ] **Step 3: Commit**

```bash
git add "staff/shared-files.php"
git commit -m "Add staff file-sharing page"
```

---

### Task 5: Final end-to-end verification and cleanup

**Files:** none (verification only)

- [ ] **Step 1: Multi-recipient check — one file shared with two people at once**

Using the real admin, teacher (184), and staff (12) sessions established in earlier tasks (recreate any missing `_t_*.php`/cookie via the same bootstrap scripts from Tasks 1, 3), admin sends a single file addressed to BOTH the teacher and the staff account in one upload:

```bash
echo "multi-recipient test file" > /tmp/zz_multi_test.txt
curl -s -b /tmp/c_admin.txt -X POST \
  -F "action=upload_shared_file" \
  -F "title=ZZ Multi Recipient Test" \
  -F "recipients[]=user|184" \
  -F "recipients[]=user|12" \
  -F "shared_file=@/tmp/zz_multi_test.txt" \
  http://localhost/msst/lms/shared-files.php
mysql -u root msst_db -e "
SELECT sf.id, COUNT(sfr.id) AS recipient_count
FROM shared_files sf JOIN shared_file_recipients sfr ON sfr.shared_file_id = sf.id
WHERE sf.title = 'ZZ Multi Recipient Test' GROUP BY sf.id;
"
curl -s -b /tmp/c_teacher.txt http://localhost/msst/lms/teacher/shared-files.php -o /tmp/multi_teacher.html
grep -c "ZZ Multi Recipient Test" /tmp/multi_teacher.html
curl -s -b /tmp/c_staff.txt http://localhost/msst/lms/staff/shared-files.php -o /tmp/multi_staff.html
grep -c "ZZ Multi Recipient Test" /tmp/multi_staff.html
curl -s -b /tmp/c_admin.txt http://localhost/msst/lms/shared-files.php -o /tmp/multi_admin.html
grep -o "ZZ Multi Recipient Test[^<]*</td><td>[^<]*" /tmp/multi_admin.html
```

Expected: `recipient_count = 2` (one `shared_files` row fanned out to two `shared_file_recipients` rows); both the teacher's and staff's Received views show the file (count ≥ 1 each); admin's own Sent view lists both recipient names comma-joined in the "To" column.

Clean up:

```bash
FILE_ID=$(mysql -u root msst_db -N -e "SELECT id FROM shared_files WHERE title='ZZ Multi Recipient Test';")
mysql -u root msst_db -e "
DELETE FROM shared_file_recipients WHERE shared_file_id=$FILE_ID;
DELETE FROM shared_files WHERE id=$FILE_ID;
"
rm -f /tmp/zz_multi_test.txt /tmp/multi_teacher.html /tmp/multi_staff.html /tmp/multi_admin.html
```

- [ ] **Step 2: Access-scoping check — a non-recipient must never see a file addressed to someone else**

Using the same sessions, admin sends a file to ONLY the teacher (not staff, not itself):

```bash
echo "scoping test file" > /tmp/zz_scope_test.txt
curl -s -b /tmp/c_admin.txt -X POST \
  -F "action=upload_shared_file" \
  -F "title=ZZ Scope Test" \
  -F "recipients[]=user|184" \
  -F "shared_file=@/tmp/zz_scope_test.txt" \
  http://localhost/msst/lms/shared-files.php
curl -s -b /tmp/c_teacher.txt http://localhost/msst/lms/teacher/shared-files.php -o /tmp/scope_teacher.html
grep -c "ZZ Scope Test" /tmp/scope_teacher.html
curl -s -b /tmp/c_staff.txt http://localhost/msst/lms/staff/shared-files.php -o /tmp/scope_staff.html
grep -c "ZZ Scope Test" /tmp/scope_staff.html
```

Expected: teacher's page shows it (count ≥ 1), staff's page does NOT (count 0) — confirming files are correctly scoped to their actual recipients only.

Clean up:

```bash
FILE_ID=$(mysql -u root msst_db -N -e "SELECT id FROM shared_files WHERE title='ZZ Scope Test';")
mysql -u root msst_db -e "
DELETE FROM shared_file_recipients WHERE shared_file_id=$FILE_ID;
DELETE FROM shared_files WHERE id=$FILE_ID;
"
FILE_PATH_CHECK=$(mysql -u root msst_db -N -e "SELECT file_path FROM shared_files WHERE id=$FILE_ID;" 2>/dev/null)
rm -f /tmp/zz_scope_test.txt /tmp/scope_teacher.html /tmp/scope_staff.html
find uploads/shared_files -name "sf_*" -newer docs/superpowers/plans/2026-07-06-staff-file-sharing.md 2>/dev/null || true
```

(The last line is a best-effort sanity check only; if any stray physical files from this manual verification session remain in `uploads/shared_files/`, remove them directly since this task's DB rows have already been deleted above.)

- [ ] **Step 3: `php -l` every changed file**

```bash
php -l shared-files.php
php -l "teacher/shared-files.php"
php -l "staff/shared-files.php"
php -l "staff/login.php"
php -l "staff/logout.php"
php -l "staff/dashboard.php"
php -l "staff/profile.php"
php -l "staff/sidebar.php"
php -l "staff/header.php"
php -l sidebar.php
php -l "teacher/sidebar.php"
```

Expected: "No syntax errors detected" for every file.

- [ ] **Step 4: Final cleanup of disposable test artifacts**

```bash
rm -f _t_admin.php _t_teacher.php _t_staff.php /tmp/c_admin.txt /tmp/c_teacher.txt /tmp/c_staff.txt /tmp/*.html
git status --short
```

Expected: no stray files; only the intentional commits from Tasks 1-4 show in `git log` (plus any pre-existing untracked files noted before this work began).
