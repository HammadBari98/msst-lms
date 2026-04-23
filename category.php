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
    <title>LMS - Category Management</title>

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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                                    <h4 class="card-title">Category Management</h4>

                                    <ul class="nav nav-tabs" id="categoryTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="true">
                                                <i class="mdi mdi-plus-circle me-1"></i> Add Category
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="false">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Category List
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="categoryTabsContent">
                                        <div class="tab-pane fade show active" id="add" role="tabpanel" aria-labelledby="add-tab">
                                            <div>
                                               
                                                <form id="addCategoryForm">
                                                    <div class="mb-3">
                                                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="categoryName" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="tab-pane fade" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div>
                                               
                                                <div class="table-responsive">
                                                    <table id="categoriesTable" class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Sl</th>
                                                                <th>Category Name</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td>1</td>
                                                                <td>Electronics</td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                    <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>2</td>
                                                                <td>Clothing</td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                    <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>3</td>
                                                                <td>Food</td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                    <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>4</td>
                                                                <td>Furniture</td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                    <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>5</td>
                                                                <td>Stationery</td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                    <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="d-flex justify-content-between mt-3">
                                                    <!-- <div>Showing 1 to 5 of 5 entries</div> -->
                                                    <nav aria-label="Category list pagination">
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize DataTable
            $('#categoriesTable').DataTable({
                responsive: true,
                paging: false, // Disable pagination as per the provided structure
                info: true,   // Enable info text ("Showing 1 to 5 of 5 entries")
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
                    },
                    {
                        extend: 'colvis',
                        className: 'btn btn-sm btn-primary'
                    }
                ]
            });

            // Form submission handler for adding category
            $('#addCategoryForm').on('submit', function(e) {
                e.preventDefault();
                alert('Category saved successfully!');
                this.reset();
                $('#categoryTabs button[data-bs-target="#list"]').tab('show');
            });
        });
    </script>
</body>
</html>