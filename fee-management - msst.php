<?php
session_start();

// Check if the admin is logged in.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Set admin details from the session.
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// --- Dummy Fee Data ---
// In a real application, this data would be from a database.
$fee_records = [
    [
        'slip_no' => 'FS-202506-101', 'student_id' => 'STU-101', 'name' => 'Kamran Ahmed', 'class' => '10-A',
        'amount' => 5000, 'due_date' => '2025-06-10', 'status' => 'Paid', 'paid_on' => '2025-06-05', 'payment_method' => 'Bank Transfer'
    ],
    [
        'slip_no' => 'FS-202506-102', 'student_id' => 'STU-102', 'name' => 'Zainab Ali', 'class' => '9-B',
        'amount' => 4500, 'due_date' => '2025-06-10', 'status' => 'Pending', 'paid_on' => null, 'payment_method' => null
    ],
    [
        'slip_no' => 'FS-202506-103', 'student_id' => 'STU-103', 'name' => 'Danish Khan', 'class' => '10-A',
        'amount' => 5000, 'due_date' => '2025-06-10', 'status' => 'Overdue', 'paid_on' => null, 'payment_method' => null
    ],
];

// Dummy fee structure for generating slips.
$fee_structure = [
    '9' => ['tuition' => 4000, 'sports' => 250, 'library' => 250],
    '10' => ['tuition' => 4500, 'lab' => 250, 'library' => 250],
];
$available_classes = ['6', '7', '8', '9', '10', '11', '12'];
$current_month_year = date('F Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .fee-slip { border: 2px solid #000; padding: 20px; font-family: 'Times New Roman', Times, serif; }
        .fee-slip-header { text-align: center; margin-bottom: 20px; }
        .fee-slip-header h4, .fee-slip-header p { margin: 0; }
        .fee-slip-details, .fee-breakdown { margin-bottom: 20px; }
        .fee-slip-details .row { border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 5px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Page Heading & Actions -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Fee Management</h1>
                <div>
                    <button class="btn btn-primary shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#generateSlipsModal">
                        <i class="fas fa-file-invoice-dollar fa-sm text-white-50"></i> Generate Fee Slips
                    </button>
                    <button class="btn btn-info shadow-sm" data-bs-toggle="modal" data-bs-target="#downloadReportModal">
                        <i class="fas fa-download fa-sm text-white-50"></i> Download Reports
                    </button>
                </div>
            </div>

            <!-- Fee Records Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Fee Records for <?= $current_month_year ?></h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr><th>Slip #</th><th>Student ID</th><th>Name</th><th>Amount (PKR)</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fee_records as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['slip_no']) ?></td>
                                        <td><?= htmlspecialchars($record['student_id']) ?></td>
                                        <td><?= htmlspecialchars($record['name']) ?></td>
                                        <td><?= number_format($record['amount']) ?></td>
                                        <td><?= htmlspecialchars($record['due_date']) ?></td>
                                        <td>
                                            <span class="badge <?= $record['status'] == 'Paid' ? 'bg-success' : ($record['status'] == 'Pending' ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= htmlspecialchars($record['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-secondary btn-sm" title="View Slip" data-bs-toggle="modal" data-bs-target="#viewSlipModal" onclick='viewSlip(<?= json_encode($record) ?>, <?= json_encode($fee_structure) ?>)'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($record['status'] != 'Paid'): ?>
                                                <button class="btn btn-success btn-sm" title="Record Payment" data-bs-toggle="modal" data-bs-target="#recordPaymentModal" onclick='prepareRecordPayment(<?= json_encode($record) ?>)'>
                                                    <i class="fas fa-cash-register"></i>
                                                </button>
                                                <button class="btn btn-warning btn-sm" title="Send Reminder" onclick="sendReminder('<?= htmlspecialchars($record['name']) ?>')">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                            <?php endif; ?>
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
    <!-- Footer -->
    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> School Management System. All rights reserved.</p></div></footer>
</div>

<!-- Modals -->
<!-- Generate Slips Modal -->
<div class="modal fade" id="generateSlipsModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Generate Fee Slips</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form id="generateSlipsForm">
            <p>Select a class to generate fee slips for the current month (<?= $current_month_year ?>).</p>
            <div class="mb-3"><label class="form-label">Class</label><select class="form-select" id="classToGenerate"><option selected disabled>Choose a class...</option><?php foreach($available_classes as $class): echo "<option value='$class'>Class $class</option>"; endforeach; ?></select></div>
        </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="generateSlipsForm" class="btn btn-primary">Generate</button></div>
</div></div></div>

<!-- View Slip Modal -->
<div class="modal fade" id="viewSlipModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Fee Slip</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div id="feeSlipContent" class="fee-slip"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button></div>
</div></div></div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="recordPaymentModalLabel">Record Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form id="recordPaymentForm">
            <input type="hidden" id="paymentSlipNo" name="slip_no">
            <p>Recording payment for <strong id="paymentStudentName"></strong>.</p>
            <div class="mb-3"><label class="form-label">Amount Paid (PKR)</label><input type="number" class="form-control" id="paymentAmount" readonly></div>
            <div class="mb-3"><label class="form-label">Payment Date</label><input type="date" class="form-control" id="paymentDate" value="<?= date('Y-m-d') ?>" required></div>
            <div class="mb-3"><label class="form-label">Payment Method</label><select class="form-select" id="paymentMethod" required><option>Cash</option><option>Bank Transfer</option><option>Online</option></select></div>
        </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="recordPaymentForm" class="btn btn-success">Record Payment</button></div>
</div></div></div>

<!-- Download Report Modal -->
<div class="modal fade" id="downloadReportModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Download Fee Reports</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p>Choose a report to download.</p>
        <div class="d-grid gap-2">
            <button class="btn btn-outline-info" onclick="downloadReport('paid')"><i class="fas fa-check-circle"></i> Paid Fees Report</button>
            <button class="btn btn-outline-warning" onclick="downloadReport('pending')"><i class="fas fa-clock"></i> Pending Fees Report</button>
            <button class="btn btn-outline-danger" onclick="downloadReport('overdue')"><i class="fas fa-exclamation-triangle"></i> Overdue Fees Report</button>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
</div></div></div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Sidebar Script ---
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    function saveSidebarState(c){localStorage.setItem('sidebarCollapsed',c);}
    function loadSidebarState(){return localStorage.getItem('sidebarCollapsed')==='true';}
    if(loadSidebarState()){sidebar.classList.add('collapsed');mainContent.classList.add('collapsed');}
    toggleBtn.addEventListener('click',()=>{sidebar.classList.toggle('collapsed');mainContent.classList.toggle('collapsed');saveSidebarState(sidebar.classList.contains('collapsed'));if(window.innerWidth<768){if(!sidebar.classList.contains('collapsed')){sidebar.classList.add('show');mainContent.classList.add('show-sidebar');}else{sidebar.classList.remove('show');mainContent.classList.remove('show-sidebar');}}});
    document.addEventListener('click',(e)=>{if(window.innerWidth<768&&sidebar.classList.contains('show')&&!sidebar.contains(e.target)&&!toggleBtn.contains(e.target)){sidebar.classList.remove('show');sidebar.classList.add('collapsed');mainContent.classList.remove('show-sidebar');mainContent.classList.add('collapsed');saveSidebarState(true);}});

    // --- Page-specific Scripts ---
    document.getElementById('generateSlipsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const selectedClass = document.getElementById('classToGenerate').value;
        if (selectedClass) {
            alert(`Generating fee slips for Class ${selectedClass}... (Simulation)`);
            bootstrap.Modal.getInstance(document.getElementById('generateSlipsModal')).hide();
        } else {
            alert('Please select a class.');
        }
    });

    function viewSlip(record, structure) {
        const classNumber = record.class.split('-')[0];
        const feeBreakdown = structure[classNumber];
        let breakdownHtml = '';
        let total = 0;
        if(feeBreakdown){
            Object.keys(feeBreakdown).forEach(key => {
                breakdownHtml += `<tr><td>${key.charAt(0).toUpperCase() + key.slice(1)} Fee</td><td class="text-end">${feeBreakdown[key].toLocaleString()}</td></tr>`;
                total += feeBreakdown[key];
            });
        }
        
        const content = `
            <div class="fee-slip-header">
                <h4>School Management System</h4>
                <p>123 Education Lane, Karachi</p>
                <p>Fee Slip for ${'<?= $current_month_year ?>'}</p>
            </div>
            <div class="fee-slip-details">
                <div class="row"><div class="col-6"><strong>Slip No:</strong> ${record.slip_no}</div><div class="col-6"><strong>Student Name:</strong> ${record.name}</div></div>
                <div class="row"><div class="col-6"><strong>Student ID:</strong> ${record.student_id}</div><div class="col-6"><strong>Class:</strong> ${record.class}</div></div>
            </div>
            <table class="table fee-breakdown">
                <thead><tr><th>Description</th><th class="text-end">Amount (PKR)</th></tr></thead>
                <tbody>${breakdownHtml}</tbody>
                <tfoot><tr><th class="text-end">Total Amount</th><th class="text-end">${total.toLocaleString()}</th></tr></tfoot>
            </table>
            <div class="text-center"><strong>Due Date: ${record.due_date}</strong></div>
        `;
        document.getElementById('feeSlipContent').innerHTML = content;
    }
    
    function prepareRecordPayment(record){
        document.getElementById('paymentSlipNo').value = record.slip_no;
        document.getElementById('paymentStudentName').textContent = record.name;
        document.getElementById('paymentAmount').value = record.amount;
    }

    document.getElementById('recordPaymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const slipNo = document.getElementById('paymentSlipNo').value;
        alert(`Payment recorded for slip ${slipNo}. (Simulation)`);
        bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal')).hide();
        location.reload();
    });

    function sendReminder(studentName) {
        alert(`Fee reminder sent to ${studentName}. (Simulation)`);
    }

    function downloadReport(reportType) {
        alert(`Downloading ${reportType} fees report... (Simulation)`);
        bootstrap.Modal.getInstance(document.getElementById('downloadReportModal')).hide();
    }
</script>
</body>
</html>
