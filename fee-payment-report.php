<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// --- Filters ---
$selected_month = $_GET['month'] ?? date('Y-m', strtotime('first day of last month'));
$month_label = date('F Y', strtotime($selected_month . '-01'));

$class_section_param = $_GET['class_section'] ?? '';
[$selected_class, $selected_section] = array_pad(explode('|', $class_section_param), 2, null);
$selected_class = $selected_class !== null && $selected_class !== '' ? (int)$selected_class : 0;
$selected_section = $selected_section !== null && $selected_section !== '' ? (int)$selected_section : 0;
$selected_class_label = 'All Classes';

$available_class_sections = [];
try {
    $available_class_sections = $pdo->query("
        SELECT DISTINCT c.id AS class_id, c.class_name, IFNULL(sec.id, 0) AS section_id, sec.section_name
        FROM classes c
        LEFT JOIN sections sec ON sec.class_id = c.id
        ORDER BY c.class_name, sec.section_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if ($selected_class) {
    foreach ($available_class_sections as $c) {
        if ($c['class_id'] == $selected_class && (int)$c['section_id'] == $selected_section) {
            $selected_class_label = 'Class ' . $c['class_name'] . ($c['section_name'] ? ' (' . $c['section_name'] . ')' : '');
            break;
        }
    }
}

// --- Fetch every active student with their fee_slips for the selected month (if any) ---
$rows = [];
try {
    $sql = "
        SELECT u.id, u.user_id_string, u.full_name, sd.father_name, sd.cell_no,
               sd.class_id, sd.section_id, c.class_name, sec.section_name,
               COUNT(fs.id) AS slip_count,
               SUM(CASE WHEN fs.status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
               COALESCE(SUM(fs.amount), 0) AS total_amount,
               COALESCE(SUM(CASE WHEN fs.status = 'Paid' THEN fs.amount ELSE 0 END), 0) AS paid_amount,
               GROUP_CONCAT(fs.slip_no) AS slip_nos,
               MAX(fs.due_date) AS due_date
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN classes c ON sd.class_id = c.id
        LEFT JOIN sections sec ON sd.section_id = sec.id
        LEFT JOIN fee_slips fs ON fs.student_id = u.id AND DATE_FORMAT(fs.month_year, '%Y-%m') = ?
        WHERE r.role_name = 'Student' AND u.status = 'Active'
    ";
    $params = [$selected_month];
    if ($selected_class) {
        $sql .= " AND sd.class_id = ? AND IFNULL(sd.section_id, 0) = ?";
        $params[] = $selected_class;
        $params[] = $selected_section;
    }
    $sql .= " GROUP BY u.id ORDER BY c.class_name, sec.section_name, u.full_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        if ((int)$row['slip_count'] === 0) {
            $row['pay_status'] = 'No Slip';
        } elseif ((int)$row['paid_count'] === (int)$row['slip_count']) {
            $row['pay_status'] = 'Paid';
        } else {
            $row['pay_status'] = 'Unpaid';
        }
    }
    unset($row);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$paid_count = count(array_filter($rows, fn($r) => $r['pay_status'] === 'Paid'));
$unpaid_count = count(array_filter($rows, fn($r) => $r['pay_status'] === 'Unpaid'));
$no_slip_count = count(array_filter($rows, fn($r) => $r['pay_status'] === 'No Slip'));
$total_collected = array_sum(array_column($rows, 'paid_amount'));
$total_outstanding = array_sum(array_map(fn($r) => $r['total_amount'] - $r['paid_amount'], $rows));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Payment Report | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .stat-card { border: none; border-left: 5px solid; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .status-filter-btn.active { box-shadow: inset 0 0 0 2px rgba(0,0,0,0.15); }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">

            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice-dollar me-2"></i>Fee Payment Report</h1>
                <button class="btn btn-secondary shadow-sm no-print" onclick="printReport()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>

            <div class="card shadow-sm mb-4 border-0 rounded-3 no-print">
                <div class="card-body bg-light rounded-3">
                    <form method="GET" id="reportFilterForm" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Month</label>
                            <input type="month" class="form-control border-primary fw-bold" name="month" value="<?= htmlspecialchars($selected_month) ?>" onchange="document.getElementById('reportFilterForm').submit()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Class</label>
                            <select class="form-select border-primary fw-bold" name="class_section" onchange="document.getElementById('reportFilterForm').submit()">
                                <option value="">All Classes</option>
                                <?php foreach ($available_class_sections as $c): ?>
                                    <option value="<?= $c['class_id'] ?>|<?= $c['section_id'] ?>" <?= ($c['class_id'] == $selected_class && (int)$c['section_id'] == $selected_section) ? 'selected' : '' ?>>
                                        Class <?= htmlspecialchars($c['class_name']) ?><?= $c['section_name'] ? ' (' . htmlspecialchars($c['section_name']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row mb-4 no-print">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card h-100" style="border-left-color: #1cc88a;">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Paid</div>
                            <div class="h4 mb-0 fw-bold"><?= $paid_count ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card h-100" style="border-left-color: #e74a3b;">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1">Unpaid</div>
                            <div class="h4 mb-0 fw-bold"><?= $unpaid_count ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card h-100" style="border-left-color: #858796;">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-secondary text-uppercase mb-1">No Slip Generated</div>
                            <div class="h4 mb-0 fw-bold"><?= $no_slip_count ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card h-100" style="border-left-color: #4e73df;">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Collected / Outstanding</div>
                            <div class="h6 mb-0 fw-bold">PKR <?= number_format($total_collected, 0) ?> / <?= number_format($total_outstanding, 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow border-0 rounded-3 printable-area">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
                    <h6 class="m-0 fw-bold text-primary">
                        Payment Status: <?= htmlspecialchars($selected_class_label) ?>
                        <span class="badge bg-secondary ms-2"><?= $month_label ?></span>
                    </h6>
                    <div class="btn-group btn-group-sm no-print" role="group">
                        <button type="button" class="btn btn-outline-dark status-filter-btn active" data-status="all">All</button>
                        <button type="button" class="btn btn-outline-success status-filter-btn" data-status="Paid">Paid</button>
                        <button type="button" class="btn btn-outline-danger status-filter-btn" data-status="Unpaid">Unpaid</button>
                        <button type="button" class="btn btn-outline-secondary status-filter-btn" data-status="No Slip">No Slip</button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($rows)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                            <h5>No active students found.</h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="reportTable" width="100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Student Name</th>
                                        <th>Class/Section</th>
                                        <th>Guardian</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Slip(s)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <tr data-status="<?= htmlspecialchars($row['pay_status']) ?>">
                                            <td class="fw-bold"><?= htmlspecialchars($row['user_id_string']) ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td><?= $row['class_name'] ? 'Class ' . htmlspecialchars($row['class_name']) . ($row['section_name'] ? ' (' . htmlspecialchars($row['section_name']) . ')' : '') : '<span class="text-muted">N/A</span>' ?></td>
                                            <td><?= htmlspecialchars($row['father_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($row['cell_no'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if ($row['pay_status'] === 'Paid'): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php elseif ($row['pay_status'] === 'Unpaid'): ?>
                                                    <span class="badge bg-danger">Unpaid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No Slip Generated</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold">
                                                <?php if ((int)$row['slip_count'] > 0): ?>
                                                    PKR <?= number_format($row['paid_amount'], 0) ?> / <?= number_format($row['total_amount'], 0) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars($row['slip_nos'] ?? '-') ?></td>
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
    <footer class="footer no-print"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> Muhaddisa School of Science and Technology.</p></div></footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        var table = $('#reportTable').DataTable({
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "language": { "search": "Search Student:" }
        });

        $('.status-filter-btn').on('click', function() {
            $('.status-filter-btn').removeClass('active');
            $(this).addClass('active');
            var status = $(this).data('status');
            if (status === 'all') {
                table.column(5).search('').draw();
                $.fn.dataTable.ext.search.pop();
            } else {
                $.fn.dataTable.ext.search = [];
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    return $(table.row(dataIndex).node()).data('status') === status;
                });
            }
            table.draw();
        });
    });

    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });
    }

    function printReport() {
        let originalTable = document.getElementById('reportTable');
        let printWindow = window.open('', '', 'height=800,width=1200');
        printWindow.document.write('<html><head><title>Fee Payment Report</title><style>');
        printWindow.document.write(`
            body { font-family: Arial, sans-serif; padding: 20px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            h2 { text-align: center; font-size: 22px; margin-bottom: 5px; }
            h4 { text-align: center; font-size: 16px; margin-bottom: 20px; color: #555; }
            table { width: 100%; table-layout: auto; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; }
            th, td { width: auto !important; min-width: 0 !important; border: 1px solid #000; padding: 4px; text-align: left; overflow: hidden; word-wrap: break-word; }
            .bg-success { background-color: #198754 !important; color: white !important; }
            .bg-danger { background-color: #dc3545 !important; color: white !important; }
            .bg-secondary { background-color: #6c757d !important; color: white !important; }
            @page { size: landscape; margin: 10mm; }
        `);
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<h2>Muhaddisa School of Science & Tech</h2>');
        printWindow.document.write('<h4>Fee Payment Report (' + '<?= $month_label ?>' + ')</h4>');

        let printTable = '<table><thead>' + originalTable.querySelector('thead').innerHTML + '</thead><tbody>';
        let rows = originalTable.querySelectorAll('tbody tr');
        rows.forEach(row => {
            if (row.style.display !== 'none') { printTable += '<tr>' + row.innerHTML + '</tr>'; }
        });
        printTable += '</tbody></table></body></html>';

        printWindow.document.write(printTable);
        printWindow.document.close();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    }
</script>
</body>
</html>
