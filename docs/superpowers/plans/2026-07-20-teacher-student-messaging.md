# Teacher–Student Messaging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let teachers and students in the same class message each other directly, with a read-only admin oversight view of every conversation.

**Architecture:** One new `messages` table (a conversation is the unique pair `teacher_id`+`student_id`, no separate conversations table). Two AJAX endpoints (`send_message.php`, `poll_messages.php`) handle send/receive with server-side class-assignment authorization. Three new pages (`teacher/messages.php`, `student/messages.php`, `messages.php` for admin) render a two-pane contact-list + thread UI, polling every 5s while a thread is open. Sidebar badges show unread counts.

**Tech Stack:** PHP 8.2 + PDO/MySQL (existing `$pdo` from `config/db_config.php`), Bootstrap 5, Font Awesome, vanilla JS `fetch()` (no new libraries). No automated test framework exists in this project — verification is manual via `curl` + the `mysql` CLI, matching every prior feature in this codebase.

## Global Constraints

- Every new/modified PHP file must pass `php -l` with no syntax errors before being considered done.
- Auth session variable names: admin = `admin_logged_in`; teacher = `teacher_logged_in` / `teacher_id` (this is the `user_id_string`, not the numeric id) / `teacher_name`; student = `student_logged_in` / `student_id` (also a `user_id_string`) / `student_name`. The numeric `users.id` must always be re-derived via `SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1`, matching the pattern already used in `teacher/award-list.php` and `student/my-scores.php`.
- Never trust a client-supplied sender id — always derive the sender's own numeric id from the session.
- New table is created via `CREATE TABLE IF NOT EXISTS` inside a `try`/`catch`, placed at the top of every entry-point page that might be the first one loaded (`teacher/messages.php`, `student/messages.php`, `messages.php`), matching the auto-patcher convention used throughout this codebase (see `student-report.php:16-24`).

---

### Task 1: `messages` table + AJAX send/poll endpoints

**Files:**
- Create: `ajax/send_message.php`
- Create: `ajax/poll_messages.php`

**Interfaces:**
- Produces DB table `messages(id, teacher_id, student_id, sender_role, body, is_read, created_at)`, consumed by Tasks 2–4.
- `POST ajax/send_message.php` — JSON body `{ "other_id": <int>, "body": "<text>" }`. Response: `{ "success": true, "id": <int>, "created_at": "<Y-m-d H:i:s>" }` or `{ "success": false, "message": "..." }`.
- `GET ajax/poll_messages.php?other_id=<int>&after_id=<int>` — Response: `{ "success": true, "messages": [ { "id", "sender_role", "body", "created_at" }, ... ] }`.
- Both endpoints require `teacher_logged_in` or `student_logged_in` session; `other_id` is the numeric `users.id` of the counterpart (the student id when called by a teacher, the teacher id when called by a student).

- [ ] **Step 1: Create the `messages` table**

Run:
```bash
"/b/PO/Website/xampp 8.2/mysql/bin/mysql.exe" -uroot msst_db -e "
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    sender_role ENUM('teacher','student') NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (teacher_id, student_id, created_at)
);"
```
Expected: no output (success), or a harmless "table already exists" no-op on rerun.

- [ ] **Step 2: Write `ajax/send_message.php`**

```php
<?php
session_start();
require_once '../config/db_config.php';
header('Content-Type: application/json');

$is_teacher = isset($_SESSION['teacher_logged_in']);
$is_student = isset($_SESSION['student_logged_in']);

if (!$is_teacher && !$is_student) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$body = isset($input['body']) ? trim((string)$input['body']) : '';
$other_id = isset($input['other_id']) ? (int)$input['other_id'] : 0;

if ($body === '' || !$other_id) {
    echo json_encode(['success' => false, 'message' => 'Message text and recipient are required']);
    exit;
}

if ($is_teacher) {
    $session_id = $_SESSION['teacher_id'];
    $stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $stmt_id->execute([$session_id, $session_id]);
    $teacher_id = (int)$stmt_id->fetchColumn();
    $student_id = $other_id;
    $sender_role = 'teacher';
} else {
    $session_id = $_SESSION['student_id'];
    $stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $stmt_id->execute([$session_id, $session_id]);
    $student_id = (int)$stmt_id->fetchColumn();
    $teacher_id = $other_id;
    $sender_role = 'student';
}

if (!$teacher_id || !$student_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
    exit;
}

// A teacher may only message a student in a class/section they are assigned to, and vice versa.
$stmt_auth = $pdo->prepare("
    SELECT 1 FROM teacher_class_assignments tca
    JOIN student_details sd ON sd.class_id = tca.class_id AND IFNULL(sd.section_id,0) = IFNULL(tca.section_id,0)
    WHERE tca.teacher_user_id = ? AND sd.user_id = ?
    LIMIT 1
");
$stmt_auth->execute([$teacher_id, $student_id]);
if (!$stmt_auth->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to message this person']);
    exit;
}

$stmt_insert = $pdo->prepare("INSERT INTO messages (teacher_id, student_id, sender_role, body) VALUES (?, ?, ?, ?)");
$stmt_insert->execute([$teacher_id, $student_id, $sender_role, $body]);

echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'created_at' => date('Y-m-d H:i:s')]);
```

