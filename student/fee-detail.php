<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

$student_user_id_string = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Get numeric student ID and details
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.user_id_string, u.full_name, u.email, u.status,
               sd.class_id, sd.section_id, sd.fee_category,
               c.class_name, s.section_name
        FROM users u
        LEFT JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN classes c ON sd.class_id = c.id
        LEFT JOIN sections s ON sd.section_id = s.id
        WHERE u.user_id_string = ?
    ");
    $stmt->execute([$student_user_id_string]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        die("Student not found. Please contact administration.");
    }
    
    $student_id = $student['id'];
    $student_name = $student['full_name'];
    
} catch (Exception $e) {
    die("Database error. Please try again later.");
}

$view_mode = $_GET['view'] ?? 'list';
$slip_no = $_GET['id'] ?? '';


// --- PHP VOUCHER RENDER FUNCTION (MODERN MSST LAYOUT) ---
function render_voucher_copy($copy_type, $data) {
    $components = $data['fee_components'] ?? [];
    $total_fee = 0;
    $breakdownHtml = '';
    
    if (!empty($components)) {
        foreach ($components as $component) {
            $amt = (float)$component['amount'];
            $total_fee += $amt;
            $name = htmlspecialchars($component['name']);
            $breakdownHtml .= "
                <tr>
                    <td>{$name}</td>
                    <td class=\"text-end\">" . number_format($amt, 0) . "</td>
                </tr>
            ";
        }
    } else {
        $breakdownHtml = '<tr><td colspan="2" class="text-center text-muted">No components found</td></tr>';
    }
    
    $recordAmount = $data['net_payable_before_due_raw'] ?? $total_fee;
    
    $installmentBadge = '';
    if (preg_match('/-(\d+)$/', $data['slip_no'], $matches)) {
        if (intval($matches[1]) < 10) {
            $installmentBadge = '<div style="background-color: #ffc107; text-align: center; font-weight: 900; font-size: 11px; padding: 5px; color: #000; letter-spacing: 1px;">INSTALLMENT ' . $matches[1] . '</div>';
        }
    }

    $paidWatermark = '';
    $paidDateHtml = '';
    if ($data['status'] === 'Paid') {
        $paidWatermark = '<div class="mv-paid-stamp">PAID</div>';
        $paidOnStr = !empty($data['paid_on']) ? htmlspecialchars($data['paid_on']) : 'N/A';
        $paidDateHtml = '<div class="mv-total-row" style="color: #198754; margin-top: 4px;"><span>Paid On:</span><strong>' . $paidOnStr . '</strong></div>';
    }
    ?>
    <div class="modern-voucher">
        <?= $paidWatermark ?>
        <div class="mv-header">
            <img src="<?= htmlspecialchars($data['logo_path']) ?>" alt="MSST Logo" class="mv-logo">
            <div class="mv-school-text">
                <div class="mv-school-name">Muhaddisa School of Science & Technology</div>
                <div class="mv-school-address">Kushmara Toq, Near Fatima Jinnah Girls HSS, Quaidabad Skardu<br>0317-9174495 , +92 3554201394 | www.msstskardu.com</div>
            </div>
            <div class="mv-copy-badge">
                <span class="mv-fbise">Affiliated with FBISE</span>
                <?= htmlspecialchars($copy_type) ?>
            </div>
        </div>
        
        <div class="mv-bank-banner" style="line-height: 1.4; padding: 6px 4px;">
            Soneri Bank Main Branch Skardu<br>
            Title: Muhaddisa School of Science and Technology<br>
            Account: 003320015592867
        </div>
        
        <?= $installmentBadge ?>
        
        <div class="mv-student-info">
            <div class="mv-info-box" style="grid-column: span 2;">
                <div class="mv-info-label">Student Name</div>
                <div class="mv-info-value" style="font-size: 0.9rem;"><?= htmlspecialchars($data['student_name']) ?></div>
            </div>
            <div class="mv-info-box">
                <div class="mv-info-label">Father's Name</div>
                <div class="mv-info-value"><?= htmlspecialchars($data['father_name'] ?? 'N/A') ?></div>
            </div>
            <div class="mv-info-box">
                <div class="mv-info-label">Student ID</div>
                <div class="mv-info-value"><?= htmlspecialchars($data['student_id_display']) ?></div>
            </div>
            <div class="mv-info-box">
                <div class="mv-info-label">Class & Section</div>
                <div class="mv-info-value"><?= htmlspecialchars($data['class_section']) ?>-<?= htmlspecialchars($data['section_alpha']) ?></div>
            </div>
            <div class="mv-info-box">
                <div class="mv-info-label">Program</div>
                <div class="mv-info-value"><?= htmlspecialchars($data['program']) ?></div>
            </div>
            <div class="mv-info-box">
                <div class="mv-info-label">Voucher No.</div>
                <div class="mv-info-value"><?= htmlspecialchars($data['slip_no']) ?></div>
            </div>
            <div class="mv-info-box">
                <div class="mv-info-label">Fee Month / Issued</div>
                <div class="mv-info-value"><?= htmlspecialchars($data['fee_period']) ?> &nbsp;|&nbsp; <?= htmlspecialchars($data['issue_date']) ?></div>
            </div>
        </div>
        
        <div class="mv-due-banner">
            <span class="mv-due-label">PAYMENT DUE DATE</span>
            <span class="mv-due-date"><?= htmlspecialchars($data['due_date']) ?></span>
        </div>
        
        <table class="mv-fee-table">
            <thead>
                <tr>
                    <th>Fee Description</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?= $breakdownHtml ?>
            </tbody>
        </table>
        
        <div class="mv-totals">
            <div class="mv-total-row">
                <span>Payable before Due Date:</span>
                <strong>PKR <?= number_format($recordAmount, 0) ?></strong>
            </div>
            <div class="mv-total-row mv-warning">
                <span>Payable after Due Date (Incl. Late Fee):</span>
                <strong>PKR <?= number_format($recordAmount + 500, 0) ?></strong>
            </div>
            <div class="mv-total-row grand">
                <span>TOTAL AMOUNT:</span>
                <span>PKR <?= number_format($recordAmount, 0) ?></span>
            </div>
            <?= $paidDateHtml ?>
        </div>
        
        <div class="mv-footer">
            <span class="mv-warning">LATE PAYMENT:</span> After due date flat Rs. 500/- will be charged per month. Duplicate fee is Rs 50/-.<br>
            <strong>Note:</strong> Rs 550/- charged as Summer Task if dues aren't paid same month.<br>
            <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #dee2e6; text-align: right; font-style: italic;">To be filled by bank</div>
        </div>
    </div>
    <?php
}

