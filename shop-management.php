<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    $shops = $pdo->query("
        SELECT
            s.*,
            COUNT(e.id) as expense_count,
            COALESCE(SUM(e.amount), 0) as total_expense,
            MAX(e.date) as last_expense_date
        FROM shops s
        LEFT JOIN expenses e ON s.id = e.shop_id
        GROUP BY s.id
        ORDER BY s.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($shops as &$shop) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE shop_id = ?");
        $stmt->execute([$shop['id']]);
        $verified_total = $stmt->fetchColumn();
        if (abs($shop['total_expense'] - $verified_total) > 0.01) {
            error_log("Total mismatch for shop {$shop['id']}: {$shop['total_expense']} vs {$verified_total}");
            $shop['total_expense'] = $verified_total;
        }
    }
    unset($shop);
} catch (Exception $e) {
    $shops = [];
    error_log("Error fetching shops: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Management | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .shop-table th { background-color: #f8f9fa; font-weight: 600; }
        .badge-count { font-size: 0.8em; padding: 3px 8px; }
        .action-buttons { white-space: nowrap; }
        .table-responsive { border-radius: 8px; border: 1px solid #dee2e6; }
        .total-amount { font-weight: bold; color: #198754; }
        .last-expense { font-size: 0.85em; color: #6c757d; }
        .modal-dialog-scrollable .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }
        #shopExpensesTable thead th { background-color: #e9ecef; font-weight: 600; white-space: nowrap; }
        #shopExpensesTable tbody td { vertical-align: middle; }
        #filteredTotal { font-size: 1rem; padding: 8px 12px; }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom: 15px; }
        .dataTables_wrapper .dataTables_length { float: left; }
        .dataTables_wrapper .dataTables_filter { float: right; }
        .dataTables_wrapper .dataTables_info { float: left; margin-top: 10px; }
        .dataTables_wrapper .dataTables_paginate { float: right; margin-top: 10px; }
        .dataTables_wrapper:after { content: ""; display: table; clear: both; }
        .dataTables_length select { width: auto; display: inline-block; }
        #verificationWarning { margin-bottom: 15px; }
        .expense-checkbox { cursor: pointer; width: 18px; height: 18px; }
        #selectAllCheckbox { cursor: pointer; width: 18px; height: 18px; }
        .payment-toolbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .badge-unpaid { background-color: #ffc107; color: #000; }
        .badge-paid { background-color: #28a745; color: #fff; }
        .selected-row { background-color: #e3f2fd !important; }
        .btn-group .btn-check:checked + .btn { background-color: #0d6efd; color: white; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-gray-800"><i class="fas fa-store"></i> Shop Management</h1>
                <button class="btn btn-primary" onclick="showAddShopModal()">
                    <i class="fas fa-plus"></i> Add New Shop
                </button>
            </div>

            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">All Shops</h6>
                    <span class="badge bg-secondary">Total: <?= count($shops) ?> shops</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover shop-table" id="shopsTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="20%">Shop Name</th>
                                    <th width="15%">Contact</th>
                                    <th width="20%">Address</th>
                                    <th width="10%" class="text-center">Expenses</th>
                                    <th width="15%" class="text-end">Total Amount</th>
                                    <th width="15%" class="text-center">Last Expense</th>
                                    <th width="15%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shops as $index => $shop): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><i class="fas fa-store text-primary me-1"></i> <?= htmlspecialchars($shop['name']) ?></strong></td>
                                    <td><?= $shop['phone'] ? htmlspecialchars($shop['phone']) : '<span class="text-muted"><i class="fas fa-phone-slash me-1"></i> N/A</span>' ?></td>
                                    <td>
                                        <?php if ($shop['address']): ?>
                                        <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($shop['address']) ?>">
                                            <?= strlen($shop['address']) > 30 ? substr(htmlspecialchars($shop['address']), 0, 30) . '...' : htmlspecialchars($shop['address']) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-info badge-count"><i class="fas fa-receipt me-1"></i> <?= $shop['expense_count'] ?></span></td>
                                    <td class="text-end total-amount" data-total="<?= $shop['total_expense'] ?>">
                                        <?= $shop['total_expense'] > 0 ? 'Rs. ' . number_format($shop['total_expense'], 2) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td class="text-center last-expense">
                                        <?= $shop['last_expense_date'] ? date('d M Y', strtotime($shop['last_expense_date'])) : '<span class="text-muted">No expenses</span>' ?>
                                    </td>
                                    <td class="text-center action-buttons">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewShopExpenses(<?= $shop['id'] ?>, '<?= htmlspecialchars(addslashes($shop['name'])) ?>', <?= $shop['total_expense'] ?>)" data-bs-toggle="tooltip" title="View Expenses"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="showEditShopModal(<?= $shop['id'] ?>, '<?= htmlspecialchars(addslashes($shop['name'])) ?>', '<?= htmlspecialchars(addslashes($shop['phone'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($shop['address'] ?? '')) ?>')" data-bs-toggle="tooltip" title="Edit Shop"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteShop(<?= $shop['id'] ?>, '<?= htmlspecialchars(addslashes($shop['name'])) ?>')" data-bs-toggle="tooltip" title="Delete Shop"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="4" class="text-end">Grand Totals:</th>
                                    <th class="text-center"><span class="badge bg-primary"><?= array_sum(array_column($shops, 'expense_count')) ?> expenses</span></th>
                                    <th class="text-end"><strong>Rs. <?= number_format(array_sum(array_column($shops, 'total_expense')), 2) ?></strong></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</div>

<!-- Add Shop Modal -->
<div class="modal fade" id="addShopModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Shop</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addShopForm">
                    <div class="mb-3">
                        <label class="form-label">Shop Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g., 03001234567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Shop address..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveShop()"><i class="fas fa-save"></i> Save Shop</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Shop Modal -->
<div class="modal fade" id="editShopModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Shop</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editShopForm">
                    <input type="hidden" name="action" value="edit_shop">
                    <input type="hidden" name="id" id="edit_shop_id">
                    <div class="mb-3">
                        <label class="form-label">Shop Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_shop_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" id="edit_shop_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_shop_address" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="updateShop()"><i class="fas fa-save"></i> Update Shop</button>
            </div>
        </div>
    </div>
</div>

<!-- Shop Expenses Modal -->
<div class="modal fade" id="shopExpensesModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="shopExpensesTitle"><i class="fas fa-receipt"></i> Shop Expenses</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="verificationWarning"></div>

                <div class="card mb-3 border-primary">
                    <div class="card-header bg-primary text-white py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-credit-card"></i> Payment Management</span>
                            <div>
                                <button class="btn btn-sm btn-light" onclick="toggleAllRows()" id="toggleAllBtn">
                                    <i class="fas fa-check-double"></i> Select All
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="filterPaid" id="filterAll" autocomplete="off" checked onclick="filterPaidStatus('all')">
                                    <label class="btn btn-outline-secondary btn-sm" for="filterAll">All</label>

                                    <input type="radio" class="btn-check" name="filterPaid" id="filterUnpaid" autocomplete="off" onclick="filterPaidStatus('unpaid')">
                                    <label class="btn btn-outline-warning btn-sm" for="filterUnpaid">Unpaid</label>

                                    <input type="radio" class="btn-check" name="filterPaid" id="filterPaid" autocomplete="off" onclick="filterPaidStatus('paid')">
                                    <label class="btn btn-outline-success btn-sm" for="filterPaid">Paid</label>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="startDate" placeholder="Start Date">
                                    <span class="input-group-text">to</span>
                                    <input type="date" class="form-control" id="endDate" placeholder="End Date">
                                    <button class="btn btn-primary btn-sm" onclick="applyDateFilter()" title="Apply Filter"><i class="fas fa-filter"></i></button>
                                    <button class="btn btn-secondary btn-sm" onclick="clearDateFilter()" title="Clear Filter"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <span class="badge bg-success fs-6 p-2" id="filteredTotal">Total: Rs. 0.00</span>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge bg-warning text-dark fs-6 p-2" id="unpaidTotal">Unpaid: Rs. 0.00</span>
                            </div>
                        </div>

                        <div class="row mt-2" id="paymentActions" style="display: none;">
                            <div class="col-md-12">
                                <div class="alert alert-info p-2 mb-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-check-circle"></i>
                                            <strong id="selectedCount">0</strong> items selected
                                            (Total: Rs. <strong id="selectedTotal">0.00</strong>)
                                        </span>
                                        <div>
                                            <button class="btn btn-sm btn-success me-2" onclick="showPaymentModal()">
                                                <i class="fas fa-check-circle"></i> Mark as Paid
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                                                <i class="fas fa-times"></i> Clear Selection
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="shopExpensesTable" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th width="3%" class="text-center">
                                    <input type="checkbox" id="selectAllCheckbox" onclick="toggleAllCheckboxes(this)">
                                </th>
                                <th width="3%">S.No</th>
                                <th width="8%">Date</th>
                                <th width="8%">Bill No</th>
                                <th width="12%">Product</th>
                                <th width="5%" class="text-center">Qty</th>
                                <th width="8%" class="text-end">Unit Price</th>
                                <th width="8%" class="text-end">Amount</th>
                                <th width="8%">C.N</th>
                                <th width="5%">Month</th>
                                <th width="8%">Category</th>
                                <th width="8%">Status</th>
                            </tr>
                        </thead>
                        <tbody id="shopExpensesBody">
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th colspan="5" class="text-end">Totals:</th>
                                <th id="totalQuantity" class="text-center">0.00</th>
                                <th></th>
                                <th id="totalAmount" class="text-end">Rs. 0.00</th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="printExpenses()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Process Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="shop_id" id="payment_shop_id">
                    <div id="selectedItemsList"></div>

                    <div class="mb-3">
                        <label class="form-label">Selected Items Summary</label>
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Total Items:</span>
                                    <strong id="paymentItemsCount">0</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Amount:</span>
                                    <strong id="paymentTotalAmount">Rs. 0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cheque Number <span class="text-danger">*</span></label>
                        <input type="text" name="cn" class="form-control" required placeholder="Enter cheque number">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="processPayment()">
                    <i class="fas fa-check-circle"></i> Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
let shopExpensesData = [];
let currentShopId = null;
let currentShopName = '';
let expectedTotal = 0;
let expensesDataTable = null;
let selectedExpenses = new Set();

$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#shopsTable')) {
        $('#shopsTable').DataTable().destroy();
    }
    $('#shopsTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 7] },
            { searchable: false, targets: [0, 4, 5, 6, 7] }
        ],
        language: {
            search: "Search shops:",
            lengthMenu: "Show _MENU_ shops per page",
            info: "Showing _START_ to _END_ of _TOTAL_ shops",
            infoEmpty: "Showing 0 to 0 of 0 shops",
            infoFiltered: "(filtered from _MAX_ total shops)",
            emptyTable: '<i class="fas fa-store fa-2x mb-3 d-block"></i>No shops found. Click "Add New Shop" to add your first shop!',
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                previous: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>'
            }
        }
    });

    $('[data-bs-toggle="tooltip"]').each(function() {
        new bootstrap.Tooltip(this);
    });
});

function showAddShopModal() {
    document.getElementById('addShopForm').reset();
    new bootstrap.Modal(document.getElementById('addShopModal')).show();
}

function saveShop() {
    const form = document.getElementById('addShopForm');
    const formData = new FormData(form);
    formData.append('action', 'add_shop');

    const saveBtn = document.querySelector('#addShopModal .btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    fetch('api_expense_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('addShopModal')).hide();
            setTimeout(() => location.reload(), 300);
        } else {
            alert('Error: ' + data.message);
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function showEditShopModal(id, name, phone, address) {
    document.getElementById('edit_shop_id').value = id;
    document.getElementById('edit_shop_name').value = name;
    document.getElementById('edit_shop_phone').value = phone || '';
    document.getElementById('edit_shop_address').value = address || '';
    new bootstrap.Modal(document.getElementById('editShopModal')).show();
}

function updateShop() {
    const form = document.getElementById('editShopForm');
    const formData = new FormData(form);

    const updateBtn = document.querySelector('#editShopModal .btn-warning');
    const originalText = updateBtn.innerHTML;
    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    updateBtn.disabled = true;

    fetch('api_expense_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            bootstrap.Modal.getInstance(document.getElementById('editShopModal')).hide();
            setTimeout(() => location.reload(), 300);
        } else {
            alert('Error: ' + data.message);
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        updateBtn.innerHTML = originalText;
        updateBtn.disabled = false;
    });
}

function deleteShop(id, name) {
    if (!confirm(`Are you sure you want to delete shop "${name}"?\n\nNote: This will only work if no expenses are linked to this shop.`)) return;

    const formData = new FormData();
    formData.append('action', 'delete_shop');
    formData.append('id', id);

    fetch('api_expense_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Cannot delete: ' + data.message);
        }
    })
    .catch(err => alert('Network error: ' + err.message));
}

