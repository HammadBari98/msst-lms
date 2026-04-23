<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Kept commented for static preview

if (!isset($_SESSION['teacher_logged_in'])) {
    header('Location: login.php');
    exit();
}

$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// --- START: DUMMY DATA FOR THIS PAGE ---

// A list of classes the teacher teaches, for the dropdown.
$teacher_classes = [
    ['id' => 1, 'name' => 'Physics - 1st Year (Pre-Engineering)'],
    ['id' => 2, 'name' => 'Chemistry - Class 10th'],
    ['id' => 3, 'name' => 'Mathematics - 2nd Year'],
];

// A list of students for the selected class. In a real app, this would be fetched via AJAX or page reload.
$students_for_class = [
    ['id' => 1, 'roll_number' => '201', 'name' => 'Ahmed Ali'],
    ['id' => 2, 'roll_number' => '202', 'name' => 'Sadia Batool'],
    ['id' => 3, 'roll_number' => '203', 'name' => 'Kamran Khan'],
    ['id' => 4, 'roll_number' => '204', 'name' => 'Ayesha Malik'],
    ['id' => 5, 'roll_number' => '205', 'name' => 'Zubair Hasan'],
];

// --- END OF DUMMY DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance | <?= htmlspecialchars($teacher_name) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #007bff; --secondary: #6c757d; --light-bg: #f8f9fc; --dark-text: #343a40; --success: #198754; --danger: #dc3545; --warning: #ffc107;}
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); overflow-x: hidden; }
        .sidebar { width: 250px; height: 100vh; position: fixed; top: 0; left: 0; background: var(--primary); color: white; transition: all 0.3s; z-index: 1000; }
        .sidebar.collapsed { width: 70px; }
        #main-content { margin-left: 250px; transition: all 0.3s; }
        #main-content.collapsed { margin-left: 70px; }
        @media (max-width: 768px) { .sidebar { left: -250px; } .sidebar.show { left: 0; } #main-content, #main-content.collapsed { margin-left: 0; } }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1.5rem; border: none;}
        .attendance-table .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }
        .attendance-table .form-check { min-width: 100px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    
    <div class="content-wrapper p-3 p-md-4">
        <div class="container-fluid">
            <h1 class="h3 mb-4">Take Attendance</h1>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="classSelect" class="form-label">Select Class</label>
                            <select id="classSelect" class="form-select">
                                <option selected disabled>Choose a class...</option>
                                <?php foreach($teacher_classes as $class): ?>
                                    <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="attendanceDate" class="form-label">Select Date</label>
                            <input type="date" id="attendanceDate" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-primary w-100">Load List</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Sheet: Physics - 1st Year</h6>
                    <span class="text-muted"><?= date('l, F j, Y') ?></span>
                </div>
                <div class="card-body">
                    <form onsubmit="alert('Saving attendance is disabled in this static preview.'); return false;">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle attendance-table">
                                <thead>
                                    <tr>
                                        <th>Roll #</th>
                                        <th>Student Name</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students_for_class as $student): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['roll_number']) ?></td>
                                        <td><?= htmlspecialchars($student['name']) ?></td>
                                        <td class="d-flex justify-content-center gap-3">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="attendance[<?= $student['id'] ?>]" id="present-<?= $student['id'] ?>" value="Present" checked>
                                                <label class="form-check-label text-success" for="present-<?= $student['id'] ?>">Present</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="attendance[<?= $student['id'] ?>]" id="absent-<?= $student['id'] ?>" value="Absent">
                                                <label class="form-check-label text-danger" for="absent-<?= $student['id'] ?>">Absent</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="attendance[<?= $student['id'] ?>]" id="leave-<?= $student['id'] ?>" value="Leave">
                                                <label class="form-check-label text-warning" for="leave-<?= $student['id'] ?>">Leave</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary">Save Attendance</button>
                        </div>
                    </form>
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