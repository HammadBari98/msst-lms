<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
require_once __DIR__ . '/config/db_config.php';

$leads = [];
$error_message = '';

try {
    // AUTO-PATCHER: Ensure admission_status column exists
    try {
        $pdo->query("SELECT admission_status FROM school_admissions LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE school_admissions ADD COLUMN admission_status VARCHAR(20) DEFAULT 'Pending'");
    }

    $stmt = $pdo->query("SELECT * FROM school_admissions ORDER BY submission_date DESC");
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database query failed: " . $e->getMessage());
    $error_message = "A database error occurred.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Leads Management | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">

            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">School Leads Management (Grade 8-9)</h1>
                <a href="leads.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-graduation-cap fa-sm text-white-50"></i> College Leads
                </a>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All School Admission Leads</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($leads)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No School Leads Found</h4>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="schoolLeadsTable" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Form No</th>
                                        <th>Full Name</th>
                                        <th>Class</th>
                                        <th>Contact</th>
                                        <th>Submission Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sl = 1; foreach ($leads as $lead): 
                                        $status = $lead['admission_status'] ?? 'Pending';
                                        $statusClass = ($status === 'Approved') ? 'success' : 'warning';
                                    ?>
                                        <tr>
                                            <td><?= $sl++ ?></td>
                                            <td class="font-mono text-sm"><?= htmlspecialchars($lead['form_no'] ?? 'N/A') ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($lead['full_name'] ?? 'N/A') ?></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($lead['applying_class'] ?? 'N/A') ?></span></td>
                                            <td><?= htmlspecialchars($lead['parent_contact'] ?? 'N/A') ?></td>
                                            <td><?= date('M d, Y', strtotime($lead['submission_date'] ?? 'now')) ?></td>
                                            <td><span class="badge bg-<?= $statusClass ?>"><?= $status ?></span></td>
                                            <td>
                                                <div class="btn-group shadow-sm">
                                                    <button class="btn btn-info btn-sm text-white" title="View" data-bs-toggle="modal" data-bs-target="#viewLeadModal" onclick='prepareViewModal(<?= htmlspecialchars(json_encode($lead)) ?>)'>
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($status !== 'Approved'): ?>
                                                    <button class="btn btn-success btn-sm" title="Approve & Enroll" onclick="approveLead(<?= $lead['id'] ?>, 'school', '<?= htmlspecialchars(addslashes($lead['full_name'])) ?>')">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-danger btn-sm" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteLeadModal" onclick='prepareDeleteModal(<?= $lead["id"] ?? 0 ?>, "<?= htmlspecialchars(addslashes($lead["full_name"] ?? "N/A")) ?>")'>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
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
    <?php include 'footer.php'; ?>
</div>

<!-- View Lead Modal (Keep your existing modal HTML here) -->
<div class="modal fade" id="viewLeadModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">School Lead Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div id="leadDetailsBody"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>

<!-- Delete Lead Modal (Keep your existing modal HTML here) -->
<div class="modal fade" id="deleteLeadModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">Are you sure you want to delete the school lead for <strong id="leadToDeleteName"></strong>?<input type="hidden" id="leadToDeleteId"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Lead</button></div></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- Add SweetAlert2 for beautiful prompts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        <?php if (!empty($leads)): ?>
            $('#schoolLeadsTable').DataTable({
                order: [[5, 'desc']], // Order by date
                columnDefs: [{ targets: [0, 7], orderable: false }],
                pageLength: 25
            });
        <?php endif; ?>
    });

    // The Approval Engine
    function approveLead(id, type, name) {
        Swal.fire({
            title: 'Approve & Enroll Student?',
            text: `This will instantly create an active MSST student account for ${name}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check-circle"></i> Yes, Enroll Now'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'approve_lead_action.php',
                    type: 'POST',
                    data: { id: id, type: type },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            Swal.fire({
                                title: 'Enrolled!',
                                html: `${response.message}<br><br><strong>Generated ID:</strong> <span class="badge bg-primary fs-6">${response.student_id}</span>`,
                                icon: 'success'
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server failed to process request.', 'error');
                    }
                });
            }
        });
    }

    // Keep your existing prepareViewModal & delete functions exactly as they were
    function prepareViewModal(leadData) {
        const modalBody = document.getElementById('leadDetailsBody');
        let content = '<div class="row">';
        for (const key in leadData) {
            let formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            content += `<div class="col-md-6 mb-2"><strong>${formattedKey}:</strong> ${leadData[key] ?? 'N/A'}</div>`;
        }
        content += '</div>';
        modalBody.innerHTML = content;
    }

    function prepareDeleteModal(id, name) {
        document.getElementById('leadToDeleteId').value = id;
        document.getElementById('leadToDeleteName').textContent = name;
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        const leadId = document.getElementById('leadToDeleteId').value;
        fetch('delete_school_lead.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `id=${leadId}` })
        .then(res => res.json()).then(data => { if(data.success) location.reload(); else alert(data.message); });
    });
</script>
</body>
</html>