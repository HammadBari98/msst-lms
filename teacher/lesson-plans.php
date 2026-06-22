<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Authenticate the user
if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// =======================================================
// 1. ROBUST TEACHER ID FETCH
// =======================================================
$raw_session_id = $_SESSION['user_db_id'] ?? $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? $_SESSION['id'] ?? null;
$teacher_user_id = 0;

if ($raw_session_id) {
    if (is_numeric($raw_session_id)) { 
        $teacher_user_id = (int)$raw_session_id; 
    } else {
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

// Fetch corresponding teacher_details ID (for cross-referencing assignments)
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
        CREATE TABLE IF NOT EXISTS lesson_plans (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            section_id INT DEFAULT 0,
            subject_topic VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            period_label VARCHAR(100) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) { /* Ignore if exists */ }

// Ensure upload directory exists
$upload_dir = __DIR__ . '/../uploads/lesson_plans/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// =======================================================
// 3. HANDLE FILE UPLOAD & DELETION
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'upload_plan') {
            // Handle File Upload
            if (isset($_FILES['plan_file']) && $_FILES['plan_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['plan_file']['tmp_name'];
                $original_name = basename($_FILES['plan_file']['name']);
                $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                
                // Allowed extensions
                $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'ppt', 'pptx'];
                
                if (!in_array($file_ext, $allowed_exts)) {
                    throw new Exception("Invalid file type. Only PDF, Word, Excel, PowerPoint, and TXT are allowed.");
                }
                
                // Generate a unique file name to prevent overwriting
                $new_file_name = uniqid('lp_') . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    // We only use class_id since sections are not assigned directly to teachers
                    $class_id = intval($_POST['class_selection']);
                    $section_id = 0;

                    $stmt = $pdo->prepare("INSERT INTO lesson_plans (teacher_id, class_id, section_id, subject_topic, title, period_label, file_name, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $teacher_user_id,
                        $class_id,
                        $section_id,
                        trim($_POST['subject_topic']),
                        trim($_POST['title']),
                        trim($_POST['period_label']),
                        $original_name, // Display name
                        'uploads/lesson_plans/' . $new_file_name // Relative path for download
                    ]);
                    $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i>Lesson plan uploaded successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                } else {
                    throw new Exception("Failed to save the uploaded file to the server.");
                }
            } else {
                throw new Exception("Please select a valid file to upload.");
            }
        } elseif ($_POST['action'] === 'delete_plan') {
            // Check ownership and get file path
            $stmt = $pdo->prepare("SELECT file_path FROM lesson_plans WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$_POST['plan_id'], $teacher_user_id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($plan) {
                // Delete physical file
                $full_path = __DIR__ . '/../' . $plan['file_path'];
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
                // Delete DB record
                $pdo->prepare("DELETE FROM lesson_plans WHERE id = ?")->execute([$_POST['plan_id']]);
                $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-trash me-2"></i>Lesson plan deleted successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
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
// A. Fetch Teacher's Assigned Classes for the Dropdown (CLEANED)
$my_assigned_classes = [];
try {
    $stmt_classes = $pdo->prepare("
        SELECT DISTINCT c.id as class_id, c.class_name 
        FROM teacher_class_assignments tca
        JOIN classes c ON tca.class_id = c.id
        WHERE tca.teacher_user_id = ? OR tca.teacher_user_id = ?
        ORDER BY c.class_name
    ");
    $stmt_classes->execute([$teacher_user_id, $teacher_details_id]);
    $my_assigned_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// B. Fetch Uploaded Lesson Plans
$my_plans = [];
try {
    $stmt_plans = $pdo->prepare("
        SELECT lp.*, c.class_name 
        FROM lesson_plans lp
        LEFT JOIN classes c ON lp.class_id = c.id
        WHERE lp.teacher_id = ?
        ORDER BY lp.created_at DESC
    ");
    $stmt_plans->execute([$teacher_user_id]);
    $my_plans = $stmt_plans->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Helper function for file icons
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch($ext) {
        case 'pdf': return '<i class="fas fa-file-pdf text-danger fa-2x"></i>';
        case 'doc': case 'docx': return '<i class="fas fa-file-word text-primary fa-2x"></i>';
        case 'xls': case 'xlsx': return '<i class="fas fa-file-excel text-success fa-2x"></i>';
        case 'ppt': case 'pptx': return '<i class="fas fa-file-powerpoint text-warning fa-2x"></i>';
        case 'txt': return '<i class="fas fa-file-alt text-secondary fa-2x"></i>';
        default: return '<i class="fas fa-file text-secondary fa-2x"></i>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Plans | <?= htmlspecialchars($teacher_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        :root {
            --primary: #4e73df; --secondary: #858796; --success: #1cc88a; 
            --info: #36b9cc; --warning: #f6c23e; --danger: #e74a3b; 
            --light-bg: #f8f9fc;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        #main-content { display: flex; flex-direction: column; min-height: 100vh; }
        .content-wrapper { flex-grow: 1; }
        
        /* Drag and Drop Styling */
        .drop-zone {
            border: 2px dashed var(--primary);
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            background-color: #f8f9fc;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .drop-zone.dragover {
            background-color: #e2e8f0;
            border-color: #2e59d9;
        }
        .drop-zone input[type="file"] {
            display: none;
        }
        .drop-zone-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        .file-info-badge {
            background: #e7f3ff;
            color: #0d6efd;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
            margin-top: 15px;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content" class="d-flex flex-column min-vh-100">
        <?php include 'header.php' ?>

        <div class="content-wrapper flex-grow-1 p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-book-open me-2 text-primary"></i>Manage Lesson Plans</h1>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#uploadPlanModal">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Upload New Plan
                    </button>
                </div>

                <?= $action_msg ?>

                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-archive me-2"></i>My Uploaded Lesson Plans</h6>
                    </div>
                    <div class="card-body bg-light p-3">
                        <?php if (empty($my_plans)): ?>
                            <div class="text-center p-5 bg-white rounded border border-light">
                                <i class="fas fa-folder-open text-muted fa-4x mb-3 opacity-25"></i>
                                <h5 class="fw-bold text-dark">No Lesson Plans Uploaded</h5>
                                <p class="text-muted mb-0">Use the button above to upload daily or weekly lesson plans for your students.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive bg-white rounded border p-3">
                                <table class="table table-hover align-middle mb-0" id="lessonPlansTable">
                                    <thead class="table-light text-muted small">
                                        <tr>
                                            <th class="text-center" style="width: 5%;">Format</th>
                                            <th>Plan Title & Subject</th>
                                            <th>Target Class</th>
                                            <th>Period / Week</th>
                                            <th>Upload Date</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_plans as $plan): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?= getFileIcon($plan['file_name']) ?>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($plan['title']) ?></div>
                                                    <div class="small text-muted"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($plan['subject_topic']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary px-2 py-1">
                                                        Class <?= htmlspecialchars($plan['class_name'] ?? 'Unknown') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold text-secondary"><?= htmlspecialchars($plan['period_label']) ?></span>
                                                </td>
                                                <td>
                                                    <div class="small fw-bold text-dark"><?= date('d M Y', strtotime($plan['created_at'])) ?></div>
                                                    <div class="small text-muted"><?= date('h:i A', strtotime($plan['created_at'])) ?></div>
                                                </td>
                                                <td class="text-center">
                                                    <a href="../<?= htmlspecialchars($plan['file_path']) ?>" download="<?= htmlspecialchars($plan['file_name']) ?>" class="btn btn-sm btn-light border text-success me-1" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this lesson plan? Students will no longer be able to access it.');">
                                                        <input type="hidden" name="action" value="delete_plan">
                                                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
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

    <div class="modal fade" id="uploadPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fas fa-cloud-upload-alt me-2"></i>Upload Lesson Plan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_plan">
                    <div class="modal-body p-4 bg-light">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select Class <span class="text-danger">*</span></label>
                                <select class="form-select" name="class_selection" required>
                                    <option value="" disabled selected>Choose a class...</option>
                                    <?php foreach ($my_assigned_classes as $cls): ?>
                                        <option value="<?= htmlspecialchars($cls['class_id']) ?>">
                                            Class <?= htmlspecialchars($cls['class_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Subject / Topic <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="subject_topic" placeholder="e.g. Mathematics - Algebra" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Plan Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" placeholder="e.g. Intro to Linear Equations" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Period / Timeframe <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="period_label" placeholder="e.g. Week 1 (May), or Daily: 22-June" required>
                            </div>
                        </div>

                        <div class="mb-2 mt-4">
                            <label class="form-label fw-bold">Upload File (PDF, Word, Excel, PPT, TXT) <span class="text-danger">*</span></label>
                            <div class="drop-zone" id="dropZone">
                                <div class="drop-zone-icon"><i class="fas fa-file-upload"></i></div>
                                <h5 class="fw-bold text-dark mb-1">Drag & Drop your file here</h5>
                                <p class="text-muted small">or click to browse from your computer</p>
                                <input type="file" name="plan_file" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" required>
                                <div id="fileDisplay" style="display: none;">
                                    <span class="file-info-badge shadow-sm"><i class="fas fa-check-circle me-2"></i><span id="fileName"></span></span>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-white border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fas fa-upload me-2"></i> Upload File</button>
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
        if ($('#lessonPlansTable').length) {
            $('#lessonPlansTable').DataTable({
                "order": [[4, "desc"]], // Sort by upload date
                "pageLength": 10,
                "columnDefs": [ { "orderable": false, "targets": 5 } ]
            });
        }
        setTimeout(() => $('.alert').alert('close'), 5000);
    });

    // Drag and Drop Logic
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileDisplay = document.getElementById('fileDisplay');
    const fileNameDisplay = document.getElementById('fileName');

    // Click to open file dialog
    dropZone.addEventListener('click', () => fileInput.click());

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight drop zone on drag
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });

    // Handle dropped files
    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    // Handle file selection via click
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            fileInput.files = files; // Update input element
            fileNameDisplay.textContent = file.name;
            fileDisplay.style.display = 'block';
        }
    }
    </script>
</body>
</html>