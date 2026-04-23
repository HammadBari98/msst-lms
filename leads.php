<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Set the admin name for the header, which is used in 'header.php'
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Use the shared database configuration
require_once __DIR__ . '/config/db_config.php';

$leads = [];
$error_message = '';

try {
    // Using the original query as requested. This will fetch all columns.
    $stmt = $pdo->query("SELECT * FROM admissions ORDER BY date DESC");
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In a production environment, log the error instead of displaying it.
    error_log("Database query failed: " . $e->getMessage());
    // Set a user-friendly error message to display in the UI
    $error_message = "A database error occurred. Please check the server logs for details.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Management | Admin Dashboard</title>
    <!-- Core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

    <!-- Custom Admin Template CSS -->
    <link rel="stylesheet" href="style.css"> 
    <link rel="shortcut icon" href="assets/images/favicon.png" />
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">

            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Leads Management</h1>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Admission Leads</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="leadsTable" class="table table-striped table-bordered" style="width:100%">
                           <thead>
                                <tr>
                                    <th>#</th>
                                    <?php
                                    // Dynamically generate table headers from the first row of data
                                    if (!empty($leads)) {
                                        foreach (array_keys($leads[0]) as $column) {
                                            echo "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $column))) . "</th>";
                                        }
                                    }
                                    ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($leads)): ?>
                                    <?php $sl = 1; foreach ($leads as $lead): ?>
                                        <tr>
                                            <td><?= $sl++ ?></td>
                                            <?php foreach ($lead as $value): ?>
                                                <td><?= htmlspecialchars($value ?? 'N/A') ?></td>
                                            <?php endforeach; ?>
                                            <td>
                                                <button class="btn btn-info btn-sm" title="View Details" data-bs-toggle="modal" data-bs-target="#viewLeadModal" onclick='prepareViewModal(<?= htmlspecialchars(json_encode($lead)) ?>)'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" title="Delete Lead" data-bs-toggle="modal" data-bs-target="#deleteLeadModal" onclick='prepareDeleteModal(<?= $lead["id"] ?? 0 ?>, "<?= htmlspecialchars($lead["name"] ?? 'N/A') ?>")'>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php elseif (empty($leads) && !$error_message): ?>
                                    <?php $columnCount = empty($leads) ? 2 : count($leads[0]) + 2; ?>
                                    <tr>
                                        <td colspan="<?= $columnCount ?>" class="text-center">No leads found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</div>


<!-- View Lead Modal -->
<div class="modal fade" id="viewLeadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lead Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be populated by JavaScript -->
                <div id="leadDetailsBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Lead Modal -->
<div class="modal fade" id="deleteLeadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the lead for <strong id="leadToDeleteName"></strong>? This action cannot be undone.
                <input type="hidden" id="leadToDeleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Lead</button>
            </div>
        </div>
    </div>
</div>


<!-- JS Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables & Buttons JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

<!-- Custom Script -->
<script>
    $(document).ready(function() {
        $('#leadsTable').DataTable({
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>" +
                 "<'row'<'col-sm-12 mt-2'B>>",
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print', 'colvis'],
            // Dynamically determine the date column for ordering if it exists
            // This is more robust than a fixed index.
            <?php
                if (!empty($leads)) {
                    $dateColumnIndex = array_search('date', array_keys($leads[0]));
                    if ($dateColumnIndex !== false) {
                        echo "order: [[" . ($dateColumnIndex + 1) . ", 'desc']],";
                    }
                }
            ?>
            columnDefs: [{ 
                targets: [0, -1], // Target the first and last columns (Sl and Actions)
                orderable: false 
            }]
        });
    });

    function prepareViewModal(leadData) {
        const modalBody = document.getElementById('leadDetailsBody');
        let content = '';
        for (const key in leadData) {
            let formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            content += `<p><strong>${formattedKey}:</strong> ${leadData[key] ?? 'N/A'}</p>`;
        }
        modalBody.innerHTML = content;
    }

    function prepareDeleteModal(id, name) {
        document.getElementById('leadToDeleteId').value = id;
        document.getElementById('leadToDeleteName').textContent = name;
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        const leadId = document.getElementById('leadToDeleteId').value;
        // This is where you would make an AJAX call to a backend script (e.g., api_delete_lead.php)
        alert(`A request would be sent to delete lead ID: ${leadId}.`);
        
        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteLeadModal'));
        deleteModal.hide();
        // location.reload(); 
    });
</script>

</body>
</html>
    