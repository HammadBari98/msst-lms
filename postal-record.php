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
    <title>LMS - Postal Record</title>

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
        .badge-confidential {
            background-color: #6f42c1;
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
                                    <h4 class="card-title">Postal Record</h4>

                                    <ul class="nav nav-tabs" id="postalTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Postal Record List
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="false">
                                                <i class="mdi mdi-plus-circle me-1"></i> Add Postal Record
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="postalTabsContent">
                                        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div class="table-responsive">
                                                <table id="postalTable" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Sl</th>
                                                            <th>Type</th>
                                                            <th>Sender Title</th>
                                                            <th>Receiver Title</th>
                                                            <th>Date</th>
                                                            <th>Confidential</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>1</td>
                                                            <td>Incoming</td>
                                                            <td>Ministry of Education</td>
                                                            <td>Principal</td>
                                                            <td>2025-05-10</td>
                                                            <td><span class="badge badge-confidential">Yes</span></td>
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
                                                            <td>Outgoing</td>
                                                            <td>Principal</td>
                                                            <td>District Education Office</td>
                                                            <td>2025-05-12</td>
                                                            <td><span class="badge bg-secondary">No</span></td>
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
                                                            <td>Incoming</td>
                                                            <td>ABC Publishers</td>
                                                            <td>Librarian</td>
                                                            <td>2025-05-14</td>
                                                            <td><span class="badge bg-secondary">No</span></td>
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
                                                <nav aria-label="Postal list pagination">
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
                                            <form id="addPostalForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="type" class="form-label required-field">Type</label>
                                                            <select class="form-select" id="type" required>
                                                                <option value="">Select Type</option>
                                                                <option value="Incoming">Incoming</option>
                                                                <option value="Outgoing">Outgoing</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="referenceNo" class="form-label required-field">Reference No</label>
                                                            <input type="text" class="form-control" id="referenceNo" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="senderTitle" class="form-label required-field">Sender Title</label>
                                                            <input type="text" class="form-control" id="senderTitle" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="receiverTitle" class="form-label required-field">Receiver Title</label>
                                                            <input type="text" class="form-control" id="receiverTitle" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="address" class="form-label required-field">Address</label>
                                                            <textarea class="form-control" id="address" rows="2" required></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="note" class="form-label">Note</label>
                                                            <textarea class="form-control" id="note" rows="2"></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="postalDate" class="form-label required-field">Date</label>
                                                            <input type="date" class="form-control" id="postalDate" value="2025-05-15" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="documentFile" class="form-label">Document File</label>
                                                            <input type="file" class="form-control" id="documentFile">
                                                        </div>
                                                        <div class="mb-3">
                                                            <div class="switch-container">
                                                                <label class="form-label">Confidential</label>
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="confidential">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-end">
                                                    <button type="reset" class="btn btn-light me-2">Reset</button>
                                                    <button type="submit" class="btn btn-primary">Save Record</button>
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
            $('#postalTable').DataTable({
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
            
            // Form submission handler for adding postal record
            $('#addPostalForm').on('submit', function(e) {
                e.preventDefault();
                alert('Postal record saved successfully!');
                this.reset();
                $('#postalTabs button[data-bs-target="#list"]').tab('show');
            });
        });
    </script>
</body>
</html>