function viewShopExpenses(shopId, shopName, expectedTotalAmount) {
    currentShopId = shopId;
    currentShopName = shopName;
    expectedTotal = parseFloat(expectedTotalAmount) || 0;

    document.getElementById('shopExpensesTitle').innerHTML =
        `<i class="fas fa-receipt"></i> Expenses: ${shopName} (Expected: Rs. ${expectedTotal.toFixed(2)})`;

    shopExpensesData = [];
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    clearSelection();

    document.getElementById('shopExpensesBody').innerHTML =
        '<tr><td colspan="12" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div> Loading expenses...</td></tr>';

    document.getElementById('totalQuantity').innerText = '0.00';
    document.getElementById('totalAmount').innerHTML = 'Rs. 0.00';
    document.getElementById('filteredTotal').innerHTML = 'Total: Rs. 0.00';
    document.getElementById('unpaidTotal').innerHTML = 'Unpaid: Rs. 0.00';
    document.getElementById('verificationWarning').innerHTML = '';

    if (expensesDataTable) {
        expensesDataTable.destroy();
        expensesDataTable = null;
    }
    if ($.fn.DataTable.isDataTable('#shopExpensesTable')) {
        $('#shopExpensesTable').DataTable().destroy();
    }
    $('#shopExpensesBody').empty();

    fetch(`api_expense_handler.php?action=get_shop_expenses&shop_id=${shopId}&t=${new Date().getTime()}`)
        .then(r => r.json())
        .then(expenses => {
            if (!Array.isArray(expenses)) {
                document.getElementById('shopExpensesBody').innerHTML =
                    '<tr><td colspan="12" class="text-center text-danger py-4">Invalid data format received</td></tr>';
                return;
            }

            const uniqueExpenses = [];
            const seenIds = new Set();
            expenses.forEach(expense => {
                if (expense.id && !seenIds.has(expense.id)) {
                    seenIds.add(expense.id);
                    uniqueExpenses.push(expense);
                } else if (!expense.id) {
                    const key = `${expense.date}_${expense.amount}_${expense.product_name}`;
                    if (!seenIds.has(key)) {
                        seenIds.add(key);
                        uniqueExpenses.push(expense);
                    }
                }
            });

            shopExpensesData = uniqueExpenses;

            const fetchedTotal = uniqueExpenses.reduce((sum, e) => sum + (parseFloat(e.amount) || 0), 0);

            const diff = Math.abs(fetchedTotal - expectedTotal);
            if (diff > 0.01) {
                const warningHtml = `
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>Warning!</strong> Total mismatch!
                        Expected: Rs. ${expectedTotal.toFixed(2)},
                        Fetched: Rs. ${fetchedTotal.toFixed(2)}.
                        Difference: Rs. ${(expectedTotal - fetchedTotal).toFixed(2)}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                document.getElementById('verificationWarning').innerHTML = warningHtml;
            }

            if (uniqueExpenses.length === 0) {
                document.getElementById('shopExpensesBody').innerHTML = `
                    <tr>
                        <td colspan="12" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No expenses found
                        </td>
                    </tr>`;
            } else {
                initializeExpensesDataTable(uniqueExpenses);
            }

            new bootstrap.Modal(document.getElementById('shopExpensesModal')).show();
        })
        .catch(error => {
            document.getElementById('shopExpensesBody').innerHTML =
                '<tr><td colspan="12" class="text-center text-danger py-4">Error loading expenses</td></tr>';
        });
}

function initializeExpensesDataTable(expenses) {
    expenses.sort((a, b) => new Date(b.date) - new Date(a.date));

    const tableData = expenses.map((expense, index) => {
        const amount = parseFloat(expense.amount) || 0;
        const quantity = parseFloat(expense.quantity) || 1;
        const unitPrice = parseFloat(expense.unit_price) || (quantity > 0 ? amount / quantity : amount);
        const isPaid = expense.is_paid == 1 || expense.is_paid == true;

        let formattedDate = '—';
        try {
            if (expense.date) {
                const dateObj = new Date(expense.date);
                if (!isNaN(dateObj.getTime())) {
                    formattedDate = dateObj.toLocaleDateString('en-GB');
                }
            }
        } catch (e) {}

        let month = '—';
        try {
            if (expense.date) {
                const dateObj = new Date(expense.date);
                if (!isNaN(dateObj.getTime())) {
                    month = dateObj.toLocaleString('default', { month: 'long', year: 'numeric' });
                }
            }
        } catch (e) {}

        return {
            id: expense.id,
            sno: index + 1,
            date: formattedDate,
            rawDate: expense.date,
            bill_no: expense.bill_no || '—',
            product_name: expense.product_name || '—',
            quantity: quantity,
            unit_price: unitPrice,
            amount: amount,
            rawAmount: amount,
            cn: expense.cn || '—',
            month: month,
            category: expense.category_name || '—',
            is_paid: isPaid
        };
    });

    updateTotals(tableData);

    const unpaidTotal = tableData.reduce((sum, item) => sum + (item.is_paid ? 0 : item.amount), 0);
    document.getElementById('unpaidTotal').innerHTML = `Unpaid: Rs. ${unpaidTotal.toFixed(2)}`;

    if (expensesDataTable) {
        expensesDataTable.destroy();
    }

    expensesDataTable = $('#shopExpensesTable').DataTable({
        data: tableData,
        columns: [
            {
                data: null,
                className: 'text-center',
                render: function(data, type, row) {
                    return `<input type="checkbox" class="expense-checkbox" value="${row.id}" onchange="toggleCheckbox(this, ${row.id})" ${selectedExpenses.has(row.id.toString()) ? 'checked' : ''}>`;
                },
                orderable: false
            },
            { data: 'sno' },
            { data: 'date' },
            { data: 'bill_no' },
            { data: 'product_name' },
            {
                data: 'quantity',
                className: 'text-center',
                render: data => data.toFixed(2)
            },
            {
                data: 'unit_price',
                className: 'text-end',
                render: data => 'Rs. ' + data.toFixed(2)
            },
            {
                data: 'amount',
                className: 'text-end',
                render: data => '<strong>Rs. ' + data.toFixed(2) + '</strong>'
            },
            {
                data: 'cn',
                render: function(data) {
                    return data && data != '—' ? data : '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'month',
                render: data => '<span class="badge bg-secondary">' + data + '</span>'
            },
            { data: 'category' },
            {
                data: 'is_paid',
                className: 'text-center',
                render: function(data) {
                    return data ?
                        '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>' :
                        '<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Unpaid</span>';
                }
            }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[2, 'desc']],
        destroy: true,
        language: {
            search: "Search expenses:",
            lengthMenu: "Show _MENU_ expenses per page",
            info: "Showing _START_ to _END_ of _TOTAL_ expenses",
            infoEmpty: "Showing 0 to 0 of 0 expenses",
            infoFiltered: "(filtered from _MAX_ total expenses)",
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                previous: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>'
            }
        },
        drawCallback: function(settings) {
            const filteredData = this.api().rows({ filter: 'applied' }).data().toArray();
            updateTotals(filteredData);

            const filteredUnpaidTotal = filteredData.reduce((sum, item) => sum + (item.is_paid ? 0 : item.amount), 0);
            document.getElementById('unpaidTotal').innerHTML = `Unpaid: Rs. ${filteredUnpaidTotal.toFixed(2)}`;

            document.querySelectorAll('.expense-checkbox').forEach(cb => {
                const id = cb.value;
                cb.checked = selectedExpenses.has(id.toString());
            });

            const checkboxes = document.querySelectorAll('.expense-checkbox');
            const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
            document.getElementById('selectAllCheckbox').checked = allChecked;

            updatePaymentActions();
            updateToggleButtonText();
        }
    });
}

function toggleAllRows() {
    const checkboxes = document.querySelectorAll('.expense-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);

    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
        const id = cb.value;
        if (cb.checked) {
            selectedExpenses.add(id);
        } else {
            selectedExpenses.delete(id);
        }
    });

    document.getElementById('selectAllCheckbox').checked = !allChecked;
    updatePaymentActions();
    updateToggleButtonText();
}

function toggleCheckbox(checkbox, id) {
    if (checkbox.checked) {
        selectedExpenses.add(id.toString());
    } else {
        selectedExpenses.delete(id.toString());
        document.getElementById('selectAllCheckbox').checked = false;
    }
    updatePaymentActions();
    updateToggleButtonText();
}

function toggleAllCheckboxes(headerCheckbox) {
    const checkboxes = document.querySelectorAll('.expense-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = headerCheckbox.checked;
        const id = cb.value;
        if (headerCheckbox.checked) {
            selectedExpenses.add(id.toString());
        } else {
            selectedExpenses.delete(id.toString());
        }
    });
    updatePaymentActions();
    updateToggleButtonText();
}

function updateToggleButtonText() {
    const checkboxes = document.querySelectorAll('.expense-checkbox');
    const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
    const toggleBtn = document.getElementById('toggleAllBtn');
    if (toggleBtn) {
        toggleBtn.innerHTML = allChecked ?
            '<i class="fas fa-times"></i> Deselect All' :
            '<i class="fas fa-check-double"></i> Select All';
    }
}

function updatePaymentActions() {
    const selectedCount = selectedExpenses.size;
    const paymentActions = document.getElementById('paymentActions');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedTotalSpan = document.getElementById('selectedTotal');

    if (selectedCount > 0) {
        paymentActions.style.display = 'block';
        selectedCountSpan.textContent = selectedCount;

        let total = 0;
        if (expensesDataTable) {
            const allData = expensesDataTable.rows().data().toArray();
            selectedExpenses.forEach(id => {
                const expense = allData.find(item => item.id == id);
                if (expense) {
                    total += expense.amount || 0;
                }
            });
        }
        selectedTotalSpan.textContent = total.toFixed(2);
    } else {
        paymentActions.style.display = 'none';
    }
}

function clearSelection() {
    selectedExpenses.clear();
    document.querySelectorAll('.expense-checkbox').forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updatePaymentActions();
    updateToggleButtonText();
}

function filterPaidStatus(status) {
    if (!expensesDataTable) return;

    $.fn.dataTable.ext.search.pop();

    if (status !== 'all') {
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const rowData = expensesDataTable.row(dataIndex).data();
            const isPaid = rowData.is_paid == 1 || rowData.is_paid == true;
            return status === 'paid' ? isPaid : !isPaid;
        });
    }

    expensesDataTable.draw();
}

function applyDateFilter() {
    if (!expensesDataTable) return;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
        alert('Start date cannot be after end date');
        return;
    }

    $.fn.dataTable.ext.search.pop();

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (!startDate && !endDate) return true;

        const rowDate = data[2];
        let rowDateObj;

        try {
            const parts = rowDate.split('/');
            if (parts.length === 3) {
                rowDateObj = new Date(parts[2], parts[1] - 1, parts[0]);
            } else {
                rowDateObj = new Date(rowDate);
            }
            if (isNaN(rowDateObj.getTime())) return true;
        } catch (e) {
            return true;
        }

        const start = startDate ? new Date(startDate) : null;
        const end = endDate ? new Date(endDate) : null;

        if (start) start.setHours(0,0,0,0);
        if (end) end.setHours(23,59,59,999);

        if (start && end) return rowDateObj >= start && rowDateObj <= end;
        if (start) return rowDateObj >= start;
        if (end) return rowDateObj <= end;

        return true;
    });

    expensesDataTable.draw();
}

function clearDateFilter() {
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    if (expensesDataTable) {
        $.fn.dataTable.ext.search.pop();
        expensesDataTable.draw();
    }
}

function showPaymentModal() {
    if (selectedExpenses.size === 0) {
        alert('Please select at least one expense to mark as paid');
        return;
    }

    document.getElementById('payment_shop_id').value = currentShopId;

    let totalAmount = 0;
    let itemsList = '<div class="mb-2"><small class="text-muted">Selected items:</small></div>';

    if (expensesDataTable) {
        const allData = expensesDataTable.rows().data().toArray();
        const selectedIds = Array.from(selectedExpenses);

        selectedIds.forEach(id => {
            const expense = allData.find(item => item.id == id);
            if (expense) {
                totalAmount += expense.amount || 0;
                itemsList += `<input type="hidden" name="expense_ids[]" value="${id}">`;

                if (expense.product_name) {
                    itemsList += `<div class="small text-muted">• ${expense.product_name} - Rs. ${(expense.amount || 0).toFixed(2)}</div>`;
                }
            }
        });
    }

    document.getElementById('selectedItemsList').innerHTML = itemsList;
    document.getElementById('paymentItemsCount').textContent = selectedExpenses.size;
    document.getElementById('paymentTotalAmount').textContent = 'Rs. ' + totalAmount.toFixed(2);

    const shopModalInstance = bootstrap.Modal.getInstance(document.getElementById('shopExpensesModal'));
    if (shopModalInstance) shopModalInstance.hide();

    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function() {
    if (currentShopId) {
        viewShopExpenses(currentShopId, currentShopName, expectedTotal);
    }
});

function processPayment() {
    const form = document.getElementById('paymentForm');
    const chequeNumber = form.querySelector('[name="cn"]').value;

    if (!chequeNumber) {
        alert('Please enter cheque number');
        return;
    }

    const formData = new FormData(form);

    const paymentBtn = document.querySelector('#paymentModal .btn-success');
    const originalText = paymentBtn.innerHTML;
    paymentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    paymentBtn.disabled = true;

    fetch('api_expense_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            clearSelection();
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
        } else {
            alert('Error: ' + data.message);
            paymentBtn.innerHTML = originalText;
            paymentBtn.disabled = false;
        }
    })
    .catch(err => {
        alert('Network error: ' + err.message);
        paymentBtn.innerHTML = originalText;
        paymentBtn.disabled = false;
    });
}

function updateTotals(data) {
    let totalAmount = 0, totalQuantity = 0;
    data.forEach(item => {
        totalAmount += item.rawAmount || item.amount || 0;
        totalQuantity += item.quantity || 1;
    });
    document.getElementById('totalQuantity').innerText = totalQuantity.toFixed(2);
    document.getElementById('totalAmount').innerHTML = 'Rs. ' + totalAmount.toFixed(2);
    document.getElementById('filteredTotal').innerHTML = 'Total: Rs. ' + totalAmount.toFixed(2);
}

function printExpenses() {
    const title = document.getElementById('shopExpensesTitle').innerText;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    let dateRangeText = '';

    if (startDate && endDate) {
        dateRangeText = ` (${new Date(startDate).toLocaleDateString()} to ${new Date(endDate).toLocaleDateString()})`;
    } else if (startDate) {
        dateRangeText = ` (From ${new Date(startDate).toLocaleDateString()})`;
    } else if (endDate) {
        dateRangeText = ` (Until ${new Date(endDate).toLocaleDateString()})`;
    }

    const filteredData = expensesDataTable ?
        expensesDataTable.rows({ filter: 'applied' }).data().toArray() :
        shopExpensesData;

    const totalAmount = filteredData.reduce((sum, item) => sum + (item.rawAmount || item.amount || 0), 0);
    const totalQuantity = filteredData.reduce((sum, item) => sum + (item.quantity || 1), 0);

    let tableHTML = `
        <table style="width:100%; border-collapse: collapse; margin-top: 20px; font-size: 12px;">
            <thead style="background-color: #f8f9fa;">
                <tr>
                    <th style="border:1px solid #ddd; padding:8px;">S.No</th>
                    <th style="border:1px solid #ddd; padding:8px;">Date</th>
                    <th style="border:1px solid #ddd; padding:8px;">Bill No</th>
                    <th style="border:1px solid #ddd; padding:8px;">Product</th>
                    <th style="border:1px solid #ddd; padding:8px; text-align:center;">Qty</th>
                    <th style="border:1px solid #ddd; padding:8px; text-align:right;">Unit Price</th>
                    <th style="border:1px solid #ddd; padding:8px; text-align:right;">Amount</th>
                    <th style="border:1px solid #ddd; padding:8px;">C.N</th>
                    <th style="border:1px solid #ddd; padding:8px;">Month</th>
                    <th style="border:1px solid #ddd; padding:8px;">Category</th>
                    <th style="border:1px solid #ddd; padding:8px;">Status</th>
                </tr>
            </thead>
            <tbody>
    `;

    filteredData.forEach((item, index) => {
        const amount = item.rawAmount || item.amount || 0;
        const quantity = item.quantity || 1;
        const unitPrice = item.unit_price || (quantity > 0 ? amount / quantity : amount);
        const status = item.is_paid ? 'Paid' : 'Unpaid';
        const statusColor = item.is_paid ? '#28a745' : '#ffc107';

        tableHTML += `
            <tr>
                <td style="border:1px solid #ddd; padding:8px;">${index + 1}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.date || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.bill_no || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.product_name || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px; text-align:center;">${quantity.toFixed(2)}</td>
                <td style="border:1px solid #ddd; padding:8px; text-align:right;">Rs. ${unitPrice.toFixed(2)}</td>
                <td style="border:1px solid #ddd; padding:8px; text-align:right;">Rs. ${amount.toFixed(2)}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.cn || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.month ? item.month.replace(/<[^>]*>/g, '') : '—'}</td>
                <td style="border:1px solid #ddd; padding:8px;">${item.category || '—'}</td>
                <td style="border:1px solid #ddd; padding:8px; background-color: ${statusColor}; color: ${item.is_paid ? 'white' : 'black'}; text-align:center;">${status}</td>
            </tr>
        `;
    });

    tableHTML += `
            </tbody>
        </table>
        <div style="margin-top:30px; padding:15px; background:#d4edda; border:1px solid #c3e6cb; border-radius:5px; font-weight:bold; text-align:right;">
            <div>Total Items: ${totalQuantity.toFixed(2)}</div>
            <div>Total Amount: Rs. ${totalAmount.toFixed(2)}</div>
        </div>
    `;

    let printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>${title}${dateRangeText}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h2 { color: #333; }
                @media print {
                    body { margin: 0.5in; }
                }
            </style>
        </head>
        <body>
            <h2>${title}</h2>
            <p>${dateRangeText || 'All expenses'} | Printed: ${new Date().toLocaleString()}</p>
            ${tableHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>
</body>
</html>
