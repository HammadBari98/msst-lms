<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

// Check if the admin is logged in.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
date_default_timezone_set('Asia/Karachi');

// --- AUTO-PATCHER: Create Attendance Table if not exists ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            class_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            status ENUM('Present', 'Absent', 'Leave', 'Late') DEFAULT 'Present',
            marked_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_attendance (student_id, attendance_date),
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    die("Database Error creating attendance table: " . $e->getMessage());
}

// --- HANDLE SAVING ATTENDANCE ---
$action_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $class_id = $_POST['class_id'];
    $att_date = $_POST['attendance_date'];
    $admin_id = $_SESSION['user_id'] ?? 0;

    try {
        $pdo->beginTransaction();
        // Insert new record, or update if it already exists for that student + date
        $stmt = $pdo->prepare("
            INSERT INTO student_attendance (student_id, class_id, attendance_date, status, marked_by) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)
        ");

        if (isset($_POST['status']) && is_array($_POST['status'])) {
            foreach ($_POST['status'] as $student_id => $status) {
                $stmt->execute([$student_id, $class_id, $att_date, $status, $admin_id]);
            }
        }
        $pdo->commit();
        $action_msg = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i>Attendance saved successfully for ' . date('d M Y', strtotime($att_date)) . '!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $action_msg = '<div class="alert alert-danger alert-dismissible shadow-sm"><i class="fas fa-times-circle me-2"></i>Error saving attendance: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// --- FETCH DATA FOR UI ---
$available_classes = [];
try {
    $available_classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Determine Selected Filters
$selected_class = $_GET['class_id'] ?? ($available_classes[0]['id'] ?? 0);
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_class_name = '';

foreach($available_classes as $c) {
    if ($c['id'] == $selected_class) { $selected_class_name = $c['class_name']; break; }
}

// Fetch Students & Their Attendance
$students = [];
if ($selected_class) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.user_id_string, u.full_name, 
            sd.father_name, s.section_name,
            a.status as attendance_status
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN sections s ON sd.section_id = s.id
        LEFT JOIN student_attendance a ON u.id = a.student_id AND a.attendance_date = ?
        WHERE r.role_name = 'Student' AND u.status = 'Active' AND sd.class_id = ?
        ORDER BY s.section_name, u.full_name
    ");
    $stmt->execute([$selected_date, $selected_class]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate Summary
$summary = ['Present' => 0, 'Absent' => 0, 'Leave' => 0, 'Late' => 0, 'Total' => count($students), 'Marked' => 0];
foreach ($students as $st) {
    if ($st['attendance_status']) {
        $summary[$st['attendance_status']]++;
        $summary['Marked']++;
    }
}
$is_fully_marked = ($summary['Marked'] === $summary['Total'] && $summary['Total'] > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .summary-card { border-radius: 12px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .summary-card:hover { transform: translateY(-3px); }
        .bg-present { background: linear-gradient(135deg, #198754, #20c997); color: white; }
        .bg-absent { background: linear-gradient(135deg, #dc3545, #f8d7da); color: white; }
        .bg-absent .card-title, .bg-absent .display-4 { color: #fff; }
        .bg-leave { background: linear-gradient(135deg, #ffc107, #ffecb5); color: #856404; }
        .bg-total { background: linear-gradient(135deg, #0d6efd, #8bb9fe); color: white; }
        
        .radio-toolbar input[type="radio"] { display: none; }
        .radio-toolbar label {
            display: inline-block; padding: 4px 12px; font-size: 0.85rem; cursor: pointer;
            border: 1px solid #dee2e6; border-radius: 20px; margin-right: 4px; transition: all 0.2s;
        }
        .radio-toolbar input[type="radio"]:checked + label { font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .radio-toolbar input[value="Present"]:checked + label { background-color: #198754; border-color: #198754; color: white; }
        .radio-toolbar input[value="Absent"]:checked + label { background-color: #dc3545; border-color: #dc3545; color: white; }
        .radio-toolbar input[value="Leave"]:checked + label { background-color: #ffc107; border-color: #ffc107; color: #000; }
        .radio-toolbar input[value="Late"]:checked + label { background-color: #fd7e14; border-color: #fd7e14; color: white; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    
    <div class="content-wrapper p-4">
        <div class="container-fluid">
            
            <div class="d-sm-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-clock me-2"></i>Daily Attendance</h1>
                <div>
                    <a href="attendance-report.php" class="btn btn-info shadow-sm text-white me-2">
                        <i class="fas fa-calendar-alt fa-sm text-white-50 me-1"></i> View Monthly Report
                    </a>
                </div>
            </div>

            <?= $action_msg ?>

            <div class="card shadow-sm mb-4 border-0 rounded-3">
                <div class="card-body bg-light rounded-3">
                    <form method="GET" id="filterForm" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Select Class</label>
                            <select class="form-select border-primary fw-bold" name="class_id" onchange="document.getElementById('filterForm').submit()">
                                <?php foreach($available_classes as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $selected_class ? 'selected' : '' ?>>
                                        Class <?= htmlspecialchars($c['class_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Attendance Date</label>
                            <input type="date" class="form-control border-primary fw-bold" name="date" value="<?= htmlspecialchars($selected_date) ?>" onchange="document.getElementById('filterForm').submit()">
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="attendance-management.php" class="btn btn-outline-secondary"><i class="fas fa-redo me-1"></i> Reset to Today</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card summary-card bg-total h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center"><div class="col"><div class="text-uppercase mb-1 fw-bold opacity-75">Total Students</div><div class="display-6 fw-bold"><?= $summary['Total'] ?></div></div><div class="col-auto"><i class="fas fa-users fa-2x opacity-50"></i></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card summary-card bg-present h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center"><div class="col"><div class="text-uppercase mb-1 fw-bold opacity-75">Present</div><div class="display-6 fw-bold"><?= $summary['Present'] ?></div></div><div class="col-auto"><i class="fas fa-user-check fa-2x opacity-50"></i></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card summary-card bg-absent h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center"><div class="col"><div class="text-uppercase mb-1 fw-bold opacity-75 text-white">Absent</div><div class="display-6 fw-bold text-white"><?= $summary['Absent'] ?></div></div><div class="col-auto"><i class="fas fa-user-times fa-2x opacity-50 text-white"></i></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card summary-card bg-leave h-100 py-2">
                        <div class="card-body">
                            <div class="row align-items-center"><div class="col"><div class="text-uppercase mb-1 fw-bold opacity-75">On Leave / Late</div><div class="display-6 fw-bold"><?= $summary['Leave'] + $summary['Late'] ?></div></div><div class="col-auto"><i class="fas fa-user-clock fa-2x opacity-50"></i></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4 border-0 rounded-3">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-clipboard-list me-2"></i> Attendance Register: Class <?= htmlspecialchars($selected_class_name) ?> 
                        <span class="badge bg-secondary ms-2"><?= date('d M Y', strtotime($selected_date)) ?></span>
                    </h6>
                    <?php if ($is_fully_marked): ?>
                        <span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i> Attendance Completed</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark px-3 py-2"><i class="fas fa-exclamation-circle me-1"></i> Pending Submission</span>
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="save_attendance">
                    <input type="hidden" name="class_id" value="<?= $selected_class ?>">
                    <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($selected_date) ?>">
                    
                    <div class="card-body p-0">
                        <?php if(empty($students)): ?>
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                                <h5>No active students found in this class.</h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4" style="width: 15%">Student ID</th>
                                            <th style="width: 30%">Student Name</th>
                                            <th style="width: 15%">Section</th>
                                            <th class="text-end pe-4" style="width: 40%">Mark Attendance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): 
                                            // Default to Present if not marked yet
                                            $status = $student['attendance_status'] ?: 'Present';
                                        ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-secondary"><?= htmlspecialchars($student['user_id_string']) ?></td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($student['full_name']) ?></div>
                                                    <div class="small text-muted">S/D of: <?= htmlspecialchars($student['father_name'] ?? 'N/A') ?></div>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($student['section_name'] ?? '-') ?></span></td>
                                                <td class="text-end pe-4">
                                                    <div class="radio-toolbar">
                                                        <input type="radio" id="p_<?= $student['id'] ?>" name="status[<?= $student['id'] ?>]" value="Present" <?= $status == 'Present' ? 'checked' : '' ?>>
                                                        <label for="p_<?= $student['id'] ?>">Present</label>

                                                        <input type="radio" id="a_<?= $student['id'] ?>" name="status[<?= $student['id'] ?>]" value="Absent" <?= $status == 'Absent' ? 'checked' : '' ?>>
                                                        <label for="a_<?= $student['id'] ?>">Absent</label>

                                                        <input type="radio" id="l_<?= $student['id'] ?>" name="status[<?= $student['id'] ?>]" value="Leave" <?= $status == 'Leave' ? 'checked' : '' ?>>
                                                        <label for="l_<?= $student['id'] ?>">Leave</label>

                                                        <input type="radio" id="lt_<?= $student['id'] ?>" name="status[<?= $student['id'] ?>]" value="Late" <?= $status == 'Late' ? 'checked' : '' ?>>
                                                        <label for="lt_<?= $student['id'] ?>">Late</label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(!empty($students)): ?>
                    <div class="card-footer bg-light p-3 text-end">
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fas fa-save me-2"></i> <?= $is_fully_marked ? 'Update Attendance' : 'Save Attendance' ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
        </div>
    </div>
    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> Muhaddisa School of Science and Technology.</p></div></footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle Script
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    if(toggleBtn) {
        toggleBtn.addEventListener('click',()=>{
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
            if(window.innerWidth<768){
                if(!sidebar.classList.contains('collapsed')){
                    sidebar.classList.add('show');
                    mainContent.classList.add('show-sidebar');
                } else {
                    sidebar.classList.remove('show');
                    mainContent.classList.remove('show-sidebar');
                }
            }
        });
    }
</script>
</body>
</html>