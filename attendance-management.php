<?php
session_start();

// Check if the admin is logged in.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Set admin details from the session.
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
date_default_timezone_set('Asia/Karachi');
$current_date = date('Y-m-d'); // Today is June 16, 2025

// --- Dummy Attendance Data ---
$students_class_10a = [
    ['id' => 'STU-101', 'name' => 'Kamran Ahmed', 'roll_number' => 15, 'status' => 'Present'],
    ['id' => 'STU-103', 'name' => 'Danish Khan', 'roll_number' => 18, 'status' => 'Absent'],
    ['id' => 'STU-105', 'name' => 'Fatima Jilani', 'roll_number' => 21, 'status' => 'Present'],
    ['id' => 'STU-108', 'name' => 'Hassan Raza', 'roll_number' => 22, 'status' => 'Leave'],
    ['id' => 'STU-112', 'name' => 'Sana Mirza', 'roll_number' => 25, 'status' => 'Present'],
];

// Calculate summary
$summary = ['Present' => 0, 'Absent' => 0, 'Leave' => 0, 'Total' => count($students_class_10a)];
foreach ($students_class_10a as $student) {
    $summary[$student['status']]++;
}

$attendance_policy = ['required_percentage' => 75];
$available_classes = ['Class 10-A', 'Class 9-B', 'Class 8-C'];

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
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Attendance Management</h1>
                <button class="btn btn-secondary shadow-sm" data-bs-toggle="modal" data-bs-target="#policyModal">
                    <i class="fas fa-gavel fa-sm text-white-50"></i> Set Attendance Policy
                </button>
            </div>

            <!-- Attendance Filters & Summary -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-body d-flex flex-wrap align-items-center">
                            <div class="me-3 mb-2 mb-md-0">
                                <label for="attendanceDate" class="form-label">Date</label>
                                <input type="date" class="form-control" id="attendanceDate" value="<?= $current_date ?>">
                            </div>
                            <div class="me-3 mb-2 mb-md-0">
                                <label for="attendanceClass" class="form-label">Class</label>
                                <select class="form-select" id="attendanceClass">
                                    <?php foreach ($available_classes as $class): echo "<option>$class</option>"; endforeach; ?>
                                </select>
                            </div>
                            <button class="btn btn-primary align-self-end">Load Attendance</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                         <div class="card-header bg-info text-white">Daily Summary (Class 10-A)</div>
                         <div class="card-body d-flex justify-content-around align-items-center text-center">
                            <div><p class="h4"><?= $summary['Present'] ?></p><p class="mb-0 text-success">Present</p></div>
                            <div><p class="h4"><?= $summary['Absent'] ?></p><p class="mb-0 text-danger">Absent</p></div>
                            <div><p class="h4"><?= $summary['Leave'] ?></p><p class="mb-0 text-warning">Leave</p></div>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Log Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Log for Class 10-A on <?= date('F d, Y', strtotime($current_date)) ?></h6>
                    <button class="btn btn-success" onclick="saveAttendance()"><i class="fas fa-save"></i> Save All Changes</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead><tr><th>Roll #</th><th>Student Name</th><th class="text-center">Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($students_class_10a as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['roll_number']) ?></td>
                                        <td><?= htmlspecialchars($student['name']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <input type="radio" class="btn-check" name="status_<?= $student['id'] ?>" id="present_<?= $student['id'] ?>" autocomplete="off" <?= $student['status'] == 'Present' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-success" for="present_<?= $student['id'] ?>">Present</label>

                                                <input type="radio" class="btn-check" name="status_<?= $student['id'] ?>" id="absent_<?= $student['id'] ?>" autocomplete="off" <?= $student['status'] == 'Absent' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-danger" for="absent_<?= $student['id'] ?>">Absent</label>

                                                <input type="radio" class="btn-check" name="status_<?= $student['id'] ?>" id="leave_<?= $student['id'] ?>" autocomplete="off" <?= $student['status'] == 'Leave' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-warning" for="leave_<?= $student['id'] ?>">Leave</label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> School Management System. All rights reserved.</p></div></footer>
</div>

<!-- Policy Modal -->
<div class="modal fade" id="policyModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Attendance Policy</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form id="policyForm">
            <div class="mb-3"><label class="form-label">Required Attendance Percentage (%)</label><input type="number" class="form-control" value="<?= $attendance_policy['required_percentage'] ?>"></div>
        </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="policyForm" class="btn btn-primary">Save Policy</button></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Sidebar Script ---
    const sidebar = document.getElementById('sidebar'), mainContent = document.getElementById('main-content'), toggleBtn = document.getElementById('sidebarToggle');
    function saveSidebarState(c){localStorage.setItem('sidebarCollapsed',c);}
    function loadSidebarState(){return localStorage.getItem('sidebarCollapsed')==='true';}
    if(loadSidebarState()){sidebar.classList.add('collapsed');mainContent.classList.add('collapsed');}
    toggleBtn.addEventListener('click',()=>{sidebar.classList.toggle('collapsed');mainContent.classList.toggle('collapsed');saveSidebarState(sidebar.classList.contains('collapsed'));if(window.innerWidth<768){if(!sidebar.classList.contains('collapsed')){sidebar.classList.add('show');mainContent.classList.add('show-sidebar');}else{sidebar.classList.remove('show');mainContent.classList.remove('show-sidebar');}}});
    document.addEventListener('click',(e)=>{if(window.innerWidth<768&&sidebar.classList.contains('show')&&!sidebar.contains(e.target)&&!toggleBtn.contains(e.target)){sidebar.classList.remove('show');sidebar.classList.add('collapsed');mainContent.classList.remove('show-sidebar');mainContent.classList.add('collapsed');saveSidebarState(true);}});

    // --- Page-specific Scripts ---
    document.getElementById('policyForm').addEventListener('submit', (e) => { e.preventDefault(); alert('Attendance policy has been updated. (Simulation)'); bootstrap.Modal.getInstance(document.getElementById('policyModal')).hide(); });
    
    function saveAttendance(){
        alert('Attendance records have been saved successfully. (Simulation)');
    }
</script>
</body>
</html>
