<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Kept commented for static preview

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// --- START: DUMMY DATA FOR THE GRADEBOOK ---
// Data is structured as an array of courses, each with its own list of students and their grades.
$gradebook_data = [
    [
        'course_name' => 'Physics - 1st Year',
        'class_name' => 'Pre-Engineering Section A',
        'students' => [
            ['id' => 1, 'student_id' => 'PE-001', 'name' => 'Ahmed Ali', 'attendance' => 95, 'mid_marks' => 40, 'final_marks' => 42, 'total' => 82, 'grade' => 'A'],
            ['id' => 2, 'student_id' => 'PE-002', 'name' => 'Sadia Batool', 'attendance' => 98, 'mid_marks' => 45, 'final_marks' => 46, 'total' => 91, 'grade' => 'A+'],
            ['id' => 3, 'student_id' => 'PE-003', 'name' => 'Kamran Khan', 'attendance' => 75, 'mid_marks' => 30, 'final_marks' => 35, 'total' => 65, 'grade' => 'B'],
        ]
    ],
    [
        'course_name' => 'Chemistry - Class 10th',
        'class_name' => 'Section B',
        'students' => [
            ['id' => 4, 'student_id' => 'C10-011', 'name' => 'Zainab Fatima', 'attendance' => 82, 'mid_marks' => 35, 'final_marks' => 38, 'total' => 73, 'grade' => 'B+'],
            ['id' => 5, 'student_id' => 'C10-012', 'name' => 'Haris Sohail', 'attendance' => 91, 'mid_marks' => 41, 'final_marks' => 40, 'total' => 81, 'grade' => 'A'],
        ]
    ]
];
// --- END OF DUMMY DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gradebook | <?= htmlspecialchars($teacher_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #007bff; --secondary: #6c757d; --light-bg: #f8f9fc; --dark-text: #343a40; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        #main-content { margin-left: 250px; transition: all 0.3s; }
        #main-content.collapsed { margin-left: 70px; }
        @media (max-width: 768px) { .sidebar { left: -250px; } .sidebar.show { left: 0; } #main-content, #main-content.collapsed { margin-left: 0; } }
        .accordion-button:not(.collapsed) { color: white; background-color: var(--primary); }
        .accordion-button:not(.collapsed)::after { filter: brightness(0) invert(1); }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .grade-input { max-width: 80px; text-align: center; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-3 p-md-4">
            <div class="container-fluid">
                <h1 class="h3 mb-4">Gradebook & Exam Results</h1>

                <div class="accordion" id="gradebookAccordion">
                    <?php if (empty($gradebook_data)): ?>
                        <div class="card"><div class="card-body text-center p-5 text-muted">No classes found to grade.</div></div>
                    <?php else: ?>
                        <?php foreach ($gradebook_data as $index => $course): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $index ?>">
                                        <span class="fw-bold me-2"><?= htmlspecialchars($course['course_name']) ?></span> (<?= htmlspecialchars($course['class_name']) ?>)
                                    </button>
                                </h2>
                                <div id="collapse-<?= $index ?>" class="accordion-collapse collapse <?= $index == 0 ? 'show' : '' ?>" data-bs-parent="#gradebookAccordion">
                                    <div class="accordion-body">
                                        <form onsubmit="alert('Saving grades is disabled in this static preview.'); return false;">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-hover align-middle text-center">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="text-start">Student Name</th>
                                                            <th>Attendance</th>
                                                            <th>Mid-Term Marks</th>
                                                            <th>Final-Term Marks</th>
                                                            <th>Total Marks</th>
                                                            <th>Grade</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($course['students'] as $student): ?>
                                                            <tr>
                                                                <td class="text-start">
                                                                    <?= htmlspecialchars($student['name']) ?><br>
                                                                    <small class="text-muted"><?= htmlspecialchars($student['student_id']) ?></small>
                                                                </td>
                                                                <td>
                                                                    <div class="fw-bold <?= $student['attendance'] >= 80 ? 'text-success' : 'text-danger' ?>">
                                                                        <?= $student['attendance'] ?>%
                                                                    </div>
                                                                </td>
                                                                <td><input type="number" class="form-control grade-input mx-auto" value="<?= $student['mid_marks'] ?>"></td>
                                                                <td><input type="number" class="form-control grade-input mx-auto" value="<?= $student['final_marks'] ?>"></td>
                                                                <td><strong><?= $student['total'] ?></strong></td>
                                                                <td><input type="text" class="form-control grade-input mx-auto" value="<?= $student['grade'] ?>"></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="text-end mt-3">
                                                <button type="submit" class="btn btn-primary">Save Grades for <?= htmlspecialchars($course['course_name']) ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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