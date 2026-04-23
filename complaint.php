<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>LMS - Complaint Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/typicons/typicons.css">
    <link rel="stylesheet" href="assets/vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />

    <style>
        .action-buttons {
            white-space: nowrap;
        }
        .file-input-container {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .file-input-btn {
            border: 1px solid #ccc;
            display: inline-block;
            padding: 6px 12px;
            cursor: pointer;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-name {
            margin-left: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="container-scroller">
        <?php include 'header.php' ?>
        <div class="container-fluid page-body-wrapper">
            <?php include 'sidebar.php' ?>

            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="row">
                        <div class="col-md-12 grid-margin">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Complaint Management</h4>

                                    <ul class="nav nav-tabs" id="complaintTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Complaint List
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="false">
                                                <i class="mdi mdi-plus-circle me-1"></i> Add Complaint
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="complaintTabsContent">
                                        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div class="table-responsive">
                                                <table id="complaintTable" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Sl</th>
                                                            <th>Complaint Type</th>
                                                            <th>Complainant Name</th>
                                                            <th>Mobile No</th>
                                                            <th>Date</th>
                                                            <th>Date Of Solution</th>
                                                            <th>Assign To</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>1</td>
                                                            <td>Technical</td>
                                                            <td>Robert Johnson</td>
                                                            <td>+1 987 654 321</td>
                                                            <td>2025-05-10</td>
                                                            <td>2025-05-12</td>
                                                            <td>IT Department</td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editComplaintModal">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-danger">
                                                                    <i class="mdi mdi-delete"></i> Delete
                                                                </button>
                                                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewComplaintModal">
                                                                    <i class="mdi mdi-eye"></i> View
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>2</td>
                                                            <td>Service</td>
                                                            <td>Emily Davis</td>
                                                            <td>+1 123 456 789</td>
                                                            <td>2025-05-08</td>
                                                            <td>-</td>
                                                            <td>Customer Support</td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editComplaintModal">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-danger">
                                                                    <i class="mdi mdi-delete"></i> Delete
                                                                </button>
                                                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewComplaintModal">
                                                                    <i class="mdi mdi-eye"></i> View
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex justify-content-between mt-3">
                                                <div>Showing 1 to 2 of 2 entries</div>
                                                <nav aria-label="Complaint list pagination">
                                                    <ul class="pagination pagination-sm">
                                                        <li class="page-item disabled">
                                                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                                                        </li>
                                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                                        <li class="page-item disabled">
                                                            <a class="page-link" href="#">Next</a>
                                                        </li>
                                                    </ul>
                                                </nav>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="add" role="tabpanel" aria-labelledby="add-tab">
                                            <form id="addComplaintForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="complaintType" class="form-label">Type <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="complaintType" required>
                                                                <option value="">Select Type</option>
                                                                <option value="Technical">Technical</option>
                                                                <option value="Service">Service</option>
                                                                <option value="Billing">Billing</option>
                                                                <option value="Facility">Facility</option>
                                                                <option value="Other">Other</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="assignTo" class="form-label">Assign To <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="assignTo" required>
                                                                <option value="">Select Department</option>
                                                                <option value="IT Department">IT Department</option>
                                                                <option value="Customer Support">Customer Support</option>
                                                                <option value="Billing Department">Billing Department</option>
                                                                <option value="Facility Management">Facility Management</option>
                                                                <option value="Administration">Administration</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="complainantName" class="form-label">Complainant Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="complainantName" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="complainantMobile" class="form-label">Complainant Mobile No</label>
                                                            <input type="tel" class="form-control" id="complainantMobile">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="complaintDate" class="form-label">Date <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control" id="complaintDate" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="solutionDate" class="form-label">Date Of Solution</label>
                                                            <input type="date" class="form-control" id="solutionDate">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="complaintNote" class="form-label">Note</label>
                                                            <textarea class="form-control" id="complaintNote" rows="3"></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Document File</label>
                                                            <div class="file-input-container">
                                                                <span class="file-input-btn">Choose File</span>
                                                                <input type="file" class="file-input" id="documentFile">
                                                                <span class="file-name" id="fileName">No file chosen</span>
                                                            </div>
                                                            <small class="text-muted">Max file size: 5MB (PDF, JPG, PNG)</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-end">
                                                    <button type="reset" class="btn btn-light me-2">Reset</button>
                                                    <button type="submit" class="btn btn-primary">Save Complaint</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include 'footer.php' ?>
            </div>
        </div>
    </div>

    <!-- Edit Complaint Modal -->
    <div class="modal fade" id="editComplaintModal" tabindex="-1" aria-labelledby="editComplaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editComplaintModalLabel">Edit Complaint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editComplaintForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editComplaintType" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="editComplaintType" required>
                                        <option value="Technical" selected>Technical</option>
                                        <option value="Service">Service</option>
                                        <option value="Billing">Billing</option>
                                        <option value="Facility">Facility</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="editAssignTo" class="form-label">Assign To <span class="text-danger">*</span></label>
                                    <select class="form-select" id="editAssignTo" required>
                                        <option value="IT Department" selected>IT Department</option>
                                        <option value="Customer Support">Customer Support</option>
                                        <option value="Billing Department">Billing Department</option>
                                        <option value="Facility Management">Facility Management</option>
                                        <option value="Administration">Administration</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="editComplainantName" class="form-label">Complainant Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editComplainantName" value="Robert Johnson" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editComplainantMobile" class="form-label">Complainant Mobile No</label>
                                    <input type="tel" class="form-control" id="editComplainantMobile" value="+1 987 654 321">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editComplaintDate" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="editComplaintDate" value="2025-05-10" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editSolutionDate" class="form-label">Date Of Solution</label>
                                    <input type="date" class="form-control" id="editSolutionDate" value="2025-05-12">
                                </div>
                                <div class="mb-3">
                                    <label for="editComplaintNote" class="form-label">Note</label>
                                    <textarea class="form-control" id="editComplaintNote" rows="3">System not working properly</textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Document File</label>
                                    <div class="file-input-container">
                                        <span class="file-input-btn">Choose File</span>
                                        <input type="file" class="file-input" id="editDocumentFile">
                                        <span class="file-name" id="editFileName">document.pdf</span>
                                    </div>
                                    <small class="text-muted">Max file size: 5MB (PDF, JPG, PNG)</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Update Complaint</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Complaint Modal -->
    <div class="modal fade" id="viewComplaintModal" tabindex="-1" aria-labelledby="viewComplaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewComplaintModalLabel">Complaint Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Type:</strong></label>
                                <p>Technical</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Assign To:</strong></label>
                                <p>IT Department</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Complainant Name:</strong></label>
                                <p>Robert Johnson</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Mobile No:</strong></label>
                                <p>+1 987 654 321</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>Date:</strong></label>
                                <p>2025-05-10</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Date Of Solution:</strong></label>
                                <p>2025-05-12</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Note:</strong></label>
                                <p>System not working properly</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Document:</strong></label>
                                <p><a href="#" class="text-primary">document.pdf</a></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/hoverable-collapse.js"></script>
    <script src="assets/js/template.js"></script>
    <script src="assets/js/settings.js"></script>
    <script src="assets/js/todolist.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize DataTable
            $('#complaintTable').DataTable({
                responsive: true,
                paging: false,
                info: true,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copy',
                        className: 'btn btn-sm btn-secondary'
                    },
                    {
                        extend: 'csv',
                        className: 'btn btn-sm btn-info'
                    },
                    {
                        extend: 'excel',
                        className: 'btn btn-sm btn-success'
                    },
                    {
                        extend: 'pdf',
                        className: 'btn btn-sm btn-danger'
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-sm btn-warning'
                    }
                ]
            });

            // Set today's date as default for complaint date
            const today = new Date().toISOString().split('T')[0];
            $('#complaintDate').val(today);

            // File input display
            $('#documentFile').change(function() {
                const fileName = $(this).val().split('\\').pop();
                $('#fileName').text(fileName || 'No file chosen');
            });

            $('#editDocumentFile').change(function() {
                const fileName = $(this).val().split('\\').pop();
                $('#editFileName').text(fileName || 'No file chosen');
            });

            // Form submission handler for adding complaint
            $('#addComplaintForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate required fields
                if (!$('#complaintType').val() || !$('#assignTo').val() || !$('#complainantName').val() || !$('#complaintDate').val()) {
                    alert('Please fill all required fields');
                    return;
                }
                
                // In a real application, you would send this to the server
                alert('Complaint saved successfully!');
                
                this.reset();
                $('#complaintDate').val(today); // Reset to today's date
                $('#fileName').text('No file chosen'); // Reset file name display
                $('#complaintTabs button[data-bs-target="#list"]').tab('show');
            });

            // Form submission handler for editing complaint
            $('#editComplaintForm').on('submit', function(e) {
                e.preventDefault();
                alert('Complaint updated successfully!');
                $('#editComplaintModal').modal('hide');
            });
        });
    </script>
</body>
</html>