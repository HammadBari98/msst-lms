<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
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

// --- PHP VOUCHER RENDER FUNCTION (DYNAMIC LIST LAYOUT) ---
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
                    <td class=\"text-end\" style=\"text-align: right;\">" . number_format($amt, 0) . "</td>
                </tr>
            ";
        }
    } else {
        $breakdownHtml = '<tr><td colspan="2" class="text-center">No components found</td></tr>';
    }
    
    $recordAmount = $data['net_payable_before_due_raw'] ?? $total_fee;
    
    $installmentBadge = '';
    if (preg_match('/-(\d+)$/', $data['slip_no'], $matches)) {
        if (intval($matches[1]) < 10) {
            $installmentBadge = '
            <div style="background-color: #ffc107; text-align: center; font-weight: bold; font-size: 11px; padding: 4px; margin-bottom: 6px; border: 1px solid #000; letter-spacing: 1px; color: #000;">
                INSTALLMENT ' . $matches[1] . '
            </div>';
        }
    }
    ?>
    <div class="voucher-instance">
        <div class="voucher-container">
            <div class="header-section">
                <div class="logo-container">
                    <img src="<?= htmlspecialchars($data['logo_path']) ?>" alt="School Logo" class="logo">
                    <div class="school-info">
                        <strong><?= htmlspecialchars($data['school_name']) ?></strong><br>
                        <?= htmlspecialchars($data['school_address_line1']) ?><br>
                        <?= htmlspecialchars($data['school_contact_line']) ?><br>
                        <?= htmlspecialchars($data['school_email_web_line']) ?>
                    </div>
                </div>
                <div class="affiliated-copy-type">
                    <span>Affiliated with FBISE</span><br>
                    <span class="copy-type-label"><?= htmlspecialchars($copy_type) ?></span>
                </div>
            </div>
            
            <div class="bank-details">
                <?= htmlspecialchars($data['bank_details_line']) ?>
            </div>
            
            <?= $installmentBadge ?>
            
            <table class="student-info-table" style="table-layout: fixed; width: 100%;">
                <tr>
                    <th style="width: 22%;">NAME</th>
                    <td colspan="3" class="student-name-value" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($data['student_name']) ?></td>
                </tr>
                <tr>
                    <th style="width: 22%;">ID</th>
                    <td class="value-cell" style="width: 28%;"><?= htmlspecialchars($data['student_id_display']) ?></td>
                    <th style="width: 22%;">CLASS</th>
                    <td class="value-cell" style="width: 28%;"><?= htmlspecialchars($data['class_section']) ?>-<?= htmlspecialchars($data['section_alpha']) ?></td>
                </tr>
                <tr>
                    <th>PROGRAM</th>
                    <td class="value-cell"><?= htmlspecialchars($data['program']) ?></td>
                    <th>PERIOD</th>
                    <td class="value-cell"><?= htmlspecialchars($data['fee_period'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <th>SLIP #</th>
                    <td class="value-cell"><?= htmlspecialchars($data['slip_no']) ?></td>
                    <th>ISSUED</th>
                    <td class="value-cell"><?= htmlspecialchars($data['issue_date']) ?></td>
                </tr>
                <tr>
                    <td colspan="4" class="due-date-cell align-middle" style="padding: 4px; font-size: 11px; text-align: center; background-color: #dc3545; color: white;">
                        <strong>DUE DATE: <?= htmlspecialchars($data['due_date']) ?></strong>
                    </td>
                </tr>
            </table>
            
            <table class="fees-table" style="table-layout: fixed; width: 100%;">
                <thead>
                    <tr>
                        <th>Fee Component</th>
                        <th style="width: 40%; text-align: right;">Amount (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $breakdownHtml ?>
                    <tr>
                        <th class="text-end" style="text-align: right;">TOTAL</th>
                        <td class="text-end" style="text-align: right;"><strong><?= number_format($recordAmount, 0) ?></strong></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="total-payable-section">
                <div>BEFORE DUE DATE<br><strong style="font-size: 10px;">PKR <?= number_format($recordAmount, 0) ?></strong></div>
                <div>AFTER DUE DATE<br><strong style="font-size: 10px;">PKR <?= number_format($recordAmount + 500, 0) ?></strong></div>
            </div>
            
            <div class="bank-fill-note">To be filled by the bank</div>
            
            <div class="late-payment-note">
                LATE PAYMENT : After due date Rs <?= htmlspecialchars($data['late_payment_daily_charge']) ?>/- will be charged per day.<br>
                <span style="display:block; margin-top: 3px;">Duplicate fee will be charged Rs. <?= htmlspecialchars($data['duplicate_fee_charge']) ?>/-</span>
            </div>
            
            <div class="footer-note">
                Note: Rs. <?= htmlspecialchars($data['summer_task_note_amount']) ?>/- will be charged as a Summer Task, if current dues will not pay in the same month.<br>
                STATUS: <strong><?= htmlspecialchars($data['status']) ?></strong>
                <?php if ($data['status'] === 'Paid' && !empty($data['paid_on'])): ?>
                    | PAID: <?= htmlspecialchars($data['paid_on']) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// --- GET REAL FEE DATA FROM DATABASE ---
if ($view_mode === 'voucher') {
    if (!empty($slip_no)) {
        try {
            $stmt = $pdo->prepare("
                SELECT fs.*, u.full_name, u.user_id_string, sd.fee_category,
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
                    'school_contact_line' => "Contact: Cell# 0317-9174495 , 03555851351 , 03554201394",
                    'school_email_web_line' => "E-mail:fees@msstskardu.com,Web:www.msstskardu.com",
                    'bank_details_line' => "Bank Al Habib : Ghulam Abbas, AC #: 20440981003831012",
                    'website_url' => "www.msstskardu.com",
                    'logo_path' => "http://localhost/msst/lms/assets/images/msst-logo.png",
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
        .voucher-page-content { display: flex; justify-content: space-between; gap: 10px; background: white; padding: 15px; border-radius: 8px; }
        .voucher-page-content > div { flex: 1; width: 32%; }
        .voucher-instance { background-color: #fff; page-break-inside: avoid; border: none; padding: 0; height: 100%; }
        .voucher-container { border: 1px dashed #666; padding: 10px; background-color: #fff; height: 100%; display: flex; flex-direction: column; }
        .header-section { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
        .logo-container { display: flex; align-items: center; }
        .logo { width: 70px; height: auto; margin-right: 10px; border: 1px solid #ddd; padding: 3px; }
        .school-info { font-size: 0.7rem; line-height: 1.2; }
        .school-info strong { font-size: 0.8rem; display: block; margin-bottom: 2px; }
        .affiliated-copy-type { text-align: right; font-size: 0.75rem; }
        .affiliated-copy-type span { display: block; border: 1px solid #000; padding: 3px 7px; margin-bottom: 4px; min-width: 120px; text-align: center; }
        .copy-type-label { font-weight: bold; background-color: #f0f0f0; }
        .bank-details { text-align: center; font-size: 0.8rem; font-weight: bold; margin-bottom: 8px; padding: 4px; border: 1px solid #000; }
        
        .student-info-table, .fees-table { width: 100%; margin-bottom: 8px; font-size: 0.65rem; border-collapse: collapse; }
        .student-info-table th, .student-info-table td, .fees-table th, .fees-table td { border: 1px solid #000; padding: 3px; vertical-align: middle; text-align: left; }
        .student-info-table th, .fees-table th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .student-info-table td.student-name-value { font-weight: bold; text-align: left !important; }
        .student-info-table td.value-cell, .fees-table td { text-align: center !important; font-weight: bold; }
        .due-date-cell { color: #fff !important; background-color: #dc3545 !important; font-weight: bold; text-align: center !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        .total-payable-section { display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.7rem; gap:5px; }
        .total-payable-section div { border: 1px solid #000; padding: 4px 8px; text-align:center; flex:1; background-color: #f8f9fa; font-weight: bold; }
        .bank-fill-note { font-size: 0.65rem; text-align: right; margin-top: 5px; margin-bottom: 5px; font-style: italic;}
        .late-payment-note { font-size: 0.7rem; margin-top: 5px; border: 1px solid #000; padding: 3px; font-weight: bold; }
        .footer-note { font-size: 0.7rem; margin-top: auto; border-top: 1px solid #000; padding-top: 4px; }
        .website-link { font-weight: bold; font-size: 0.75rem; float: right; }
        .print-button-container { text-align: center; margin-bottom: 20px; }

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
                @page { size: A4 landscape; margin: 3mm; }
                body { margin: 0; padding: 0; font-family: Arial, sans-serif; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background: #fff; }
                .voucher-page { display: flex; flex-direction: row; justify-content: space-between; width: 100%; height: 195mm; box-sizing: border-box; }
                .voucher-col { width: 32%; height: 100%; box-sizing: border-box; }
                .voucher-instance { height: 100%; }
                .voucher-container { border: 1px dashed #000; height: 100%; padding: 5px; display: flex; flex-direction: column; box-sizing: border-box; }
                .header-section { display: flex; justify-content: space-between; border-bottom: 1px solid #000; padding-bottom: 3px; margin-bottom: 3px; }
                .logo-container { display: flex; align-items: center; }
                .logo { width: 45px; height: auto; margin-right: 5px; border: 1px solid #ddd; padding: 2px; }
                .school-info { font-size: 7px; line-height: 1.1; }
                .school-info strong { font-size: 10px; margin-bottom: 1px; display: block; }
                .affiliated-copy-type { text-align: right; font-size: 7px; }
                .affiliated-copy-type span { display: block; border: 1px solid #000; padding: 1px 3px; margin-bottom: 1px; }
                .copy-type-label { font-weight: bold; background-color: #e9ecef !important; font-size: 9px !important;}
                .bank-details { text-align: center; font-size: 8px; font-weight: bold; border: 1px solid #000; padding: 2px; margin-bottom: 3px; background-color: #f8f9fa !important; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 3px; font-size: 7px; table-layout: fixed; }
                th, td { border: 1px solid #000; padding: 2px; text-align: left; overflow: hidden; white-space: nowrap; }
                th { background-color: #f2f2f2 !important; font-weight: bold; text-align: center; }
                td { text-align: center; }
                .student-name-value { font-weight: bold; text-align: left !important; }
                .value-cell { font-weight: bold; text-align: center !important; }
                .due-date-cell { background-color: #dc3545 !important; color: white !important; font-weight: bold; text-align: center !important; font-size: 9px !important; vertical-align: middle !important;}
                .total-payable-section { display: flex; justify-content: space-between; margin-top: auto; font-size: 8px; gap: 3px;}
                .total-payable-section div { border: 1px solid #000; padding: 3px; font-weight: bold; background-color: #f8f9fa !important; flex: 1; text-align: center; }
                .bank-fill-note { font-size: 6px; text-align: right; margin-top: 3px; font-style: italic; }
                .late-payment-note { font-size: 7px; margin-top: 2px; padding: 2px; border: 1px solid #000; font-weight: bold; text-align: left; }
                .footer-note { font-size: 7px; margin-top: auto; border-top: 1px solid #000; padding-top: 3px; text-align: left; }
            </style></head>
            <body><div class="voucher-page"><div class="voucher-col">${parentCopy}</div><div class="voucher-col">${hostelCopy}</div><div class="voucher-col">${bankCopy}</div></div></body></html>
        `);
        printWindow.document.close(); printWindow.focus(); setTimeout(() => { printWindow.print(); printWindow.close(); }, 300);
    }
    </script>
</body>
</html>