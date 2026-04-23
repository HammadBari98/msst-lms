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
    <title>LMS - Menu Management</title>

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
                                    <h4 class="card-title">Menu Management</h4>

                                    <ul class="nav nav-tabs" id="menuTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Menu List
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="false">
                                                <i class="mdi mdi-plus-circle me-1"></i> Add Menu
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="menuTabsContent">
                                        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div class="table-responsive">
                                                <table id="menuTable" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Sl</th>
                                                            <th>Menu Type</th>
                                                            <th>Title</th>
                                                            <th>Position</th>
                                                            <th>Sub Menu</th>
                                                            <th>Publish</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>1</td>
                                                            <td>Main Menu</td>
                                                            <td>Home</td>
                                                            <td>1</td>
                                                            <td>No</td>
                                                            <td>
                                                                <div class="switch-container">
                                                                    <div class="form-check form-switch">
                                                                        <input class="form-check-input" type="checkbox" id="publish1" checked>
                                                                    </div>
                                                                    <label class="form-check-label" for="publish1">Active</label>
                                                                </div>
                                                            </td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editMenuModal">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-danger">
                                                                    <i class="mdi mdi-delete"></i> Delete
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>2</td>
                                                            <td>Main Menu</td>
                                                            <td>About Us</td>
                                                            <td>2</td>
                                                            <td>Yes</td>
                                                            <td>
                                                                <div class="switch-container">
                                                                    <div class="form-check form-switch">
                                                                        <input class="form-check-input" type="checkbox" id="publish2" checked>
                                                                    </div>
                                                                    <label class="form-check-label" for="publish2">Active</label>
                                                                </div>
                                                            </td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editMenuModal">
                                                                    <i class="mdi mdi-pencil"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-danger">
                                                                    <i class="mdi mdi-delete"></i> Delete
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>3</td>
                                                            <td>Sub Menu</td>
                                                            <td>Our History</td>
                                                            <td>1</td>
                                                            <td>No</td>
                                                            <td>
                                                                <div class="switch-container">
                                                                    <div class="form-check form-switch">
                                                                        <input class="form-check-input" type="checkbox" id="publish3">
                                                                    </div>
                                                                    <label class="form-check-label" for="publish3">Inactive</label>
                                                                </div>
                                                            </td>
                                                            <td class="action-buttons">
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editMenuModal">
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
                                                <nav aria-label="Menu list pagination">
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
                                            <form id="addMenuForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="menuTitle" class="form-label">Title <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="menuTitle" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="menuPosition" class="form-label">Position <span class="text-danger">*</span></label>
                                                            <input type="number" class="form-control" id="menuPosition" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="menuType" class="form-label">Menu Type <span class="text-danger">*</span></label>
                                                            <select class="form-select" id="menuType" required>
                                                                <option value="">Select Type</option>
                                                                <option value="Main Menu">Main Menu</option>
                                                                <option value="Sub Menu">Sub Menu</option>
                                                                <option value="Footer Menu">Footer Menu</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="parentMenu" class="form-label">Parent Menu</label>
                                                            <select class="form-select" id="parentMenu">
                                                                <option value="">None</option>
                                                                <option value="1">Home</option>
                                                                <option value="2">About Us</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <div class="switch-container">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="menuPublish" checked>
                                                                </div>
                                                                <label class="form-check-label" for="menuPublish">Publish</label>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <div class="switch-container">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="menuTargetNewWindow">
                                                                </div>
                                                                <label class="form-check-label" for="menuTargetNewWindow">Target New Window</label>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <div class="switch-container">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="menuExternalUrl">
                                                                </div>
                                                                <label class="form-check-label" for="menuExternalUrl">External Url</label>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3" id="externalUrlField" style="display: none;">
                                                            <label for="externalUrl" class="form-label">External URL</label>
                                                            <input type="url" class="form-control" id="externalUrl">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-end">
                                                    <button type="reset" class="btn btn-light me-2">Reset</button>
                                                    <button type="submit" class="btn btn-primary">Save Menu</button>
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

    <!-- Edit Menu Modal -->
    <div class="modal fade" id="editMenuModal" tabindex="-1" aria-labelledby="editMenuModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMenuModalLabel">Edit Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editMenuForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editMenuTitle" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editMenuTitle" value="Home" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editMenuPosition" class="form-label">Position <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="editMenuPosition" value="1" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editMenuType" class="form-label">Menu Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="editMenuType" required>
                                        <option value="Main Menu" selected>Main Menu</option>
                                        <option value="Sub Menu">Sub Menu</option>
                                        <option value="Footer Menu">Footer Menu</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="editParentMenu" class="form-label">Parent Menu</label>
                                    <select class="form-select" id="editParentMenu">
                                        <option value="">None</option>
                                        <option value="1">Home</option>
                                        <option value="2">About Us</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="switch-container">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="editMenuPublish" checked>
                                        </div>
                                        <label class="form-check-label" for="editMenuPublish">Publish</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="switch-container">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="editMenuTargetNewWindow">
                                        </div>
                                        <label class="form-check-label" for="editMenuTargetNewWindow">Target New Window</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="switch-container">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="editMenuExternalUrl">
                                        </div>
                                        <label class="form-check-label" for="editMenuExternalUrl">External Url</label>
                                    </div>
                                </div>
                                <div class="mb-3" id="editExternalUrlField" style="display: none;">
                                    <label for="editExternalUrl" class="form-label">External URL</label>
                                    <input type="url" class="form-control" id="editExternalUrl">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Update Menu</button>
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
            $('#menuTable').DataTable({
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

            // Toggle external URL field in add form
            $('#menuExternalUrl').change(function() {
                if($(this).is(':checked')) {
                    $('#externalUrlField').show();
                } else {
                    $('#externalUrlField').hide();
                }
            });

            // Toggle external URL field in edit form
            $('#editMenuExternalUrl').change(function() {
                if($(this).is(':checked')) {
                    $('#editExternalUrlField').show();
                } else {
                    $('#editExternalUrlField').hide();
                }
            });

            // Form submission handler for adding menu
            $('#addMenuForm').on('submit', function(e) {
                e.preventDefault();
                alert('Menu saved successfully!');
                this.reset();
                $('#menuTabs button[data-bs-target="#list"]').tab('show');
            });

            // Form submission handler for editing menu
            $('#editMenuForm').on('submit', function(e) {
                e.preventDefault();
                alert('Menu updated successfully!');
                $('#editMenuModal').modal('hide');
            });
        });
    </script>
</body>
</html>