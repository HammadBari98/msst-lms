<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    $categories = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM expense_categories c
        LEFT JOIN expense_products p ON c.id = p.category_id
        GROUP BY c.id
        ORDER BY c.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $products = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM expense_products p
        JOIN expense_categories c ON p.category_id = c.id
        ORDER BY c.name, p.name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Categories & Products | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-primary"><i class="fas fa-tags"></i> Expense Categories & Products</h1>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>

            <div id="alert-placeholder"></div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Expense Categories</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="categoriesTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Products</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                    <td><span class="badge bg-info"><?= $cat['product_count'] ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick='editCategory(<?= json_encode($cat) ?>)' data-bs-toggle="modal" data-bs-target="#editCategoryModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick='deleteCategory(<?= $cat["id"] ?>, "<?= htmlspecialchars(addslashes($cat["name"])) ?>")' data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick='openAddProductModal(<?= $cat["id"] ?>, "<?= htmlspecialchars(addslashes($cat["name"])) ?>")' data-bs-toggle="modal" data-bs-target="#addProductModal">
                                            <i class="fas fa-plus-circle"></i> Add Product
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Products</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Product Name</th>
                                    <th>Company</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['category_name']) ?></span></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= htmlspecialchars($p['company'] ?? '—') ?></td>
                                    <td><?= number_format($p['current_price'], 2) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick='editProduct(<?= json_encode($p) ?>)' data-bs-toggle="modal" data-bs-target="#editProductModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick='deleteProduct(<?= $p["id"] ?>, "<?= htmlspecialchars(addslashes($p["name"])) ?>")' data-bs-toggle="modal" data-bs-target="#deleteModal">
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

        </div>
    </div>
    <?php include 'footer.php'; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal">
    <div class="modal-dialog">
        <form id="addCatForm">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5>Add Category</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_category">
                    <input type="text" name="name" class="form-control" placeholder="e.g., Stationery" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Add</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal">
    <div class="modal-dialog">
        <form id="editCatForm">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5>Edit Category</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="id" id="editCatId">
                    <div class="mb-3">
                        <label>Category Name</label>
                        <input type="text" name="name" id="editCatName" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal">
    <div class="modal-dialog">
        <form id="addProdForm">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5>Add Product to <span id="prodCatName"></span></h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    <input type="hidden" name="category_id" id="prodCatId">
                    <div class="mb-3">
                        <label>Product Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Company (Optional)</label>
                        <input type="text" name="company" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Price <small class="text-muted">(default price for expense entry)</small></label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" value="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal">
    <div class="modal-dialog">
        <form id="editProdForm">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5>Edit Product</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="id" id="editProdId">
                    <div class="mb-3">
                        <label>Product Name</label>
                        <input type="text" name="name" id="editProdName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Company (Optional)</label>
                        <input type="text" name="company" id="editProdCompany" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Price</label>
                        <input type="number" name="price" id="editProdPrice" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
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
                <input type="hidden" id="deleteType">
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

<script>
$(document).ready(() => {
    $('#categoriesTable, #productsTable').DataTable({ pageLength: 25 });
});

function openAddProductModal(id, name) {
    document.getElementById('prodCatId').value = id;
    document.getElementById('prodCatName').textContent = name;
}

function editCategory(category) {
    document.getElementById('editCatId').value = category.id;
    document.getElementById('editCatName').value = category.name;
}

function editProduct(product) {
    document.getElementById('editProdId').value = product.id;
    document.getElementById('editProdName').value = product.name;
    document.getElementById('editProdCompany').value = product.company || '';
    document.getElementById('editProdPrice').value = product.current_price || 0;
}

function deleteCategory(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name + ' category';
    document.getElementById('deleteType').value = 'category';
}

function deleteProduct(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name + ' product';
    document.getElementById('deleteType').value = 'product';
}

['addCatForm', 'editCatForm', 'addProdForm', 'editProdForm'].forEach(id => {
    document.getElementById(id)?.addEventListener('submit', async e => {
        e.preventDefault();
        const data = new FormData(e.target);
        const res = await fetch('api_expense_handler.php', { method: 'POST', body: data });
        const json = await res.json();
        showAlert(json.message, res.ok ? 'success' : 'danger');
        if (res.ok) setTimeout(() => location.reload(), 1000);
    });
});

document.getElementById('confirmDelete').addEventListener('click', async () => {
    const id = document.getElementById('deleteId').value;
    const type = document.getElementById('deleteType').value;

    if (!id || !type) {
        showAlert('Nothing to delete', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_' + type);
    formData.append('id', id);

    try {
        const res = await fetch('api_expense_handler.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        showAlert(json.message, res.ok ? 'success' : 'danger');
        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            setTimeout(() => location.reload(), 1000);
        }
    } catch (error) {
        showAlert('Error: ' + error.message, 'danger');
    }
});

function showAlert(msg, type) {
    const div = `<div class="alert alert-${type} alert-dismissible fade show"><div>${msg}</div><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    document.getElementById('alert-placeholder').insertAdjacentHTML('beforeend', div);

    setTimeout(() => {
        const alerts = document.querySelectorAll('#alert-placeholder .alert');
        if (alerts.length > 0) {
            alerts[0].remove();
        }
    }, 5000);
}
</script>
</body>
</html>