- [ ] **Step 3: Write `ajax/poll_messages.php`**

```php
<?php
session_start();
require_once '../config/db_config.php';
header('Content-Type: application/json');

$is_teacher = isset($_SESSION['teacher_logged_in']);
$is_student = isset($_SESSION['student_logged_in']);

if (!$is_teacher && !$is_student) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$other_id = isset($_GET['other_id']) ? (int)$_GET['other_id'] : 0;
$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

if ($is_teacher) {
    $session_id = $_SESSION['teacher_id'];
    $stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $stmt_id->execute([$session_id, $session_id]);
    $teacher_id = (int)$stmt_id->fetchColumn();
    $student_id = $other_id;
    $own_role = 'teacher';
} else {
    $session_id = $_SESSION['student_id'];
    $stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $stmt_id->execute([$session_id, $session_id]);
    $student_id = (int)$stmt_id->fetchColumn();
    $teacher_id = $other_id;
    $own_role = 'student';
}

if (!$teacher_id || !$student_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
    exit;
}

$stmt_msgs = $pdo->prepare("
    SELECT id, sender_role, body, created_at
    FROM messages
    WHERE teacher_id = ? AND student_id = ? AND id > ?
    ORDER BY id ASC
");
$stmt_msgs->execute([$teacher_id, $student_id, $after_id]);
$rows = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);

// Mark as read any messages sent by the other party that we just fetched.
$stmt_read = $pdo->prepare("
    UPDATE messages SET is_read = 1
    WHERE teacher_id = ? AND student_id = ? AND sender_role != ? AND is_read = 0
");
$stmt_read->execute([$teacher_id, $student_id, $own_role]);

echo json_encode(['success' => true, 'messages' => $rows]);
```

- [ ] **Step 4: Lint both files**

Run:
```bash
php -l ajax/send_message.php && php -l ajax/poll_messages.php
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Manual verification via curl**

Using the real authorized pair `teacher_id=197` (`TEA-90195`) / `student_id=86` (`STU-54044`):

```bash
# Fake a teacher session
cat > /tmp/_t_session.php <<'EOF'
<?php
session_start();
$_SESSION['teacher_logged_in'] = true;
$_SESSION['teacher_id'] = 'TEA-90195';
$_SESSION['teacher_name'] = 'Test Teacher (QA)';
echo "OK";
EOF
cp /tmp/_t_session.php "ajax/../_qa_teacher_session.php"
rm -f /tmp/lms_teacher_cookies.txt
curl -s -c /tmp/lms_teacher_cookies.txt "http://localhost/msst/lms/_qa_teacher_session.php"

# Teacher sends a message to student 86 (authorized pair)
curl -s -b /tmp/lms_teacher_cookies.txt -X POST -H "Content-Type: application/json" \
  -d '{"other_id":86,"body":"Hello from teacher QA test"}' \
  "http://localhost/msst/lms/ajax/send_message.php"
echo

# Teacher polls the thread
curl -s -b /tmp/lms_teacher_cookies.txt "http://localhost/msst/lms/ajax/poll_messages.php?other_id=86&after_id=0"
echo

# Negative test: teacher_id=160 (TEA-70321) is NOT assigned to student 86's class — must be rejected
cat > "_qa_teacher2_session.php" <<'EOF'
<?php
session_start();
$_SESSION['teacher_logged_in'] = true;
$_SESSION['teacher_id'] = 'TEA-70321';
$_SESSION['teacher_name'] = 'Unauthorized Teacher QA';
echo "OK";
EOF
rm -f /tmp/lms_teacher2_cookies.txt
curl -s -c /tmp/lms_teacher2_cookies.txt "http://localhost/msst/lms/_qa_teacher2_session.php"
curl -s -b /tmp/lms_teacher2_cookies.txt -X POST -H "Content-Type: application/json" \
  -d '{"other_id":86,"body":"Should be rejected"}' \
  "http://localhost/msst/lms/ajax/send_message.php"
echo

# Unauthenticated check
curl -s -X POST -H "Content-Type: application/json" -d '{"other_id":86,"body":"no session"}' \
  "http://localhost/msst/lms/ajax/send_message.php"
echo

rm -f "_qa_teacher_session.php" "_qa_teacher2_session.php"
```

Expected:
- First send: `{"success":true,"id":<n>,"created_at":"..."}`
- Poll: `{"success":true,"messages":[{"id":<n>,"sender_role":"teacher","body":"Hello from teacher QA test","created_at":"..."}]}`
- Negative test: `{"success":false,"message":"You are not authorized to message this person"}` with HTTP 403
- Unauthenticated: `{"success":false,"message":"Unauthorized"}` with HTTP 401
- Confirm the temp `_qa_*_session.php` files are deleted afterward (`ls` should show they no longer exist).

- [ ] **Step 6: Commit**

```bash
git add ajax/send_message.php ajax/poll_messages.php
git commit -m "Add messages table and send/poll AJAX endpoints for teacher-student messaging"
```

---

### Task 2: `teacher/messages.php`

**Files:**
- Create: `teacher/messages.php`

**Interfaces:**
- Consumes: `ajax/send_message.php`, `ajax/poll_messages.php` from Task 1 (same request/response contract).
- Produces: a page reachable by teachers listing their contactable students with a chat thread, used as the reference UI pattern for Task 3 (student side) and Task 4 (admin read-only side).

- [ ] **Step 1: Write `teacher/messages.php`**

```php
<?php
session_start();
require_once '../config/db_config.php';

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            student_id INT NOT NULL,
            sender_role ENUM('teacher','student') NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation (teacher_id, student_id, created_at)
        )
    ");
} catch (PDOException $e) { /* Ignore if already up to date */ }

