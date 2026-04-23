<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Not needed for static version

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- START: DUMMY DATA FOR SUPPORT TICKETS ---
$submitted_tickets = [
    [
        'id' => 'TKT-8921',
        'subject' => 'Issue with course material download',
        'ticket_type' => 'Technical Support',
        'submitted_on' => '2025-06-08 11:30:00',
        'status' => 'Resolved',
        'admin_reply' => 'Hi ' . htmlspecialchars($student_name) . ', the issue with the PDF file for ENG-101 has been resolved. Please try downloading it again. Thank you for reporting.'
    ],
    [
        'id' => 'TKT-8920',
        'subject' => 'Feedback on Cafeteria Menu',
        'ticket_type' => 'Feedback',
        'submitted_on' => '2025-06-05 15:00:00',
        'status' => 'In Progress',
        'admin_reply' => 'Thank you for your valuable feedback. We have forwarded your suggestions to the cafeteria management for consideration.'
    ],
    [
        'id' => 'TKT-8915',
        'subject' => 'Question about exam schedule',
        'ticket_type' => 'General Support',
        'submitted_on' => '2025-06-02 09:15:00',
        'status' => 'Open',
        'admin_reply' => null // No reply yet
    ]
];
// --- END OF DUMMY DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Feedback | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df; --light-bg: #f8f9fc; --success: #1cc88a; 
            --info: #36b9cc; --warning: #f6c23e; --dark-text: #5a5c69;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .nav-pills .nav-link { color: var(--dark-text); }
        .nav-pills .nav-link.active { background-color: var(--primary); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                <h1 class="h3 mb-4">Support Center</h1>

                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0">
                        <ul class="nav nav-pills" id="supportTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="submit-request-tab" data-bs-toggle="pill" data-bs-target="#submit-request" type="button">
                                    <i class="fas fa-paper-plane me-2"></i>Submit a Request
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="my-tickets-tab" data-bs-toggle="pill" data-bs-target="#my-tickets" type="button">
                                    <i class="fas fa-history me-2"></i>My Tickets
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="supportTabContent">
                            <div class="tab-pane fade show active" id="submit-request" role="tabpanel">
                                <h5 class="mb-3">New Support Ticket</h5>
                                <p class="text-muted">Have a question, a technical issue, or some feedback? Let us know by filling out the form below.</p>
                                <form onsubmit="alert('Form submission is disabled in this static preview.'); return false;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="ticket_type" class="form-label">Type of Request <span class="text-danger">*</span></label>
                                            <select class="form-select" id="ticket_type" name="ticket_type" required>
                                                <option value="" selected disabled>Please select one...</option>
                                                <option value="General Support">General Support</option>
                                                <option value="Technical Issue">Technical Issue</option>
                                                <option value="Feedback">Feedback</option>
                                                <option value="Complaint">Complaint</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="subject" name="subject" required>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                                        </div>
                                        <div class="col-12 text-end">
                                            <button type="submit" class="btn btn-primary">Submit Ticket</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="my-tickets" role="tabpanel">
                                <h5 class="mb-3">My Submission History</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr><th>Ticket ID</th><th>Subject</th><th>Type</th><th>Date Submitted</th><th>Status</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($submitted_tickets)): ?>
                                                <tr><td colspan="5" class="text-center text-muted py-4">You have not submitted any tickets yet.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($submitted_tickets as $ticket): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($ticket['id']) ?></strong></td>
                                                        <td><?= htmlspecialchars($ticket['subject']) ?></td>
                                                        <td><?= htmlspecialchars($ticket['ticket_type']) ?></td>
                                                        <td><?= date('M j, Y', strtotime($ticket['submitted_on'])) ?></td>
                                                        <td>
                                                            <?php
                                                                $status_class = 'bg-secondary';
                                                                if ($ticket['status'] == 'Open') $status_class = 'bg-primary';
                                                                if ($ticket['status'] == 'In Progress') $status_class = 'bg-warning text-dark';
                                                                if ($ticket['status'] == 'Resolved') $status_class = 'bg-success';
                                                            ?>
                                                            <span class="badge <?= $status_class ?>"><?= htmlspecialchars($ticket['status']) ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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