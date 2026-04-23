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
    $sql = "SELECT * FROM it_registrations ORDER BY registration_date DESC";
    $stmt = $pdo->query($sql);
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
    <title>IT Course Leads | MSST Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .student-photo {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            border: 2px solid #dee2e6;
        }
        .student-photo:hover {
            transform: scale(1.05);
            transition: transform 0.3s;
        }
        .office-use-col {
            background-color: #fef9c3;
            font-weight: bold;
        }
        .form-no {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #7F5C23;
        }
        .leads-table th {
            background-color: #7F5C23;
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">IT Course Leads Management</h1>
                <div>
                    <a href="school_leads.php" class="btn btn-sm btn-secondary shadow-sm me-2">
                        <i class="fas fa-school fa-sm text-white-50"></i> School Leads
                    </a>
                    <a href="leads.php" class="btn btn-sm btn-primary shadow-sm">
                        <i class="fas fa-graduation-cap fa-sm text-white-50"></i> College Leads
                    </a>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">All IT Course Registrations</h6>
                    <span class="badge bg-primary"><?php echo count($leads); ?> Registrations</span>
                </div>
                <div class="card-body">
                    <?php if (empty($leads)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-laptop-code fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No IT Course Registrations Found</h4>
                            <p class="text-muted">There are no IT course registrations yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="itLeadsTable" class="table table-striped table-bordered" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Form No</th>
                                        <th>Reg. Date</th>
                                        <th>Full Name</th>
                                        <th>Father's Name</th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>Course</th>
                                        <th>Time Slot</th>
                                        <th>CNIC</th>
                                        <th>DOB</th>
                                        <th class="office-use-col">Fee Amount</th>
                                        <th class="office-use-col">Received By</th>
                                        <th class="office-use-col">Fee Date</th>
                                        <th class="office-use-col">Signature</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sl = 1; foreach ($leads as $lead): ?>
                                        <tr>
                                            <td><?= $sl++ ?></td>
                                            <td>
                                                <?php if (!empty($lead['photo_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($lead['photo_path']); ?>" target="_blank" data-bs-toggle="tooltip" title="View Photo">
                                                        <img src="<?php echo htmlspecialchars($lead['photo_path']); ?>" alt="Student Photo" class="student-photo">
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">No photo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="form-no"><?= htmlspecialchars($lead['form_no'] ?? 'N/A') ?></span></td>
                                            <td><?= date('d M Y, g:i A', strtotime($lead['registration_date'] ?? 'now')) ?></td>
                                            <td><?= htmlspecialchars($lead['full_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($lead['father_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <a href="tel:<?= htmlspecialchars($lead['contact_number'] ?? '') ?>" class="text-decoration-none">
                                                    <i class="fas fa-phone-alt me-1 text-success"></i>
                                                    <?= htmlspecialchars($lead['contact_number'] ?? 'N/A') ?>
                                                </a>
                                            </td>
                                            <td>
                                                <a href="mailto:<?= htmlspecialchars($lead['email'] ?? '') ?>" class="text-decoration-none">
                                                    <i class="fas fa-envelope me-1 text-primary"></i>
                                                    <?= htmlspecialchars($lead['email'] ?? 'N/A') ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= htmlspecialchars($lead['preferred_course'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($lead['time_slot'] ?? 'N/A') ?>
                                                </span>
                                            </td>
                                            <td><small><?= htmlspecialchars($lead['cnic'] ?? 'N/A') ?></small></td>
                                            <td><?= $lead['dob'] ? date('d M Y', strtotime($lead['dob'])) : 'N/A' ?></td>
                                            <td class="office-use-col">
                                                <?php if (!empty($lead['fee_received_amount'])): ?>
                                                    <span class="badge bg-success">
                                                        Rs. <?= htmlspecialchars($lead['fee_received_amount']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="office-use-col">
                                                <?= htmlspecialchars($lead['received_by_admin'] ?? 'N/A') ?>
                                            </td>
                                            <td class="office-use-col">
                                                <?= $lead['fee_received_date'] ? date('d M Y', strtotime($lead['fee_received_date'])) : 'N/A' ?>
                                            </td>
                                            <td class="office-use-col">
                                                <?= htmlspecialchars($lead['admin_signature'] ?? 'N/A') ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" title="View Details" data-bs-toggle="modal" data-bs-target="#viewLeadModal" onclick='prepareViewModal(<?= htmlspecialchars(json_encode($lead)) ?>)'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" title="Delete Registration" data-bs-toggle="modal" data-bs-target="#deleteLeadModal" onclick='prepareDeleteModal(<?= $lead["id"] ?? 0 ?>, "<?= htmlspecialchars($lead["full_name"] ?? 'N/A') ?>")'>
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
                <h5 class="modal-title">IT Course Registration Details</h5>
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
                Are you sure you want to delete the IT course registration for <strong id="leadToDeleteName"></strong>? This action cannot be undone.
                <input type="hidden" id="leadToDeleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Registration</button>
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
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        <?php if (!empty($leads)): ?>
            $('#itLeadsTable').DataTable({
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                order: [[3, 'desc']], // Order by registration date
                columnDefs: [{
                    targets: [0, 16], // # column and Actions column
                    orderable: false
                }],
                pageLength: 25,
                language: {
                    search: "Search registrations:"
                }
            });
        <?php endif; ?>
    });

    function prepareViewModal(leadData) {
        const modalBody = document.getElementById('leadDetailsBody');
        let content = '<div class="row">';
        
        // Photo and basic info
        content += `<div class="col-md-4 text-center mb-4">
                        <div class="card">
                            <div class="card-body">`;
        
        if (leadData.photo_path) {
            content += `<img src="${leadData.photo_path}" alt="Student Photo" class="img-fluid rounded mb-3" style="max-height: 200px;">
                        <a href="${leadData.photo_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt me-1"></i>Open Photo
                        </a>`;
        } else {
            content += `<div class="py-5 text-muted">
                            <i class="fas fa-user-circle fa-5x"></i>
                            <p class="mt-2">No Photo Available</p>
                        </div>`;
        }
        
        content += `</div></div></div>`;
        
        // Student Information
        content += `<div class="col-md-8">
                        <div class="row">`;
        
        const fieldGroups = {
            'Registration Details': [
                {key: 'form_no', label: 'Form Number'},
                {key: 'registration_date', label: 'Registration Date & Time'},
                {key: 'preferred_course', label: 'Course'},
                {key: 'time_slot', label: 'Time Slot'}
            ],
            'Personal Information': [
                {key: 'full_name', label: 'Full Name'},
                {key: 'father_name', label: "Father's Name"},
                {key: 'cnic', label: 'CNIC'},
                {key: 'dob', label: 'Date of Birth'}
            ],
            'Contact Information': [
                {key: 'contact_number', label: 'Contact Number'},
                {key: 'email', label: 'Email Address'},
                {key: 'address', label: 'Address'}
            ],
            'Payment Information': [
                {key: 'fee_received_amount', label: 'Fee Amount'},
                {key: 'received_by_admin', label: 'Received By'},
                {key: 'fee_received_date', label: 'Fee Date'},
                {key: 'admin_signature', label: 'Admin Signature'}
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
                    
                    // Format dates
                    if (field.key.includes('date') || field.key.includes('dob')) {
                        if (value && value !== 'N/A') {
                            try {
                                if (field.key === 'registration_date') {
                                    value = new Date(value).toLocaleString('en-US', {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                } else {
                                    value = new Date(value).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric'
                                    });
                                }
                            } catch (e) {
                                value = value;
                            }
                        }
                    }
                    
                    // Add badges for certain fields
                    if (field.key === 'preferred_course') {
                        value = `<span class="badge bg-info">${value}</span>`;
                    } else if (field.key === 'time_slot') {
                        value = `<span class="badge bg-secondary">${value}</span>`;
                    } else if (field.key === 'fee_received_amount') {
                        if (value && value !== 'N/A') {
                            value = `<span class="badge bg-success">Rs. ${value}</span>`;
                        } else {
                            value = `<span class="badge bg-warning text-dark">Pending</span>`;
                        }
                    } else if (field.key === 'contact_number') {
                        value = `<a href="tel:${value}" class="text-decoration-none">
                                    <i class="fas fa-phone-alt me-1 text-success"></i>${value}
                                </a>`;
                    } else if (field.key === 'email') {
                        value = `<a href="mailto:${value}" class="text-decoration-none">
                                    <i class="fas fa-envelope me-1 text-primary"></i>${value}
                                </a>`;
                    }
                    
                    content += `<p class="mb-2"><strong>${field.label}:</strong> 
                                <span class="float-end">${value}</span></p>`;
                }
            });
            
            content += `</div></div></div>`;
        }
        
        content += `</div></div></div>`;
        modalBody.innerHTML = content;
    }

    function prepareDeleteModal(id, name) {
        document.getElementById('leadToDeleteId').value = id;
        document.getElementById('leadToDeleteName').textContent = name;
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        const leadId = document.getElementById('leadToDeleteId').value;
        
        fetch('delete_it_lead.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${leadId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting registration: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error deleting registration: ' + error);
        });
        
        const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteLeadModal'));
        deleteModal.hide();
    });
</script>
</body>
</html>