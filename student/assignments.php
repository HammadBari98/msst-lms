<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Not needed for static version

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];
$success_message = '';
$error_message = '';

// --- START: DUMMY DATA FOR ASSIGNMENTS ---

// Sample data for assignments that are NOT yet submitted
$pending_assignments = [
    [
        'id' => 3,
        'title' => 'Historical Context Report',
        'course_name' => 'English Literature',
        'description' => 'A report on the historical events surrounding the poems of World War I.',
        'due_date' => '2025-06-17 23:59:59',
        'total_marks' => 50
    ],
    [
        'id' => 4,
        'title' => 'Lab Report #3: Kinematics',
        'course_name' => 'Introduction to Physics',
        'description' => 'Submit your full lab report for the third kinematics experiment.',
        'due_date' => '2025-06-22 23:59:59',
        'total_marks' => 100
    ]
];

// Sample data for assignments that have already been submitted or graded
$submitted_assignments = [
    [
        'id' => 1,
        'title' => 'Character Analysis Essay',
        'course_name' => 'English Literature',
        'submission_date' => '2025-06-05 14:30:00',
        'status' => 'Graded',
        'marks_awarded' => 85,
        'total_marks' => 100
    ],
    [
        'id' => 2,
        'title' => 'Problem Set #1',
        'course_name' => 'Calculus II',
        'submission_date' => '2025-06-08 18:00:00',
        'status' => 'Submitted',
        'marks_awarded' => null, // Not graded yet
        'total_marks' => 100
    ]
];

// --- END OF DUMMY DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #4e73df; --light-bg: #f8f9fc; --success: #1cc88a; --warning: #f6c23e; --danger: #e74a3b; }
        body { background-color: var(--light-bg); font-family: 'Segoe UI', sans-serif; }
        .nav-tabs .nav-link { color: #6e707e; }
        .nav-tabs .nav-link.active { color: var(--primary); font-weight: bold; border-color: #dee2e6 #dee2e6 #fff; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                <h1 class="h3 mb-4">My Assignments</h1>

                <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
                <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>

                <ul class="nav nav-tabs mb-3" id="assignmentsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                            Pending <span class="badge bg-danger ms-1"><?= count($pending_assignments) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="submitted-tab" data-bs-toggle="tab" data-bs-target="#submitted" type="button" role="tab">
                            Submitted & Graded
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="assignmentsTabContent">
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        <?php if (empty($pending_assignments)): ?>
                            <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">You have no pending assignments. Great job!</div></div>
                        <?php else: ?>
                            <?php foreach ($pending_assignments as $assignment): ?>
                                <div class="card shadow-sm mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center bg-white">
                                        <h6 class="m-0 font-weight-bold text-primary"><?= htmlspecialchars($assignment['title']) ?></h6>
                                        <small>Due: <strong class="text-danger"><?= date('M j, Y, g:i a', strtotime($assignment['due_date'])) ?></strong></small>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($assignment['course_name']) ?></h6>
                                        <p><?= htmlspecialchars($assignment['description']) ?></p>
                                        <form method="POST" enctype="multipart/form-data" onsubmit="alert('File submission is disabled in this static preview.'); return false;">
                                            <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                                            <div class="input-group">
                                                <input type="file" class="form-control" name="assignment_file" required>
                                                <button class="btn btn-success" type="submit" name="submit_assignment">
                                                    <i class="fas fa-upload me-2"></i>Submit
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="submitted" role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr><th>Assignment</th><th>Course</th><th>Submitted On</th><th>Status</th><th>Grade</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($submitted_assignments)): ?>
                                                <tr><td colspan="5" class="text-center text-muted py-4">No assignments submitted yet.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($submitted_assignments as $assignment): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($assignment['title']) ?></td>
                                                        <td><?= htmlspecialchars($assignment['course_name']) ?></td>
                                                        <td><?= date('M j, Y', strtotime($assignment['submission_date'])) ?></td>
                                                        <td>
                                                            <?php if($assignment['status'] == 'Graded'): ?>
                                                                <span class="badge bg-success">Graded</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">Submitted</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if($assignment['status'] == 'Graded'): ?>
                                                                <strong><?= htmlspecialchars($assignment['marks_awarded']) ?> / <?= htmlspecialchars($assignment['total_marks']) ?></strong>
                                                            <?php else: ?>
                                                                <span class="text-muted">Pending</span>
                                                            <?php endif; ?>
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