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
    <title>LMS - Call Log Management</title>

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
                                    <h4 class="card-title">Call Log Management</h4>

                                    <ul class="nav nav-tabs" id="callLogTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Call Log List
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="false">
                                                <i class="mdi mdi-plus-circle me-1"></i> Add Call Log
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="callLogTabsContent">
                                        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div class="table-responsive">
                                                <table id="callLogTable" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Sl</th>
                                                            <th>Name</th>
                                                            <th>Mobile No</th>
                                                            <th>Calling Purpose</th>
                                                            <th>Call Type</th>
                                                            <th>Date</th>
                                                            <th>Start Time</th>
                                                            <th>End Time</th>
                                                            <th>Follow Up</th>
                                                            <th>Duration</th>
                                                            <th>Note</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>1</td>
                                                            <td>John Doe</td>
                                                            <td>+1 234 567 890</td>
                                                            <td>Product Inquiry</td>
                                                            <td>Incoming</td>
                                                            <td>2025-05-14</td>
                                                            <td>10:30 AM</td>
                                                            <td>10:45 AM</td>
                                                            <td>2025-05-21</td>
                                                            <td>15 min</td>
                                                            <td>Interested in premium package</td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editCallLogModal">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-danger">
                                                                    <i class="mdi mdi-delete"></i> Delete
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>2</td>
                                                            <td>Jane Smith</td>
                                                            <td>+1 987 654 321</td>
                                                            <td>Support</td>
                                                            <td>Outgoing</td>
                                                            <td>2025-05-13</td>
                                                            <td>2:15 PM</td>
                                                            <td>2:30 PM</td>
                                                            <td>-</td>
                                                            <td>15 min</td>
                                                            <td>Resolved billing issue</td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editCallLogModal">
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
                                                <nav aria-label="Call log list pagination">
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
                                            <form id="addCallLogForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="callType" class="form-label">Call Type <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="callType" required>
                                                                <option value="">Select Type</option>
                                                                <option value="Incoming">Incoming</option>
                                                                <option value="Outgoing">Outgoing</option>
                                                                <option value="Missed">Missed</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="callingPurpose" class="form-label">Calling Purpose <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="callingPurpose" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="callerName" class="form-label">Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="callerName" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="mobileNo" class="form-label">Mobile No <span class="text-danger">*</span></label>
                                                            <input type="tel" class="form-control" id="mobileNo" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="callDate" class="form-label">Date <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control" id="callDate" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Time Slot <span class="text-danger">*</span></label>
                                                            <div class="time-slot-container">
                                                                <input type="time" class="form-control" id="startTime" required>
                                                                <span>to</span>
                                                                <input type="time" class="form-control" id="endTime" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="followUpDate" class="form-label">Follow Up Date</label>
                                                            <input type="date" class="form-control" id="followUpDate">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="callNote" class="form-label">Note</label>
                                                            <textarea class="form-control" id="callNote" rows="2"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-end">
                                                    <button type="reset" class="btn btn-light me-2">Reset</button>
                                                    <button type="submit" class="btn btn-primary">Save Call Log</button>
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

    <!-- Edit Call Log Modal -->
    <div class="modal fade" id="editCallLogModal" tabindex="-1" aria-labelledby="editCallLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCallLogModalLabel">Edit Call Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editCallLogForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editCallType" class="form-label">Call Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="editCallType" required>
                                        <option value="Incoming" selected>Incoming</option>
                                        <option value="Outgoing">Outgoing</option>
                                        <option value="Missed">Missed</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="editCallingPurpose" class="form-label">Calling Purpose <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editCallingPurpose" value="Product Inquiry" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editCallerName" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editCallerName" value="John Doe" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editMobileNo" class="form-label">Mobile No <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="editMobileNo" value="+1 234 567 890" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editCallDate" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="editCallDate" value="2025-05-14" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Time Slot <span class="text-danger">*</span></label>
                                    <div class="time-slot-container">
                                        <input type="time" class="form-control" id="editStartTime" value="10:30" required>
                                        <span>to</span>
                                        <input type="time" class="form-control" id="editEndTime" value="10:45" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="editFollowUpDate" class="form-label">Follow Up Date</label>
                                    <input type="date" class="form-control" id="editFollowUpDate" value="2025-05-21">
                                </div>
                                <div class="mb-3">
                                    <label for="editCallNote" class="form-label">Note</label>
                                    <textarea class="form-control" id="editCallNote" rows="2">Interested in premium package</textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Update Call Log</button>
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
            $('#callLogTable').DataTable({
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

            // Set today's date as default for call date
            const today = new Date().toISOString().split('T')[0];
            $('#callDate').val(today);

            // Form submission handler for adding call log
            $('#addCallLogForm').on('submit', function(e) {
                e.preventDefault();
                
                // Calculate duration
                const startTime = $('#startTime').val();
                const endTime = $('#endTime').val();
                
                if (startTime && endTime) {
                    const start = new Date(`2000-01-01T${startTime}`);
                    const end = new Date(`2000-01-01T${endTime}`);
                    const durationMs = end - start;
                    const durationMinutes = Math.floor(durationMs / 60000);
                    const durationText = durationMinutes > 0 ? `${durationMinutes} min` : '0 min';
                    
                    // In a real application, you would send this to the server
                    alert(`Call log saved successfully!\nDuration: ${durationText}`);
                    
                    this.reset();
                    $('#callDate').val(today); // Reset to today's date
                    $('#callLogTabs button[data-bs-target="#list"]').tab('show');
                } else {
                    alert('Please enter both start and end times');
                }
            });

            // Form submission handler for editing call log
            $('#editCallLogForm').on('submit', function(e) {
                e.preventDefault();
                alert('Call log updated successfully!');
                $('#editCallLogModal').modal('hide');
            });
        });
    </script>
</body>
</html>