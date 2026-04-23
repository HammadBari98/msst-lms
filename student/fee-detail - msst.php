<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Not needed for static version

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: student_login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// --- NEW: Check for view mode (list vs voucher) ---
$view_mode = $_GET['view'] ?? 'list'; // Default to 'list' view
$voucher_id = $_GET['id'] ?? 0; // The ID of the voucher to show

// --- RENDER VOUCHER FUNCTION (from your code) ---
function render_voucher_copy($copy_type, $data) {
    // This function is exactly as you provided it, placed here for use.
    // The HTML output is now within the function.
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
            <div class="bank-details"><?= htmlspecialchars($data['bank_details_line']) ?></div>
            <table class="student-info-table">
                <tr>
                    <th>STUDENT NAME</th><td class="student-name-value" colspan="3"><?= htmlspecialchars($data['student_name']) ?></td>
                    <th>CLASS/SECTION</th><td class="value-cell"><?= htmlspecialchars($data['class_section']) ?></td>
                    <th>SECTION</th><td class="value-cell"><?= htmlspecialchars($data['section_alpha']) ?></td>
                    <th rowspan="2" class="due-date-cell align-middle">DUE DATE <br> <?= htmlspecialchars($data['due_date']) ?></th>
                </tr>
                <tr>
                    <th>STUDENT ID</th><td class="value-cell"><?= htmlspecialchars($data['student_id_display']) ?></td>
                    <th>CAMPUS CODE</th><td class="value-cell"><?= htmlspecialchars($data['campus_code']) ?></td>
                    <th>ISSUE DATE</th><td class="value-cell issue-date-cell"><?= htmlspecialchars($data['issue_date']) ?></td>
                    <th>VALIDITY DATE</th><td class="value-cell validity-date-cell"><?= htmlspecialchars($data['validity_date']) ?></td>
                </tr>
            </table>
            <table class="fees-table">
                <thead>
                    <tr>
                        <th>REGISTRATION FEE</th><th>ADMISSION FEE</th><th>SECURITY FEE</th><th>FEE PERIOD</th><th>ANNUAL CHARGES</th>
                        <th>PROSPECTUS</th><th>BOOKS</th><th>NOTEBOOK</th><th>DAIRY</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($data['reg_fee']) ?></td><td><?= htmlspecialchars($data['adm_fee']) ?></td>
                        <td><?= htmlspecialchars($data['sec_fee']) ?></td><td><?= htmlspecialchars($data['fee_period']) ?></td>
                        <td><?= htmlspecialchars($data['annual_charges']) ?></td><td><?= htmlspecialchars($data['prospectus']) ?></td>
                        <td><?= htmlspecialchars($data['books']) ?></td><td><?= htmlspecialchars($data['notebook']) ?></td>
                        <td><?= htmlspecialchars($data['dairy']) ?></td>
                    </tr>
                    <tr>
                        <th>ID CARD</th><th>MONTHLY FEE</th><th>IT FEE</th><th>UNIFORM</th><th>ARREARS</th>
                        <th>Summer Task</th><th>TUITION FEE</th><th>OTHER</th><th></th>
                    </tr>
                    <tr>
                        <td><?= htmlspecialchars($data['id_card_fee']) ?></td><td><?= htmlspecialchars($data['monthly_fee']) ?></td>
                        <td><?= htmlspecialchars($data['it_fee']) ?></td><td><?= htmlspecialchars($data['uniform_fee']) ?></td>
                        <td><?= htmlspecialchars($data['arrears']) ?></td><td><?= htmlspecialchars($data['summer_task_fee_item']) ?></td>
                        <td><?= htmlspecialchars($data['tuition_fee']) ?></td><td><?= htmlspecialchars($data['other_fee']) ?></td><td></td>
                    </tr>
                </tbody>
            </table>
            <div class="total-payable-section">
                <div>NET PAYABLE BEFORE DUE DATE &nbsp;&nbsp;&nbsp;&nbsp; <strong><?= htmlspecialchars($data['net_payable_before_due']) ?></strong></div>
                <div>NET PAYABLE AFTER DUE DATE</div>
            </div>
            <div class="bank-fill-note">To be filled by the bank</div>
            <div class="late-payment-note">
                LATE PAYMENT : After due date Rs <?= htmlspecialchars($data['late_payment_daily_charge']) ?>/- will be charged per day.
                <span class="float-end">Duplicate fee will be charged Rs. <?= htmlspecialchars($data['duplicate_fee_charge']) ?>/-</span>
            </div>
            <div class="footer-note">
                Note: Rs. <?= htmlspecialchars($data['summer_task_note_amount']) ?>/- will be charged as a Summer Task, if current dues will not pay in the same month.
                <span class="website-link">Website : <?= htmlspecialchars($data['website_url']) ?></span>
            </div>
        </div>
    </div>
    <?php
}

