<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Check for session messages
$action_msg = '';
if (isset($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// --- HANDLE QUICK EDIT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_edit') {
    $student_id = $_POST['edit_student_id'];
    $att_date = $_POST['edit_date'];
    $status = $_POST['edit_status'];
    $class_id = $_POST['edit_class_id'];
    $section_id = $_POST['edit_section_id'] !== '' ? (int)$_POST['edit_section_id'] : null;
    $admin_id = $_SESSION['user_id'] ?? 0;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO student_attendance (student_id, class_id, section_id, attendance_date, status, marked_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), section_id = VALUES(section_id)
        ");
        $stmt->execute([$student_id, $class_id, $section_id, $att_date, $status, $admin_id]);
        $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i>Attendance updated for ' . date('d M', strtotime($att_date)) . '!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } catch (Exception $e) {
        $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible shadow-sm"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    header("Location: attendance-report.php?class_id=$class_id&month=" . substr($att_date, 0, 7));
    exit;
}

// Fetch available classes for the dropdown
$available_class_sections = [];
try {
    $available_class_sections = $pdo->query("
        SELECT DISTINCT c.id AS class_id, c.class_name, IFNULL(sec.id, 0) AS section_id, sec.section_name
        FROM classes c
        LEFT JOIN sections sec ON sec.class_id = c.id
        ORDER BY c.class_name, sec.section_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Setup Filters
[$selected_class, $selected_section] = array_pad(explode('|', $_GET['class_section'] ?? ''), 2, null);
if ($selected_class === null || $selected_class === '') {
    $selected_class = $available_class_sections[0]['class_id'] ?? 0;
    $selected_section = $available_class_sections[0]['section_id'] ?? 0;
}
$selected_section = (int)$selected_section;
$selected_month = $_GET['month'] ?? date('Y-m'); // Default to current month
$selected_class_name = '';
$selected_section_name = '';

foreach($available_class_sections as $c) {
    if ($c['class_id'] == $selected_class && (int)$c['section_id'] == $selected_section) {
        $selected_class_name = $c['class_name'];
        $selected_section_name = $c['section_name'];
        break;
    }
}

$days_in_month = date('t', strtotime($selected_month . '-01'));
$month_label = date('F Y', strtotime($selected_month . '-01'));

$students = [];
$attendance_data = [];
$off_days = [];

if ($selected_class) {
    try {
        // Fetch Off Days for this month
        $stmt_offs = $pdo->prepare("SELECT off_date, title FROM school_off_days WHERE DATE_FORMAT(off_date, '%Y-%m') = ?");
        $stmt_offs->execute([$selected_month]);
        while ($row = $stmt_offs->fetch(PDO::FETCH_ASSOC)) {
            $off_days[(int)date('d', strtotime($row['off_date']))] = $row['title'];
        }

        // 1. Fetch Students in the selected class
        $stmt_students = $pdo->prepare("
            SELECT u.id, u.user_id_string, u.full_name, sd.father_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN student_details sd ON u.id = sd.user_id
            WHERE r.role_name = 'Student' AND u.status = 'Active' AND sd.class_id = ?
                AND IFNULL(sd.section_id, 0) = ?
            ORDER BY u.full_name
        ");
        $stmt_students->execute([$selected_class, $selected_section]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch Attendance for this class in this month
        $stmt_att = $pdo->prepare("
            SELECT student_id, DAY(attendance_date) as day, status
            FROM student_attendance
            WHERE class_id = ? AND IFNULL(section_id, 0) = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
        ");
        $stmt_att->execute([$selected_class, $selected_section, $selected_month]);
        $raw_attendance = $stmt_att->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_attendance as $row) {
            $attendance_data[$row['student_id']][$row['day']] = $row['status'];
        }

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | Admin Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    
    <style>
        .att-cell { text-align: center; font-weight: bold; font-size: 0.8rem; vertical-align: middle; cursor: pointer; transition: transform 0.1s, box-shadow 0.1s; }
        .att-cell:hover { transform: scale(1.1); box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 10; position: relative; border-radius: 4px; }
        .att-P { color: #198754; background-color: #e8f5e9 !important; }
        .att-A { color: #dc3545; background-color: #f8d7da !important; }
        .att-L { color: #856404; background-color: #fff3cd !important; }
        .att-LT { color: #fd7e14; background-color: #ffebd3 !important; }
        .att-empty { color: #ccc; }
        
        /* NEW: Off Day Style */
        .att-off { background-color: #ffeb3b !important; color: #856404 !important; font-weight: bold; cursor: not-allowed; }
        
        .summary-cell { pointer-events: none; } 
        
        .table-responsive { overflow-x: auto; }
        .freeze-col { position: sticky; left: 0; background-color: #f8f9fa; z-index: 2; border-right: 1px solid #dee2e6; }
        .freeze-col-2 { position: sticky; left: 80px; background-color: #f8f9fa; z-index: 2; border-right: 2px solid #dee2e6; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        thead .freeze-col, thead .freeze-col-2 { z-index: 3; background-color: #e9ecef; }
        table.dataTable { border-collapse: collapse !important; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-line me-2"></i>Monthly Attendance Matrix</h1>
                <div>
                    <button class="btn btn-secondary shadow-sm me-2 no-print" onclick="printReport()">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                    <a href="attendance-management.php" class="btn btn-primary shadow-sm no-print">
                        <i class="fas fa-edit me-1"></i> Mark Daily Attendance
                    </a>
                </div>
            </div>

            <?= $action_msg ?>

            <div class="card shadow-sm mb-4 border-0 rounded-3 no-print">
                <div class="card-body bg-light rounded-3">
                    <form method="GET" id="reportFilterForm" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Select Class</label>
                            <select class="form-select border-primary fw-bold" name="class_section" onchange="document.getElementById('reportFilterForm').submit()">
                                <?php foreach($available_class_sections as $c): ?>
                                    <option value="<?= $c['class_id'] ?>|<?= $c['section_id'] ?>" <?= ($c['class_id'] == $selected_class && (int)$c['section_id'] == $selected_section) ? 'selected' : '' ?>>
                                        Class <?= htmlspecialchars($c['class_name']) ?><?= $c['section_name'] ? ' (' . htmlspecialchars($c['section_name']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Select Month</label>
                            <input type="month" class="form-control border-primary fw-bold" name="month" value="<?= htmlspecialchars($selected_month) ?>" onchange="document.getElementById('reportFilterForm').submit()">
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow border-0 rounded-3 printable-area">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Attendance Grid: Class <?= htmlspecialchars($selected_class_name) ?><?= $selected_section_name ? ' (' . htmlspecialchars($selected_section_name) . ')' : '' ?>
                        <span class="badge bg-secondary ms-2"><?= $month_label ?></span>
                    </h6>
                    <div class="small fw-bold no-print">
                        <span class="me-3"><i class="fas fa-square text-success"></i> P = Present</span>
                        <span class="me-3"><i class="fas fa-square text-danger"></i> A = Absent</span>
                        <span class="me-3"><i class="fas fa-square text-warning"></i> L = Leave</span>
                        <span class="me-3"><i class="fas fa-square" style="color: #fd7e14;"></i> LT = Late</span>
                        <span><i class="fas fa-square" style="color: #ffeb3b;"></i> OFF</span>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if(empty($students)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                            <h5>No active students found in this class.</h5>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-2 no-print"><i class="fas fa-info-circle me-1"></i> <strong>Tip:</strong> Click on any day cell to quickly edit that student's attendance!</p>
                        <div class="table-responsive border rounded" style="max-height: 65vh;">
                            <table class="table table-bordered table-hover mb-0" id="reportTable" style="min-width: 1200px;">
                                <thead class="align-middle text-center">
                                    <tr>
                                        <th class="freeze-col text-start ps-3" style="width: 80px; min-width: 80px;">ID</th>
                                        <th class="freeze-col-2 text-start" style="width: 180px; min-width: 180px;">Student Name</th>
                                        
                                        <?php for($d = 1; $d <= $days_in_month; $d++): ?>
                                            <?php $is_off = isset($off_days[$d]); ?>
                                            <th style="width: 30px; min-width: 30px; font-size: 0.75rem;" class="<?= $is_off ? 'bg-warning bg-opacity-25' : '' ?>" <?= $is_off ? 'title="'.htmlspecialchars($off_days[$d]).'"' : '' ?>><?= $d ?></th>
                                        <?php endfor; ?>
                                        
                                        <th style="width: 50px; min-width: 50px;" class="bg-success text-white">P</th>
                                        <th style="width: 50px; min-width: 50px;" class="bg-danger text-white">A</th>
                                        <th style="width: 50px; min-width: 50px;" class="bg-warning text-dark">L</th>
                                        <th style="width: 60px; min-width: 60px;" class="bg-primary text-white">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): 
                                        $p_count = 0; $a_count = 0; $l_count = 0; $lt_count = 0; $total_marked = 0;
                                    ?>
                                        <tr>
                                            <td class="freeze-col text-start ps-3 fw-bold text-secondary" style="font-size: 0.8rem;">
                                                <?= htmlspecialchars($student['user_id_string']) ?>
                                            </td>
                                            <td class="freeze-col-2 text-start bg-white">
                                                <div class="fw-bold text-dark" style="font-size: 0.85rem; mb-1"><?= htmlspecialchars($student['full_name']) ?></div>
                                                <div class="text-muted fw-semibold" style="font-size: 0.7rem;">S/D: <?= htmlspecialchars($student['father_name'] ?? 'N/A') ?></div>
                                            </td>
                                            
                                            <?php for($d = 1; $d <= $days_in_month; $d++): 
                                                $is_off = isset($off_days[$d]);
                                                $exact_date = $selected_month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                                                $status = $attendance_data[$student['id']][$d] ?? null;
                                                
                                                if ($is_off) {
                                                    // It's an Off Day, show it and don't count towards marks
                                                    echo "<td class='att-cell att-off' title='".htmlspecialchars($off_days[$d])."'>OFF</td>";
                                                } else {
                                                    // Standard Marking Logic
                                                    $cell_class = 'att-empty'; $mark = '-';
                                                    if ($status === 'Present') { $cell_class = 'att-P'; $mark = 'P'; $p_count++; $total_marked++; }
                                                    elseif ($status === 'Absent') { $cell_class = 'att-A'; $mark = 'A'; $a_count++; $total_marked++; }
                                                    elseif ($status === 'Leave') { $cell_class = 'att-L'; $mark = 'L'; $l_count++; $total_marked++; }
                                                    elseif ($status === 'Late') { $cell_class = 'att-LT'; $mark = 'LT'; $lt_count++; $p_count++; $total_marked++; } 
                                                    
                                                    echo "<td class='att-cell $cell_class' title='Click to edit' onclick='openEditModal({$student['id']}, \"".htmlspecialchars($student['full_name'], ENT_QUOTES)."\", \"$exact_date\", \"".($status ?: 'Present')."\")'>$mark</td>";
                                                }
                                            endfor; ?>
                                            
                                            <?php 
                                                $percent = $total_marked > 0 ? round(($p_count / $total_marked) * 100, 1) : 0;
                                                $percent_color = $percent >= 75 ? 'text-success' : ($percent >= 50 ? 'text-warning' : 'text-danger');
                                            ?>
                                            <td class="att-cell summary-cell fw-bold bg-light"><?= $p_count ?></td>
                                            <td class="att-cell summary-cell fw-bold bg-light text-danger"><?= $a_count ?></td>
                                            <td class="att-cell summary-cell fw-bold bg-light text-warning"><?= $l_count + $lt_count ?></td>
                                            <td class="att-cell summary-cell fw-bold bg-light <?= $percent_color ?>"><?= $total_marked > 0 ? $percent . '%' : '-' ?></td>
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
    <footer class="footer no-print"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> Muhaddisa School of Science and Technology.</p></div></footer>
</div>

<div class="modal fade" id="quickEditModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Attendance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="quick_edit">
                <input type="hidden" name="edit_class_id" value="<?= $selected_class ?>">
                <input type="hidden" name="edit_section_id" value="<?= $selected_section ?>">
                <input type="hidden" name="edit_student_id" id="editStudentId">
                <input type="hidden" name="edit_date" id="editDate">
                
                <div class="modal-body bg-light p-4 text-center">
                    <p class="text-muted small mb-1">Student</p>
                    <h5 class="fw-bold text-dark mb-3" id="editStudentName"></h5>
                    <p class="text-muted small mb-1">Date</p>
                    <h6 class="fw-bold text-primary mb-4" id="editDateDisplay"></h6>
                    
                    <div class="d-grid gap-2">
                        <input type="radio" class="btn-check" name="edit_status" id="opt_present" value="Present" autocomplete="off">
                        <label class="btn btn-outline-success fw-bold" for="opt_present">Present</label>

                        <input type="radio" class="btn-check" name="edit_status" id="opt_absent" value="Absent" autocomplete="off">
                        <label class="btn btn-outline-danger fw-bold" for="opt_absent">Absent</label>

                        <input type="radio" class="btn-check" name="edit_status" id="opt_leave" value="Leave" autocomplete="off">
                        <label class="btn btn-outline-warning fw-bold" for="opt_leave">Leave</label>

                        <input type="radio" class="btn-check" name="edit_status" id="opt_late" value="Late" autocomplete="off">
                        <label class="btn btn-outline-secondary fw-bold" for="opt_late">Late</label>
                    </div>
                </div>
                <div class="modal-footer border-top bg-white">
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">Save Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        if ($('#reportTable').length > 0) {
            $('#reportTable').DataTable({
                "pageLength": 15,
                "lengthMenu": [10, 15, 25, 50, 100],
                "ordering": false, 
                "language": { "search": "Search Student:" }
            });
        }
        setTimeout(() => $('.alert').alert('close'), 5000);
    });

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

    function openEditModal(studentId, studentName, dateStr, currentStatus) {
        document.getElementById('editStudentId').value = studentId;
        document.getElementById('editStudentName').textContent = studentName;
        document.getElementById('editDate').value = dateStr;
        
        const dateObj = new Date(dateStr);
        document.getElementById('editDateDisplay').textContent = dateObj.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        
        if(currentStatus === 'Present') document.getElementById('opt_present').checked = true;
        else if(currentStatus === 'Absent') document.getElementById('opt_absent').checked = true;
        else if(currentStatus === 'Leave') document.getElementById('opt_leave').checked = true;
        else if(currentStatus === 'Late') document.getElementById('opt_late').checked = true;
        else document.getElementById('opt_present').checked = true; 
        
        new bootstrap.Modal(document.getElementById('quickEditModal')).show();
    }

    function printReport() {
        let originalTable = document.getElementById('reportTable');
        let printWindow = window.open('', '', 'height=800,width=1200');
        printWindow.document.write('<html><head><title>Attendance Report</title><style>');
        printWindow.document.write(`
            body { font-family: Arial, sans-serif; padding: 20px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            h2 { text-align: center; font-size: 22px; margin-bottom: 5px; }
            h4 { text-align: center; font-size: 16px; margin-bottom: 20px; color: #555; }
            table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 20px; }
            th, td { border: 1px solid #000; padding: 4px; text-align: center; }
            th.text-start, td.text-start { text-align: left; }
            .att-P { background-color: #e8f5e9 !important; color: #198754 !important; font-weight: bold; }
            .att-A { background-color: #f8d7da !important; color: #dc3545 !important; font-weight: bold; }
            .att-L { background-color: #fff3cd !important; color: #856404 !important; font-weight: bold; }
            .att-LT { background-color: #ffebd3 !important; color: #fd7e14 !important; font-weight: bold; }
            .att-off { background-color: #ffeb3b !important; color: #856404 !important; font-weight: bold; }
            .bg-success { background-color: #198754 !important; color: white !important; }
            .bg-danger { background-color: #dc3545 !important; color: white !important; }
            .bg-warning { background-color: #ffc107 !important; color: black !important; }
            .bg-primary { background-color: #0d6efd !important; color: white !important; }
            @page { size: landscape; margin: 10mm; }
        `);
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h2>Muhaddisa School of Science & Tech</h2>');
        printWindow.document.write('<h4>Attendance Report: Class <?= htmlspecialchars($selected_class_name) ?> (' + '<?= $month_label ?>' + ')</h4>');
        
        let printTable = '<table><thead>' + originalTable.querySelector('thead').innerHTML + '</thead><tbody>';
        let rows = originalTable.querySelectorAll('tbody tr');
        rows.forEach(row => { printTable += '<tr>' + row.innerHTML + '</tr>'; });
        printTable += '</tbody></table></body></html>';
        
        printWindow.document.write(printTable);
        printWindow.document.close();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    }
</script>
</body>
</html>