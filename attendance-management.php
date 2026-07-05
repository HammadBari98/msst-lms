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

// --- AUTO-PATCHER: Create Tables if not exists ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            class_id INT NOT NULL,
            section_id INT DEFAULT NULL,
            attendance_date DATE NOT NULL,
            status ENUM('Present', 'Absent', 'Leave', 'Late') DEFAULT 'Present',
            marked_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_attendance (student_id, attendance_date),
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    // NEW: Off Days Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS school_off_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            off_date DATE NOT NULL UNIQUE,
            title VARCHAR(255) DEFAULT 'Off Day'
        )
    ");
    // Patch for pre-existing installs where student_attendance already existed without section_id
    $col_check = $pdo->query("SHOW COLUMNS FROM student_attendance LIKE 'section_id'")->fetch();
    if (!$col_check) {
        $pdo->exec("ALTER TABLE student_attendance ADD COLUMN section_id INT DEFAULT NULL AFTER class_id");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$action_msg = '';

// --- HANDLE POST ACTIONS (Save Attendance & Manage Off Days) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'save_attendance') {
        $class_id = $_POST['class_id'];
        $section_id = $_POST['section_id'] !== '' ? (int)$_POST['section_id'] : null;
        $att_date = $_POST['attendance_date'];
        $admin_id = $_SESSION['user_id'] ?? 0;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO student_attendance (student_id, class_id, section_id, attendance_date, status, marked_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), section_id = VALUES(section_id)
            ");

            if (isset($_POST['status']) && is_array($_POST['status'])) {
                foreach ($_POST['status'] as $student_id => $status) {
                    $stmt->execute([$student_id, $class_id, $section_id, $att_date, $status, $admin_id]);
                }
            }
            $pdo->commit();
            $action_msg = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i>Attendance saved successfully for ' . date('d M Y', strtotime($att_date)) . '!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $action_msg = '<div class="alert alert-danger alert-dismissible shadow-sm"><i class="fas fa-times-circle me-2"></i>Error saving attendance: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    } 
    elseif ($_POST['action'] === 'add_off_day') {
        try {
            $pdo->prepare("INSERT IGNORE INTO school_off_days (off_date, title) VALUES (?, ?)")->execute([$_POST['off_date'], $_POST['title']]);
            $action_msg = '<div class="alert alert-success alert-dismissible shadow-sm">Off Day added successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (Exception $e) {}
    } 
    elseif ($_POST['action'] === 'delete_off_day') {
        try {
            $pdo->prepare("DELETE FROM school_off_days WHERE id = ?")->execute([$_POST['off_id']]);
            $action_msg = '<div class="alert alert-success alert-dismissible shadow-sm">Off Day removed! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (Exception $e) {}
    }
}

// --- FETCH DATA FOR UI ---
$available_class_sections = [];
try {
    $available_class_sections = $pdo->query("
        SELECT DISTINCT c.id AS class_id, c.class_name, IFNULL(sec.id, 0) AS section_id, sec.section_name
        FROM classes c
        LEFT JOIN sections sec ON sec.class_id = c.id
        ORDER BY c.class_name, sec.section_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch Off Days
$stmt_offs = $pdo->query("SELECT * FROM school_off_days ORDER BY off_date DESC");
$off_days_list = $stmt_offs->fetchAll(PDO::FETCH_ASSOC);
$off_dates_assoc = [];
foreach($off_days_list as $od) { $off_dates_assoc[$od['off_date']] = $od['title']; }

// Determine Selected Filters
[$selected_class, $selected_section] = array_pad(explode('|', $_GET['class_section'] ?? ''), 2, null);
if ($selected_class === null || $selected_class === '') {
    $selected_class = $available_class_sections[0]['class_id'] ?? 0;
    $selected_section = $available_class_sections[0]['section_id'] ?? 0;
}
$selected_section = (int)$selected_section;
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_class_name = '';
$selected_section_name = '';

foreach($available_class_sections as $c) {
    if ($c['class_id'] == $selected_class && (int)$c['section_id'] == $selected_section) {
        $selected_class_name = $c['class_name'];
        $selected_section_name = $c['section_name'];
        break;
    }
}

$is_current_date_off = $off_dates_assoc[$selected_date] ?? false;

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
            AND IFNULL(sd.section_id, 0) = ?
        ORDER BY s.section_name, u.full_name
    ");
    $stmt->execute([$selected_date, $selected_class, $selected_section]);
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
                    <button class="btn btn-warning shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#offDaysModal">
                        <i class="fas fa-calendar-times"></i> Manage Off Days
                    </button>
                    <a href="attendance-report.php" class="btn btn-info shadow-sm text-white">
                        <i class="fas fa-calendar-alt fa-sm text-white-50 me-1"></i> View Monthly Report
                    </a>
                </div>
            </div>

            <?= $action_msg ?>

            <?php if ($is_current_date_off): ?>
                <div class="alert alert-warning shadow-sm border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-umbrella-beach fa-2x me-3"></i>
                    <div>
                        <strong>Notice:</strong> This date is globally marked as an <strong>Off Day (<?= htmlspecialchars($is_current_date_off) ?>)</strong>. Attendance marking is not required.
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4 border-0 rounded-3">
                <div class="card-body bg-light rounded-3">
                    <form method="GET" id="filterForm" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Select Class</label>
                            <select class="form-select border-primary fw-bold" name="class_section" onchange="document.getElementById('filterForm').submit()">
                                <?php foreach($available_class_sections as $c): ?>
                                    <option value="<?= $c['class_id'] ?>|<?= $c['section_id'] ?>" <?= ($c['class_id'] == $selected_class && (int)$c['section_id'] == $selected_section) ? 'selected' : '' ?>>
                                        Class <?= htmlspecialchars($c['class_name']) ?><?= $c['section_name'] ? ' (' . htmlspecialchars($c['section_name']) . ')' : '' ?>
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
                        <i class="fas fa-clipboard-list me-2"></i> Attendance Register: Class <?= htmlspecialchars($selected_class_name) ?><?= $selected_section_name ? ' (' . htmlspecialchars($selected_section_name) . ')' : '' ?>
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
                    <input type="hidden" name="section_id" value="<?= $selected_section ?>">
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

<div class="modal fade" id="offDaysModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-calendar-times me-2"></i>Manage Off Days</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" class="mb-4 bg-white p-3 border rounded shadow-sm">
                    <input type="hidden" name="action" value="add_off_day">
                    <h6 class="fw-bold mb-3">Add New Off Day</h6>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <input type="date" name="off_date" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="title" class="form-control" placeholder="Reason (e.g. Sunday, Eid)" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </form>

                <h6 class="fw-bold mb-2">Existing Off Days</h6>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-hover bg-white border align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0;">
                            <tr>
                                <th>Date</th>
                                <th>Reason</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($off_days_list as $od): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= date('d M Y', strtotime($od['off_date'])) ?></td>
                                    <td><?= htmlspecialchars($od['title']) ?></td>
                                    <td class="text-end">
                                        <form method="post" class="m-0" onsubmit="return confirm('Remove this Off Day?');">
                                            <input type="hidden" name="action" value="delete_off_day">
                                            <input type="hidden" name="off_id" value="<?= $od['id'] ?>">
                                            <button type="submit" class="btn btn-sm text-danger p-0"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(empty($off_days_list)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No off days configured.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    if(toggleBtn) {
        toggleBtn.addEventListener('click',()=>{
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
            if(window.innerWidth<768){
                if(!sidebar.classList.contains('collapsed')){
                    sidebar.classList.add('show'); mainContent.classList.add('show-sidebar');
                } else {
                    sidebar.classList.remove('show'); mainContent.classList.remove('show-sidebar');
                }
            }
        });
    }
</script>
</body>
</html> 