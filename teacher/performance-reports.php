<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Kept commented for static preview

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// --- START: DUMMY DATA FOR SIMPLE PERFORMANCE REPORTS ---
// This data structure is simplified for a clean table view.
$class_performance_data = [
    [
        'course_name' => 'Physics - 1st Year',
        'class_name' => 'Pre-Engineering Section A',
        'students' => [
            ['student_id' => 'PE-001', 'name' => 'Ahmed Ali', 'attendance_percentage' => 95, 'final_grade' => 'A'],
            ['student_id' => 'PE-002', 'name' => 'Sadia Batool', 'attendance_percentage' => 98, 'final_grade' => 'A+'],
            ['student_id' => 'PE-003', 'name' => 'Kamran Khan', 'attendance_percentage' => 75, 'final_grade' => 'B'],
            ['student_id' => 'PE-004', 'name' => 'Nida Yasir', 'attendance_percentage' => 88, 'final_grade' => 'A-'],
        ]
    ],
    [
        'course_name' => 'Chemistry - Class 10th',
        'class_name' => 'Section B',
        'students' => [
            ['student_id' => 'C10-011', 'name' => 'Zainab Fatima', 'attendance_percentage' => 82, 'final_grade' => 'B+' ],
            ['student_id' => 'C10-012', 'name' => 'Haris Sohail', 'attendance_percentage' => 91, 'final_grade' => 'A' ],
        ]
    ],
    [
        'course_name' => 'Mathematics - 2nd Year',
        'class_name' => 'Pre-Engineering Section A',
        'students' => [] // Example of a class with no students graded yet
    ],
];
// --- END OF DUMMY DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Reports | <?= htmlspecialchars($teacher_name) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #007bff; --secondary: #6c757d; --light-bg: #f8f9fc; --success: #198754; --warning: #ffc107; --danger: #dc3545;}
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        #main-content { margin-left: 250px; transition: all 0.3s; }
        #main-content.collapsed { margin-left: 70px; }
        @media (max-width: 768px) { .sidebar { left: -250px; } .sidebar.show { left: 0; } #main-content, #main-content.collapsed { margin-left: 0; } }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1.5rem; border: none;}
        .accordion-button:not(.collapsed) { color: white; background-color: var(--primary); }
        .accordion-button:not(.collapsed)::after { filter: brightness(0) invert(1); }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper p-3 p-md-4">
        <div class="container-fluid">
            <h1 class="h3 mb-4">Student Performance Reports</h1>

            <div class="accordion" id="performanceAccordion">
                <?php if (empty($class_performance_data)): ?>
                    <div class="card"><div class="card-body text-center p-5 text-muted">No class performance data is available.</div></div>
                <?php else: ?>
                    <?php foreach ($class_performance_data as $index => $class_data): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $index ?>">
                                    <span class="fw-bold me-2"><?= htmlspecialchars($class_data['course_name']) ?></span> | <span class="ms-2"><?= htmlspecialchars($class_data['class_name']) ?></span>
                                </button>
                            </h2>
                            <div id="collapse-<?= $index ?>" class="accordion-collapse collapse <?= $index == 0 ? 'show' : '' ?>" data-bs-parent="#performanceAccordion">
                                <div class="accordion-body">
                                    <?php if (empty($class_data['students'])): ?>
                                        <p class="text-center text-muted m-3">No student data available for this class yet.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Student ID</th>
                                                        <th>Student Name</th>
                                                        <th class="text-center">Attendance %</th>
                                                        <th class="text-center">Final Grade</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($class_data['students'] as $student): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($student['student_id']) ?></td>
                                                            <td><?= htmlspecialchars($student['name']) ?></td>
                                                            <td class="text-center">
                                                                <?php 
                                                                    $att_class = 'text-secondary';
                                                                    if ($student['attendance_percentage'] >= 90) $att_class = 'text-success';
                                                                    else if ($student['attendance_percentage'] < 80) $att_class = 'text-danger';
                                                                ?>
                                                                <strong class="<?= $att_class ?>"><?= $student['attendance_percentage'] ?>%</strong>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge fs-6 <?= str_contains($student['final_grade'], 'A') ? 'bg-success' : (str_contains($student['final_grade'], 'B') ? 'bg-primary' : 'bg-warning') ?>">
                                                                    <?= htmlspecialchars($student['final_grade']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // The final, correct sidebar toggle script for desktop and mobile
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