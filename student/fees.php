<?php
session_start();

// FIX: Define $student_name for use in the included header.php file.
// This uses the same logic as the voucher data to ensure consistency.
$student_name = $_SESSION['student_name'] ?? "Fozia Batool";

// Ensure this path is correct.
// require_once __DIR__ . '/../config/db_config.php'; // Uncomment if voucher data comes from DB

// Check if student is logged in, if session data is used for voucher details.
if (!isset($_SESSION['student_logged_in'])) {
    // header('Location: login.php'); // Adjust to your student login page.
    // exit();
}

// --- Voucher Data ---
// This data should ideally be fetched dynamically for the specific student/period.
$voucher_data = [
    'student_name' => $student_name, // Use the variable defined above
    'student_id_display' => $_SESSION['student_id'] ?? "MHS-2025-17114",
    'class_section' => "1st Year",
    'section_alpha' => "A",
    'campus_code' => "1",
    'issue_date' => "5/5/2025",
    'validity_date' => "5/6/2025",
    'due_date' => "25/05/25",

    'reg_fee' => "2000",
    'adm_fee' => "3000",
    'sec_fee' => "5000",
    'fee_period' => "May",
    'annual_charges' => "0",
    'prospectus' => "0",
    'books' => "0",
    'notebook' => "250",
    'dairy' => "0",

    'id_card_fee' => "0",
    'monthly_fee' => "2250",
    'it_fee' => "500",
    'uniform_fee' => "0",
    'arrears' => "0",
    'summer_task_fee_item' => "0",
    'tuition_fee' => "0",
    'other_fee' => "0",

    'net_payable_before_due' => "13000",
    'late_payment_daily_charge' => "25",
    'duplicate_fee_charge' => "50",
    'summer_task_note_amount' => "550",

    'school_name' => "Muhaddisa School of Science and Technology",
    'school_address_line1' => "Head Office : Kushmara Toq, Near Fatima Jinnah Girls HSS, Quaidabad Skardu",
    'school_contact_line' => "Contact: Cell# 0317-9174495 , 03555851351 , 03554201394",
    'school_email_web_line' => "E-mail:fees@msstskardu.com,Web:www.msstskardu.com",
    'bank_details_line' => "Bank Al Habib : Ghulam Abbas, AC #: 20440981003831012",
    'website_url' => "www.msstskardu.com",
    'logo_path' => "https://via.placeholder.com/80x70.png?text=Logo",
];

