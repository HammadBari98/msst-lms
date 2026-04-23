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
    <title>LMS - Product Management</title>

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
                                    <h4 class="card-title">Product Management</h4>

                                    <ul class="nav nav-tabs" id="productTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Product List
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button" role="tab" aria-controls="create" aria-selected="false">
                                                <i class="mdi mdi-plus-circle me-1"></i> Create Product
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="productTabsContent">
                                        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div class="table-responsive">
                                                <table id="productsTable" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Sl</th>
                                                            <th>Name</th>
                                                            <th>Code</th>
                                                            <th>Category</th>
                                                            <th>Purchase Unit</th>
                                                            <th>Sale Unit</th>
                                                            <th>Unit Ratio</th>
                                                            <th>Purchase Price</th>
                                                            <th>Sales Price</th>
                                                            <th>Remarks</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>1</td>
                                                            <td>Premium Rice</td>
                                                            <td>PR001</td>
                                                            <td>Food</td>
                                                            <td>KG</td>
                                                            <td>Gram</td>
                                                            <td>1000</td>
                                                            <td>$1.20</td>
                                                            <td>$1.50</td>
                                                            <td>High quality rice</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>2</td>
                                                            <td>Organic Sugar</td>
                                                            <td>OS001</td>
                                                            <td>Food</td>
                                                            <td>KG</td>
                                                            <td>Gram</td>
                                                            <td>1000</td>
                                                            <td>$0.80</td>
                                                            <td>$1.00</td>
                                                            <td>Organic certified</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>3</td>
                                                            <td>Mineral Water</td>
                                                            <td>MW001</td>
                                                            <td>Beverage</td>
                                                            <td>Liter</td>
                                                            <td>ML</td>
                                                            <td>1000</td>
                                                            <td>$0.30</td>
                                                            <td>$0.50</td>
                                                            <td>500ml bottles</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>4</td>
                                                            <td>Notebook</td>
                                                            <td>NB001</td>
                                                            <td>Stationery</td>
                                                            <td>Box</td>
                                                            <td>Piece</td>
                                                            <td>12</td>
                                                            <td>$12.00</td>
                                                            <td>$15.00</td>
                                                            <td>100 pages each</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex justify-content-between mt-3">
                                                <div>Showing 1 to 4 of 4 entries</div>
                                                <nav aria-label="Product list pagination">
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

                                        <div class="tab-pane fade" id="create" role="tabpanel" aria-labelledby="create-tab">
                                            <div>
                                               
                                                <form id="createProductForm">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="productName" class="form-label">Product Name <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" id="productName" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="productCode" class="form-label">Product Code <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" id="productCode" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="productCategory" class="form-label">Product Category <span class="text-danger">*</span></label>
                                                                <select class="form-select" id="productCategory" required>
                                                                    <option value="" selected>Select Category</option>
                                                                    <option value="Food">Food</option>
                                                                    <option value="Beverage">Beverage</option>
                                                                    <option value="Electronics">Electronics</option>
                                                                    <option value="Clothing">Clothing</option>
                                                                    <option value="Stationery">Stationery</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="purchaseUnit" class="form-label">Purchase Unit <span class="text-danger">*</span></label>
                                                                <select class="form-select" id="purchaseUnit" required>
                                                                    <option value="" selected>Select Unit</option>
                                                                    <option value="KG">KG</option>
                                                                    <option value="Liter">Liter</option>
                                                                    <option value="Piece">Piece</option>
                                                                    <option value="Box">Box</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="salesUnit" class="form-label">Sales Unit <span class="text-danger">*</span></label>
                                                                <select class="form-select" id="salesUnit" required>
                                                                    <option value="" selected>Select Unit</option>
                                                                    <option value="Gram">Gram</option>
                                                                    <option value="ML">ML</option>
                                                                    <option value="Piece">Piece</option>
                                                                    <option value="Pack">Pack</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="unitRatio" class="form-label">Unit Ratio <span class="text-danger">*</span><br></label>
                                                                <input type="number" class="form-control" id="unitRatio" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="purchasePrice" class="form-label">Purchase Price <span class="text-danger">*</span></label>
                                                                <input type="number" class="form-control" id="purchasePrice" step="0.01" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="salesPrice" class="form-label">Sales Price <span class="text-danger">*</span></label>
                                                                <input type="number" class="form-control" id="salesPrice" step="0.01" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="remarks" class="form-label">Remarks</label>
                                                                <textarea class="form-control" id="remarks" rows="3"></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-end">
                                                        <button type="reset" class="btn btn-light me-2">Reset</button>
                                                        <button type="submit" class="btn btn-primary">Save Product</button>
                                                    </div>
                                                </form>
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
            $('#productsTable').DataTable({
                responsive: true,
                paging: false, // Disable pagination as per the provided structure
                info: true,   // Enable info text
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

            // Form submission handler for creating product
            $('#createProductForm').on('submit', function(e) {
                e.preventDefault();
                alert('Product saved successfully!');
                this.reset();
                $('#productTabs button[data-bs-target="#list"]').tab('show');
            });
        });
    </script>
</body>
</html>

