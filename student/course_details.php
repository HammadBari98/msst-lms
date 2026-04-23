<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // No longer needed for static version

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- START: DUMMY DATA FOR TESTING ---
// This block replaces all the database queries.

// 1. Dummy data for the main course details
$course = [
    'id' => 1,
    'course_code' => 'ENG-101',
    'course_name' => 'English Literature',
    'course_description' => 'An introduction to classic and modern English literature, exploring various genres, key authors, and historical periods. This course aims to develop critical reading and analytical skills.',
    'syllabus_path' => 'uploads/sample_syllabus.pdf' // Example path
];

// 2. Dummy data for the course materials list
$materials = [
    [
        'material_title' => 'Lecture Notes - Week 1: Introduction',
        'material_description' => 'An overview of the course structure and key themes.',
        'file_path' => 'uploads/week1_notes.pdf'
    ],
    [
        'material_title' => 'Assignment 1: Character Analysis',
        'material_description' => 'Instructions for the first written assignment.',
        'file_path' => 'uploads/assignment_1.docx'
    ],
    [
        'material_title' => 'Mid-Term Exam Study Guide',
        'material_description' => 'A comprehensive guide covering all topics for the mid-term exam.',
        'file_path' => 'uploads/study_guide.pdf'
    ],
    [
        'material_title' => 'Lecture Slides - Week 2: Shakespeare',
        'material_description' => 'Presentation slides covering Shakespearean tragedies.',
        'file_path' => 'uploads/week2_slides.pptx'
    ],
    [
        'material_title' => 'Project Resources',
        'material_description' => 'A compressed folder of all required resources for the final project.',
        'file_path' => 'uploads/project_files.zip'
    ]
];

// --- END: DUMMY DATA ---


// Helper function to get a Font Awesome icon based on file type
function get_file_icon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf': return 'fas fa-file-pdf text-danger';
        case 'doc': case 'docx': return 'fas fa-file-word text-primary';
        case 'xls': case 'xlsx': return 'fas fa-file-excel text-success';
        case 'ppt': case 'pptx': return 'fas fa-file-powerpoint text-warning';
        case 'zip': case 'rar': return 'fas fa-file-archive text-secondary';
        case 'jpg': case 'jpeg': case 'png': case 'gif': return 'fas fa-file-image text-info';
        default: return 'fas fa-file-alt text-muted';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['course_name'] ?? 'Course Details') ?> | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df; --secondary: #1cc88a; --accent: #36b9cc;
            --light-bg: #f8f9fc; --dark-text: #5a5c69;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .welcome-message {
            background: linear-gradient(45deg, var(--primary), var(--accent));
            color: white; padding: 2.5rem; border-radius: 0.75rem;
            margin-bottom: 2rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
        .material-icon { font-size: 2.5rem; width: 50px; text-align: center; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <div class="welcome-message">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb" style="--bs-breadcrumb-divider-color: rgba(255,255,255,0.5); --bs-breadcrumb-item-active-color: white;">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="enrolled_subjects.php" class="text-white-50">My Subjects</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($course['course_name']) ?></li>
                        </ol>
                    </nav>
                    <h1 class="h2 font-weight-bold"><?= htmlspecialchars($course['course_name']) ?></h1>
                    <p class="lead mb-0"><?= htmlspecialchars($course['course_description']) ?></p>
                </div>

                <div class="row">
                    <div class="col-lg-4 mb-4">
                            <div class="card shadow-sm h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Syllabus</h6>
                            </div>
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <i class="fas fa-book-open fa-3x text-gray-300 mb-3"></i>
                                <p>Download the official course syllabus.</p>
                                <?php if (!empty($course['syllabus_path'])): ?>
                                    <a href="<?= htmlspecialchars($course['syllabus_path']) ?>" class="btn btn-success" download>
                                        <i class="fas fa-download me-2"></i>Download Syllabus
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted mt-3">Syllabus not available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Course Materials & Resources</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($materials)): ?>
                                    <p class="text-muted p-3 text-center">No materials have been uploaded for this course yet.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($materials as $material): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <div class="material-icon me-3">
                                                        <i class="<?= get_file_icon($material['file_path']) ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <strong class="d-block"><?= htmlspecialchars($material['material_title']) ?></strong>
                                                        <small class="text-muted"><?= htmlspecialchars($material['material_description'] ?? '') ?></small>
                                                    </div>
                                                </div>
                                                <a href="<?= htmlspecialchars($material['file_path']) ?>" class="btn btn-outline-primary btn-sm ms-3" download title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
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