<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Not needed for static version

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- START: DUMMY DATA FOR GRADES & EXAMS ---

// 1. Dummy data for the summary card
$gpa_summary = [
    'overall_gpa' => 3.85,
    'total_credits' => 75,
    'grade_distribution' => [
        'A' => 18,
        'B' => 5,
        'C' => 2,
    ]
];

// 2. Dummy data for the upcoming exam schedule
$upcoming_exams = [
    [
        'course_name' => 'Calculus II',
        'exam_date' => '2025-06-20 09:00:00',
        'location' => 'Hall B, Seat 42'
    ],
    [
        'course_name' => 'Introduction to Physics',
        'exam_date' => '2025-06-23 13:00:00',
        'location' => 'Physics Lab #1'
    ]
];

// 3. Dummy data for past results, grouped by semester
$result_history = [
    'Spring 2025' => [
        ['course_code' => 'ENG-101', 'course_name' => 'English Literature', 'marks_obtained' => 85, 'total_marks' => 100, 'letter_grade' => 'A-'],
        ['course_code' => 'PHY-101', 'course_name' => 'Introduction to Physics', 'marks_obtained' => 92, 'total_marks' => 100, 'letter_grade' => 'A'],
        ['course_code' => 'CHM-101', 'course_name' => 'General Chemistry', 'marks_obtained' => 88, 'total_marks' => 100, 'letter_grade' => 'A-']
    ],
    'Fall 2024' => [
        ['course_code' => 'CS-100', 'course_name' => 'Intro to Computing', 'marks_obtained' => 95, 'total_marks' => 100, 'letter_grade' => 'A+'],
        ['course_code' => 'MTH-101', 'course_name' => 'Calculus I', 'marks_obtained' => 81, 'total_marks' => 100, 'letter_grade' => 'B+']
    ]
];

// --- END OF DUMMY DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades & Exams | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df; --secondary: #1cc88a; --accent: #36b9cc;
            --light-bg: #f8f9fc; --dark-text: #5a5c69;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .gpa-summary-card .display-4 { font-weight: 700; color: var(--primary); }
        .accordion-button:not(.collapsed) { color: #fff; background-color: var(--primary); }
        .accordion-button:focus { box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                <h1 class="h3 mb-4 text-dark-text">Grades & Exam Performance</h1>

                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card shadow-sm gpa-summary-card">
                            <div class="card-body">
                                <div class="row align-items-center text-center">
                                    <div class="col-md-4">
                                        <h6 class="text-uppercase text-muted">Overall GPA</h6>
                                        <p class="display-4"><?= number_format($gpa_summary['overall_gpa'], 2) ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="text-uppercase text-muted">Credits Earned</h6>
                                        <p class="display-4"><?= $gpa_summary['total_credits'] ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <h6 class="text-uppercase text-muted">Grade Distribution</h6>
                                        <div class="d-flex justify-content-center mt-3">
                                            <?php foreach($gpa_summary['grade_distribution'] as $grade => $count): ?>
                                                <div class="mx-2">
                                                    <span class="badge bg-primary fs-6"><?= $grade ?></span>
                                                    <p class="fw-bold fs-5 mb-0"><?= $count ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Upcoming Exam Schedule</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr><th>Course</th><th>Date & Time</th><th>Location</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($upcoming_exams)): ?>
                                                <tr><td colspan="3" class="text-center text-muted py-4">No upcoming exams scheduled.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($upcoming_exams as $exam): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($exam['course_name']) ?></strong></td>
                                                        <td><?= date('l, F j, Y', strtotime($exam['exam_date'])) ?> at <?= date('g:i A', strtotime($exam['exam_date'])) ?></td>
                                                        <td><?= htmlspecialchars($exam['location']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <h4 class="h5 mb-3 text-dark-text">Result History</h4>
                        <div class="accordion" id="resultAccordion">
                            <?php if (empty($result_history)): ?>
                                <div class="card"><div class="card-body text-center text-muted">Your result history is not available yet.</div></div>
                            <?php else: ?>
                                <?php $is_first = true; foreach ($result_history as $semester => $results): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?= $is_first ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= str_replace(' ', '', $semester) ?>">
                                                <?= htmlspecialchars($semester) ?>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= str_replace(' ', '', $semester) ?>" class="accordion-collapse collapse <?= $is_first ? 'show' : '' ?>" data-bs-parent="#resultAccordion">
                                            <div class="accordion-body p-0">
                                                <table class="table table-striped table-bordered mb-0">
                                                    <thead>
                                                        <tr class="table-light"><th>Course Code</th><th>Course Name</th><th>Marks</th><th>Grade</th></tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($results as $result): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($result['course_code']) ?></td>
                                                                <td><?= htmlspecialchars($result['course_name']) ?></td>
                                                                <td><?= htmlspecialchars($result['marks_obtained']) ?> / <?= htmlspecialchars($result['total_marks']) ?></td>
                                                                <td><span class="badge bg-success"><?= htmlspecialchars($result['letter_grade']) ?></span></td>
                                                            </tr>
                                                        <?php $is_first = false; endforeach; ?>
                                                    </tbody>
                                                </table>
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