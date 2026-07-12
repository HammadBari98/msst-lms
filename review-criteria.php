<?php
session_start();
require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/include/review_schema.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

ensure_review_schema($pdo);

$stmt = $pdo->query("
    SELECT rc.id, rc.label, rc.sort_order, rc.is_active,
           COUNT(trr.id) AS usage_count
    FROM review_criteria rc
    LEFT JOIN teacher_review_ratings trr ON trr.criteria_id = rc.id
    GROUP BY rc.id
    ORDER BY rc.sort_order ASC
");
$criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Criteria | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .order-btn { padding: 0.1rem 0.4rem; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div id="main-content">
        <?php include 'header.php'; ?>

        <div class="content-wrapper">
            <div class="container-fluid">

                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Review Criteria</h1>
                    <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> Add Criteria</button>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Aspects Students Rate Teachers On</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">These are the aspects that appear on the student feedback form. Deactivating a criteria hides it from new reviews but keeps existing ratings for it intact. Reorder with the arrows to change the order students see them in.</p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="criteriaTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 90px;">Order</th>
                                        <th>Label</th>
                                        <th>Status</th>
                                        <th>Times Rated</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="criteriaBody">
                                    <?php if (empty($criteria)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No criteria yet. Click "Add Criteria" to create the first one.</td></tr>
                                    <?php else: foreach ($criteria as $i => $c): ?>
                                        <tr data-id="<?= $c['id'] ?>">
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary order-btn move-btn" data-id="<?= $c['id'] ?>" data-direction="up" <?= $i === 0 ? 'disabled' : '' ?>><i class="fas fa-arrow-up"></i></button>
                                                <button class="btn btn-sm btn-outline-secondary order-btn move-btn" data-id="<?= $c['id'] ?>" data-direction="down" <?= $i === count($criteria) - 1 ? 'disabled' : '' ?>><i class="fas fa-arrow-down"></i></button>
                                            </td>
                                            <td class="criteria-label"><?= htmlspecialchars($c['label']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span>
                                            </td>
                                            <td><?= (int)$c['usage_count'] ?></td>
                                            <td class="text-center text-nowrap">
                                                <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?= $c['id'] ?>" data-label="<?= htmlspecialchars($c['label'], ENT_QUOTES) ?>" title="Edit label"><i class="fas fa-edit"></i></button>
                                                <button class="btn btn-sm btn-outline-<?= $c['is_active'] ? 'secondary' : 'success' ?> toggle-btn" data-id="<?= $c['id'] ?>" title="<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="fas fa-<?= $c['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $c['id'] ?>" data-usage="<?= (int)$c['usage_count'] ?>" title="Delete"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include 'footer.php'; ?>
    </div>

    <div class="modal fade" id="criteriaModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="criteriaModalTitle">Add Criteria</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" id="criteriaId">
                    <div class="mb-3">
                        <label class="form-label">Label</label>
                        <input type="text" class="form-control" id="criteriaLabel" placeholder="e.g. Punctuality" maxlength="150" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCriteriaBtn"><i class="fas fa-save me-2"></i>Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('criteriaModalTitle').textContent = 'Add Criteria';
            document.getElementById('criteriaId').value = '';
            document.getElementById('criteriaLabel').value = '';
            new bootstrap.Modal(document.getElementById('criteriaModal')).show();
        }

        $(document).on('click', '.edit-btn', function() {
            document.getElementById('criteriaModalTitle').textContent = 'Edit Criteria';
            document.getElementById('criteriaId').value = $(this).data('id');
            document.getElementById('criteriaLabel').value = $(this).data('label');
            new bootstrap.Modal(document.getElementById('criteriaModal')).show();
        });

        document.getElementById('saveCriteriaBtn').addEventListener('click', async function() {
            const id = document.getElementById('criteriaId').value;
            const label = document.getElementById('criteriaLabel').value.trim();
            if (!label) {
                alert('Label is required');
                return;
            }
            const formData = new FormData();
            formData.append('action', id ? 'update' : 'add');
            if (id) formData.append('id', id);
            formData.append('label', label);

            try {
                const response = await fetch('api_review_criteria_handler.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Failed to save criteria');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        $(document).on('click', '.toggle-btn', async function() {
            const id = $(this).data('id');
            try {
                const response = await fetch('api_review_criteria_handler.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'toggle_active', id })
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Failed to update status');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        $(document).on('click', '.move-btn', async function() {
            const id = $(this).data('id');
            const direction = $(this).data('direction');
            try {
                const response = await fetch('api_review_criteria_handler.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'move', id, direction })
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Failed to reorder');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        $(document).on('click', '.delete-btn', async function() {
            const id = $(this).data('id');
            const usage = $(this).data('usage');
            const warning = usage > 0
                ? `This criteria has been used in ${usage} existing rating(s). Deleting it will also remove those ratings. Continue?`
                : 'Delete this criteria?';
            if (!confirm(warning)) return;

            try {
                const response = await fetch('api_review_criteria_handler.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'delete', id })
                });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || 'Failed to delete criteria');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show-sidebar');
                }
            });
        }
    </script>
</body>
</html>
