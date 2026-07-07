<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// =======================================================
// 1. ROBUST TEACHER ID FETCH
// =======================================================
$raw_session_id = $_SESSION['teacher_user_db_id'] ?? $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? $_SESSION['id'] ?? null;
$teacher_user_id = 0;

if ($raw_session_id) {
    if (is_numeric($raw_session_id)) { $teacher_user_id = (int)$raw_session_id; } 
    else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
        $stmt->execute([$raw_session_id]);
        $teacher_user_id = $stmt->fetchColumn() ?: 0;
    }
}
if (!$teacher_user_id) {
    $email = $_SESSION['teacher_email'] ?? $_SESSION['email'] ?? '';
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $teacher_user_id = $stmt->fetchColumn() ?: 0;
    }
}

$teacher_details_id = 0;
if ($teacher_user_id > 0) {
    try {
        $stmt_td = $pdo->prepare("SELECT id FROM teacher_details WHERE user_id = ? LIMIT 1");
        $stmt_td->execute([$teacher_user_id]);
        $teacher_details_id = $stmt_td->fetchColumn() ?: 0;
    } catch (Exception $e) {}
}

// =======================================================
// 2. AUTO-PATCHER (Create Table & Folders)
// =======================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS study_materials (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            section_id INT DEFAULT NULL,
            subject_topic VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            material_type VARCHAR(50) NOT NULL, 
            file_name VARCHAR(255) DEFAULT NULL,
            file_path VARCHAR(500) DEFAULT NULL,
            video_url VARCHAR(1000) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $col_check = $pdo->query("SHOW COLUMNS FROM study_materials LIKE 'section_id'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE study_materials ADD COLUMN section_id INT DEFAULT NULL AFTER class_id");
    }
} catch (PDOException $e) { /* Ignore if exists */ }

