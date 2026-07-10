<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// =======================================================
// AUTO-PATCHER: Expense & Shop Management tables
// =======================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expense_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expense_products (
            id INT PRIMARY KEY AUTO_INCREMENT,
            category_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            company VARCHAR(100) DEFAULT NULL,
            current_price DECIMAL(10,2) DEFAULT 0.00,
            last_updated DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product (category_id, name, company),
            FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_price_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_id INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            effective_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES expense_products(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shops (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expenses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            bill_no VARCHAR(50) NOT NULL UNIQUE,
            date DATE NOT NULL,
            shop_id INT DEFAULT NULL,
            product_id INT DEFAULT NULL,
            quantity INT DEFAULT 1,
            unit_price DECIMAL(10,2) DEFAULT 0.00,
            amount DECIMAL(10,2) NOT NULL,
            balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            month VARCHAR(7) NOT NULL,
            is_paid TINYINT(1) NOT NULL DEFAULT 0,
            cn VARCHAR(50) DEFAULT NULL,
            payment_date DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_month (month),
            KEY idx_date (date),
            FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE SET NULL,
            FOREIGN KEY (product_id) REFERENCES expense_products(id) ON DELETE SET NULL
        )
    ");
} catch (PDOException $e) { /* Ignore if already up to date */ }

// SEPARATE FILTERS FOR SUMMARY AND EXPENSE DETAILS

// 1. Summary filter (month range)
$summary_start = $_GET['summary_start'] ?? date('Y-01');
$summary_end = $_GET['summary_end'] ?? date('Y-m');

// 2. Expense details filter (date range)
$details_start = $_GET['details_start'] ?? date('Y-m-01', strtotime('-1 month'));
$details_end = $_GET['details_end'] ?? date('Y-m-t');

// 3. Payment status filter
$payment_filter = $_GET['payment_filter'] ?? 'all';