$teacher_session_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'];
$stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
$stmt_id->execute([$teacher_session_id, $teacher_session_id]);
$teacher_id = (int)$stmt_id->fetchColumn();

// Contactable students: active students in classes/sections this teacher is assigned to.
$stmt_contacts = $pdo->prepare("
    SELECT DISTINCT u.id, u.full_name, u.user_id_string, c.class_name, sec.section_name,
        (SELECT body FROM messages m WHERE m.teacher_id = ? AND m.student_id = u.id ORDER BY m.id DESC LIMIT 1) AS last_message,
        (SELECT COUNT(*) FROM messages m WHERE m.teacher_id = ? AND m.student_id = u.id AND m.sender_role = 'student' AND m.is_read = 0) AS unread_count
    FROM teacher_class_assignments tca
    JOIN classes c ON tca.class_id = c.id
    LEFT JOIN sections sec ON tca.section_id = sec.id
    JOIN student_details sd ON sd.class_id = tca.class_id AND IFNULL(sd.section_id,0) = IFNULL(tca.section_id,0)
    JOIN users u ON u.id = sd.user_id
    JOIN roles r ON u.role_id = r.id
    WHERE tca.teacher_user_id = ? AND r.role_name = 'Student' AND u.status = 'Active'
    ORDER BY c.class_name, sec.section_name, u.full_name
");
$stmt_contacts->execute([$teacher_id, $teacher_id, $teacher_id]);
$contacts = $stmt_contacts->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        :root { --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }

        .chat-shell { height: calc(100vh - 220px); min-height: 420px; display: flex; border: 1px solid #dee2e6; border-radius: 0.5rem; overflow: hidden; background: white; }
        .contact-list { width: 300px; border-right: 1px solid #dee2e6; overflow-y: auto; flex-shrink: 0; }
        .contact-item { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f3f5; cursor: pointer; }
        .contact-item:hover, .contact-item.active { background-color: #f1f3f5; }
        .contact-item .preview { font-size: 0.8rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
        .thread-pane { flex-grow: 1; display: flex; flex-direction: column; }
        .thread-messages { flex-grow: 1; overflow-y: auto; padding: 1rem; }
        .bubble { max-width: 70%; padding: 0.5rem 0.85rem; border-radius: 1rem; margin-bottom: 0.5rem; clear: both; }
        .bubble.mine { background: #906833; color: white; float: right; border-bottom-right-radius: 0.25rem; }
        .bubble.theirs { background: #e9ecef; color: #212529; float: left; border-bottom-left-radius: 0.25rem; }
        .bubble small { display: block; opacity: 0.7; font-size: 0.7rem; margin-top: 0.2rem; }
        .thread-input { border-top: 1px solid #dee2e6; padding: 0.75rem; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">

                <h1 class="h3 text-gray-800 fw-bold mb-4"><i class="fas fa-comments text-primary me-2"></i>Messages</h1>

                <div class="chat-shell">
                    <div class="contact-list" id="contactList">
                        <?php if (empty($contacts)): ?>
                            <div class="p-3 text-muted small">No students in your assigned classes yet.</div>
                        <?php else: foreach ($contacts as $c): ?>
                            <div class="contact-item" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['full_name']) ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?= htmlspecialchars($c['full_name']) ?></strong>
                                    <?php if ($c['unread_count'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill"><?= (int)$c['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">Class <?= htmlspecialchars($c['class_name']) . ($c['section_name'] ? ' (' . htmlspecialchars($c['section_name']) . ')' : '') ?></div>
                                <div class="preview"><?= $c['last_message'] ? htmlspecialchars($c['last_message']) : '<em>No messages yet</em>' ?></div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="thread-pane">
                        <div class="thread-messages" id="threadMessages">
                            <div class="text-center text-muted p-5">Select a student to start messaging.</div>
                        </div>
                        <div class="thread-input d-none" id="threadInputBar">
                            <form id="sendForm" class="d-flex gap-2">
                                <input type="text" id="messageBody" class="form-control" placeholder="Type a message..." autocomplete="off" required>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let activeContactId = null;
        let lastMessageId = 0;
        let pollTimer = null;

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function renderMessages(messages) {
            const pane = document.getElementById('threadMessages');
            if (lastMessageId === 0) pane.innerHTML = '';
            messages.forEach(m => {
                lastMessageId = Math.max(lastMessageId, m.id);
                const bubble = document.createElement('div');
                bubble.className = 'bubble ' + (m.sender_role === 'teacher' ? 'mine' : 'theirs');
                bubble.innerHTML = escapeHtml(m.body) + '<small>' + escapeHtml(m.created_at) + '</small>';
                pane.appendChild(bubble);
            });
            if (messages.length > 0) pane.scrollTop = pane.scrollHeight;
        }

        function loadThread(contactId) {
            activeContactId = contactId;
            lastMessageId = 0;
            document.getElementById('threadMessages').innerHTML = '<div class="text-center text-muted p-5"><span class="spinner-border spinner-border-sm"></span></div>';
            document.getElementById('threadInputBar').classList.remove('d-none');
            fetch('../ajax/poll_messages.php?other_id=' + contactId + '&after_id=0')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('threadMessages').innerHTML = '';
                        if (data.messages.length === 0) {
                            document.getElementById('threadMessages').innerHTML = '<div class="text-center text-muted p-5">No messages yet. Say hello!</div>';
                        } else {
                            renderMessages(data.messages);
                        }
                    }
                });
        }

        document.querySelectorAll('.contact-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.contact-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                loadThread(this.dataset.id);
            });
        });

        document.getElementById('sendForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!activeContactId) return;
            const input = document.getElementById('messageBody');
            const body = input.value.trim();
            if (!body) return;
            input.value = '';
            fetch('../ajax/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ other_id: activeContactId, body })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (document.getElementById('threadMessages').textContent.includes('No messages yet')) {
                        document.getElementById('threadMessages').innerHTML = '';
                    }
                    renderMessages([{ id: data.id, sender_role: 'teacher', body, created_at: data.created_at }]);
                } else {
                    alert(data.message || 'Failed to send message');
                }
            });
        });

        pollTimer = setInterval(function() {
            if (!activeContactId) return;
            fetch('../ajax/poll_messages.php?other_id=' + activeContactId + '&after_id=' + lastMessageId)
                .then(res => res.json())
                .then(data => { if (data.success && data.messages.length > 0) renderMessages(data.messages); });
        }, 5000);

        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.getElementById('main-content');
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show-sidebar');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    </script>
</body>
</html>
```

- [ ] **Step 2: Lint**

Run: `php -l teacher/messages.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual verification via curl**

