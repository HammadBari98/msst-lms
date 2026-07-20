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
