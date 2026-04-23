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
    <title>LMS - Admission Enquiry</title>

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
        .switch-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-left: 0;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .required-field::after {
            content: " *";
            color: red;
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
                                    <h4 class="card-title">Admission Enquiry</h4>

                                    <ul class="nav nav-tabs" id="enquiryTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Enquiry List
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="false">
                                                <i class="mdi mdi-plus-circle me-1"></i> Add Enquiry
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="enquiryTabsContent">
                                        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div class="table-responsive">
                                                <table id="enquiryTable" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Sl</th>
                                                            <th>Name</th>
                                                            <th>Mobile No</th>
                                                            <th>Guardian</th>
                                                            <th>Reference</th>
                                                            <th>Enquiry Date</th>
                                                            <th>Next Follow Up Date</th>
                                                            <th>Status</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>1</td>
                                                            <td>John Doe</td>
                                                            <td>9876543210</td>
                                                            <td>Father</td>
                                                            <td>Website</td>
                                                            <td>2025-05-10</td>
                                                            <td>2025-05-17</td>
                                                            <td><span class="badge bg-success">Active</span></td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary">
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
                                                            <td>8765432109</td>
                                                            <td>Mother</td>
                                                            <td>Friend</td>
                                                            <td>2025-05-12</td>
                                                            <td>2025-05-19</td>
                                                            <td><span class="badge bg-warning">Pending</span></td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-danger">
                                                                    <i class="mdi mdi-delete"></i> Delete
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>3</td>
                                                            <td>Robert Johnson</td>
                                                            <td>7654321098</td>
                                                            <td>Father</td>
                                                            <td>Newspaper</td>
                                                            <td>2025-05-14</td>
                                                            <td>2025-05-21</td>
                                                            <td><span class="badge bg-danger">Closed</span></td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary">
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
                                                <div>Showing 1 to 3 of 3 entries</div>
                                                <nav aria-label="Enquiry list pagination">
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
                                            <form id="addEnquiryForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="name" class="form-label required-field">Name</label>
                                                            <input type="text" class="form-control" id="name" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="gender" class="form-label required-field">Gender</label>
                                                            <select class="form-select" id="gender" required>
                                                                <option value="">Select Gender</option>
                                                                <option value="Male">Male</option>
                                                                <option value="Female">Female</option>
                                                                <option value="Other">Other</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="dob" class="form-label">Date Of Birth</label>
                                                            <input type="date" class="form-control" id="dob">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="previousSchool" class="form-label">Previous School</label>
                                                            <input type="text" class="form-control" id="previousSchool">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="fatherName" class="form-label required-field">Father Name</label>
                                                            <input type="text" class="form-control" id="fatherName" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="motherName" class="form-label required-field">Mother Name</label>
                                                            <input type="text" class="form-control" id="motherName" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="mobileNo" class="form-label required-field">Mobile No</label>
                                                            <input type="tel" class="form-control" id="mobileNo" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="email" class="form-label">Email</label>
                                                            <input type="email" class="form-control" id="email">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="address" class="form-label required-field">Address</label>
                                                            <textarea class="form-control" id="address" rows="2" required></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="noOfChild" class="form-label required-field">No Of Child</label>
                                                            <input type="number" class="form-control" id="noOfChild" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="assigned" class="form-label required-field">Assigned</label>
                                                            <input type="text" class="form-control" id="assigned" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="reference" class="form-label required-field">Reference</label>
                                                            <input type="text" class="form-control" id="reference" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="response" class="form-label required-field">Response</label>
                                                            <select class="form-select" id="response" required>
                                                                <option value="">Select Response</option>
                                                                <option value="Interested">Interested</option>
                                                                <option value="Not Interested">Not Interested</option>
                                                                <option value="Follow Up">Follow Up</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="note" class="form-label">Note</label>
                                                            <textarea class="form-control" id="note" rows="2"></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="enquiryDate" class="form-label required-field">Date</label>
                                                            <input type="date" class="form-control" id="enquiryDate" value="2025-05-15" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="classApplying" class="form-label required-field">Class Applying For</label>
                                                            <select class="form-select" id="classApplying" required>
                                                                <option value="">Select Class</option>
                                                                <option value="Nursery">Nursery</option>
                                                                <option value="LKG">LKG</option>
                                                                <option value="UKG">UKG</option>
                                                                <option value="1">Class 1</option>
                                                                <option value="2">Class 2</option>
                                                                <!-- Add more classes as needed -->
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-end">
                                                    <button type="reset" class="btn btn-light me-2">Reset</button>
                                                    <button type="submit" class="btn btn-primary">Save Enquiry</button>
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
            $('#enquiryTable').DataTable({
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
            // Form submission handler for adding enquiry
            $('#addEnquiryForm').on('submit', function(e) {
                e.preventDefault();
                alert('Enquiry saved successfully!');
                this.reset();
                $('#enquiryTabs button[data-bs-target="#list"]').tab('show');
            });
        });
    </script>
</body>
</html>