```bash
cat > "teacher/_qa_session.php" <<'EOF'
<?php
session_start();
$_SESSION['teacher_logged_in'] = true;
$_SESSION['teacher_id'] = 'TEA-90195';
$_SESSION['teacher_name'] = 'Test Teacher (QA)';
echo "OK";
EOF
rm -f /tmp/lms_t3_cookies.txt
curl -s -c /tmp/lms_t3_cookies.txt "http://localhost/msst/lms/teacher/_qa_session.php"
curl -s -b /tmp/lms_t3_cookies.txt -o /tmp/teacher_messages_out.html -w "HTTP %{http_code}\n" "http://localhost/msst/lms/teacher/messages.php"
grep -c "contact-item" /tmp/teacher_messages_out.html
grep -o "Sumeira Batool" /tmp/teacher_messages_out.html | head -1
grep -i -E "fatal error|parse error" /tmp/teacher_messages_out.html || echo "no PHP errors"
rm -f "teacher/_qa_session.php"
```

Expected: `HTTP 200`, at least one `contact-item` match, `Sumeira Batool` present (a student in teacher 197's assigned class), no PHP errors, and confirm `teacher/_qa_session.php` no longer exists afterward.

- [ ] **Step 4: Commit**

```bash
git add teacher/messages.php
git commit -m "Add teacher-side messaging page"
```

---

### Task 3: `student/messages.php`

**Files:**
- Create: `student/messages.php`

**Interfaces:**
- Consumes: same `ajax/send_message.php` / `ajax/poll_messages.php` contract as Task 2, but `other_id` here is a teacher id (not a student id).
- Mirrors Task 2's UI structure exactly, with the contact list query reversed (teachers who teach the student's own class).

- [ ] **Step 1: Write `student/messages.php`**