// --- DUMMY DATA BLOCKS ---
if ($view_mode === 'voucher') {
    // This data is for a single voucher view
    $voucher_data = [
        'student_name' => $_SESSION['student_name'] ?? "Fozia Batool",
        'student_id_display' => $_SESSION['student_id'] ?? "MHS-2025-17114",
        'class_section' => "1st Year", 'section_alpha' => "A", 'campus_code' => "1",
        'issue_date' => "05/05/2025", 'validity_date' => "05/06/2025", 'due_date' => "25/05/25",
        'reg_fee' => "2000", 'adm_fee' => "3000", 'sec_fee' => "5000", 'fee_period' => "May",
        'annual_charges' => "0", 'prospectus' => "0", 'books' => "0", 'notebook' => "250", 'dairy' => "0",
        'id_card_fee' => "0", 'monthly_fee' => "2250", 'it_fee' => "500", 'uniform_fee' => "0", 'arrears' => "0",
        'summer_task_fee_item' => "0", 'tuition_fee' => "0", 'other_fee' => "0",
        'net_payable_before_due' => "13000", 'late_payment_daily_charge' => "25", 'duplicate_fee_charge' => "50",
        'summer_task_note_amount' => "550", 'school_name' => "Minhaj School of Science and Technology",
        'school_address_line1' => "Head Office : Kushmara Toq, Near Fatima Jinnah Girls HSS, Quaidabad Skardu",
        'school_contact_line' => "Contact: Cell# 0317-9174495 , 03555851351 , 03554201394",
        'school_email_web_line' => "E-mail:fees@msstskardu.com,Web:www.msstskardu.com",
        'bank_details_line' => "Bank Al Habib : Ghulam Abbas, AC #: 20440981003831012",
        'website_url' => "www.msstskardu.com",
        'logo_path' => "https://i.imgur.com/rS2gH6x.png", // Using a generic logo placeholder
    ];
} else {
    // This data is for the default list view
    $overall_fee_status = ['status' => 'Paid', 'total_paid' => 75000.00, 'total_due' => 0.00, 'next_due_date' => null];
    $installment_plan = [
        ['id' => 1, 'name' => '1st Installment - Spring 2025', 'due_date' => '2025-02-15', 'amount' => 25000.00, 'status' => 'Paid', 'paid_on' => '2025-02-10'],
        ['id' => 2, 'name' => '2nd Installment - Spring 2025', 'due_date' => '2025-04-15', 'amount' => 25000.00, 'status' => 'Paid', 'paid_on' => '2025-04-12'],
        ['id' => 3, 'name' => 'Final Installment - Spring 2025', 'due_date' => '2025-06-15', 'amount' => 25000.00, 'status' => 'Paid', 'paid_on' => '2025-06-01'],
    ];
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
        .status-card.unpaid { border-left-color: var(--danger); }
        .table-hover tbody tr:hover { background-color: #f1f3f5; }

        /* VOUCHER SPECIFIC STYLES */
        .voucher-instance { background-color: #fff; margin-bottom: 25px; page-break-inside: avoid; }
        .voucher-container { border: 1px solid #000; padding: 10px; }
        .header-section { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
        .logo-container { display: flex; align-items: center; }
        .logo { width: 70px; height: auto; margin-right: 10px; border: 1px solid #ddd; padding: 3px; }
        .school-info { font-size: 0.7rem; line-height: 1.2; }
        .school-info strong { font-size: 0.8rem; display: block; margin-bottom: 2px; }
        .affiliated-copy-type { text-align: right; font-size: 0.75rem; }
        .affiliated-copy-type span { display: block; border: 1px solid #000; padding: 3px 7px; margin-bottom: 4px; min-width: 120px; text-align: center; }
        .affiliated-copy-type span.copy-type-label { font-weight: bold; background-color: #f0f0f0; }
        .bank-details { text-align: center; font-size: 0.8rem; font-weight: bold; margin-bottom: 8px; padding: 4px; border: 1px solid #000; }
        .student-info-table, .fees-table { width: 100%; margin-bottom: 8px; font-size: 0.75rem; border-collapse: collapse; }
        .student-info-table th, .student-info-table td, .fees-table th, .fees-table td { border: 1px solid #000; padding: 3px 5px; vertical-align: middle; text-align: left; }
        .student-info-table th, .fees-table th { background-color: #f2f2f2; font-weight: bold; }
        .student-info-table td.student-name-value { font-weight: bold; }
        .student-info-table td.value-cell { text-align: center !important; font-weight: bold; }
        .fees-table th, .fees-table td { text-align: center; }
        .due-date-cell { background-color: #dc3545 !important; color: #fff !important; font-weight: bold; text-align: center !important; }
        .total-payable-section { display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.85rem; }
        .total-payable-section div { border: 1px solid #000; padding: 4px 8px; }
        .bank-fill-note { font-size: 0.65rem; text-align: right; margin-top: 5px; margin-bottom: 5px; }
        .late-payment-note, .footer-note { font-size: 0.7rem; margin-top: 8px; }
        .footer-note { border-top: 1px solid #000; padding-top: 4px; }
        .website-link { font-weight: bold; font-size: 0.75rem; display: block; text-align: right; }
        .print-button-container { text-align: center; margin-bottom: 20px; }

        /* PRINT STYLES */
        @media print {
            body { background-color: #fff !important; }
            #sidebar, #main-content > header, #main-content > footer, .print-button-container, .breadcrumb, h1.h3 { display: none !important; }
            #main-content { margin-left: 0 !important; }
            .content-wrapper { padding: 0 !important; }
            .voucher-instance { margin-bottom: 10mm; }
            .affiliated-copy-type span.copy-type-label, .student-info-table th, .fees-table th { background-color: #f2f2f2 !important; }
            .due-date-cell { background-color: #dc3545 !important; color: #fff !important; }
            a { text-decoration: none; color: #000; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">

                <?php if ($view_mode === 'voucher'): ?>
                    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="fees.php">Fee Details</a></li><li class="breadcrumb-item active">Fee Voucher</li></ol></nav>
                    <h1 class="h3 mb-4">Fee Voucher</h1>
                    <div class="print-button-container"><button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Vouchers</button></div>
                    <div class="voucher-page-content">
                        <?php
                            render_voucher_copy("PARENT COPY", $voucher_data);
                            render_voucher_copy("BANK COPY", $voucher_data);
                            render_voucher_copy("SCHOOL COPY", $voucher_data);
                        ?>
                    </div>

                <?php else: ?>
                    <h1 class="h3 mb-4">My Fee Details</h1>
                    <div class="row">
                        <div class="col-12 mb-4">
                            <?php 
                                $status_class = ($overall_fee_status['status'] == 'Paid') ? 'paid' : 'unpaid';
                                $status_icon = ($overall_fee_status['status'] == 'Paid') ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-danger';
                                $status_text_class = ($overall_fee_status['status'] == 'Paid') ? 'text-success' : 'text-danger';
                                $status_title = ($overall_fee_status['status'] == 'Paid') ? 'All Dues Cleared' : 'Payment Due';
                            ?>
                            <div class="card shadow-sm status-card <?= $status_class ?>">
                                <div class="card-body p-4"><div class="row align-items-center">
                                    <div class="col-md-2 text-center"><i class="fas <?= $status_icon ?>" style="font-size: 4rem;"></i></div>
                                    <div class="col-md-7"><h4 class="card-title font-weight-bold <?= $status_text_class ?>"><?= $status_title ?></h4><p class="text-muted mb-0">Here is a summary of your account statement.</p></div>
                                    <div class="col-md-3 text-md-end mt-3 mt-md-0"><h6 class="text-muted mb-1">Next Payment Due</h6><h5 class="font-weight-bold"><?= $overall_fee_status['next_due_date'] ? date('M j, Y', strtotime($overall_fee_status['next_due_date'])) : 'N/A' ?></h5></div>
                                </div></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Installment Plan & History</h6></div>
                                <div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle">
                                    <thead><tr><th>Installment Details</th><th>Due Date</th><th>Amount</th><th>Status</th><th>Paid On</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($installment_plan)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">No installment plan found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($installment_plan as $item): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                                    <td><?= date('M j, Y', strtotime($item['due_date'])) ?></td>
                                                    <td>Rs. <?= number_format($item['amount'], 2) ?></td>
                                                    <td><span class="badge <?= ($item['status'] == 'Paid') ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($item['status']) ?></span></td>
                                                    <td><?= $item['paid_on'] ? date('M j, Y', strtotime($item['paid_on'])) : 'N/A' ?></td>
                                                    <td>
                                                        <?php if($item['status'] == 'Paid'): ?>
                                                            <a href="fees.php?view=voucher&id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-receipt me-1"></i> Receipt</a>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-primary" disabled><i class="fas fa-money-bill-wave me-1"></i> Pay Now</button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table></div></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

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