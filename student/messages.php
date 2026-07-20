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