```php
<?php
session_start();
require_once '../config/db_config.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            student_id INT NOT NULL,
            sender_role ENUM('teacher','student') NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation (teacher_id, student_id, created_at)
        )
    ");
} catch (PDOException $e) { /* Ignore if already up to date */ }

$student_session_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
$stmt_id->execute([$student_session_id, $student_session_id]);
$student_id = (int)$stmt_id->fetchColumn();

// Contactable teachers: teachers assigned to this student's own class/section.
$stmt_contacts = $pdo->prepare("
    SELECT DISTINCT u.id, u.full_name, u.user_id_string,
        (SELECT body FROM messages m WHERE m.student_id = ? AND m.teacher_id = u.id ORDER BY m.id DESC LIMIT 1) AS last_message,
        (SELECT COUNT(*) FROM messages m WHERE m.student_id = ? AND m.teacher_id = u.id AND m.sender_role = 'teacher' AND m.is_read = 0) AS unread_count
    FROM student_details sd
    JOIN teacher_class_assignments tca ON tca.class_id = sd.class_id AND IFNULL(tca.section_id,0) = IFNULL(sd.section_id,0)
    JOIN users u ON u.id = tca.teacher_user_id
    WHERE sd.user_id = ?
    ORDER BY u.full_name
");
$stmt_contacts->execute([$student_id, $student_id, $student_id]);
$contacts = $stmt_contacts->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        :root { --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }

        .chat-shell { height: calc(100vh - 220px); min-height: 420px; display: flex; border: 1px solid #dee2e6; border-radius: 0.5rem; overflow: hidden; background: white; }
        .contact-list { width: 300px; border-right: 1px solid #dee2e6; overflow-y: auto; flex-shrink: 0; }
        .contact-item { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f3f5; cursor: pointer; }
        .contact-item:hover, .contact-item.active { background-color: #f1f3f5; }
        .contact-item .preview { font-size: 0.8rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
        .thread-pane { flex-grow: 1; display: flex; flex-direction: column; }
        .thread-messages { flex-grow: 1; overflow-y: auto; padding: 1rem; }
        .bubble { max-width: 70%; padding: 0.5rem 0.85rem; border-radius: 1rem; margin-bottom: 0.5rem; clear: both; }
        .bubble.mine { background: #906833; color: white; float: right; border-bottom-right-radius: 0.25rem; }
        .bubble.theirs { background: #e9ecef; color: #212529; float: left; border-bottom-left-radius: 0.25rem; }
        .bubble small { display: block; opacity: 0.7; font-size: 0.7rem; margin-top: 0.2rem; }
        .thread-input { border-top: 1px solid #dee2e6; padding: 0.75rem; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">

                <h1 class="h3 text-gray-800 fw-bold mb-4"><i class="fas fa-comments text-primary me-2"></i>Messages</h1>

                <div class="chat-shell">
                    <div class="contact-list" id="contactList">
                        <?php if (empty($contacts)): ?>
                            <div class="p-3 text-muted small">No teachers assigned to your class yet.</div>
                        <?php else: foreach ($contacts as $c): ?>
                            <div class="contact-item" data-id="<?= (int)$c['id'] ?>" data-name="<?= htmlspecialchars($c['full_name']) ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?= htmlspecialchars($c['full_name']) ?></strong>
                                    <?php if ($c['unread_count'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill"><?= (int)$c['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="preview"><?= $c['last_message'] ? htmlspecialchars($c['last_message']) : '<em>No messages yet</em>' ?></div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="thread-pane">
                        <div class="thread-messages" id="threadMessages">
                            <div class="text-center text-muted p-5">Select a teacher to start messaging.</div>
                        </div>
                        <div class="thread-input d-none" id="threadInputBar">
                            <form id="sendForm" class="d-flex gap-2">
                                <input type="text" id="messageBody" class="form-control" placeholder="Type a message..." autocomplete="off" required>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let activeContactId = null;
        let lastMessageId = 0;

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function renderMessages(messages) {
            const pane = document.getElementById('threadMessages');
            if (lastMessageId === 0) pane.innerHTML = '';
            messages.forEach(m => {
                lastMessageId = Math.max(lastMessageId, m.id);
                const bubble = document.createElement('div');
                bubble.className = 'bubble ' + (m.sender_role === 'student' ? 'mine' : 'theirs');
                bubble.innerHTML = escapeHtml(m.body) + '<small>' + escapeHtml(m.created_at) + '</small>';
                pane.appendChild(bubble);
            });
            if (messages.length > 0) pane.scrollTop = pane.scrollHeight;
        }

        function loadThread(contactId) {
            activeContactId = contactId;
            lastMessageId = 0;
            document.getElementById('threadMessages').innerHTML = '<div class="text-center text-muted p-5"><span class="spinner-border spinner-border-sm"></span></div>';
            document.getElementById('threadInputBar').classList.remove('d-none');
            fetch('../ajax/poll_messages.php?other_id=' + contactId + '&after_id=0')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('threadMessages').innerHTML = '';
                        if (data.messages.length === 0) {
                            document.getElementById('threadMessages').innerHTML = '<div class="text-center text-muted p-5">No messages yet. Say hello!</div>';
                        } else {
                            renderMessages(data.messages);
                        }
                    }
                });
        }

        document.querySelectorAll('.contact-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.contact-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                loadThread(this.dataset.id);
            });
        });

        document.getElementById('sendForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!activeContactId) return;
            const input = document.getElementById('messageBody');
            const body = input.value.trim();
            if (!body) return;
            input.value = '';
            fetch('../ajax/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ other_id: activeContactId, body })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (document.getElementById('threadMessages').textContent.includes('No messages yet')) {
                        document.getElementById('threadMessages').innerHTML = '';
                    }
                    renderMessages([{ id: data.id, sender_role: 'student', body, created_at: data.created_at }]);
                } else {
                    alert(data.message || 'Failed to send message');
                }
            });
        });

        setInterval(function() {
            if (!activeContactId) return;
            fetch('../ajax/poll_messages.php?other_id=' + activeContactId + '&after_id=' + lastMessageId)
                .then(res => res.json())
                .then(data => { if (data.success && data.messages.length > 0) renderMessages(data.messages); });
        }, 5000);

        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.getElementById('main-content');
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show-sidebar');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    </script>
</body>
</html>
```

- [ ] **Step 2: Lint**