try {
    // 1. Expenses for selected DATE range with payment status filter
    $sql = "
        SELECT e.*, p.name AS product_name, p.company, p.current_price,
               c.name AS category_name, s.name as shop, s.phone
        FROM expenses e
        LEFT JOIN expense_products p ON e.product_id = p.id
        LEFT JOIN expense_categories c ON p.category_id = c.id
        LEFT JOIN shops s ON e.shop_id = s.id
        WHERE e.date BETWEEN ? AND ?
    ";

    $params = [$details_start, $details_end];

    if ($payment_filter == 'paid') {
        $sql .= " AND e.is_paid = 1";
    } elseif ($payment_filter == 'unpaid') {
        $sql .= " AND e.is_paid = 0";
    }

    $sql .= " ORDER BY e.date, e.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Monthly totals for SUMMARY (uses month range)
    $stmt_range = $pdo->prepare("
        SELECT
            DATE_FORMAT(date, '%M,%Y') as month_year,
            DATE_FORMAT(date, '%M-%Y') as ref,
            SUM(amount) as total
        FROM expenses
        WHERE DATE_FORMAT(date, '%Y-%m') BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY date
    ");
    $stmt_range->execute([$summary_start, $summary_end]);
    $monthly_totals = $stmt_range->fetchAll(PDO::FETCH_ASSOC);

    // 3. Grand total (for summary)
    $grand_total = array_sum(array_column($monthly_totals, 'total'));

    // 4. Categories for dropdowns
    $categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // 5. Next bill no
    $last = $pdo->query("SELECT bill_no FROM expenses ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next_serial = $last ? (int)substr($last, 3) + 1 : 1001;
    $next_bill_no = "EXP" . $next_serial;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Expenses | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .no-print { }
        @media print {
            .no-print { display: none !important; }
            body { margin: 15px; font-family: Arial, sans-serif; }
            table { font-size: 11px; width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000; padding: 4px; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .print-only { display: block !important; }
            #printable, #single-print { width: 100%; }
            h5 { margin: 8px 0; font-size: 14px; }
            .signature { margin-top: 30px; display: flex; justify-content: space-between; }
            .invoice-box { border: 1px solid #000; padding: 15px; margin: 20px 0; }
            .invoice-header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        }
        .print-only { display: none; }
        .badge-paid { background-color: #28a745; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.8em; display: inline-block; }
        .badge-unpaid { background-color: #ffc107; color: black; padding: 5px 10px; border-radius: 20px; font-size: 0.8em; display: inline-block; }
        .payment-filter { margin-left: 10px; }
        .payment-filter .btn-check:checked + .btn { background-color: #0d6efd; color: white; }
        .cn-badge { background-color: #17a2b8; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; display: inline-block; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h1 class="h3 text-success"><i class="fas fa-money-bill-wave"></i> Manage Expenses</h1>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-success" onclick="printSummaryOnly()">
                        <i class="fas fa-file-invoice"></i> Print Summary Only
                    </button>
                    <button class="btn btn-outline-primary" onclick="printReport()">
                        <i class="fas fa-print"></i> Print Full Report
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus"></i> Add Expense
                    </button>
                </div>
            </div>

            <div id="alert-placeholder"></div>

            <!-- SUMMARY CARD -->
            <div class="card shadow mt-4 no-print">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Summary <?= date('M Y', strtotime($summary_start)) ?> to <?= date('M Y', strtotime($summary_end)) ?>
                    </h6>
                    <form method="GET" class="d-flex gap-2">
                        <input type="month" name="summary_start" value="<?= $summary_start ?>" class="form-control form-control-sm" required>
                        <input type="month" name="summary_end" value="<?= $summary_end ?>" class="form-control form-control-sm" required>
                        <input type="hidden" name="details_start" value="<?= $details_start ?>">
                        <input type="hidden" name="details_end" value="<?= $details_end ?>">
                        <button class="btn btn-sm btn-primary w-100">Update Summary</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Amount</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_totals as $m): ?>
                                <tr>
                                    <td><?= $m['month_year'] ?></td>
                                    <td class="text-end"><?= number_format($m['total']) ?></td>
                                    <td><?= $m['ref'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-danger fw-bold">
                                    <td>Total Expense</td>
                                    <td class="text-end"><?= number_format($grand_total) ?></td>
                                    <td>Summary</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- EXPENSES TABLE -->
            <div class="card shadow mt-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center no-print">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Expenses from <?= date('d M Y', strtotime($details_start)) ?> to <?= date('d M Y', strtotime($details_end)) ?>
                        <span class="badge bg-success ms-2"><?= count($expenses) ?></span>
                    </h6>
                    <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="me-2 small">From:</label>
                        <input type="date" name="details_start" class="form-control form-control-sm"
                               value="<?= $details_start ?>" style="width:150px">
                        <label class="me-2 small">To:</label>
                        <input type="date" name="details_end" class="form-control form-control-sm"
                               value="<?= $details_end ?>" style="width:150px">

                        <div class="btn-group" role="group" style="margin-left: 10px;">
                            <input type="radio" class="btn-check" name="payment_filter" id="filterAll"
                                   value="all" <?= ($payment_filter ?? 'all') == 'all' ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="btn btn-outline-secondary btn-sm" for="filterAll">All</label>

                            <input type="radio" class="btn-check" name="payment_filter" id="filterUnpaid"
                                   value="unpaid" <?= ($payment_filter ?? '') == 'unpaid' ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="btn btn-outline-warning btn-sm" for="filterUnpaid">Unpaid</label>

                            <input type="radio" class="btn-check" name="payment_filter" id="filterPaid"
                                   value="paid" <?= ($payment_filter ?? '') == 'paid' ? 'checked' : '' ?>
                                   onchange="this.form.submit()">
                            <label class="btn btn-outline-success btn-sm" for="filterPaid">Paid</label>
                        </div>

                        <input type="hidden" name="summary_start" value="<?= $summary_start ?>">
                        <input type="hidden" name="summary_end" value="<?= $summary_end ?>">

                        <button class="btn btn-sm btn-primary">Filter</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="expensesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>S.No</th>
                                    <th>Bill No</th>
                                    <th>Date</th>
                                    <th>Shop</th>
                                    <th>Phone</th>
                                    <th>Qty</th>
                                    <th>Rs.</th>
                                    <th>Balance</th>
                                    <th>Category</th>
                                    <th>Products</th>
                                    <th>C.N</th>
                                    <th>Status</th>
                                    <th class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $running_balance = 0;
                                $sno = 1;
                                foreach ($expenses as $e):
                                    $running_balance += $e['amount'];
                                    $is_paid = $e['is_paid'] ?? 0;
                                    $cn_number = $e['cn'] ?? '';
                                ?>
                                <tr>
                                    <td><?= $sno++ ?></td>
                                    <td><code><?= $e['bill_no'] ?></code></td>
                                    <td><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                    <td><?= htmlspecialchars($e['shop'] ?? 'N/A') ?></td>
                                    <td><?= $e['phone'] ?: '<em>N/A</em>' ?></td>
                                    <td class="text-center"><?= $e['quantity'] ?? 1 ?></td>
                                    <td class="text-end text-danger fw-bold"><?= number_format($e['amount']) ?></td>
                                    <td class="text-end"><?= number_format($e['balance']) ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($e['category_name'] ?? '—') ?></span></td>
                                    <td><?= htmlspecialchars(($e['product_name'] ?? '') . ($e['company'] ? ' ('.$e['company'].')' : '')) ?></td>
                                    <td>
                                        <?php if ($cn_number): ?>
                                            <span class="cn-badge"><?= htmlspecialchars($cn_number) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_paid): ?>
                                            <span class="badge-paid">Paid</span>
                                        <?php else: ?>
                                            <span class="badge-unpaid">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <button class="btn btn-sm btn-primary" onclick="printSingle2(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>)" title="Print Invoice">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editExpense2(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>)" data-bs-toggle="modal" data-bs-target="#editModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteExpense2(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['bill_no'])) ?>')" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PRINTABLE CONTENT -->
            <div id="printable" class="print-only">
                <div id="summary-page">
                    <div class="text-center mb-4">
                        <h5>Muhaddisa School of Science and Technology — Expense Summary</h5>
                    </div>
                    <div class="text-center fw-bold mb-4">
                        Summary <?= date('M Y', strtotime($summary_start)) ?> to <?= date('M Y', strtotime($summary_end)) ?>
                    </div>

                    <table style="font-size:11px; width:100%; border-collapse:collapse; margin-bottom:30px;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="border:1px solid #000; padding:5px;">Month</th>
                                <th style="border:1px solid #000; padding:5px; text-align:right;">Amount</th>
                                <th style="border:1px solid #000; padding:5px;">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_totals as $m): ?>
                            <tr>
                                <td style="border:1px solid #000; padding:5px;"><?= $m['month_year'] ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:right;"><?= number_format($m['total']) ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $m['ref'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:bold; background:#f8d7da;">
                                <td style="border:1px solid #000; padding:5px;">Total Expense</td>
                                <td style="border:1px solid #000; padding:5px; text-align:right;"><?= number_format($grand_total) ?></td>
                                <td style="border:1px solid #000; padding:5px;">Summary</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="page-break-before: always;">
                    <div class="text-center mb-3">
                        <h5>Muhaddisa School of Science and Technology — Expense Detail Report</h5>
                    </div>
                    <table style="font-size:11px; width:100%; border-collapse:collapse;">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th style="border:1px solid #000; padding:5px;">S.No</th>
                                <th style="border:1px solid #000; padding:5px;">Bill No</th>
                                <th style="border:1px solid #000; padding:5px;">Date</th>
                                <th style="border:1px solid #000; padding:5px;">Shop</th>
                                <th style="border:1px solid #000; padding:5px;">Phone</th>
                                <th style="border:1px solid #000; padding:5px;">Qty</th>
                                <th style="border:1px solid #000; padding:5px;">Rs.</th>
                                <th style="border:1px solid #000; padding:5px;">Balance</th>
                                <th style="border:1px solid #000; padding:5px;">Category</th>
                                <th style="border:1px solid #000; padding:5px;">Items</th>
                                <th style="border:1px solid #000; padding:5px;">C.N</th>
                                <th style="border:1px solid #000; padding:5px;">Status</th>
                                <th style="border:1px solid #000; padding:5px;">Month</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($expenses as $i => $e):
                                $is_paid = $e['is_paid'] ?? 0;
                                $cn_number = $e['cn'] ?? '';
                            ?>
                            <tr>
                                <td style="border:1px solid #000; padding:5px;"><?= $i + 1 ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['bill_no'] ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= htmlspecialchars($e['shop'] ?? 'N/A') ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['phone'] ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:center;"><?= $e['quantity'] ?? 1 ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:right;"><?= number_format($e['amount']) ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:right;"><?= number_format($e['balance']) ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['category_name'] ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['product_name'] . ($e['company'] ? ' ('.$e['company'].')' : '') ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $cn_number ?></td>
                                <td style="border:1px solid #000; padding:5px; text-align:center;"><?= $is_paid ? 'Paid' : 'Unpaid' ?></td>
                                <td style="border:1px solid #000; padding:5px;"><?= $e['month'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="single-print" class="print-only" style="display:none;"></div>

        </div>
    </div>
    <?php include 'footer.php'; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-lg">
        <form id="addForm">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5>Add Expense</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>Bill No</label>
                            <input type="text" name="bill_no" class="form-control" value="<?= $next_bill_no ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label>Date</label>
                            <input type="date" name="date" id="dateInput" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label>Shop</label>
                            <select name="shop_id" id="shopSelect" class="form-select" onchange="updateShopDetails()" required>
                                <option value="">Select Shop</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Phone</label>
                            <input type="text" id="phoneInput" name="phone" class="form-control" readonly style="background:#f8f9fa;">
                            <small class="text-muted">Auto-filled from shop</small>
                        </div>
                        <div class="col-md-4">
                            <label>Category</label>
                            <select name="category_id" class="form-select" onchange="syncProducts(this, 'product_id_add')" required>
                                <option value="">Select</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Product</label>
                            <select name="product_id" id="product_id_add" class="form-select" onchange="updateProductPrice()" required>
                                <option value="">Select Category First</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Quantity</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" value="1" min="1" required
                                   oninput="handleQuantityChange()" onchange="handleQuantityChange()">
                        </div>
                        <div class="col-md-3">
                            <label>Unit Price</label>
                            <input type="number" step="0.01" name="unit_price" id="unit_price" class="form-control" required
                                   oninput="handleUnitPriceChange()" onchange="handleUnitPriceChange()">
                            <small class="text-muted">Edit to auto-calculate total</small>
                        </div>
                        <div class="col-md-3">
                            <label>Total Amount</label>
                            <input type="number" step="0.01" name="amount" id="total_amount" class="form-control"
                                   oninput="handleTotalAmountChange()" onchange="handleTotalAmountChange()">
                            <small class="text-muted">Edit to auto-calculate unit price</small>
                        </div>
                        <div class="col-md-3">
                            <label>Cheque No. <small class="text-muted">(optional)</small></label>
                            <input type="text" name="cn" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAndContinueBtn">
                        <i class="fas fa-save"></i> Save & Continue
                    </button>
                    <button type="button" class="btn btn-success" id="saveAndExitBtn">
                        <i class="fas fa-save"></i> Save & Exit
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-lg">
        <form id="editForm">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5>Edit Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label>Bill No</label>
                            <input type="text" name="bill_no" class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label>Date</label>
                            <input type="date" name="date" id="editDateInput" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label>Shop</label>
                            <select name="shop_id" id="editShopSelect" class="form-select" onchange="updateEditShopDetails()">
                                <option value="">Select Shop</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Phone</label>
                            <input type="text" id="editPhoneInput" name="phone" class="form-control" readonly style="background:#f8f9fa;">
                            <small class="text-muted">Auto-filled from shop</small>
                        </div>
                        <div class="col-md-4">
                            <label>Category</label>
                            <select name="category_id" class="form-select" onchange="syncProducts(this, 'product_id_edit')" required>
                                <option value="">Select</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Product</label>
                            <select name="product_id" id="product_id_edit" class="form-select" onchange="updateEditProductPrice()" required>
                                <option value="">Select Category First</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Quantity</label>
                            <input type="number" name="quantity" id="editQuantity" class="form-control" value="1" min="1" required
                                   oninput="handleEditQuantityChange()" onchange="handleEditQuantityChange()">
                        </div>
                        <div class="col-md-3">
                            <label>Unit Price</label>
                            <input type="number" step="0.01" name="unit_price" id="editUnitPrice" class="form-control" required
                                   oninput="handleEditUnitPriceChange()" onchange="handleEditUnitPriceChange()">
                            <small class="text-muted">Edit to auto-calculate total</small>
                        </div>
                        <div class="col-md-3">
                            <label>Total Amount</label>
                            <input type="number" step="0.01" name="amount" id="editTotalAmount" class="form-control"
                                   oninput="handleEditTotalAmountChange()" onchange="handleEditTotalAmountChange()">
                            <small class="text-muted">Edit to auto-calculate unit price</small>
                        </div>
                        <div class="col-md-3">
                            <label>Month</label>
                            <input type="text" id="editMonthField" class="form-control" readonly style="background:#f8f9fa;">
                            <small class="text-muted">For display only — recomputed from date on save</small>
                        </div>
                        <div class="col-md-3">
                            <label>Cheque No. <small class="text-muted">(optional)</small></label>
                            <input type="text" name="cn" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5>Confirm Delete</h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Delete <strong id="deleteName"></strong>?
                <input type="hidden" id="deleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/js/expense-management.js"></script>
</body>
</html>
