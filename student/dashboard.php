<?php
session_start();
require_once __DIR__ . '/../config/db_config.php'; // Adjust path if needed

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_name = $_SESSION['student_name'] ?? 'Student';
$is_admin = $_SESSION['is_admin'] ?? false;

// =======================================================
// 1. ROBUST STUDENT ID TRANSLATOR (The Core Fix)
// =======================================================
$raw_session_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? $_SESSION['id'] ?? null;
$student_user_id = 0;

if ($raw_session_id) {
    if (is_numeric($raw_session_id)) { 
        $student_user_id = (int)$raw_session_id; 
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
        $stmt->execute([$raw_session_id]);
        $student_user_id = $stmt->fetchColumn() ?: 0;
    }
}
if (!$student_user_id) {
    $email = $_SESSION['student_email'] ?? $_SESSION['email'] ?? '';
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $student_user_id = $stmt->fetchColumn() ?: 0;
    }
}

// =======================================================
// 2. DYNAMIC DATA FETCHING
// =======================================================
try {
    // A. Get Student's Class and Program (Section)
    $stmt_details = $pdo->prepare("SELECT class_id, section_id FROM student_details WHERE user_id = ?");
    $stmt_details->execute([$student_user_id]);
    $student_details = $stmt_details->fetch(PDO::FETCH_ASSOC);
    
    $class_id = $student_details['class_id'] ?? 0;
    $program_id = $student_details['section_id'] ?? 0;

    // B. Count Enrolled Subjects (From new dynamic mappings)
    $stmt_subs = $pdo->prepare("SELECT COUNT(*) FROM program_subjects WHERE program_id = ?");
    $stmt_subs->execute([$program_id]);
    $courses_count = $stmt_subs->fetchColumn() ?: 0;

    // C. Calculate Attendance Percentage
    $stmt_att = $pdo->prepare("
        SELECT 
            COUNT(id) as total_days,
            SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present_days 
        FROM student_attendance 
        WHERE student_id = ? AND status IS NOT NULL AND status != ''
    ");
    $stmt_att->execute([$student_user_id]);
    $att_data = $stmt_att->fetch(PDO::FETCH_ASSOC);
    
    $total_days = $att_data['total_days'] ? (int)$att_data['total_days'] : 0;
    $present_days = $att_data['present_days'] ? (int)$att_data['present_days'] : 0;
    $attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100) . '%' : 'No Data';

    // D. Fetch Fee Status & Format the Month
    $stmt_fees = $pdo->prepare("SELECT * FROM fee_slips WHERE student_id = ? ORDER BY id DESC LIMIT 10");
    $stmt_fees->execute([$student_user_id]);
    $fee_slips = $stmt_fees->fetchAll(PDO::FETCH_ASSOC);
    
    $fees_status = "No Dues";
    $fees_color = "text-success";
    $fees_border = "var(--success)";
    $fee_link = "fee-detail.php"; // Fallback link
    $upcoming_events = [];
    
    // Find if there is an unpaid slip
    $unpaid_slip = null;
    foreach($fee_slips as $slip) {
        if (in_array($slip['status'], ['Unpaid', 'Pending', 'Partially Paid'])) {
            $unpaid_slip = $slip;
            break;
        }
    }
    
    $active_slip = $unpaid_slip ?: ($fee_slips[0] ?? null);
    
    if ($active_slip) {
        $fee_link = "fee-detail.php?id=" . $active_slip['id']; // Direct link to specific slip
        
        // Smart Month Formatting (Checks for billing_month column, otherwise extracts from due_date)
        $month_str = !empty($active_slip['billing_month']) 
            ? $active_slip['billing_month'] 
            : date('F Y', strtotime($active_slip['due_date'] ?? 'now'));
            
        if ($unpaid_slip) {
            $fees_status = "Unpaid (" . htmlspecialchars($month_str) . ")";
            $fees_color = "text-danger";
            $fees_border = "var(--danger)";
            
            // Safely grab the amount regardless of what the column is named in your DB
            $fee_amount = $unpaid_slip['total_amount'] ?? $unpaid_slip['total_fee'] ?? $unpaid_slip['amount'] ?? $unpaid_slip['payable_amount'] ?? 0;
            
            // Add unpaid fee to upcoming events timeline
            $upcoming_events[] = [
                'type' => 'fee',
                'title' => 'Fee Payment Pending',
                'time' => 'Due: ' . date('d M Y', strtotime($unpaid_slip['due_date'])),
                'details' => 'Amount: PKR ' . number_format((float)$fee_amount, 2) . ' (' . htmlspecialchars($month_str) . ')',
                'icon' => 'fa-file-invoice-dollar',
                'color' => 'text-danger',
                'raw_date' => $unpaid_slip['due_date']
            ];
        } else {
            $fees_status = "Paid (" . htmlspecialchars($month_str) . ")";
        }
    }

    // E. Fetch Pending Assignments & Upcoming Assessments
    $assignments_due = 0;
    if ($class_id > 0) {
        // Count total pending assignments specifically (scoped to this student's own section)
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM assessments WHERE class_id = ? AND IFNULL(section_id, 0) = ? AND assessment_type = 'Assignment' AND assessment_date >= CURRENT_DATE");
        $stmt_count->execute([$class_id, (int)$program_id]);
        $assignments_due = $stmt_count->fetchColumn() ?: 0;
        
        // Fetch all recent and upcoming assessments for timeline (scoped to this student's own section)
        $stmt_assess = $pdo->prepare("
            SELECT * FROM assessments 
            WHERE class_id = ? AND IFNULL(section_id, 0) = ? AND assessment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY)
            ORDER BY assessment_date ASC LIMIT 10
        ");
        $stmt_assess->execute([$class_id, (int)$program_id]);
        $assessments = $stmt_assess->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($assessments as $ass) {
            $icon = 'fa-file-alt';
            $color = 'text-primary';
            if ($ass['assessment_type'] === 'Exam') { $icon = 'fa-laptop-code'; $color = 'text-danger'; }
            elseif ($ass['assessment_type'] === 'Test') { $icon = 'fa-edit'; $color = 'text-info'; }

            $upcoming_events[] = [
                'type' => 'assessment',
                'title' => htmlspecialchars($ass['subject_name']) . ' ' . htmlspecialchars($ass['assessment_type']),
                'time' => date('d M Y', strtotime($ass['assessment_date'])),
                'details' => htmlspecialchars($ass['title']) . ' (' . floatval($ass['total_marks']) . ' Marks)',
                'icon' => $icon,
                'color' => $color,
                'raw_date' => $ass['assessment_date']
            ];
        }
    }

    // Sort upcoming events strictly by date (closest events first)
    usort($upcoming_events, function($a, $b) { return strtotime($a['raw_date']) - strtotime($b['raw_date']); });
    $upcoming_events = array_slice($upcoming_events, 0, 5);

} catch (Exception $e) {
    // Graceful Fail
    $courses_count = 0; $assignments_due = 0; $attendance_percentage = "Error";
    $fees_status = "System Error"; $fees_color = "text-secondary"; $fees_border = "var(--secondary)";
    $upcoming_events = []; $fee_link = "#";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($student_name) ?> | Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df; --secondary: #858796; --success: #1cc88a; 
            --info: #36b9cc; --warning: #f6c23e; --danger: #e74a3b; 
            --light-bg: #f8f9fc; --accent: #4e73df;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .welcome-message {
            background: linear-gradient(45deg, var(--primary), #2e59d9);
            color: white; padding: 2.5rem; border-radius: 0.75rem;
            margin-bottom: 2rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
        .stat-card { border-left: 5px solid var(--primary); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.1); }
        .upcoming-list .list-group-item { border: 0; border-left: 5px solid #e9ecef; margin-bottom: 5px; border-radius: 4px; }
        .upcoming-list .list-group-item:hover { background-color: #f8f9fa; }
        .clickable-card { text-decoration: none; color: inherit; display: block; }
        .clickable-card:hover { text-decoration: none; color: inherit; }
    </style>
</head>
<body>

    <?php include 'sidebar.php' ?>

    <div id="main-content">
        <?php include 'header.php' ?>

        <div class="content-wrapper p-4">
            <div class="container-fluid">

                <div class="welcome-message">
                    <h1 class="h2 font-weight-bold mb-1">Welcome Back, <?= htmlspecialchars($student_name) ?>!</h1>
                    <p class="lead mb-0"><i class="fas fa-graduation-cap me-2"></i> Here's a quick look at your academic progress.</p>
                </div>

                <h2 class="h4 mb-4 text-gray-800"><i class="fas fa-chart-pie me-2 text-primary"></i> Quick Stats</h2>
                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--primary);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Enrolled Subjects</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $courses_count ?></div>
                                </div>
                                <i class="fas fa-book-open fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--warning);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Assignments</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $assignments_due ?></div>
                                </div>
                                <i class="fas fa-clipboard-list fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--info);">
                            <div class="card-body d-flex align-items-center">
                                <div>
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Attendance</div>
                                    <div class="h4 mb-0 font-weight-bold text-gray-800">
                                        <span class="<?= ($attendance_percentage !== 'No Data' && intval($attendance_percentage) < 75) ? 'text-danger' : '' ?>"><?= $attendance_percentage ?></span>
                                    </div>
                                </div>
                                <i class="fas fa-user-check fa-3x text-gray-300 ms-auto"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <a href="<?= htmlspecialchars($fee_link) ?>" class="clickable-card h-100">
                            <div class="card stat-card shadow-sm h-100" style="border-left-color: <?= $fees_border ?>; cursor: pointer;">
                                <div class="card-body d-flex align-items-center">
                                    <div>
                                        <div class="text-xs font-weight-bold text-uppercase mb-1 <?= $fees_color ?>">Fee Status</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800 <?= $fees_color ?>" style="line-height: 1.2;"><?= htmlspecialchars($fees_status) ?></div>
                                        <small class="text-muted"><i class="fas fa-external-link-alt"></i> Click to view slip</small>
                                    </div>
                                    <i class="fas fa-file-invoice-dollar fa-3x text-gray-300 ms-auto"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-header py-3 bg-white border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-bell me-2"></i>Upcoming Deadlines & Events</h6>
                    </div>
                    <div class="card-body bg-light">
                        <div class="list-group list-group-flush upcoming-list">
                            <?php if (!empty($upcoming_events)): ?>
                                <?php foreach ($upcoming_events as $event): ?>
                                    <div class="list-group-item px-4 py-3 shadow-sm bg-white">
                                        <div class="d-flex w-100 align-items-center">
                                            <div class="me-4 text-center" style="width: 40px;">
                                                <i class="fas <?= $event['icon'] ?> fa-2x <?= $event['color'] ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <h6 class="mb-1 font-weight-bold text-dark"><?= $event['title'] ?></h6>
                                                    <span class="badge bg-light text-dark border <?= $event['color'] ?> border-opacity-25 px-2 py-1">
                                                        <i class="far fa-clock me-1"></i><?= $event['time'] ?>
                                                    </span>
                                                </div>
                                                <p class="mb-0 text-muted small mt-1"><?= $event['details'] ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center p-5 bg-white rounded shadow-sm border border-light">
                                    <i class="fas fa-calendar-check fa-4x text-success mb-3 opacity-50"></i>
                                    <h5 class="fw-bold text-dark">You're all caught up!</h5>
                                    <p class="text-muted mb-0">No upcoming assignments, exams, or fee deadlines on your schedule right now.</p>
                                </div>
                            <?php endif; ?>
                        </div>
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
    </script>
</body>
</html>