Run: `php -l student/messages.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual verification via curl**

```bash
cat > "student/_qa_session.php" <<'EOF'
<?php
session_start();
$_SESSION['student_logged_in'] = true;
$_SESSION['student_id'] = 'STU-54044';
$_SESSION['student_name'] = 'Test Student (QA)';
echo "OK";
EOF
rm -f /tmp/lms_s3_cookies.txt
curl -s -c /tmp/lms_s3_cookies.txt "http://localhost/msst/lms/student/_qa_session.php"
curl -s -b /tmp/lms_s3_cookies.txt -o /tmp/student_messages_out.html -w "HTTP %{http_code}\n" "http://localhost/msst/lms/student/messages.php"
grep -c "contact-item" /tmp/student_messages_out.html
grep -o "test teacher ics" /tmp/student_messages_out.html | head -1
grep -i -E "fatal error|parse error" /tmp/student_messages_out.html || echo "no PHP errors"
rm -f "student/_qa_session.php"
```

Expected: `HTTP 200`, at least one `contact-item` match, teacher "test teacher ics" (id 197, assigned to STU-54044's class) present, no PHP errors, and confirm `student/_qa_session.php` no longer exists afterward. Also confirm the message sent in Task 2's test appears here (it was sent to student 86, not this student STU-54044, so it will NOT appear — that's expected; this just confirms the page loads cleanly).

- [ ] **Step 4: Commit**

```bash
git add student/messages.php
git commit -m "Add student-side messaging page"
```

---

### Task 4: Admin read-only `messages.php` + sidebar links + unread badges

**Files:**
- Create: `messages.php` (repo root, admin-only)
- Modify: `sidebar.php:31-32` (admin sidebar — add a "Messages" link after "Award List")
- Modify: `teacher/sidebar.php:196` (add a "Messages" link with unread badge after "Award List")
- Modify: `student/sidebar.php:202` (add a "Messages" link with unread badge after "My Scores")

**Interfaces:**
- Consumes: `messages` table from Task 1.
- No new AJAX endpoints — admin's view is a plain server-rendered read-only page (no send box, no polling).

- [ ] **Step 1: Write `messages.php`**

```php
<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            student_id INT NOT NULL,
            sender_role ENUM('teacher','student') NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation (teacher_id, student_id, created_at)
        )
    ");
} catch (PDOException $e) { /* Ignore if already up to date */ }