$upload_dir = __DIR__ . '/../uploads/study_materials/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// =======================================================
// 3. HANDLE FILE UPLOAD & DELETION
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'upload_material') {
            [$class_id, $section_id] = array_pad(explode('|', $_POST['class_selection'] ?? ''), 2, 0);
            $class_id = (int)$class_id;
            $section_id = (int)$section_id ?: null;
            $material_type = $_POST['material_type']; // 'File' or 'Link'
            
            $file_name = null;
            $file_path = null;
            $video_url = null;

            // Maximum allowed file size (100MB in Bytes)
            $max_file_size = 100 * 1024 * 1024; 

            if ($material_type === 'File') {
                if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                    
                    $file_size = $_FILES['material_file']['size'];
                    
                    // Backend validation for 100MB Limit
                    if ($file_size > $max_file_size) {
                        throw new Exception("The selected file is larger than the 100MB limit. Please upload it to Google Drive, Mega, or WeTransfer, and share the link instead.");
                    }

                    $file_tmp = $_FILES['material_file']['tmp_name'];
                    $original_name = basename($_FILES['material_file']['name']);
                    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    
                    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'mp4', 'webm', 'avi', 'mkv'];
                    if (!in_array($file_ext, $allowed_exts)) {
                        throw new Exception("Invalid file type. Please upload a valid document, image, or video format.");
                    }
                    
                    $new_file_name = uniqid('sm_') . '_' . time() . '.' . $file_ext;
                    $destination = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $destination)) {
                        $file_name = $original_name;
                        $file_path = 'uploads/study_materials/' . $new_file_name;
                    } else {
                        throw new Exception("Failed to save the file. Please contact your system administrator.");
                    }
                } elseif (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_INI_SIZE) {
                    throw new Exception("File exceeds the server's maximum upload limit. Please use an external link instead.");
                } else {
                    throw new Exception("Please select a valid file to upload.");
                }
            } elseif ($material_type === 'Link') {
                $video_url = trim($_POST['video_url']);
                if (empty($video_url) || !filter_var($video_url, FILTER_VALIDATE_URL)) {
                    throw new Exception("Please provide a valid URL.");
                }
            }

            $stmt = $pdo->prepare("INSERT INTO study_materials (teacher_id, class_id, section_id, subject_topic, title, material_type, file_name, file_path, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $teacher_user_id, $class_id, $section_id, trim($_POST['subject_topic']), trim($_POST['title']), 
                $material_type, $file_name, $file_path, $video_url
            ]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i>Study material added successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            
        } elseif ($_POST['action'] === 'delete_material') {
            $stmt = $pdo->prepare("SELECT file_path FROM study_materials WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$_POST['material_id'], $teacher_user_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($material) {
                if (!empty($material['file_path'])) {
                    $full_path = __DIR__ . '/../' . $material['file_path'];
                    if (file_exists($full_path)) unlink($full_path);
                }
                $pdo->prepare("DELETE FROM study_materials WHERE id = ?")->execute([$_POST['material_id']]);
                $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-trash me-2"></i>Material deleted successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            }
        }
    } catch (Exception $e) {
        $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible shadow-sm"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$action_msg = '';
if (isset($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// =======================================================
// 4. FETCH DATA
// =======================================================
$my_assigned_class_sections = [];
try {
    $stmt_classes = $pdo->prepare("
        SELECT DISTINCT c.id AS class_id, c.class_name, IFNULL(tca.section_id, 0) AS section_id, sec.section_name
        FROM teacher_class_assignments tca
        JOIN classes c ON tca.class_id = c.id
        LEFT JOIN sections sec ON tca.section_id = sec.id
        WHERE tca.teacher_user_id = ? OR tca.teacher_user_id = ?
        ORDER BY c.class_name, sec.section_name
    ");
    $stmt_classes->execute([$teacher_user_id, $teacher_details_id]);
    $my_assigned_class_sections = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$my_materials = [];
try {
    $stmt_materials = $pdo->prepare("
        SELECT sm.*, c.class_name, sec.section_name 
        FROM study_materials sm
        LEFT JOIN classes c ON sm.class_id = c.id
        LEFT JOIN sections sec ON sm.section_id = sec.id
        WHERE sm.teacher_id = ?
        ORDER BY sm.created_at DESC
    ");
    $stmt_materials->execute([$teacher_user_id]);
    $my_materials = $stmt_materials->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

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
    <title>Study Materials | <?= htmlspecialchars($teacher_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        :root { --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
        .material-badge { font-size: 0.8rem; padding: 5px 10px; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content" class="d-flex flex-column min-vh-100">
        <?php include 'header.php' ?>

        <div class="content-wrapper flex-grow-1 p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-book me-2 text-primary"></i>Study Materials</h1>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
                        <i class="fas fa-upload me-1"></i> Add Material
                    </button>
                </div>

                <?= $action_msg ?>

                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-archive me-2"></i>My Uploaded Resources</h6>
                    </div>
                    <div class="card-body bg-light p-3">
                        <?php if (empty($my_materials)): ?>
                            <div class="text-center p-5 bg-white rounded border border-light">
                                <i class="fas fa-folder-open text-muted fa-4x mb-3 opacity-25"></i>
                                <h5 class="fw-bold text-dark">No Study Materials Found</h5>
                                <p class="text-muted mb-0">Upload notes, PDFs, or share video links to help your students learn better.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive bg-white rounded border p-3">
                                <table class="table table-hover align-middle mb-0" id="materialsTable">
                                    <thead class="table-light text-muted small">
                                        <tr>
                                            <th class="text-center" style="width: 5%;">Type</th>
                                            <th>Title & Subject</th>
                                            <th>Target Class</th>
                                            <th>Resource Type</th>
                                            <th>Upload Date</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_materials as $mat): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?= getMaterialIcon($mat['material_type'], $mat['file_name']) ?>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($mat['title']) ?></div>
                                                    <div class="small text-muted"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($mat['subject_topic']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary px-2 py-1">Class <?= htmlspecialchars($mat['class_name'] ?? 'Unknown') ?><?= $mat['section_name'] ? ' (' . htmlspecialchars($mat['section_name']) . ')' : '' ?></span>
                                                </td>
                                                <td>
                                                    <?php if($mat['material_type'] == 'Link' || $mat['material_type'] == 'Video'): ?>
                                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger material-badge"><i class="fas fa-link me-1"></i>External Link</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info bg-opacity-10 text-info border border-info material-badge"><i class="fas fa-file-alt me-1"></i>Uploaded File</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="small fw-bold text-dark"><?= date('d M Y', strtotime($mat['created_at'])) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if($mat['material_type'] == 'Link' || $mat['material_type'] == 'Video'): ?>
                                                        <a href="<?= htmlspecialchars($mat['video_url']) ?>" target="_blank" class="btn btn-sm btn-light border text-primary me-1" title="Open Link">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="../<?= htmlspecialchars($mat['file_path']) ?>" download="<?= htmlspecialchars($mat['file_name']) ?>" class="btn btn-sm btn-light border text-success me-1" title="Download File">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this material? Students will lose access.');">
                                                        <input type="hidden" name="action" value="delete_material">
                                                        <input type="hidden" name="material_id" value="<?= $mat['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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
                 &copy; MSST LMS <?= date('Y') ?> | Teacher Portal
            </div>
        </footer>
    </div>

    <div class="modal fade" id="uploadMaterialModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>Add Study Material</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_material">
                    <div class="modal-body p-4 bg-light">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select Class <span class="text-danger">*</span></label>
                                <select class="form-select" name="class_selection" required>
                                    <option value="" disabled selected>Choose a class...</option>
                                    <?php foreach ($my_assigned_class_sections as $cls): ?>
                                        <option value="<?= $cls['class_id'] ?>|<?= $cls['section_id'] ?>">Class <?= htmlspecialchars($cls['class_name']) ?><?= $cls['section_name'] ? ' (' . htmlspecialchars($cls['section_name']) . ')' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Subject / Topic <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="subject_topic" placeholder="e.g. Biology - Cell Structure" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Material Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" placeholder="e.g. Chapter 1 Notes (Full)" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Material Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="material_type" id="typeFile" value="File" checked onchange="toggleMaterialInput()">
                                    <label class="form-check-label fw-semibold" for="typeFile"><i class="fas fa-upload text-primary me-1"></i> Server Upload</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="material_type" id="typeLink" value="Link" onchange="toggleMaterialInput()">
                                    <label class="form-check-label fw-semibold" for="typeLink"><i class="fas fa-link text-danger me-1"></i> External Link</label>
                                </div>
                            </div>
                        </div>

                        <div id="fileInputContainer" class="mt-3 p-3 bg-white border rounded">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold mb-0">Select File to Upload</label>
                                <span class="badge bg-danger rounded-pill px-3 py-1 shadow-sm"><i class="fas fa-weight-hanging me-1"></i>Max Limit: 100 MB</span>
                            </div>
                            
                            <input type="file" class="form-control" name="material_file" id="materialFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,image/*,video/*">
                            
                            <div id="sizeWarningAlert" class="alert alert-warning mt-3 mb-0 small border-0 shadow-sm align-items-center d-none">
                                <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                                <div>
                                    <strong>File too large!</strong> The selected file exceeds the 100MB limit. Please upload it directly to <strong class="text-dark">Google Drive, Mega, or WeTransfer</strong>, and use the <em>"External Link"</em> option above to share it instead.
                                </div>
                            </div>
                        </div>

                        <div id="videoUrlContainer" class="mt-3 p-3 bg-white border rounded" style="display: none;">
                            <label class="form-label fw-bold">Paste Website URL</label>
                            <input type="url" class="form-control" name="video_url" id="videoUrl" placeholder="https://drive.google.com/file/d/...">
                            <small class="text-muted d-block mt-2"><i class="fas fa-info-circle text-primary me-1"></i> Paste the public viewing link here. Students will click this to open the material.</small>
                        </div>

                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4" id="submitBtn"><i class="fas fa-save me-2"></i> Save Material</button>
                    </div>
                </form>
            </div>
        </div>
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
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            }
        });
    }

    $(document).ready(function() {
        if ($('#materialsTable').length) {
            $('#materialsTable').DataTable({
                "order": [[4, "desc"]], 
                "pageLength": 10,
                "columnDefs": [ { "orderable": false, "targets": [0, 5] } ]
            });
        }
        setTimeout(() => $('.alert').alert('close'), 5000);
    });

    function toggleMaterialInput() {
        const isFile = document.getElementById('typeFile').checked;
        const fileContainer = document.getElementById('fileInputContainer');
        const videoContainer = document.getElementById('videoUrlContainer');
        const fileInput = document.getElementById('materialFile');
        const videoInput = document.getElementById('videoUrl');

        if (isFile) {
            fileContainer.style.display = 'block';
            videoContainer.style.display = 'none';
            fileInput.required = true;
            videoInput.required = false;
        } else {
            fileContainer.style.display = 'none';
            videoContainer.style.display = 'block';
            fileInput.required = false;
            videoInput.required = true;
        }
    }
    toggleMaterialInput();

    // Frontend File Size Validation & Dynamic Alert Toggling
    document.getElementById('materialFile').addEventListener('change', function() {
        const warningAlert = document.getElementById('sizeWarningAlert');
        
        if (this.files.length > 0) {
            const fileSize = this.files[0].size;
            const maxSize = 100 * 1024 * 1024; // 100MB in Bytes
            
            if (fileSize > maxSize) {
                // Show the alert and clear the file
                warningAlert.classList.remove('d-none');
                warningAlert.classList.add('d-flex');
                this.value = ''; 
            } else {
                // Hide the alert if file is valid
                warningAlert.classList.add('d-none');
                warningAlert.classList.remove('d-flex');
            }
        } else {
            // Hide alert if user cancels file selection
            warningAlert.classList.add('d-none');
            warningAlert.classList.remove('d-flex');
        }
    });
    </script>
</body>
</html>