<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Kept commented for static preview

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// --- START: DUMMY DATA FOR THIS PAGE ---

// A list of classes the teacher teaches, for the "Compose" dropdown.
$teacher_classes = [
    ['id' => 1, 'name' => 'Physics - 1st Year (Pre-Engineering)'],
    ['id' => 2, 'name' => 'Chemistry - Class 10th'],
    ['id' => 3, 'name' => 'Mathematics - 2nd Year'],
];

// A list of message threads started by the teacher.
$message_threads = [
    [
        'id' => 1,
        'subject' => 'Regarding Extra Class on Saturday',
        'sent_to' => 'Physics - 1st Year',
        'sent_on' => '2025-06-12 11:00 AM',
        'initial_message' => 'Dear students, please note that we will have an extra class this coming Saturday at 10:00 AM to cover the remaining topics for the mid-term exam. Attendance is mandatory. Please let me know if you have any unavoidable conflicts.',
        'reply_count' => 2,
        'replies' => [
            ['student_name' => 'Ahmed Ali', 'student_id' => 'PE-001', 'replied_on' => '2025-06-12 01:30 PM', 'reply_message' => 'Noted, sir. Thank you for the session.'],
            ['student_name' => 'Sadia Batool', 'student_id' => 'PE-002', 'replied_on' => '2025-06-12 02:15 PM', 'reply_message' => 'Okay sir, I will be there.'],
        ]
    ],
    [
        'id' => 2,
        'subject' => 'Submission Deadline for Practical Notebooks',
        'sent_to' => 'Chemistry - Class 10th',
        'sent_on' => '2025-06-10 09:00 AM',
        'initial_message' => 'This is a reminder that the final deadline to submit your chemistry practical notebooks for checking is this Friday. No submissions will be accepted after the deadline.',
        'reply_count' => 0,
        'replies' => [] // No replies for this message yet
    ]
];

// --- END OF DUMMY DATA ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages & Notices | <?= htmlspecialchars($teacher_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #007bff; --light-bg: #f8f9fc; --dark-text: #343a40; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        #main-content { margin-left: 250px; transition: all 0.3s; }
        #main-content.collapsed { margin-left: 70px; }
        @media (max-width: 768px) { .sidebar { left: -250px; } .sidebar.show { left: 0; } #main-content, #main-content.collapsed { margin-left: 0; } }
        .accordion-button:not(.collapsed) { color: white; background-color: var(--primary); }
        .accordion-button:not(.collapsed)::after { filter: brightness(0) invert(1); }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .reply-card { background-color: #f8f9fa; border-left: 4px solid var(--secondary); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-3 p-md-4">
            <div class="container-fluid">
                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 m-0 mb-3 mb-sm-0">Messages & Notices</h1>
                    <button class="btn btn-primary w-30 w-sm-auto" data-bs-toggle="modal" data-bs-target="#composeModal">
                        <i class="fas fa-paper-plane me-2"></i>Compose New Message
                    </button>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="m-0 font-weight-bold text-primary">Sent Messages</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="accordion" id="messagesAccordion">
                            <?php if (empty($message_threads)): ?>
                                <div class="p-4 text-center text-muted">You have not sent any messages yet.</div>
                            <?php else: ?>
                                <?php foreach ($message_threads as $index => $thread): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $thread['id'] ?>">
                                                <span class="fw-bold me-2"><?= htmlspecialchars($thread['subject']) ?></span>
                                                <span class="text-muted small me-2">(To: <?= htmlspecialchars($thread['sent_to']) ?>)</span>
                                                <span class="badge bg-secondary ms-auto me-2"><?= $thread['reply_count'] ?> Replies</span>
                                            </button>
                                        </h2>
                                        <div id="collapse-<?= $thread['id'] ?>" class="accordion-collapse collapse <?= $index == 0 ? 'show' : '' ?>" data-bs-parent="#messagesAccordion">
                                            <div class="accordion-body">
                                                <div class="p-3 border rounded mb-3">
                                                    <h6>Your Original Message:</h6>
                                                    <p><?= nl2br(htmlspecialchars($thread['initial_message'])) ?></p>
                                                    <small class="text-muted">Sent on: <?= htmlspecialchars($thread['sent_on']) ?></small>
                                                </div>
                                                
                                                <h6>Student Replies:</h6>
                                                <?php if (empty($thread['replies'])): ?>
                                                    <p class="text-muted">No replies for this message yet.</p>
                                                <?php else: ?>
                                                    <?php foreach ($thread['replies'] as $reply): ?>
                                                        <div class="card reply-card mb-2">
                                                            <div class="card-body p-2">
                                                                <div class="d-flex justify-content-between">
                                                                    <strong><?= htmlspecialchars($reply['student_name']) ?></strong>
                                                                    <small class="text-muted"><?= htmlspecialchars($reply['replied_on']) ?></small>
                                                                </div>
                                                                <p class="mb-0"><?= htmlspecialchars($reply['reply_message']) ?></p>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="composeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Compose New Message / Notice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form onsubmit="alert('Sending messages is disabled in this static preview.'); return false;">
                        <div class="mb-3">
                            <label class="form-label">Send To <span class="text-danger">*</span></label>
                            <select class="form-select" required>
                                <option value="">Select a class/group...</option>
                                <?php foreach($teacher_classes as $class): ?><option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option><?php endforeach; ?>
                                <option value="all">All Students</option>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Subject <span class="text-danger">*</span></label><input type="text" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Message <span class="text-danger">*</span></label><textarea class="form-control" rows="8" required></textarea></div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="composeForm" class="btn btn-primary">Send Message</button></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // The final, correct sidebar toggle script
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    }
    document.addEventListener('click', function (event) {
        if (sidebar && toggleBtn && !sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        }
    });
    </script>
</body>
</html>