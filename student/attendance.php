<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Authenticate the user
if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

$student_name = $_SESSION['student_name'] ?? 'Student';

// =======================================================
// 1. GUARANTEED SAFE STUDENT ID TRANSLATOR
// =======================================================
$current_user_db_id = 0;

// A. Prioritize the known string ID (e.g., ST-001) to fetch the exact users.id
if (!empty($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_id']]);
    $current_user_db_id = $stmt->fetchColumn();
}

// B. Fallback to email if the string ID fails
if (!$current_user_db_id && !empty($_SESSION['student_email'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_email']]);
    $current_user_db_id = $stmt->fetchColumn();
}

// C. Absolute last resort: Check if there's a valid numeric ID
if (!$current_user_db_id && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $current_user_db_id = (int)$_SESSION['user_id'];
}

// =======================================================
// 2. FETCH ATTENDANCE DATA
// =======================================================
$selected_month = $_GET['month'] ?? date('Y-m'); // Default to current month
$monthly_records = [];
$overall_stats = [
    'total' => 0, 'present' => 0, 'absent' => 0, 'leave' => 0, 'late' => 0, 'percentage' => 100
];

if ($current_user_db_id > 0) {
    try {
        // A. Fetch Overall Statistics (All-Time)
        $stmt_stats = $pdo->prepare("
            SELECT 
                COUNT(id) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_days,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days
            FROM student_attendance 
            WHERE student_id = ? AND status IS NOT NULL AND status != ''
        ");
        $stmt_stats->execute([$current_user_db_id]);
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

        if ($stats && $stats['total_days'] > 0) {
            $overall_stats['total'] = (int)$stats['total_days'];
            $overall_stats['present'] = (int)$stats['present_days'];
            $overall_stats['absent'] = (int)$stats['absent_days'];
            $overall_stats['leave'] = (int)$stats['leave_days'];
            $overall_stats['late'] = (int)$stats['late_days'];
            
            // Calculate percentage (Present + Late count as attended)
            $attended = $overall_stats['present'] + $overall_stats['late'];
            $overall_stats['percentage'] = round(($attended / $overall_stats['total']) * 100);
        }

        // B. Fetch Daily Records for the Selected Month (Using LIKE for safety against VARCHAR dates)
        $stmt_monthly = $pdo->prepare("
            SELECT attendance_date, status 
            FROM student_attendance 
            WHERE student_id = ? AND attendance_date LIKE ?
            ORDER BY attendance_date DESC
        ");
        // Appending '%' matches everything that starts with '2026-06', bypassing formatting rules
        $stmt_monthly->execute([$current_user_db_id, $selected_month . '%']);
        $monthly_records = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "A database error occurred while fetching your attendance.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df; --secondary: #858796; --success: #1cc88a; 
            --info: #36b9cc; --warning: #f6c23e; --danger: #e74a3b; 
            --light-bg: #f8f9fc;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .stat-card { border-left: 4px solid; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .border-left-primary { border-left-color: var(--primary); }
        .border-left-success { border-left-color: var(--success); }
        .border-left-danger { border-left-color: var(--danger); }
        .border-left-warning { border-left-color: var(--warning); }
        
        .progress-bar-custom {
            height: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 fw-bold"><i class="fas fa-user-check me-2 text-primary"></i>My Attendance</h1>
                </div>

                <!-- Overall Stats Row -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-primary shadow-sm h-100 py-2 border-0">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Overall Attendance</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $overall_stats['percentage'] ?>%</div>
                                        <div class="progress progress-bar-custom mt-2">
                                            <div class="progress-bar <?= $overall_stats['percentage'] >= 75 ? 'bg-success' : ($overall_stats['percentage'] >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                                 role="progressbar" style="width: <?= $overall_stats['percentage'] ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-percentage fa-2x text-gray-300 opacity-50"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-success shadow-sm h-100 py-2 border-0">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Presents</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $overall_stats['present'] ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300 opacity-50"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-danger shadow-sm h-100 py-2 border-0">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Absents</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $overall_stats['absent'] ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-calendar-times fa-2x text-gray-300 opacity-50"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card border-left-warning shadow-sm h-100 py-2 border-0">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Leaves / Late</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $overall_stats['leave'] ?> / <?= $overall_stats['late'] ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300 opacity-50"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Details -->
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row align-items-center justify-content-between border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0"><i class="far fa-calendar-alt me-2"></i>Monthly Attendance Record</h6>
                        
                        <form method="GET" class="d-flex align-items-center">
                            <label class="me-2 fw-bold text-secondary small mb-0">Select Month:</label>
                            <input type="month" name="month" class="form-control form-control-sm border-primary" value="<?= htmlspecialchars($selected_month) ?>" onchange="this.form.submit()">
                        </form>
                    </div>
                    
                    <div class="card-body p-0">
                        <?php if (empty($monthly_records)): ?>
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-clipboard-list fa-3x mb-3 opacity-25"></i>
                                <h5>No attendance records found for <?= date('F Y', strtotime($selected_month . '-01')) ?></h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Date</th>
                                            <th>Day</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthly_records as $record): 
                                            $date_obj = new DateTime($record['attendance_date']);
                                            $status = $record['status'];
                                            
                                            // Determine badge color
                                            $badge_class = 'bg-secondary';
                                            if ($status === 'Present') $badge_class = 'bg-success';
                                            elseif ($status === 'Absent') $badge_class = 'bg-danger';
                                            elseif ($status === 'Leave') $badge_class = 'bg-warning text-dark';
                                            elseif ($status === 'Late') $badge_class = 'bg-info text-dark';
                                        ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-dark"><?= $date_obj->format('d M Y') ?></td>
                                                <td class="text-muted"><?= $date_obj->format('l') ?></td>
                                                <td>
                                                    <span class="badge <?= $badge_class ?> px-3 py-2 rounded-pill shadow-sm">
                                                        <?= htmlspecialchars($status) ?>
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
        </div>
        
        <footer class="footer mt-auto py-3 bg-white border-top">
            <div class="container text-center text-muted small">
                 &copy; MSST LMS <?= date('Y') ?> | Student Portal
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    </script>
</body>
</html>