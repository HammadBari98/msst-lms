<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Not needed for static version

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- DUMMY DATA FOR MESSAGES & NOTICES ---
$notices = [
    [
        'title' => 'Mid-Term Exam Schedule Announcement',
        'posted_by' => 'Admin Office',
        'date' => '2025-06-08',
        'content' => 'Dear Students, the schedule for the upcoming mid-term examinations has been finalized. Please check the "Exams/Grades" page for the detailed date sheet. All students are advised to clear any outstanding dues to be eligible to sit for the exams. Best of luck.'
    ],
    [
        'title' => 'Holiday on account of National Day',
        'posted_by' => 'Principal',
        'date' => '2025-06-05',
        'content' => 'The institution will remain closed on Tuesday, June 10th, 2025, on account of National Day. Classes will resume as per the normal schedule on Wednesday, June 11th.'
    ]
];

$personal_messages = [
    [
        'from' => 'Mr. Ahmed Khan (Physics Teacher)',
        'subject' => 'Your Lab Report Submission',
        'message' => 'Hi ' . htmlspecialchars($student_name) . ',<br><br>I have received your lab report. Please see me after class tomorrow to discuss a few points. Overall, a good effort, but there are some areas for improvement in your data analysis section that I\'d like to go over with you.<br><br>Best,<br>Mr. Khan',
        'date' => '2025-06-09',
        'is_read' => false 
    ],
    [
        'from' => 'Accounts Office',
        'subject' => 'Fee Voucher - June 2025',
        'message' => 'Your fee voucher for the month of June has been generated. Please check the "Fee Details" page for more information. The due date is June 25th, 2025.',
        'date' => '2025-06-05',
        'is_read' => true
    ],
    [
        'from' => 'Admin Office',
        'subject' => 'Welcome to the Student Portal!',
        'message' => 'Welcome to the new Learning Management System. We hope you find it useful. Please explore all the features and report any issues to the IT department.',
        'date' => '2025-06-01',
        'is_read' => true
    ]
];
// --- END OF DUMMY DATA ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages & Notices | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4e73df; --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .accordion-button:not(.collapsed) { color: #fff; background-color: var(--primary); }
        .accordion-button:not(.collapsed)::after { filter: brightness(0) invert(1); }
        .message.unread { border-left: 4px solid var(--primary); background-color: #f0f5ff; }
        .message.unread .message-subject { font-weight: bold; }
        .modal-body .message-content {
            white-space: pre-wrap; /* Allows line breaks like \n to work */
            word-wrap: break-word; /* Prevents long text from overflowing */
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                <h1 class="h3 mb-4">Messages & Notices</h1>
                <div class="row">
                    <div class="col-lg-7 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">General Notices & Announcements</h6></div>
                            <div class="card-body">
                                <div class="accordion" id="noticesAccordion">
                                    <?php foreach($notices as $index => $notice): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button <?= $index == 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#notice-<?= $index ?>">
                                                    <?= htmlspecialchars($notice['title']) ?><small class="ms-auto text-muted pe-2"><?= date('M j, Y', strtotime($notice['date'])) ?></small>
                                                </button>
                                            </h2>
                                            <div id="notice-<?= $index ?>" class="accordion-collapse collapse <?= $index == 0 ? 'show' : '' ?>" data-bs-parent="#noticesAccordion">
                                                <div class="accordion-body">
                                                    <p><?= nl2br(htmlspecialchars($notice['content'])) ?></p><hr>
                                                    <small class="text-muted">Posted by: <strong><?= htmlspecialchars($notice['posted_by']) ?></strong></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Personal Messages</h6></div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach($personal_messages as $msg): ?>
                                        <a href="#" 
                                           class="list-group-item list-group-item-action message <?= !$msg['is_read'] ? 'unread' : '' ?>" 
                                           data-bs-toggle="modal" data-bs-target="#messageModal"
                                           data-from="<?= htmlspecialchars($msg['from']) ?>"
                                           data-subject="<?= htmlspecialchars($msg['subject']) ?>"
                                           data-date="<?= date('l, M j, Y, g:i a', strtotime($msg['date'])) ?>"
                                           data-message="<?= htmlspecialchars($msg['message']) ?>"
                                           data-is-read="<?= $msg['is_read'] ? '1' : '0' ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <p class="mb-1 message-subject"><?= htmlspecialchars($msg['subject']) ?></p>
                                                <small><?= date('M j', strtotime($msg['date'])) ?></small>
                                            </div>
                                            <p class="mb-1 text-muted small">From: <?= htmlspecialchars($msg['from']) ?></p>
                                            <small class="text-muted text-truncate d-block"><?= strip_tags($msg['message']) ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-subject">Message Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2"><strong>From:</strong> <span id="modal-from"></span></p>
                    <p class="text-muted mb-3"><small><strong>Date:</strong> <span id="modal-date"></span></small></p>
                    <hr>
                    <div class="message-content" id="modal-message-body">
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // --- NEW: JAVASCRIPT TO POPULATE THE MESSAGE MODAL ---
        $('#messageModal').on('show.bs.modal', function(event) {
            // Get the link that triggered the modal
            var button = $(event.relatedTarget);

            // Extract data from the data-* attributes
            var from = button.data('from');
            var subject = button.data('subject');
            var date = button.data('date');
            var message = button.data('message');
            var isRead = button.data('is-read');

            // Update the modal's content
            var modal = $(this);
            modal.find('#modal-subject').text(subject);
            modal.find('#modal-from').text(from);
            modal.find('#modal-date').text(date);
            // Use .html() to correctly render line breaks like <br>
            modal.find('#modal-message-body').html(message);

            // If the message was unread, remove the 'unread' style from the list item
            if (isRead == 0) { // Check for 0, not false
                button.removeClass('unread');
                // We would also update the data attribute so it's not marked as unread again
                button.data('is-read', '1');
            }
        });
    });

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