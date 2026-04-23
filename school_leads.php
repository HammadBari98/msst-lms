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
                        <!-- Show simple message when no data -->
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No School Leads Found</h4>
                            <p class="text-muted">There are no Grade 8-9 admission applications yet.</p>
                        </div>
                    <?php else: ?>
                        <!-- Only show DataTable when there's data -->
                        <div class="table-responsive">
                            <table id="schoolLeadsTable" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Form No</th>
                                        <th>Date</th>
                                        <th>Class</th>
                                        <th>Group</th>
                                        <th>Full Name</th>
                                        <th>Gender</th>
                                        <th>Father Name</th>
                                        <th>Contact</th>
                                        <th>Last School</th>
                                        <th>Payment Status</th>
                                        <th>Submission Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sl = 1; foreach ($leads as $lead): ?>
                                        <tr>
                                            <td><?= $sl++ ?></td>
                                            <td class="font-mono text-sm"><?= htmlspecialchars($lead['form_no'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($lead['date'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="badge bg-<?= ($lead['applying_class'] ?? '') === 'Grade 8' ? 'primary' : 'info' ?>">
                                                    <?= htmlspecialchars($lead['applying_class'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if(!empty($lead['grade9_group'])): ?>
                                                    <span class="badge bg-success"><?= htmlspecialchars($lead['grade9_group']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($lead['full_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="badge bg-<?= ($lead['gender'] ?? '') === 'Male' ? 'primary' : 'danger' ?>">
                                                    <?= htmlspecialchars($lead['gender'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($lead['father_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <div><?= htmlspecialchars($lead['parent_contact'] ?? 'N/A') ?></div>
                                                <?php if(!empty($lead['email'])): ?>
                                                    <div class="text-xs text-muted"><?= htmlspecialchars($lead['email']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($lead['last_school'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="badge bg-<?= ($lead['payment_status'] ?? '') === 'Paid' ? 'success' : 'warning' ?>">
                                                    <?= htmlspecialchars($lead['payment_status'] ?? 'Pending') ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($lead['submission_date'] ?? 'now')) ?></td>
                                            <td>
    <button class="btn btn-info btn-sm" title="View Details" data-bs-toggle="modal" data-bs-target="#viewLeadModal" onclick='prepareViewModal(<?= htmlspecialchars(json_encode($lead)) ?>)'>
        <i class="fas fa-eye"></i>
    </button>
    <!-- REMOVE THIS PAYMENT BUTTON -->
    <!-- <button class="btn btn-warning btn-sm" title="Update Payment" onclick='updatePaymentStatus(<?= $lead["id"] ?>, "<?= $lead["payment_status"] ?? "Pending" ?>")'>
        <i class="fas fa-money-bill"></i>
    </button> -->
    <button class="btn btn-danger btn-sm" title="Delete Lead" data-bs-toggle="modal" data-bs-target="#deleteLeadModal" onclick='prepareDeleteModal(<?= $lead["id"] ?? 0 ?>, "<?= htmlspecialchars($lead["full_name"] ?? 'N/A') ?>")'>
        <i class="fas fa-trash"></i>
    </button>
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

<!-- View Lead Modal -->
<div class="modal fade" id="viewLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">School Lead Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
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
                Are you sure you want to delete the school lead for <strong id="leadToDeleteName"></strong>? This action cannot be undone.
                <input type="hidden" id="leadToDeleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Lead</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Only initialize DataTable if the table exists (when there's data)
        <?php if (!empty($leads)): ?>
            $('#schoolLeadsTable').DataTable({
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                order: [[11, 'desc']], // Order by submission date
                columnDefs: [{ 
                    targets: [0, 12], // # column and Actions column
                    orderable: false 
                }],
                pageLength: 25
            });
        <?php endif; ?>
    });

    function prepareViewModal(leadData) {
        const modalBody = document.getElementById('leadDetailsBody');
        let content = '<div class="row">';
        
        const fieldGroups = {
            'Basic Information': [
                {key: 'form_no', label: 'Form Number'},
                {key: 'date', label: 'Application Date'},
                {key: 'applying_class', label: 'Applying Class'},
                {key: 'grade9_group', label: 'Grade 9 Group'}
            ],
            'Student Details': [
                {key: 'full_name', label: 'Full Name'},
                {key: 'gender', label: 'Gender'},
                {key: 'dob', label: 'Date of Birth'},
                {key: 'dob_words', label: 'Date of Birth (Words)'},
                {key: 'nationality', label: 'Nationality'},
                {key: 'other_nationality', label: 'Other Nationality'},
                {key: 'religion', label: 'Religion'}
            ],
            'Contact Information': [
                {key: 'current_address', label: 'Current Address'},
                {key: 'permanent_address', label: 'Permanent Address'},
                {key: 'parent_contact', label: 'Parent Contact'},
                {key: 'email', label: 'Email'}
            ],
            'Family Information': [
                {key: 'father_name', label: 'Father Name'},
                {key: 'mother_name', label: 'Mother Name'},
                {key: 'guardian_name', label: 'Guardian Name'},
                {key: 'monthly_income', label: 'Monthly Income'}
            ],
            'Emergency Contacts': [
                {key: 'emergency_contact', label: 'Emergency Contact'},
                {key: 'emergency_person', label: 'Emergency Person'},
                {key: 'emergency_contact_number', label: 'Emergency Contact Number'}
            ],
            'Academic Information': [
                {key: 'last_school', label: 'Last School'},
                {key: 'last_class', label: 'Last Class'},
                {key: 'marks_grade', label: 'Marks/Grade'},
                {key: 'leaving_certificate', label: 'Leaving Certificate'}
            ],
            'Health & Transport': [
                {key: 'medical_condition', label: 'Medical Condition'},
                {key: 'medical_specify', label: 'Medical Details'},
                {key: 'transport_required', label: 'Transport Required'}
            ],
            'System Information': [
                {key: 'submission_date', label: 'Submission Date'},
                {key: 'payment_status', label: 'Payment Status'},
                {key: 'receipt_file', label: 'Receipt File'}
            ]
        };

        for (const [groupName, fields] of Object.entries(fieldGroups)) {
            content += `<div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">${groupName}</h6>
                    </div>
                    <div class="card-body">`;
            
            fields.forEach(field => {
                if (leadData.hasOwnProperty(field.key)) {
                    let value = leadData[field.key] ?? 'N/A';
                    
                    if (field.key === 'payment_status') {
                        value = `<span class="badge bg-${value === 'Paid' ? 'success' : 'warning'}">${value}</span>`;
                    } else if (field.key === 'applying_class') {
                        value = `<span class="badge bg-${value === 'Grade 8' ? 'primary' : 'info'}">${value}</span>`;
                    } else if (field.key === 'grade9_group' && value) {
                        value = `<span class="badge bg-success">${value}</span>`;
                    } else if (field.key === 'gender') {
                        value = `<span class="badge bg-${value === 'Male' ? 'primary' : 'danger'}">${value}</span>`;
                    } else if (field.key === 'medical_condition') {
                        value = `<span class="badge bg-${value === 'Yes' ? 'danger' : 'success'}">${value}</span>`;
                    } else if (field.key === 'transport_required') {
                        value = `<span class="badge bg-${value === 'Yes' ? 'info' : 'secondary'}">${value}</span>`;
                    } else if (field.key === 'leaving_certificate') {
                        value = `<span class="badge bg-${value === 'Yes' ? 'success' : 'warning'}">${value}</span>`;
                    } else if (field.key === 'submission_date') {
                        value = new Date(value).toLocaleString();
                    }
                    
                    content += `<p class="mb-2"><strong>${field.label}:</strong> <span class="float-end">${value}</span></p>`;
                }
            });
            
            content += `</div></div></div>`;
        }
        
        content += '</div>';
        modalBody.innerHTML = content;
    }

    function prepareDeleteModal(id, name) {
        document.getElementById('leadToDeleteId').value = id;
        document.getElementById('leadToDeleteName').textContent = name;
    }

    function updatePaymentStatus(id, currentStatus) {
        const newStatus = currentStatus === 'Paid' ? 'Pending' : 'Paid';
        if (confirm(`Change payment status for lead ID ${id} to "${newStatus}"?`)) {
            fetch('update_school_payment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `id=${id}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating payment status: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating payment status: ' + error);
            });
        }
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        const leadId = document.getElementById('leadToDeleteId').value;
        
        fetch('delete_school_lead.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${leadId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting lead: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error deleting lead: ' + error);
        });

        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteLeadModal'));
        deleteModal.hide();
    });
</script>

</body>
</html>