// --- GET REAL FEE DATA FROM DATABASE ---
if ($view_mode === 'voucher') {
    if (!empty($slip_no)) {
        try {
            $stmt = $pdo->prepare("
                SELECT fs.*, u.full_name, u.user_id_string, sd.fee_category, sd.father_name,
                       c.class_name, s.section_name
                FROM fee_slips fs
                LEFT JOIN users u ON fs.student_id = u.id
                LEFT JOIN student_details sd ON u.id = sd.user_id
                LEFT JOIN classes c ON sd.class_id = c.id
                LEFT JOIN sections s ON sd.section_id = s.id
                WHERE fs.slip_no = ? AND fs.student_id = ?
            ");
            $stmt->execute([$slip_no, $student_id]);
            $fee_slip = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($fee_slip) {
                // Fetch dynamic components explicitly
                $components_stmt = $pdo->prepare("SELECT component_name as name, amount FROM fee_slip_components WHERE slip_no = ?");
                $components_stmt->execute([$slip_no]);
                $fee_components = $components_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $voucher_data = [
                    'student_name' => $fee_slip['full_name'],
                    'father_name' => $fee_slip['father_name'] ?: 'N/A',
                    'student_id_display' => $fee_slip['user_id_string'],
                    'class_section' => $fee_slip['class_name'] ?? 'N/A',
                    'section_alpha' => $fee_slip['section_name'] ?? 'N/A',
                    'program' => $fee_slip['fee_category'] ?? 'N/A',
                    'slip_no' => $fee_slip['slip_no'],
                    'issue_date' => date('d/m/Y', strtotime($fee_slip['generated_date'])),
                    'validity_date' => date('d/m/Y', strtotime($fee_slip['due_date'] . ' +10 days')),
                    'due_date' => date('d/m/Y', strtotime($fee_slip['due_date'])),
                    'fee_period' => date('M Y', strtotime($fee_slip['month_year'])),
                    'fee_components' => $fee_components,
                    'net_payable_before_due_raw' => $fee_slip['amount'],
                    'late_payment_daily_charge' => "25",
                    'duplicate_fee_charge' => "50",
                    'summer_task_note_amount' => "550",
                    'school_name' => "Muhaddisa School of Science and Technology",
                    'school_address_line1' => "Head Office : Kushmara Toq, Near Fatima Jinnah Girls HSS, Quaidabad Skardu",
                    'school_contact_line' => "Contact: Cell# 0317-9174495 , 03555851351 , 03554201394 , +92 3554201394",
                    'school_email_web_line' => "E-mail:fees@msstskardu.com,Web:www.msstskardu.com",
                    'bank_details_line' => "Bank Al Habib : Ghulam Abbas, AC #: 20440981003831012",
                    'website_url' => "www.msstskardu.com",
                    'logo_path' => "../assets/images/msst-logo.png",
                    'status' => $fee_slip['status'],
                    'paid_on' => $fee_slip['paid_on'] ? date('d/m/Y', strtotime($fee_slip['paid_on'])) : '',
                ];
            } else {
                $error_message = "Fee slip not found or you don't have access to it.";
            }
        } catch (Exception $e) {
            $error_message = "Error loading fee slip: " . $e->getMessage();
        }
    } else {
        $error_message = "No fee slip specified.";
    }
} else {
    // List view logic
    try {
        $stmt = $pdo->prepare("
            SELECT fs.*, c.class_name, s.section_name
            FROM fee_slips fs
            LEFT JOIN student_details sd ON fs.student_id = sd.user_id
            LEFT JOIN classes c ON sd.class_id = c.id
            LEFT JOIN sections s ON sd.section_id = s.id
            WHERE fs.student_id = ?
            ORDER BY fs.month_year DESC, fs.generated_date DESC
        ");
        $stmt->execute([$student_id]);
        $fee_slips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($fee_slips)) {
            $no_fees_message = true;
        } else {
            $total_paid = 0; $total_due = 0; $next_due_date = null; $all_paid = true;
            foreach ($fee_slips as $slip) {
                if ($slip['status'] === 'Paid') {
                    $total_paid += $slip['amount'];
                } else {
                    $total_due += $slip['amount'];
                    $all_paid = false;
                    if (!$next_due_date || strtotime($slip['due_date']) < strtotime($next_due_date)) {
                        $next_due_date = $slip['due_date'];
                    }
                }
            }
            $overall_fee_status = [
                'status' => $all_paid ? 'Paid' : 'Pending',
                'total_paid' => $total_paid,
                'total_due' => $total_due,
                'next_due_date' => $next_due_date
            ];
            
            $installment_plan = [];
            foreach ($fee_slips as $slip) {
                $installment_plan[] = [
                    'id' => $slip['slip_no'],
                    'name' => 'Fee Slip - ' . date('F Y', strtotime($slip['month_year'])),
                    'due_date' => $slip['due_date'],
                    'amount' => $slip['amount'],
                    'status' => $slip['status'],
                    'paid_on' => $slip['paid_on']
                ];
            }
        }
    } catch (Exception $e) { $error_message = "Error loading fee data."; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Details | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary: #4e73df; --success: #1cc88a; --danger: #e74a3b; --light-bg: #f8f9fc; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg); }
        .status-card { border-left: 5px solid; }
        .status-card.paid { border-left-color: var(--success); }
        .status-card.pending { border-left-color: #ffc107; }
        .status-card.unpaid { border-left-color: var(--danger); }
        .table-hover tbody tr:hover { background-color: #f1f3f5; }

        
/* VOUCHER SPECIFIC STYLES */
        .voucher-page-content { background: #e9ecef; padding: 20px; display: flex; justify-content: space-between; gap: 15px; overflow-x: auto; border-radius: 8px; }
        .voucher-page-content > div { flex: 1; min-width: 310px; }
        
        .modern-voucher { position: relative; background: #ffffff; border: 1px solid #dee2e6; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); overflow: hidden; font-family: 'Arial', sans-serif; height: 100%; display: flex; flex-direction: column; }
        .mv-paid-stamp { position: absolute; top: 45%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 3.5rem; font-weight: 900; color: rgba(40, 167, 69, 0.15); border: 5px solid rgba(40, 167, 69, 0.15); padding: 10px 30px; border-radius: 12px; pointer-events: none; z-index: 10; text-transform: uppercase; letter-spacing: 6px; }
        
        .mv-header { display: flex; align-items: center; padding: 12px 15px; border-bottom: 3px solid #906833; background: #f8f9fa; gap: 12px; }
        .mv-logo { width: 45px; height: 45px; object-fit: contain; flex-shrink: 0; }
        .mv-school-text { flex: 1; line-height: 1.2; min-width: 0; }
        .mv-school-name { font-size: 0.75rem; font-weight: 900; color: #906833; text-transform: uppercase; margin-bottom: 2px; }
        .mv-school-address { font-size: 0.6rem; color: #6c757d; }
        
        .mv-copy-badge { background: #906833; color: #fff; font-size: 0.7rem; font-weight: bold; padding: 5px 10px; border-radius: 6px; text-align: center; flex-shrink: 0; }
        .mv-fbise { font-size: 0.55rem; display: block; margin-bottom: 2px; color: #e9ecef; font-weight: normal; letter-spacing: 0.5px;}
        
        .mv-bank-banner { background: #f1f3f5; text-align: center; font-size: 0.7rem; font-weight: bold; padding: 6px; color: #495057; border-bottom: 1px solid #dee2e6; }
        
        .mv-student-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 15px; background: #fff; }
        .mv-info-box { display: flex; flex-direction: column; }
        .mv-info-label { font-size: 0.6rem; color: #868e96; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .mv-info-value { font-size: 0.75rem; color: #212529; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .mv-due-banner { background: #dc3545; color: white; display: flex; justify-content: space-between; align-items: center; padding: 8px 15px; }
        .mv-due-label { font-size: 0.75rem; font-weight: bold; letter-spacing: 1px;}
        .mv-due-date { font-size: 0.95rem; font-weight: 900; }
        
        .mv-fee-table { width: 100%; border-collapse: collapse; margin: 0; }
        .mv-fee-table th { background: #f8f9fa; font-size: 0.65rem; color: #6c757d; padding: 8px 15px; text-align: left; border-top: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; text-transform: uppercase; }
        .mv-fee-table th.text-end { text-align: right; }
        .mv-fee-table td { padding: 6px 15px; font-size: 0.75rem; color: #495057; border-bottom: 1px dashed #e9ecef; }
        .mv-fee-table td.text-end { text-align: right; font-weight: bold; color: #212529; }
        
        .mv-totals { padding: 15px; background: #f8f9fa; border-top: 2px solid #212529; }
        .mv-total-row { display: flex; justify-content: space-between; margin-bottom: 4px; align-items: center; font-size: 0.75rem; color: #495057; }
        .mv-total-row strong { color: #212529; }
        .mv-total-row.grand { font-size: 0.95rem; font-weight: 900; color: #906833; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ced4da; }
        
        .mv-footer { padding: 12px 15px; font-size: 0.65rem; color: #6c757d; line-height: 1.4; background: #fff; margin-top: auto; border-top: 1px solid #dee2e6; }
        .mv-warning { color: #dc3545; font-weight: bold; }

        .print-button-container { margin-bottom: 20px; }

        /* No fees card */
        .no-fees-card { border: 2px dashed #dee2e6; background-color: #f8f9fa; }
        .no-fees-icon { font-size: 4rem; color: #6c757d; }
        .student-info-card { border-left: 4px solid #4e73df; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">

                <?php if ($view_mode === 'voucher'): ?>
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?></div>
                        <div class="text-center mt-4"><a href="fee-detail.php" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i> Back</a></div>
                    <?php else: ?>
                        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="fee-detail.php">Fee Details</a></li><li class="breadcrumb-item active">Fee Voucher</li></ol></nav>
                        <h1 class="h3 mb-4">Fee Voucher</h1>
                        <div class="print-button-container text-start">
                            <button class="btn btn-primary" onclick="printVoucher()"><i class="fas fa-print me-2"></i>Print Vouchers</button>
                            <a href="fee-detail.php" class="btn btn-secondary ms-2"><i class="fas fa-arrow-left me-2"></i>Back to Fee Details</a>
                        </div>
                        <div class="voucher-page-content">
                            <div id="parent-copy"><?php render_voucher_copy("PARENT COPY", $voucher_data); ?></div>
                            <div id="hostel-copy"><?php render_voucher_copy("HOSTEL COPY", $voucher_data); ?></div>
                            <div id="bank-copy"><?php render_voucher_copy("BANK COPY", $voucher_data); ?></div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <h1 class="h3 mb-4">My Fee Details</h1>
                    
                    <div class="card shadow-sm mb-4 student-info-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?= htmlspecialchars($student['full_name']) ?></p>
                                    <p><strong>Student ID:</strong> <?= htmlspecialchars($student['user_id_string']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Class:</strong> <?= htmlspecialchars($student['class_name'] ?? 'Not Assigned') ?>-<?= htmlspecialchars($student['section_name'] ?? 'N/A') ?></p>
                                    <p><strong>Program:</strong> <span class="badge bg-primary"><?= htmlspecialchars($student['fee_category'] ?? 'N/A') ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($no_fees_message)): ?>
                        <div class="card shadow-sm no-fees-card"><div class="card-body p-5 text-center"><div class="mb-4"><i class="fas fa-file-invoice no-fees-icon"></i></div><h3 class="text-muted mb-3">No Fee Slips Generated Yet</h3><p class="text-muted mb-4">Your fee slips will appear here once they are generated by the school administration.</p></div></div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-12 mb-4">
                                <?php 
                                    $status_class = strtolower($overall_fee_status['status']);
                                    $status_icon = ($overall_fee_status['status'] == 'Paid') ? 'fa-check-circle text-success' : 'fa-clock text-warning';
                                    $status_text_class = ($overall_fee_status['status'] == 'Paid') ? 'text-success' : 'text-warning';
                                    $status_title = ($overall_fee_status['status'] == 'Paid') ? 'All Dues Cleared' : 'Payment Pending';
                                ?>
                                <div class="card shadow-sm status-card <?= $status_class ?>">
                                    <div class="card-body p-4"><div class="row align-items-center"><div class="col-md-2 text-center"><i class="fas <?= $status_icon ?>" style="font-size: 4rem;"></i></div><div class="col-md-7"><h4 class="card-title font-weight-bold <?= $status_text_class ?>"><?= $status_title ?></h4><p class="text-muted mb-0">Summary of your account statement.</p><div class="mt-2"><span class="badge bg-success me-2">Total Paid: Rs. <?= number_format($overall_fee_status['total_paid'], 2) ?></span><?php if ($overall_fee_status['total_due'] > 0): ?><span class="badge bg-danger">Total Due: Rs. <?= number_format($overall_fee_status['total_due'], 2) ?></span><?php endif; ?></div></div><div class="col-md-3 text-md-end mt-3 mt-md-0"><h6 class="text-muted mb-1">Next Payment Due</h6><h5 class="font-weight-bold"><?= $overall_fee_status['next_due_date'] ? date('M j, Y', strtotime($overall_fee_status['next_due_date'])) : 'N/A' ?></h5></div></div></div>
                                </div>
                            </div>
                            
                            <div class="col-12"><div class="card shadow-sm"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Fee Slips & History</h6></div><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle">
                                <thead><tr><th>Slip Details</th><th>Month/Year</th><th>Due Date</th><th>Amount</th><th>Status</th><th>Paid On</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($installment_plan as $item): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($item['name']) ?></strong><br><small class="text-muted">Slip #: <?= htmlspecialchars($item['id']) ?></small></td>
                                            <td><?= date('F Y', strtotime(substr($item['id'], 3, 6) . '01')) ?></td>
                                            <td><?= date('M j, Y', strtotime($item['due_date'])) ?></td>
                                            <td>Rs. <?= number_format($item['amount'], 2) ?></td>
                                            <td><span class="badge <?= ($item['status'] == 'Paid') ? 'bg-success' : 'bg-warning text-dark' ?>"><?= htmlspecialchars($item['status']) ?></span></td>
                                            <td><?= $item['paid_on'] ? date('M j, Y', strtotime($item['paid_on'])) : 'Not Paid' ?></td>
                                            <td>
                                                <a href="fee-detail.php?view=voucher&id=<?= $item['id'] ?>" class="btn btn-sm <?= ($item['status'] == 'Paid') ? 'btn-outline-primary' : 'btn-info text-white' ?>"><i class="fas <?= ($item['status'] == 'Paid') ? 'fa-receipt' : 'fa-print' ?> me-1"></i> <?= ($item['status'] == 'Paid') ? 'View Receipt' : 'Print Voucher' ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table></div></div></div></div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            if (window.innerWidth <= 768) { sidebar.classList.toggle('show'); } 
            else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('collapsed'); }
        });
    }

   
    function printVoucher() {
        const parentCopy = document.getElementById('parent-copy').innerHTML;
        const hostelCopy = document.getElementById('hostel-copy').innerHTML;
        const bankCopy = document.getElementById('bank-copy').innerHTML;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
        <html><head><title>Fee Slip - MSST</title>
        <style>
            @page { size: A4 landscape; margin: 8mm; } 
            body { margin: 0; padding: 0; font-family: Arial, sans-serif; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background: #fff; }
            
            .voucher-page { display: flex; flex-direction: row; justify-content: space-between; width: 100%; height: 185mm; box-sizing: border-box; }
            .voucher-col { width: 32.5%; height: 100%; box-sizing: border-box; padding: 3px; } 
            
            .modern-voucher { position: relative !important; background: #ffffff; border: 1.5px solid #000; border-radius: 8px; overflow: hidden; height: 100%; display: flex; flex-direction: column; box-sizing: border-box; }
            .mv-header { display: flex; align-items: center; padding: 8px 10px; border-bottom: 2px solid #000; background: #f8f9fa !important; gap: 8px; }
            .mv-logo { width: 38px; height: 38px; object-fit: contain; flex-shrink: 0; }
            .mv-school-text { flex: 1; line-height: 1.1; min-width: 0; }
            .mv-school-name { font-size: 9px; font-weight: 900; color: #000; text-transform: uppercase; margin-bottom: 2px; }
            .mv-school-address { font-size: 7px; color: #333; }
            .mv-copy-badge { background: #e9ecef !important; color: #000; font-size: 9px; font-weight: bold; padding: 4px 6px; border: 1px solid #000; border-radius: 4px; text-align: center; flex-shrink: 0; width: 85px; box-sizing: border-box;}
            .mv-fbise { font-size: 7px; display: block; margin-bottom: 2px; font-weight: normal; }
            
            .mv-bank-banner { background: #f1f3f5 !important; text-align: center; font-size: 8px; font-weight: bold; padding: 4px; color: #000; border-bottom: 1px solid #000; }
            
            .mv-student-info { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; padding: 8px 10px; border-bottom: 1px dashed #000; }
            .mv-info-box { display: flex; flex-direction: column; }
            .mv-info-label { font-size: 6.5px; color: #555; font-weight: bold; text-transform: uppercase; margin-bottom: 1px; }
            .mv-info-value { font-size: 9px; color: #000; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            
            .mv-due-banner { background: #000 !important; color: white !important; display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; }
            .mv-due-label { font-size: 8.5px; font-weight: bold; letter-spacing: 1px;}
            .mv-due-date { font-size: 11px; font-weight: 900; }
            
            .mv-fee-table { width: 100%; border-collapse: collapse; margin: 0; }
            .mv-fee-table th { background: #f8f9fa !important; font-size: 7px; color: #000; padding: 5px 10px; text-align: left; border-top: 1px solid #000; border-bottom: 1px solid #000; text-transform: uppercase; }
            .mv-fee-table th.text-end { text-align: right; }
            .mv-fee-table td { padding: 4px 10px; font-size: 8.5px; color: #000; border-bottom: 1px dashed #ccc; }
            .mv-fee-table td.text-end { text-align: right; font-weight: bold; }
            
            .mv-totals { padding: 8px 10px; background: #f8f9fa !important; border-top: 1.5px solid #000; }
            .mv-total-row { display: flex; justify-content: space-between; margin-bottom: 3px; align-items: center; font-size: 8px; color: #000; }
            .mv-total-row.grand { font-size: 10.5px; font-weight: 900; margin-top: 5px; padding-top: 5px; border-top: 1px dashed #000; }
            
            .mv-footer { padding: 8px 10px; font-size: 7.5px; color: #000; line-height: 1.3; margin-top: auto; border-top: 1px solid #000; }
            .mv-warning { font-weight: bold; }

            /* BULLETPROOF PRINT POSITIONING FOR WATERMARK */
            .mv-paid-stamp { 
                display: block !important;
                position: absolute !important; 
                top: 45% !important; 
                left: 50% !important; 
                transform: translate(-50%, -50%) rotate(-35deg) !important; 
                font-size: 3.5rem !important; 
                font-weight: 900 !important; 
                color: rgba(40, 167, 69, 0.25) !important; 
                border: 4px solid rgba(40, 167, 69, 0.25) !important; 
                padding: 10px 20px !important; 
                border-radius: 12px !important; 
                pointer-events: none !important; 
                z-index: 999 !important; 
                text-transform: uppercase !important; 
                letter-spacing: 6px !important; 
                text-align: center !important;
                background: transparent !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        </style></head>
        <body><div class="voucher-page"><div class="voucher-col">${parentCopy}</div><div class="voucher-col">${hostelCopy}</div><div class="voucher-col">${bankCopy}</div></div></body></html>
        `);
        printWindow.document.close(); 
        printWindow.focus(); 
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    }
    </script>
</body>
</html>