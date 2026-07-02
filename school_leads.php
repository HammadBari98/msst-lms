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
$action_msg = '';

// Check for session messages (like Delete success)
if (isset($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// --- NEW EDIT LOGIC FOR SCHOOL LEADS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_lead') {
    $lead_id = $_POST['id'];
    $update_fields = [];
    $params = [];

    foreach ($_POST as $key => $value) {
        if ($key !== 'action' && $key !== 'id') {
            $update_fields[] = "`$key` = ?";
            $params[] = $value;
        }
    }

    if (!empty($update_fields)) {
        $params[] = $lead_id;
        // NOTE: Table is 'school_admissions' here
        $sql = "UPDATE `school_admissions` SET " . implode(', ', $update_fields) . " WHERE id = ?";
        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $_SESSION['action_msg'] = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle me-2'></i>Lead updated successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        } catch (PDOException $e) {
            $_SESSION['action_msg'] = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-times-circle me-2'></i>Error updating lead.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
    header("Location: school_leads.php");
    exit;
}
// --- NEW DELETE LEAD LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_lead') {
    $lead_id = $_POST['id'];
    try {
        // NOTE: Make sure this targets the school_admissions table!
        $stmt = $pdo->prepare("DELETE FROM `school_admissions` WHERE id = ?");
        if ($stmt->execute([$lead_id])) {
            $_SESSION['action_msg'] = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle me-2'></i>School Lead deleted successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    } catch (PDOException $e) {
        $_SESSION['action_msg'] = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-times-circle me-2'></i>Error deleting lead.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    header("Location: school_leads.php");
    exit;
}
// --- END NEW EDIT LOGIC ---
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

            <?= $action_msg ?>
            <?= $error_message ?>

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
                                                    <button class="btn btn-sm btn-primary text-white me-1 mb-1" title="Edit Lead" onclick='prepareEditModal(<?= htmlspecialchars(json_encode($lead), ENT_QUOTES, "UTF-8") ?>)' data-bs-toggle="modal" data-bs-target="#editLeadModal">
    <i class="fas fa-edit"></i>
</button>
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
<div class="modal fade" id="deleteLeadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the school lead for <strong id="leadToDeleteName"></strong>? This action cannot be undone.
                    <input type="hidden" name="action" value="delete_lead">
                    <input type="hidden" name="id" id="leadToDeleteId">
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="editLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Lead Information</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" value="edit_lead">
                    <input type="hidden" name="id" id="editLeadId">
                    <div id="editLeadFormBody" class="row bg-white p-3 rounded shadow-sm m-1">
                        </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
// --- NEW EDIT BUILDER FUNCTION ---
    function prepareEditModal(leadData) {
        document.getElementById('editLeadId').value = leadData.id;
        const formBody = document.getElementById('editLeadFormBody');
        let content = '';
        
        // Exclude system fields from being edited directly
        const skipFields = ['id', 'submission_date', 'date', 'admission_status'];

        for (const key in leadData) {
            if (skipFields.includes(key)) continue;

            let formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            let value = leadData[key] ?? '';

            // Make text areas for long text, and standard inputs for short text
            if (value !== null && value.toString().length > 60) {
                content += `
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold text-secondary">${formattedKey}</label>
                        <textarea class="form-control" name="${key}" rows="3">${value}</textarea>
                    </div>`;
            } else {
                content += `
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">${formattedKey}</label>
                        <input type="text" class="form-control" name="${key}" value="${value}">
                    </div>`;
            }
        }
        formBody.innerHTML = content;
    }
    function prepareDeleteModal(id, name) {
        document.getElementById('leadToDeleteId').value = id;
        document.getElementById('leadToDeleteName').textContent = name;
    }

  
</script>
</body>
</html>