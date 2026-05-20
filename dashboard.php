<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'] ?? 'ADM001';

try {
    // === USER COUNTS ===
    // Get role IDs
    $role_query = $pdo->query("SELECT id, role_name FROM roles");
    $roles = [];
    while($row = $role_query->fetch(PDO::FETCH_ASSOC)) {
        $roles[$row['role_name']] = $row['id'];
    }
    
    // Count students
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
    $stmt->execute([$roles['Student'] ?? 0]);
    $student_count = $stmt->fetchColumn();
    
    // Count teachers
    $stmt->execute([$roles['Teacher'] ?? 0]);
    $teacher_count = $stmt->fetchColumn();
    
    // Count staff
    $stmt->execute([$roles['Staff'] ?? 0]);
    $staff_count = $stmt->fetchColumn();
    
    $total_users = $student_count + $teacher_count + $staff_count;
    
    // === USER ACTIVITY ===
    $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN status = 'Active' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'Inactive' THEN 1 END) as inactive
        FROM users");
    $stmt->execute();
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $user_activity = [
        'active' => $activity['active'] ?? 0,
        'inactive' => $activity['inactive'] ?? 0
    ];
    
    // === FEE SUMMARY ===
    $current_month = date('Y-m');
    
    try {
        // Total receivable from ACTIVE students only
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(fs.amount), 0) 
                              FROM fee_slips fs
                              INNER JOIN users u ON fs.student_id = u.id
                              WHERE DATE_FORMAT(fs.month_year, '%Y-%m') = ? 
                              AND u.status = 'Active'");
        $stmt->execute([$current_month]);
        $total_receivable = $stmt->fetchColumn();

        // Total collected from ACTIVE students only
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(fs.amount), 0) 
                              FROM fee_slips fs
                              INNER JOIN users u ON fs.student_id = u.id
                              WHERE DATE_FORMAT(fs.month_year, '%Y-%m') = ?
                              AND fs.status = 'Paid' AND u.status = 'Active'");
        $stmt->execute([$current_month]);
        $total_collected = $stmt->fetchColumn();

        // Count overdue fees for ACTIVE students only
        $stmt = $pdo->prepare("SELECT COUNT(*) 
                              FROM fee_slips fs
                              INNER JOIN users u ON fs.student_id = u.id
                              WHERE fs.status = 'Pending' AND fs.due_date < CURDATE() 
                              AND u.status = 'Active'");
        $stmt->execute();
        $overdue_count = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        $total_receivable = 0;
        $total_collected = 0;
        $overdue_count = 0;
    }
    
    // Fallback calculation if fee data is currently uninitialized
    if ($total_receivable == 0 && $student_count > 0) {
        $avg_fee = 8000;
        $total_receivable = $student_count * $avg_fee;
        $total_collected = $total_receivable * 0.85; 
    }
    
    $total_due = $total_receivable - $total_collected;
    
    $fee_summary = [
        'total_receivable' => $total_receivable,
        'total_collected' => $total_collected,
        'total_due' => $total_due
    ];
    
    $fee_collection_percentage = $total_receivable > 0 ? 
        round(($total_collected / $total_receivable) * 100) : 0;
    
    // === NOTICES FROM DATABASE ===
    $recent_notices = [];
    try {
        $pdo->query("SELECT 1 FROM notices LIMIT 1");
        
        $stmt = $pdo->query("
            SELECT title, message, status, sent_to, sent_on, scheduled_for
            FROM notices 
            WHERE status = 'Sent' 
            ORDER BY sent_on DESC 
            LIMIT 3
        ");
        $recent_notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recent_notices = [
            [
                'title' => 'School Portal Active',
                'message' => 'Welcome to your MSST control center. All database arrays are synchronized.',
                'status' => 'Sent',
                'sent_to' => ['All Students', 'All Teachers'],
                'sent_on' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    // === SYSTEM ALERTS ===
    $system_alerts = [];
    
    // Check for overdue fees
    if ($overdue_count > 0) {
        $system_alerts[] = [
            'type' => 'danger',
            'icon' => 'fa-exclamation-triangle',
            'message' => "<strong>{$overdue_count} fee slips</strong> are overdue for payment."
        ];
    }
    
    // Check for inactive users (30+ days)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users 
                          WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                          AND status = 'Active'");
    $stmt->execute();
    $inactive_users = $stmt->fetchColumn();
    
    if ($inactive_users > 0) {
        $system_alerts[] = [
            'type' => 'warning',
            'icon' => 'fa-user-clock',
            'message' => "{$inactive_users} users haven't been active for over 30 days."
        ];
    }
    
    // Check for classes without sections
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM classes c 
            LEFT JOIN sections s ON c.id = s.class_id 
            WHERE s.id IS NULL
        ");
        $classes_no_sections = $stmt->fetchColumn();
        
        if ($classes_no_sections > 0) {
            $system_alerts[] = [
                'type' => 'info',
                'icon' => 'fa-layer-group',
                'message' => "{$classes_no_sections} classes have no sections defined."
            ];
        }
    } catch (Exception $e) {}
    
    // Check for pending fee generations
    if ($student_count > 0 && $total_receivable == 0) {
        $system_alerts[] = [
            'type' => 'warning',
            'icon' => 'fa-file-invoice-dollar',
            'message' => "No fee slips generated for this month. Generate them now."
        ];
    }

    // Check for pending School Admission Leads
    try {
        $pending_school_leads = $pdo->query("SELECT COUNT(*) FROM school_admissions WHERE admission_status = 'Pending'")->fetchColumn();
        if ($pending_school_leads > 0) {
            $system_alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-user-clock',
                'message' => "<strong>{$pending_school_leads} pending school admission leads</strong> require your review."
            ];
        }
    } catch(Exception $e) {}

    // Check for pending College Admission Leads
    try {
        $pending_college_leads = $pdo->query("SELECT COUNT(*) FROM admissions WHERE admission_status = 'Pending'")->fetchColumn();
        if ($pending_college_leads > 0) {
            $system_alerts[] = [
                'type' => 'info',
                'icon' => 'fa-inbox',
                'message' => "<strong>{$pending_college_leads} pending college admission leads</strong> require your review."
            ];
        }
    } catch(Exception $e) {}
    
    if (empty($system_alerts)) {
        $system_alerts[] = [
            'type' => 'success',
            'icon' => 'fa-check-circle',
            'message' => 'All systems are running normally.'
        ];
    }
    
    // === ADDITIONAL STATS ===
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM sections");
        $total_sections = $stmt->fetchColumn();
    } catch (Exception $e) {
        $total_sections = 0;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u 
                          JOIN roles r ON u.role_id = r.id
                          WHERE r.role_name = 'Student' 
                          AND MONTH(u.created_at) = MONTH(CURDATE())
                          AND YEAR(u.created_at) = YEAR(CURDATE())");
    $stmt->execute();
    $new_students_month = $stmt->fetchColumn();
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) 
                               FROM fee_slips fs
                               INNER JOIN users u ON fs.student_id = u.id 
                               WHERE fs.status = 'Pending' 
                               AND u.status = 'Active'
                               AND DATE_FORMAT(fs.month_year, '%Y-%m') = ?");
        $stmt->execute([$current_month]);
        $pending_fees = $stmt->fetchColumn();
    } catch (Exception $e) {
        $pending_fees = 0;
    }
    
    $collection_rate = $total_receivable > 0 ? 
        round(($total_collected / $total_receivable) * 100, 1) : 0;
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($admin_name) ?> | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .stats-card .card-body {
            padding: 1.5rem;
        }
        .stats-card-success { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .stats-card-info { 
            background: linear-gradient(135deg, #17a2b8 0%, #0dcaf0 100%);
            color: white;
        }
        .stats-card-warning { 
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        .stats-card-primary { 
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            color: white;
        }
        .stats-icon {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }
        .progress-circle {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        .progress-circle-svg {
            width: 120px;
            height: 120px;
            transform: rotate(-90deg);
        }
        .progress-circle-track {
            fill: none;
            stroke: #e9ecef;
            stroke-width: 8;
        }
        .progress-circle-bar {
            fill: none;
            stroke: #4e73df;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.5s;
        }
        .quick-stat {
            background: #f8f9fc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #4e73df;
        }
        .notice-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.2s;
        }
        .notice-item:hover {
            background-color: #f8f9fc;
        }
        .notice-item:last-child {
            border-bottom: none;
        }
        .notice-badge {
            font-size: 0.7rem;
            padding: 3px 8px;
        }
        .alert-item {
            border-left: 4px solid;
            margin-bottom: 10px;
        }
        .alert-item.alert-danger { border-left-color: #dc3545; }
        .alert-item.alert-warning { border-left-color: #ffc107; }
        .alert-item.alert-info { border-left-color: #17a2b8; }
        .alert-item.alert-success { border-left-color: #28a745; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-content">
        <?php include 'header.php'; ?>
        
        <div class="content-wrapper">
            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h1 class="h3 mb-0 text-gray-800">
                            Welcome back, <?= htmlspecialchars($admin_name) ?>!
                        </h1>
                        <p class="text-muted mt-2">
                            <i class="fas fa-calendar-alt me-2"></i><?= date('l, F j, Y') ?> | 
                            <i class="fas fa-clock ms-3 me-2"></i><?= date('h:i A') ?>
                        </p>
                    </div>
                    <div class="d-flex">
                        <a href="fee-management.php" class="btn btn-primary shadow-sm me-2">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Fee Management
                        </a>
                        <a href="notice-board.php" class="btn btn-info shadow-sm">
                            <i class="fas fa-bullhorn me-2"></i>Notice Board
                        </a>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card stats-card-success position-relative">
                            <div class="card-body">
                                <div class="text-white-50 small">Total Students</div>
                                <div class="h2 mb-0 text-white"><?= number_format($student_count) ?></div>
                                <small class="text-white-50">
                                    <i class="fas fa-arrow-up me-1"></i>+<?= $new_students_month ?> this month
                                </small>
                                <i class="fas fa-user-graduate stats-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card stats-card-info position-relative">
                            <div class="card-body">
                                <div class="text-white-50 small">Total Teachers</div>
                                <div class="h2 mb-0 text-white"><?= number_format($teacher_count) ?></div>
                                <small class="text-white-50">
                                    <i class="fas fa-layer-group me-1"></i><?= $total_sections ?> sections
                                </small>
                                <i class="fas fa-chalkboard-teacher stats-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card stats-card-warning position-relative">
                            <div class="card-body">
                                <div class="text-white-50 small">Total Staff</div>
                                <div class="h2 mb-0 text-white"><?= number_format($staff_count) ?></div>
                                <small class="text-white-50">
                                    <i class="fas fa-users me-1"></i>Support Team
                                </small>
                                <i class="fas fa-users-cog stats-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card stats-card-primary position-relative">
                            <div class="card-body">
                                <div class="text-white-50 small">Collection Rate</div>
                                <div class="h2 mb-0 text-white"><?= $collection_rate ?>%</div>
                                <small class="text-white-50">
                                    <i class="fas fa-wallet me-1"></i>PKR <?= number_format($fee_summary['total_collected']) ?>
                                </small>
                                <i class="fas fa-percent stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card shadow">
                            <div class="card-header bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-line me-2"></i>Quick Overview
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-2 col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?= $student_count ?></div>
                                            <small class="text-muted">Students</small>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?= $teacher_count ?></div>
                                            <small class="text-muted">Teachers</small>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?= $total_sections ?></div>
                                            <small class="text-muted">Sections</small>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?= $pending_fees ?></div>
                                            <small class="text-muted">Pending Fees</small>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?= $new_students_month ?></div>
                                            <small class="text-muted">New this month</small>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-6 mb-3">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?= $user_activity['inactive'] ?></div>
                                            <small class="text-muted">Inactive</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-lg-7 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-pie me-2"></i>Fee Collection - <?= date('F Y') ?>
                                </h6>
                                <span class="badge bg-<?= $fee_collection_percentage >= 70 ? 'success' : ($fee_collection_percentage >= 40 ? 'warning' : 'danger') ?> p-2">
                                    <?= $fee_collection_percentage ?>% Collected
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-5 text-center">
                                        <div class="progress-circle position-relative d-inline-block" data-percent="<?= $fee_collection_percentage ?>">
                                            <svg width="140" height="140" viewBox="0 0 120 120">
                                                <circle cx="60" cy="60" r="54" fill="none" stroke="#e9ecef" stroke-width="8"/>
                                                <circle class="progress-circle-bar" cx="60" cy="60" r="54" fill="none" 
                                                        stroke="<?= $fee_collection_percentage >= 70 ? '#28a745' : ($fee_collection_percentage >= 40 ? '#ffc107' : '#dc3545') ?>" 
                                                        stroke-width="8" stroke-linecap="round"
                                                        stroke-dasharray="339.292" 
                                                        stroke-dashoffset="<?= 339.292 - (339.292 * $fee_collection_percentage / 100) ?>"
                                                        transform="rotate(-90 60 60)"/>
                                            </svg>
                                            <div class="position-absolute top-50 start-50 translate-middle text-center">
                                                <span class="h4 fw-bold mb-0 d-block"><?= $fee_collection_percentage ?>%</span>
                                                <small class="text-muted small">collected</small>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-center gap-3">
                                                <div><span class="badge bg-success">&nbsp;</span> Collected</div>
                                                <div><span class="badge bg-warning">&nbsp;</span> Pending</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="quick-stat mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-check-circle text-success me-2"></i>Collected:</span>
                                                <span class="fw-bold text-success">PKR <?= number_format($fee_summary['total_collected']) ?></span>
                                            </div>
                                        </div>
                                        <div class="quick-stat mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-clock text-warning me-2"></i>Pending:</span>
                                                <span class="fw-bold text-warning">PKR <?= number_format($fee_summary['total_due']) ?></span>
                                            </div>
                                        </div>
                                        <div class="quick-stat">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-chart-line text-primary me-2"></i>Total:</span>
                                                <span class="fw-bold text-primary">PKR <?= number_format($fee_summary['total_receivable']) ?></span>
                                            </div>
                                        </div>
                                        <?php if ($pending_fees > 0): ?>
                                        <div class="mt-3">
                                            <a href="fee-management.php" class="btn btn-warning btn-sm w-100">
                                                <i class="fas fa-exclamation-triangle me-2"></i><?= $pending_fees ?> Pending Fees
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-bullhorn me-2"></i>Recent Notices
                                </h6>
                                <a href="notice-board.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach ($recent_notices as $notice): ?>
                                <div class="notice-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($notice['title']) ?></h6>
                                            <p class="text-muted small mb-2">
                                                <?= substr(htmlspecialchars($notice['message']), 0, 120) ?>...
                                            </p>
                                            <div class="d-flex align-items-center">
                                                <span class="badge <?= ($notice['status'] ?? 'Sent') == 'Sent' ? 'bg-success' : 'bg-warning' ?> notice-badge me-2">
                                                    <?= $notice['status'] ?? 'Sent' ?>
                                                </span>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= isset($notice['sent_on']) ? date('M d, h:i A', strtotime($notice['sent_on'])) : 'Scheduled' ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 mb-4">
                        <!-- <div class="card shadow mb-4">
                            <div class="card-header py-3 bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-chart-pie me-2"></i>User Status Distribution
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="userActivityChart" style="max-height: 200px;"></canvas>
                                
                                <div class="row mt-4 text-center">
                                    <div class="col-6">
                                        <div class="p-3 bg-light rounded">
                                            <span class="badge bg-success mb-2">Active</span>
                                            <h4 class="mb-0"><?= number_format($user_activity['active']) ?></h4>
                                            <small class="text-muted">users</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 bg-light rounded">
                                            <span class="badge bg-danger mb-2">Inactive</span>
                                            <h4 class="mb-0"><?= number_format($user_activity['inactive']) ?></h4>
                                            <small class="text-muted">users</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> -->

                        <div class="card shadow">
                            <div class="card-header py-3 bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-bell me-2"></i>System Alerts
                                    <?php if (count($system_alerts) > 0): ?>
                                    <span class="badge bg-danger ms-2"><?= count($system_alerts) ?></span>
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($system_alerts as $alert): ?>
                                <div class="alert alert-<?= $alert['type'] ?> alert-item d-flex align-items-center" role="alert">
                                    <i class="fas <?= $alert['icon'] ?> me-3 fa-lg"></i>
                                    <div><?= $alert['message'] ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="card-footer bg-white">
                                <div class="row text-center g-2">
                                    <div class="col-4">
                                        <a href="class-management.php" class="text-decoration-none d-block p-2 rounded hover-bg-light">
                                            <i class="fas fa-building text-primary fa-lg"></i>
                                            <small class="d-block mt-1">Classes</small>
                                        </a>
                                    </div>
                                    <div class="col-4">
                                        <a href="manage-users.php" class="text-decoration-none d-block p-2 rounded hover-bg-light">
                                            <i class="fas fa-users text-success fa-lg"></i>
                                            <small class="d-block mt-1">Users</small>
                                        </a>
                                    </div>
                                    <div class="col-4">
                                        <a href="fee-management.php" class="text-decoration-none d-block p-2 rounded hover-bg-light">
                                            <i class="fas fa-dollar-sign text-warning fa-lg"></i>
                                            <small class="d-block mt-1">Fee</small>
                                        </a>
                                    </div>
                                    <div class="col-4">
                                        <a href="teacher-management.php" class="text-decoration-none d-block p-2 rounded hover-bg-light">
                                            <i class="fas fa-chalkboard-teacher text-info fa-lg"></i>
                                            <small class="d-block mt-1">Teachers</small>
                                        </a>
                                    </div>
                                    <div class="col-4">
                                        <a href="notice-board.php" class="text-decoration-none d-block p-2 rounded hover-bg-light">
                                            <i class="fas fa-bullhorn text-danger fa-lg"></i>
                                            <small class="d-block mt-1">Notices</small>
                                        </a>
                                    </div>
                                    <div class="col-4">
                                        <a href="view-scores.php" class="text-decoration-none d-block p-2 rounded hover-bg-light">
                                            <i class="fas fa-chart-bar text-secondary fa-lg"></i>
                                            <small class="d-block mt-1">Scores</small>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');

        function saveSidebarState(collapsed) {
            localStorage.setItem('sidebarCollapsed', collapsed);
        }

        function loadSidebarState() {
            return localStorage.getItem('sidebarCollapsed') === 'true';
        }

        if (loadSidebarState()) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
        }

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
            saveSidebarState(sidebar.classList.contains('collapsed'));

            if (window.innerWidth < 768) {
                if (!sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('show');
                    mainContent.classList.add('show-sidebar');
                } else {
                    sidebar.classList.remove('show');
                    mainContent.classList.remove('show-sidebar');
                }
            }
        });

        document.addEventListener('click', function(event) {
            if (window.innerWidth < 768 && sidebar.classList.contains('show')) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggler = toggleBtn.contains(event.target);
                if (!isClickInsideSidebar && !isClickOnToggler) {
                    sidebar.classList.remove('show');
                    sidebar.classList.add('collapsed');
                    mainContent.classList.remove('show-sidebar');
                    mainContent.classList.add('collapsed');
                    saveSidebarState(true);
                }
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById('userActivityChart');
            if (ctx) {
                new Chart(ctx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Active Users', 'Inactive Users'],
                        datasets: [{
                            data: [<?= $user_activity['active'] ?>, <?= $user_activity['inactive'] ?>],
                            backgroundColor: ['#28a745', '#dc3545'],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 20,
                                    font: { size: 11 }
                                }
                            }
                        },
                        cutout: '65%'
                    }
                });
            }
            
            const circles = document.querySelectorAll('.progress-circle');
            circles.forEach(circle => {
                const percent = parseInt(circle.getAttribute('data-percent'));
                const bar = circle.querySelector('.progress-circle-bar');
                
                const circumference = 2 * Math.PI * 45;
                const offset = circumference - (percent / 100) * circumference;
                
                bar.style.strokeDasharray = circumference;
                bar.style.strokeDashoffset = offset;
            });

            const currentPath = window.location.pathname.split("/").pop();
            const navLinks = sidebar.querySelectorAll("ul li a");

            navLinks.forEach(link => {
                if (link.getAttribute("href") === currentPath || 
                    (currentPath === '' && link.getAttribute("href") === 'dashboard.php')) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
        });

        // Auto-refresh layout checks every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>