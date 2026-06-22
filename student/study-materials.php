<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

$student_name = $_SESSION['student_name'] ?? 'Student';

// =======================================================
// 1. GUARANTEED SAFE STUDENT ID TRANSLATOR
// =======================================================
$current_user_db_id = 0;

if (!empty($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_id']]);
    $current_user_db_id = $stmt->fetchColumn();
}
if (!$current_user_db_id && !empty($_SESSION['student_email'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_email']]);
    $current_user_db_id = $stmt->fetchColumn();
}
if (!$current_user_db_id && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $current_user_db_id = (int)$_SESSION['user_id'];
}

// =======================================================
// 2. FETCH STUDENT'S CLASS & MATERIALS
// =======================================================
$student_class_id = 0;
$class_materials = [];
$error_message = null;

if ($current_user_db_id > 0) {
    try {
        $stmt_class = $pdo->prepare("SELECT class_id FROM student_details WHERE user_id = ? LIMIT 1");
        $stmt_class->execute([$current_user_db_id]);
        $student_class_id = $stmt_class->fetchColumn();

        if ($student_class_id) {
            $stmt_materials = $pdo->prepare("
                SELECT sm.*, u.full_name as teacher_name 
                FROM study_materials sm
                LEFT JOIN users u ON sm.teacher_id = u.id
                WHERE sm.class_id = ?
                ORDER BY sm.created_at DESC
            ");
            $stmt_materials->execute([$student_class_id]);
            $class_materials = $stmt_materials->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_message = "A database error occurred while fetching your study materials.";
    }
}

function getMaterialIcon($type, $filename) {
    if ($type === 'Link' || $type === 'Video') return '<i class="fas fa-link text-primary fa-2x"></i>';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch($ext) {
        case 'pdf': return '<i class="fas fa-file-pdf text-danger fa-2x"></i>';
        case 'doc': case 'docx': return '<i class="fas fa-file-word text-primary fa-2x"></i>';
        case 'xls': case 'xlsx': return '<i class="fas fa-file-excel text-success fa-2x"></i>';
        case 'ppt': case 'pptx': return '<i class="fas fa-file-powerpoint text-warning fa-2x"></i>';
        case 'jpg': case 'jpeg': case 'png': return '<i class="fas fa-file-image text-info fa-2x"></i>';
        case 'mp4': case 'webm': case 'avi': case 'mkv': return '<i class="fas fa-video text-danger fa-2x"></i>';
        default: return '<i class="fas fa-file-alt text-secondary fa-2x"></i>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --light-bg: #f8f9fc; --primary: #4e73df; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
        .teacher-badge { background-color: #e7f3ff; color: #0d6efd; border: 1px solid #b6d4fe; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content" class="d-flex flex-column min-vh-100">
        <?php include 'header.php' ?>

        <div class="content-wrapper flex-grow-1 p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-book-reader me-2 text-primary"></i>Study Materials</h1>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger shadow-sm border-0">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-folder me-2"></i>Class Notes & Videos</h6>
                    </div>
                    <div class="card-body bg-light p-3">
                        
                        <?php if (!$student_class_id): ?>
                            <div class="text-center p-5 bg-white rounded border border-light">
                                <i class="fas fa-user-slash text-muted fa-4x mb-3 opacity-25"></i>
                                <h5 class="fw-bold text-dark">Class Not Assigned</h5>
                                <p class="text-muted mb-0">You have not been assigned to a specific class yet.</p>
                            </div>
                        <?php elseif (empty($class_materials)): ?>
                            <div class="text-center p-5 bg-white rounded border border-light">
                                <i class="fas fa-box-open text-muted fa-4x mb-3 opacity-25"></i>
                                <h5 class="fw-bold text-dark">No Materials Available</h5>
                                <p class="text-muted mb-0">Your teachers haven't uploaded any study materials or videos for your class yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive bg-white rounded border p-3">
                                <table class="table table-hover align-middle mb-0" id="studentMaterialsTable">
                                    <thead class="table-light text-muted small">
                                        <tr>
                                            <th class="text-center" style="width: 5%;">Format</th>
                                            <th style="width: 35%;">Title & Subject</th>
                                            <th style="width: 20%;">Teacher</th>
                                            <th style="width: 20%;">Type & Date</th>
                                            <th class="text-center" style="width: 20%;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($class_materials as $mat): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?= getMaterialIcon($mat['material_type'], $mat['file_name']) ?>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark" style="font-size: 1.05rem;"><?= htmlspecialchars($mat['title']) ?></div>
                                                    <div class="small text-primary fw-semibold"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($mat['subject_topic']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge teacher-badge px-3 py-2 rounded-pill">
                                                        <i class="fas fa-chalkboard-teacher me-1"></i> <?= htmlspecialchars($mat['teacher_name'] ?? 'Unknown Teacher') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $ext = strtolower(pathinfo($mat['file_name'], PATHINFO_EXTENSION));
                                                        $is_video_file = in_array($ext, ['mp4', 'webm', 'avi', 'ogg', 'mkv']);
                                                    ?>
                                                    <?php if($mat['material_type'] == 'Link' || $mat['material_type'] == 'Video'): ?>
                                                        <div class="badge bg-danger bg-opacity-10 text-danger border border-danger mb-1"><i class="fas fa-link me-1"></i>External Link</div>
                                                    <?php elseif($is_video_file): ?>
                                                        <div class="badge bg-danger bg-opacity-10 text-danger border border-danger mb-1"><i class="fas fa-video me-1"></i>Video File</div>
                                                    <?php else: ?>
                                                        <div class="badge bg-info bg-opacity-10 text-info border border-info mb-1"><i class="fas fa-file-alt me-1"></i>Document</div>
                                                    <?php endif; ?>
                                                    <div class="small text-muted fw-bold"><?= date('d M Y', strtotime($mat['created_at'])) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if($mat['material_type'] == 'Link' || $mat['material_type'] == 'Video'): ?>
                                                        <a href="<?= htmlspecialchars($mat['video_url']) ?>" target="_blank" class="btn btn-outline-primary shadow-sm fw-bold w-100">
                                                            <i class="fas fa-external-link-alt me-1"></i> Open Link
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="../<?= htmlspecialchars($mat['file_path']) ?>" download="<?= htmlspecialchars($mat['file_name']) ?>" class="btn btn-primary shadow-sm fw-bold w-100">
                                                            <i class="fas fa-cloud-download-alt me-1"></i> Download
                                                        </a>
                                                    <?php endif; ?>
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
        </div>
        
        <footer class="footer mt-auto py-3 bg-white border-top">
            <div class="container text-center text-muted small">
                 &copy; MSST LMS <?= date('Y') ?> | Student Portal
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (window.innerWidth <= 768 && sidebar && toggleBtn && !sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                mainContent.classList.remove('show');
            }
        }
    });

    $(document).ready(function() {
        if ($('#studentMaterialsTable').length) {
            $('#studentMaterialsTable').DataTable({
                "order": [[3, "desc"]], 
                "pageLength": 10,
                "language": { "search": "Search Materials:" },
                "columnDefs": [ { "orderable": false, "targets": [0, 4] } ] 
            });
        }
    });
    </script>
</body>
</html>