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
    <title>LMS - Visitor Log Management</title>

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
        .time-slot-container {
            display: flex;
            align-items: center;
            gap: 10px;
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
                                    <h4 class="card-title">Visitor Log Management</h4>

                                    <ul class="nav nav-tabs" id="visitorLogTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Visitor List
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="false">
                                                <i class="mdi mdi-plus-circle me-1"></i> Add Visitor
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="visitorLogTabsContent">
                                        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div class="table-responsive">
                                                <table id="visitorTable" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Sl</th>
                                                            <th>Name</th>
                                                            <th>Visiting Purpose</th>
                                                            <th>Date</th>
                                                            <th>Entry Time</th>
                                                            <th>Exit Time</th>
                                                            <th>Number Of Visitors</th>
                                                            <th>Token/Pass</th>
                                                            <th>Note</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>1</td>
                                                            <td>John Smith</td>
                                                            <td>Meeting</td>
                                                            <td>2025-05-14</td>
                                                            <td>10:00 AM</td>
                                                            <td>11:30 AM</td>
                                                            <td>2</td>
                                                            <td>VIP-001</td>
                                                            <td>Meeting with HR manager</td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editVisitorModal">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-danger">
                                                                    <i class="mdi mdi-delete"></i> Delete
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>2</td>
                                                            <td>Sarah Johnson</td>
                                                            <td>Delivery</td>
                                                            <td>2025-05-14</td>
                                                            <td>2:15 PM</td>
                                                            <td>2:30 PM</td>
                                                            <td>1</td>
                                                            <td>DEL-145</td>
                                                            <td>Package delivery</td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editVisitorModal">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-danger">
                                                                    <i class="mdi mdi-delete"></i> Delete
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex justify-content-between mt-3">
                                                <div>Showing 1 to 2 of 2 entries</div>
                                                <nav aria-label="Visitor list pagination">
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
                                            <form id="addVisitorForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="visitingPurpose" class="form-label">Visiting Purpose <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="visitingPurpose" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="visitorName" class="form-label">Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="visitorName" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="mobileNo" class="form-label">Mobile No</label>
                                                            <input type="tel" class="form-control" id="mobileNo">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="visitorDate" class="form-label">Date <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control" id="visitorDate" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="entryTime" class="form-label">Entry Time <span class="text-danger">*</span></label>
                                                            <input type="time" class="form-control" id="entryTime" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="exitTime" class="form-label">Exit Time</label>
                                                            <input type="time" class="form-control" id="exitTime">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="numberOfVisitors" class="form-label">Number Of Visitors <span class="text-danger">*</span></label>
                                                            <input type="number" class="form-control" id="numberOfVisitors" min="1" value="1" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="idNumber" class="form-label">ID Number</label>
                                                            <input type="text" class="form-control" id="idNumber">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="tokenPass" class="form-label">Token/Pass</label>
                                                            <input type="text" class="form-control" id="tokenPass">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="visitorNote" class="form-label">Note</label>
                                                            <textarea class="form-control" id="visitorNote" rows="2"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-end">
                                                    <button type="reset" class="btn btn-light me-2">Reset</button>
                                                    <button type="submit" class="btn btn-primary">Save Visitor</button>
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

    <!-- Edit Visitor Modal -->
    <div class="modal fade" id="editVisitorModal" tabindex="-1" aria-labelledby="editVisitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVisitorModalLabel">Edit Visitor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editVisitorForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editVisitingPurpose" class="form-label">Visiting Purpose <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editVisitingPurpose" value="Meeting" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editVisitorName" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editVisitorName" value="John Smith" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editMobileNo" class="form-label">Mobile No</label>
                                    <input type="tel" class="form-control" id="editMobileNo" value="+1 234 567 890">
                                </div>
                                <div class="mb-3">
                                    <label for="editVisitorDate" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="editVisitorDate" value="2025-05-14" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editEntryTime" class="form-label">Entry Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="editEntryTime" value="10:00" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editExitTime" class="form-label">Exit Time</label>
                                    <input type="time" class="form-control" id="editExitTime" value="11:30">
                                </div>
                                <div class="mb-3">
                                    <label for="editNumberOfVisitors" class="form-label">Number Of Visitors <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="editNumberOfVisitors" min="1" value="2" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editIdNumber" class="form-label">ID Number</label>
                                    <input type="text" class="form-control" id="editIdNumber" value="ID-789456">
                                </div>
                                <div class="mb-3">
                                    <label for="editTokenPass" class="form-label">Token/Pass</label>
                                    <input type="text" class="form-control" id="editTokenPass" value="VIP-001">
                                </div>
                                <div class="mb-3">
                                    <label for="editVisitorNote" class="form-label">Note</label>
                                    <textarea class="form-control" id="editVisitorNote" rows="2">Meeting with HR manager</textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Update Visitor</button>
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
            $('#visitorTable').DataTable({
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

            // Set today's date as default for visitor date
            const today = new Date().toISOString().split('T')[0];
            $('#visitorDate').val(today);

            // Set current time as default for entry time
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            $('#entryTime').val(`${hours}:${minutes}`);

            // Form submission handler for adding visitor
            $('#addVisitorForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate required fields
                if (!$('#visitingPurpose').val() || !$('#visitorName').val() || !$('#visitorDate').val() || !$('#entryTime').val() || !$('#numberOfVisitors').val()) {
                    alert('Please fill all required fields');
                    return;
                }
                
                // In a real application, you would send this to the server
                alert('Visitor log saved successfully!');
                
                this.reset();
                $('#visitorDate').val(today); // Reset to today's date
                $('#entryTime').val(`${hours}:${minutes}`); // Reset to current time
                $('#numberOfVisitors').val(1); // Reset to default
                $('#visitorLogTabs button[data-bs-target="#list"]').tab('show');
            });

            // Form submission handler for editing visitor
            $('#editVisitorForm').on('submit', function(e) {
                e.preventDefault();
                alert('Visitor log updated successfully!');
                $('#editVisitorModal').modal('hide');
            });
        });
    </script>
</body>
</html>