// Every teacher-student pair that has at least one message, most recently active first.
$stmt_convos = $pdo->query("
    SELECT m.teacher_id, m.student_id,
           tu.full_name AS teacher_name, su.full_name AS student_name,
           MAX(m.created_at) AS last_at,
           (SELECT body FROM messages m2 WHERE m2.teacher_id = m.teacher_id AND m2.student_id = m.student_id ORDER BY m2.id DESC LIMIT 1) AS last_message,
           COUNT(*) AS message_count
    FROM messages m
    JOIN users tu ON tu.id = m.teacher_id
    JOIN users su ON su.id = m.student_id
    GROUP BY m.teacher_id, m.student_id
    ORDER BY last_at DESC
");
$conversations = $stmt_convos->fetchAll(PDO::FETCH_ASSOC);

$selected_teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$thread = [];
if ($selected_teacher_id && $selected_student_id) {
    $stmt_thread = $pdo->prepare("
        SELECT sender_role, body, created_at
        FROM messages
        WHERE teacher_id = ? AND student_id = ?
        ORDER BY id ASC
    ");
    $stmt_thread->execute([$selected_teacher_id, $selected_student_id]);
    $thread = $stmt_thread->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root { --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }

        .chat-shell { height: calc(100vh - 220px); min-height: 420px; display: flex; border: 1px solid #dee2e6; border-radius: 0.5rem; overflow: hidden; background: white; }
        .contact-list { width: 320px; border-right: 1px solid #dee2e6; overflow-y: auto; flex-shrink: 0; }
        .contact-item { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f3f5; text-decoration: none; color: inherit; display: block; }
        .contact-item:hover, .contact-item.active { background-color: #f1f3f5; }
        .contact-item .preview { font-size: 0.8rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
        .thread-pane { flex-grow: 1; display: flex; flex-direction: column; }
        .thread-messages { flex-grow: 1; overflow-y: auto; padding: 1rem; }
        .bubble { max-width: 70%; padding: 0.5rem 0.85rem; border-radius: 1rem; margin-bottom: 0.5rem; clear: both; }
        .bubble.teacher { background: #906833; color: white; float: left; border-bottom-left-radius: 0.25rem; }
        .bubble.student { background: #e9ecef; color: #212529; float: right; border-bottom-right-radius: 0.25rem; }
        .bubble small { display: block; opacity: 0.7; font-size: 0.7rem; margin-top: 0.2rem; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div id="main-content">
        <?php include 'header.php'; ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">

                <h1 class="h3 text-gray-800 fw-bold mb-4"><i class="fas fa-comments text-primary me-2"></i>Teacher &ndash; Student Messages <span class="badge bg-secondary">Read-only</span></h1>

                <div class="chat-shell">
                    <div class="contact-list">
                        <?php if (empty($conversations)): ?>
                            <div class="p-3 text-muted small">No conversations yet.</div>
                        <?php else: foreach ($conversations as $c): ?>
                            <?php
                                $is_active = $selected_teacher_id === (int)$c['teacher_id'] && $selected_student_id === (int)$c['student_id'];
                            ?>
                            <a class="contact-item <?= $is_active ? 'active' : '' ?>" href="messages.php?teacher_id=<?= (int)$c['teacher_id'] ?>&student_id=<?= (int)$c['student_id'] ?>">
                                <strong><?= htmlspecialchars($c['teacher_name']) ?></strong> &harr; <?= htmlspecialchars($c['student_name']) ?>
                                <div class="preview"><?= htmlspecialchars($c['last_message']) ?></div>
                            </a>
                        <?php endforeach; endif; ?>
                    </div>
                    <div class="thread-pane">
                        <div class="thread-messages">
                            <?php if (!$selected_teacher_id || !$selected_student_id): ?>
                                <div class="text-center text-muted p-5">Select a conversation to view.</div>
                            <?php elseif (empty($thread)): ?>
                                <div class="text-center text-muted p-5">No messages in this conversation.</div>
                            <?php else: foreach ($thread as $m): ?>
                                <div class="bubble <?= $m['sender_role'] ?>">
                                    <?= htmlspecialchars($m['body']) ?>
                                    <small><?= htmlspecialchars($m['sender_role']) ?> &middot; <?= htmlspecialchars($m['created_at']) ?></small>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include 'footer.php'; ?>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show-sidebar');
                }
            });
        }
    </script>
</body>
</html>
```

- [ ] **Step 2: Add "Messages" link to the admin sidebar**

In `sidebar.php`, find this line (added in a previous feature):
```php
        <li><a href="award-list.php"><i class="fas fa-award fa-fw me-2"></i> <span>Award List</span></a></li>
```
Add immediately after it:
```php
        <li><a href="messages.php"><i class="fas fa-comments fa-fw me-2"></i> <span>Messages</span></a></li>
```

- [ ] **Step 3: Add "Messages" link with unread badge to the teacher sidebar**

At the very top of `teacher/sidebar.php` (line 1, before the existing ` <style>` tag), add a PHP block that computes the unread count:
```php
<?php
$__msg_unread = 0;
if (isset($_SESSION['teacher_logged_in'], $pdo)) {
    $__stmt_tid = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $__stmt_tid->execute([$_SESSION['teacher_id'], $_SESSION['teacher_id']]);
    $__tid = (int)$__stmt_tid->fetchColumn();
    if ($__tid) {
        $__stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE teacher_id = ? AND sender_role = 'student' AND is_read = 0");
        $__stmt_unread->execute([$__tid]);
        $__msg_unread = (int)$__stmt_unread->fetchColumn();
    }
}
?>
```

Then change:
```php
    <li><a href="award-list.php"><i class="fas fa-trophy"></i> <span>Award List</span></a></li>
```
to:
```php
    <li><a href="award-list.php"><i class="fas fa-trophy"></i> <span>Award List</span></a></li>
    <li><a href="messages.php"><i class="fas fa-comments"></i> <span>Messages</span> <?php if ($__msg_unread > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?= $__msg_unread ?></span><?php endif; ?></a></li>
```

- [ ] **Step 4: Add "Messages" link with unread badge to the student sidebar**

At the very top of `student/sidebar.php`, before the leading blank line and ` <style>`, add:
```php
<?php
$__msg_unread = 0;
if (isset($_SESSION['student_logged_in'], $pdo)) {
    $__stmt_sid = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $__stmt_sid->execute([$_SESSION['student_id'], $_SESSION['student_id']]);
    $__sid = (int)$__stmt_sid->fetchColumn();
    if ($__sid) {
        $__stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE student_id = ? AND sender_role = 'teacher' AND is_read = 0");
        $__stmt_unread->execute([$__sid]);
        $__msg_unread = (int)$__stmt_unread->fetchColumn();
    }
}
?>
```

Then change:
```php
    <li><a href="my-scores.php"><i class="fas fa-chart-bar"></i> <span>My Scores</span></a></li>
```
to:
```php
    <li><a href="my-scores.php"><i class="fas fa-chart-bar"></i> <span>My Scores</span></a></li>
    <li><a href="messages.php"><i class="fas fa-comments"></i> <span>Messages</span> <?php if ($__msg_unread > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?= $__msg_unread ?></span><?php endif; ?></a></li>
```

- [ ] **Step 5: Lint all modified/created files**

Run:
```bash
php -l messages.php && php -l sidebar.php && php -l teacher/sidebar.php && php -l student/sidebar.php
```
Expected: `No syntax errors detected` for all four.

- [ ] **Step 6: Manual verification via curl**

```bash
# Admin session (reuse the same trick used for award-list.php testing)
cat > "_qa_admin_session.php" <<'EOF'
<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_name'] = 'Test Admin (QA)';
echo "OK";
EOF
rm -f /tmp/lms_admin_cookies.txt
curl -s -c /tmp/lms_admin_cookies.txt "http://localhost/msst/lms/_qa_admin_session.php"

# Directory view: should list the teacher<->student(86) conversation created in Task 1
curl -s -b /tmp/lms_admin_cookies.txt -o /tmp/admin_messages_list.html "http://localhost/msst/lms/messages.php"
grep -o "test teacher ics" /tmp/admin_messages_list.html | head -1
grep -o "Sumeira Batool" /tmp/admin_messages_list.html | head -1

# Thread view for that specific pair
curl -s -b /tmp/lms_admin_cookies.txt -o /tmp/admin_messages_thread.html \
  "http://localhost/msst/lms/messages.php?teacher_id=197&student_id=86"
grep -o "Hello from teacher QA test" /tmp/admin_messages_thread.html
grep -i -E "fatal error|parse error" /tmp/admin_messages_list.html /tmp/admin_messages_thread.html || echo "no PHP errors"

rm -f "_qa_admin_session.php"

# Sidebar badge check: teacher 197 has 0 unread from students at this point (only sent messages exist);
# confirm teacher/sidebar.php still renders without error when included by messages.php
curl -s -c /tmp/lms_t4_cookies.txt "http://localhost/msst/lms/teacher/_qa_session.php" 2>/dev/null
cat > "teacher/_qa_session.php" <<'EOF'
<?php
session_start();
$_SESSION['teacher_logged_in'] = true;
$_SESSION['teacher_id'] = 'TEA-90195';
$_SESSION['teacher_name'] = 'Test Teacher (QA)';
echo "OK";
EOF
curl -s -c /tmp/lms_t4_cookies.txt "http://localhost/msst/lms/teacher/_qa_session.php"
curl -s -b /tmp/lms_t4_cookies.txt -o /tmp/teacher_messages_page2.html -w "HTTP %{http_code}\n" "http://localhost/msst/lms/teacher/messages.php"
grep -i -E "fatal error|parse error" /tmp/teacher_messages_page2.html || echo "no PHP errors"

# Student replies, then teacher's badge count should reflect it, then teacher polls (marking it read)
cat > "student/_qa_session.php" <<'EOF'
<?php
session_start();
$_SESSION['student_logged_in'] = true;
$_SESSION['student_id'] = 'STU-54044';
$_SESSION['student_name'] = 'Test Student (QA)';
echo "OK";
EOF
rm -f /tmp/lms_s4_cookies.txt
curl -s -c /tmp/lms_s4_cookies.txt "http://localhost/msst/lms/student/_qa_session.php"
curl -s -b /tmp/lms_s4_cookies.txt -X POST -H "Content-Type: application/json" \
  -d '{"other_id":197,"body":"Reply from student QA test"}' \
  "http://localhost/msst/lms/ajax/send_message.php"
echo

# Teacher's sidebar badge should now show 1 unread
curl -s -b /tmp/lms_t4_cookies.txt -o /tmp/teacher_messages_page3.html "http://localhost/msst/lms/teacher/messages.php"
grep -o "badge bg-danger[^<]*<[^<]*Messages[^<]*</a>" /tmp/teacher_messages_page3.html || grep -B2 "Messages</span>" /tmp/teacher_messages_page3.html | head -5

# Teacher polls the thread (this marks the student's message as read)
curl -s -b /tmp/lms_t4_cookies.txt "http://localhost/msst/lms/ajax/poll_messages.php?other_id=86&after_id=0"
echo

MYSQL="/b/PO/Website/xampp 8.2/mysql/bin/mysql.exe"
"$MYSQL" -uroot msst_db -e "SELECT id, sender_role, body, is_read FROM messages WHERE teacher_id=197 AND student_id=86 ORDER BY id;"

rm -f "teacher/_qa_session.php" "student/_qa_session.php"
```

Expected: the admin conversation list shows "test teacher ics" and "Sumeira Batool"; the thread view shows "Hello from teacher QA test"; the student's reply POST returns `{"success":true,...}`; the teacher's poll response includes both the teacher's original message and the student's reply; the final `mysql` SELECT shows both rows with `is_read=1` (the student's reply was marked read by the teacher's poll). No PHP errors anywhere. Confirm all temp session files are removed afterward.

- [ ] **Step 7: Commit**

```bash
git add messages.php sidebar.php teacher/sidebar.php student/sidebar.php
git commit -m "Add admin read-only messages view and unread badges to teacher/student sidebars"
```

---

### Task 5: End-to-end cleanup check and push

**Files:** none (verification only)

- [ ] **Step 1: Confirm no leftover QA scaffolding files exist**

Run:
```bash
find "B:/PO/Website/xampp 8.2/htdocs/msst/lms" -maxdepth 3 -iname "_qa_*"
```
Expected: no output (empty).

- [ ] **Step 2: Full lint pass on every new/modified file**

Run:
```bash
php -l ajax/send_message.php
php -l ajax/poll_messages.php
php -l teacher/messages.php
php -l student/messages.php
php -l messages.php
php -l sidebar.php
php -l teacher/sidebar.php
php -l student/sidebar.php
```
Expected: `No syntax errors detected` for all eight.

- [ ] **Step 3: Review `git status` and `git log` for this feature's commits**

Run:
```bash
git status
git log --oneline -6
```
Expected: working tree clean except for unrelated pre-existing untracked files (uploads, docs, SQL dump — not part of this feature); the 4 commits from Tasks 1–4 present.

- [ ] **Step 4: Push to main**

```bash
git push origin main
```