// Function to render a single voucher copy
function render_voucher_copy($copy_type, $data) {
?>
    <div class="voucher-instance">
        <div class="voucher-container">
            <div class="header-section">
                <div class="logo-container">
                    <img src="../assets/images/msst-logo.png" alt="School Logo" class="logo">
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

            <table class="student-info-table">
                <tr>
                    <th>STUDENT NAME</th>
                    <td class="student-name-value" colspan="3"><?= htmlspecialchars($data['student_name']) ?></td>
                    <th>CLASS/SECTION</th>
                    <td class="value-cell"><?= htmlspecialchars($data['class_section']) ?></td>
                    <th>SECTION</th>
                    <td class="value-cell"><?= htmlspecialchars($data['section_alpha']) ?></td>
                    <th rowspan="2" class="due-date-cell align-middle">DUE DATE <br> <?= htmlspecialchars($data['due_date']) ?></th>
                </tr>
                <tr>
                    <th>STUDENT ID</th>
                    <td class="value-cell"><?= htmlspecialchars($data['student_id_display']) ?></td>
                    <th>CAMPUS CODE</th>
                    <td class="value-cell"><?= htmlspecialchars($data['campus_code']) ?></td>
                    <th>ISSUE DATE</th>
                    <td class="value-cell issue-date-cell"><?= htmlspecialchars($data['issue_date']) ?></td>
                    <th>VALIDITY DATE</th>
                    <td class="value-cell validity-date-cell"><?= htmlspecialchars($data['validity_date']) ?></td>
                </tr>
            </table>

            <table class="fees-table">
                <thead>
                    <tr>
                        <th>REGISTRATION FEE</th>
                        <th>ADMISSION FEE</th>
                        <th>SECURITY FEE</th>
                        <th>FEE PERIOD</th>
                        <th>ANNUAL CHARGES</th>
                        <th>PROSPECTUS</th>
                        <th>BOOKS</th>
                        <th>NOTEBOOK</th>
                        <th>DAIRY</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($data['reg_fee']) ?></td>
                        <td><?= htmlspecialchars($data['adm_fee']) ?></td>
                        <td><?= htmlspecialchars($data['sec_fee']) ?></td>
                        <td><?= htmlspecialchars($data['fee_period']) ?></td>
                        <td><?= htmlspecialchars($data['annual_charges']) ?></td>
                        <td><?= htmlspecialchars($data['prospectus']) ?></td>
                        <td><?= htmlspecialchars($data['books']) ?></td>
                        <td><?= htmlspecialchars($data['notebook']) ?></td>
                        <td><?= htmlspecialchars($data['dairy']) ?></td>
                    </tr>
                    <tr>
                        <th>ID CARD</th>
                        <th>MONTHLY FEE</th>
                        <th>IT FEE</th>
                        <th>UNIFORM</th>
                        <th>ARREARS</th>
                        <th>Summer Task</th>
                        <th>TUITION FEE</th>
                        <th>OTHER</th>
                        <th></th>
                    </tr>
                    <tr>
                        <td><?= htmlspecialchars($data['id_card_fee']) ?></td>
                        <td><?= htmlspecialchars($data['monthly_fee']) ?></td>
                        <td><?= htmlspecialchars($data['it_fee']) ?></td>
                        <td><?= htmlspecialchars($data['uniform_fee']) ?></td>
                        <td><?= htmlspecialchars($data['arrears']) ?></td>
                        <td><?= htmlspecialchars($data['summer_task_fee_item']) ?></td>
                        <td><?= htmlspecialchars($data['tuition_fee']) ?></td>
                        <td><?= htmlspecialchars($data['other_fee']) ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="total-payable-section">
                <div>NET PAYABLE BEFORE DUE DATE &nbsp;&nbsp;&nbsp;&nbsp; <strong><?= htmlspecialchars($data['net_payable_before_due']) ?></strong></div>
                <div>NET PAYABLE AFTER DUE DATE</div>
            </div>
            <div class="bank-fill-note">
                To be filled by the bank
            </div>

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Voucher | <?= htmlspecialchars($voucher_data['student_name'] ?? 'LMS') ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* TEMPLATE STYLES: Copied EXACTLY from your working profile.php <style> block */
        :root {
            --primary: #4e73df;
            --secondary: #1cc88a;
            --accent: #36b9cc;
            --light-bg: #f8f9fc;
            --dark-text: #5a5c69;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            overflow-x: hidden;
        }
        .profile-img {
            width: 120px; height: 120px; border-radius: 50%;
            background-color: #e9ecef; display: flex;
            align-items: center; justify-content: center; margin: 0 auto 1rem;
        }
        .profile-img i { font-size: 3rem; color: var(--primary); }
        .card {
            border: none; border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .card-header {
            background-color: var(--primary); color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        
        /* VOUCHER SPECIFIC STYLES */
        .voucher-page-content {
            /* No styles needed here */
        }
        .voucher-page-content .voucher-instance {
            background-color: #fff;
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .voucher-page-content .voucher-container {
            border: 1px solid #000;
            padding: 10px;
            background-color: #fff;
        }
        .voucher-page-content .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .voucher-page-content .logo-container {
            display: flex;
            align-items: center;
        }
        .voucher-page-content .logo {
            width: 70px;
            height: auto;
            margin-right: 10px;
            border: 1px solid #ddd;
            padding: 3px;
        }
        .voucher-page-content .school-info {
            font-size: 0.7rem;
            line-height: 1.2;
        }
        .voucher-page-content .school-info strong {
            font-size: 0.8rem;
            display: block;
            margin-bottom: 2px;
        }
        .voucher-page-content .affiliated-copy-type {
            text-align: right;
            font-size: 0.75rem;
        }
        .voucher-page-content .affiliated-copy-type span {
            display: block;
            border: 1px solid #000;
            padding: 3px 7px;
            margin-bottom: 4px;
            min-width: 120px;
            text-align: center;
        }
        .voucher-page-content .affiliated-copy-type span.copy-type-label {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .voucher-page-content .bank-details {
            text-align: center;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 8px;
            padding: 4px;
            border: 1px solid #000;
        }
        .voucher-page-content .student-info-table, .voucher-page-content .fees-table {
            width: 100%;
            margin-bottom: 8px;
            font-size: 0.75rem;
            border-collapse: collapse;
        }
        .voucher-page-content .student-info-table th, .voucher-page-content .student-info-table td,
        .voucher-page-content .fees-table th, .voucher-page-content .fees-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            vertical-align: middle;
            text-align: left;
        }
        .voucher-page-content .student-info-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .voucher-page-content .student-info-table td.student-name-value {
            text-align: left;
            font-weight: bold;
        }
        .voucher-page-content .student-info-table td.value-cell,
        .voucher-page-content .student-info-table td.issue-date-cell,
        .voucher-page-content .student-info-table td.validity-date-cell {
            text-align: center !important;
            font-weight: bold;
        }
        .voucher-page-content .fees-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .voucher-page-content .fees-table td {
            text-align: center;
        }
        .voucher-page-content .due-date-cell {
            color: #000 !important;
            font-weight: bold;
            text-align: center !important;
        }
        .voucher-page-content .total-payable-section {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 0.85rem;
        }
        .voucher-page-content .total-payable-section div {
            border: 1px solid #000;
            padding: 4px 8px;
        }
        .voucher-page-content .total-payable-section div:first-child strong {
            font-weight: bold;
        }
        .voucher-page-content .bank-fill-note {
            font-size: 0.65rem;
            text-align: right;
            margin-top: 5px;
            margin-bottom: 5px;
        }
        .voucher-page-content .late-payment-note {
            font-size: 0.7rem;
            margin-top: 5px;
        }
        .voucher-page-content .footer-note {
            font-size: 0.7rem;
            margin-top: 8px;
            border-top: 1px solid #000;
            padding-top: 4px;
        }
        .voucher-page-content .website-link {
            font-weight: bold;
            font-size: 0.75rem;
            display: block;
            text-align: right;
            margin-top: 2px;
        }
        .print-button-container { text-align: center; margin: 20px 0; }

        /* PRINT STYLES */
        @media print {
            body {
                background-color: #fff !important; padding: 0 !important; margin: 0 !important; font-size: 9pt !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }

            #sidebar, #main-content > header, #main-content > nav.navbar, .topbar,
            #main-content > footer.footer, .page-title-h1, .print-button-container {
                display: none !important;
            }

            #main-content {
                margin-left: 0 !important; padding: 0 !important; width: 100% !important;
                border: none !important; box-shadow: none !important; float: none !important;
            }
            .content-wrapper, .container-fluid.page-container-for-voucher {
                padding: 5mm !important; margin: 0 !important; width: 100% !important; max-width: 100% !important;
            }
            .voucher-page-content { margin: 0; padding: 0; }
            .voucher-page-content .voucher-instance {
                border: none !important; margin: 0 0 10mm 0 !important; padding: 0 !important;
                box-shadow: none !important; width: 100% !important;
            }
            .voucher-page-content .voucher-container { border: 1px solid #000 !important; padding: 5px !important; }
            .voucher-page-content .affiliated-copy-type span.copy-type-label,
            .voucher-page-content .student-info-table th,
            .voucher-page-content .fees-table th { background-color: #f2f2f2 !important; }
            .voucher-page-content .due-date-cell { background-color: #dc3545 !important; color: #000 !important; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div id="main-content">
        <?php include 'header.php'; ?>

        <div class="content-wrapper">
            <div class="container-fluid page-container-for-voucher">
                <h1 class="h3 mb-4 text-gray-800 page-title-h1">Fee Voucher</h1>

                <div class="print-button-container">
                    <button class="btn btn-primary" onclick="window.print()">Print Vouchers</button>
                </div>

                <div class="voucher-page-content">
                    <?php
                        render_voucher_copy("PARENT COPY", $voucher_data);
                        
                        render_voucher_copy("MSST COPY", $voucher_data);
                        render_voucher_copy("BANK COPY", $voucher_data);
                    ?>
                </div>
            </div>
        </div>

        <footer class="footer mt-auto py-3 bg-white">
            <div class="container-fluid">
                <div class="text-center">
                    <span class="text-muted">© LMS <?= date('Y') ?></span>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');

        if (sidebar && mainContent && toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            });

            toggleBtn.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show');
                }
            });
        }
    </script>
</body>
</html>
