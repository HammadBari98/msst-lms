<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Kept commented for static preview

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// --- Dummy Data for Pakistani College Context ---
$teacher_courses = [
    ['id' => 1, 'name' => 'Physics - 1st Year (Pre-Engineering)'],
    ['id' => 2, 'name' => 'Chemistry - Class 10th'],
    ['id' => 3, 'name' => 'Mathematics - 2nd Year'],
];

$assignments = [
    [
        'id' => 1, 'title' => 'Chapter 2: Vectors & Equilibrium Exercise', 'description' => 'Solve all the numerical problems from the end of Chapter 2.',
        'course_id' => 1, 'course_name' => 'Physics - 1st Year', 'due_date' => '2025-09-15T23:59', 'total_marks' => 50,
        'attachment_file_path' => 'uploads/assignments/physics_worksheet_ch2.pdf',
        'submissions' => [
            ['student_id' => 'PE-001', 'student_name' => 'Ahmed Ali', 'submission_date' => '2025-09-14 18:30:00', 'file_path' => '#', 'status' => 'Graded', 'marks_awarded' => 45],
            ['student_id' => 'PE-002', 'student_name' => 'Sadia Batool', 'submission_date' => '2025-09-15 11:15:00', 'file_path' => '#', 'status' => 'Submitted', 'marks_awarded' => null],
        ]
    ],
    [
        'id' => 2, 'title' => 'Practical #3: Salt Analysis', 'description' => 'Submit your complete practical notebook write-up for the salt analysis experiment.',
        'course_id' => 2, 'course_name' => 'Chemistry - Class 10th', 'due_date' => '2025-09-20T17:00', 'total_marks' => 25,
        'attachment_file_path' => null,
        'submissions' => [] 
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments | <?= htmlspecialchars($teacher_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #007bff; --secondary: #6c757d; --light-bg: #f8f9fc; --success: #198754; --warning: #ffc107;}
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        #main-content { margin-left: 250px; transition: all 0.3s; }
        #main-content.collapsed { margin-left: 70px; }
        @media (max-width: 768px) { .sidebar { left: -250px; } .sidebar.show { left: 0; } #main-content, #main-content.collapsed { margin-left: 0; } }
        .accordion-button:not(.collapsed) { color: white; background-color: var(--primary); }
        .accordion-button:not(.collapsed)::after { filter: brightness(0) invert(1); }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-3 p-md-4">
            <div class="container-fluid">
                <div class="d-sm-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 m-0 mb-3 mb-sm-0">Manage Assignments</h1>
                    <button class="btn btn-primary w-30 w-sm-auto" data-bs-toggle="modal" data-bs-target="#assignmentModal" data-is-edit="false">
                        <i class="fas fa-plus me-2"></i>Create New Assignment
                    </button>
                </div>

                <div class="accordion" id="assignmentsAccordion">
                    <?php foreach ($assignments as $index => $assignment): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $assignment['id'] ?>">
                                    <span class="fw-bold me-2"><?= htmlspecialchars($assignment['title']) ?></span> (<?= htmlspecialchars($assignment['course_name']) ?>)
                                    <span class="badge bg-secondary ms-auto me-2"><?= count($assignment['submissions']) ?> Submissions</span>
                                </button>
                            </h2>
                            <div id="collapse-<?= $assignment['id'] ?>" class="accordion-collapse collapse <?= $index == 0 ? 'show' : '' ?>" data-bs-parent="#assignmentsAccordion">
                                <div class="accordion-body">
                                    <div class="d-flex flex-wrap justify-content-between text-muted mb-3">
                                        <span class="me-3 mb-2"><strong>Due:</strong> <?= date('M j, Y, g:i a', strtotime($assignment['due_date'])) ?></span>
                                        <span class="me-3 mb-2"><strong>Marks:</strong> <?= $assignment['total_marks'] ?></span>
                                        <button class="btn btn-sm btn-outline-secondary mb-2" data-bs-toggle="modal" data-bs-target="#assignmentModal"
                                            data-is-edit="true" data-id="<?= $assignment['id'] ?>" data-title="<?= htmlspecialchars($assignment['title']) ?>"
                                            data-description="<?= htmlspecialchars($assignment['description']) ?>" data-course-id="<?= $assignment['course_id'] ?>"
                                            data-due-date="<?= $assignment['due_date'] ?>" data-total-marks="<?= $assignment['total_marks'] ?>"
                                            data-attachment-path="<?= htmlspecialchars($assignment['attachment_file_path']) ?>">
                                            <i class="fas fa-pencil-alt me-1"></i> Edit
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive d-none d-md-block">
                                        <table class="table table-hover">
                                            <thead><tr><th>Student Name</th><th>Submitted On</th><th>Status</th><th>Actions</th></tr></thead>
                                            <tbody>
                                                <?php if(empty($assignment['submissions'])): ?>
                                                    <tr><td colspan="4" class="text-center text-muted">No submissions received yet.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($assignment['submissions'] as $sub): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($sub['student_name']) ?></td>
                                                            <td><?= date('M j, Y, g:i a', strtotime($sub['submission_date'])) ?></td>
                                                            <td><span class="badge <?= $sub['status'] == 'Graded' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $sub['status'] ?></span></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary grade-btn" data-bs-toggle="modal" data-bs-target="#gradeModal" data-student-name="<?= htmlspecialchars($sub['student_name']) ?>" data-submission-file="<?= htmlspecialchars($sub['file_path']) ?>" data-marks-awarded="<?= htmlspecialchars($sub['marks_awarded'] ?? '') ?>" title="Grade">
                                                                    <i class="fas fa-check-double me-1"></i> Grade
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="d-md-none">
                                        <?php if(empty($assignment['submissions'])): ?>
                                            <p class="text-center text-muted">No submissions received yet.</p>
                                        <?php else: ?>
                                            <?php foreach ($assignment['submissions'] as $sub): ?>
                                                <div class="card mb-2">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between"><h6 class="card-title"><?= htmlspecialchars($sub['student_name']) ?></h6><span class="badge <?= $sub['status'] == 'Graded' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $sub['status'] ?></span></div>
                                                        <p class="card-text small text-muted">Submitted: <?= date('M j, Y, g:i a', strtotime($sub['submission_date'])) ?></p>
                                                        <button class="btn btn-sm btn-primary grade-btn" data-bs-toggle="modal" data-bs-target="#gradeModal" data-student-name="<?= htmlspecialchars($sub['student_name']) ?>" data-submission-file="<?= htmlspecialchars($sub['file_path']) ?>" data-marks-awarded="<?= htmlspecialchars($sub['marks_awarded'] ?? '') ?>"><i class="fas fa-check-double me-1"></i> Grade</button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="assignmentModalLabel">Create New Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="assignmentForm" onsubmit="alert('Static preview only.'); return false;">
                        <input type="hidden" id="assignment-id" name="id">
                        <div class="mb-3"><label class="form-label">Course</label><select class="form-select" id="assignment-course-id" name="course_id"><?php foreach($teacher_courses as $course): ?><option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Assignment Title</label><input type="text" class="form-control" id="assignment-title" name="title"></div>
                        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" rows="3" id="assignment-description" name="description"></textarea></div>
                        <div class="mb-3"><label for="assignment-attachment" class="form-label">Attachment (Optional)</label><input class="form-control" type="file" id="assignment-attachment" name="attachment"><div id="current-attachment-info" class="form-text" style="display: none;">Currently attached: <a href="#" id="current-attachment-link" target="_blank"></a></div></div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Due Date</label><input type="datetime-local" class="form-control" id="assignment-due-date" name="due_date"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Total Marks</label><input type="number" class="form-control" value="100" id="assignment-total-marks" name="total_marks"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="assignmentForm" class="btn btn-primary">Save Assignment</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gradeModal" tabindex="-1">
         <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Grade Submission</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p><strong>Student:</strong> <span id="grade-student-name"></span></p>
                    <p><a href="#" id="grade-submission-link" class="btn btn-sm btn-secondary" download><i class="fas fa-download me-2"></i>View Submitted File</a></p>
                    <hr>
                    <form onsubmit="alert('Static preview only.'); return false;">
                        <div class="mb-3"><label class="form-label">Marks Awarded</label><input type="number" class="form-control" id="grade-marks-awarded"></div>
                        <div class="mb-3"><label class="form-label">Remarks / Feedback</label><textarea class="form-control" rows="4"></textarea></div>
                    </form>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary">Save Grade</button></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Logic for Create/Edit Assignment Modal
        $('#assignmentModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var isEdit = button.data('is-edit');
            var modal = $(this);
            var form = modal.find('#assignmentForm');
            var attachmentInfo = modal.find('#current-attachment-info');
            var attachmentLink = modal.find('#current-attachment-link');
            if (isEdit) {
                modal.find('.modal-title').text('Edit Assignment');
                modal.find('#assignment-id').val(button.data('id'));
                modal.find('#assignment-title').val(button.data('title'));
                modal.find('#assignment-description').val(button.data('description'));
                modal.find('#assignment-course-id').val(button.data('course-id'));
                modal.find('#assignment-due-date').val(button.data('due-date'));
                modal.find('#assignment-total-marks').val(button.data('total-marks'));
                var currentAttachment = button.data('attachment-path');
                if (currentAttachment) {
                    attachmentLink.attr('href', currentAttachment).text(currentAttachment.split('/').pop());
                    attachmentInfo.show();
                } else {
                    attachmentInfo.hide();
                }
            } else {
                modal.find('.modal-title').text('Create New Assignment');
                form.trigger('reset');
                modal.find('#assignment-id').val('');
                attachmentInfo.hide();
            }
        });
        
        // Logic to populate the Grade Modal (Full code restored)
        $('#gradeModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var studentName = button.data('student-name');
            var submissionFile = button.data('submission-file');
            var marksAwarded = button.data('marks-awarded');
            var modal = $(this);
            modal.find('#grade-student-name').text(studentName);
            modal.find('#grade-submission-link').attr('href', submissionFile);
            modal.find('#grade-marks-awarded').val(marksAwarded);
        });
    });

    // Final, correct sidebar toggle script
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