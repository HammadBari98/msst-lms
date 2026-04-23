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
    <title>LMS - Supplier Management</title>
  
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    <!-- Vendor CSS -->
    <link rel="stylesheet" href="assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/typicons/typicons.css">
    <link rel="stylesheet" href="assets/vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css">
  
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
  
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
  
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="container-scroller">
        <?php include 'header.php' ?>
        <div class="container-fluid page-body-wrapper">
            <?php include 'sidebar.php' ?>

            <!-- Main Content -->
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="row">
                        <div class="col-md-12 grid-margin">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Supplier Management</h4>
                                    
                                    <!-- Tab Navigation -->
                                    <ul class="nav nav-tabs" id="supplierTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">
                                                <i class="mdi mdi-format-list-bulleted me-1"></i> Supplier List
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button" role="tab" aria-controls="create" aria-selected="false">
                                                <i class="mdi mdi-plus-circle me-1"></i> Create Supplier
                                            </button>
                                        </li>
                                    </ul>
                                    
                                    <!-- Tab Content -->
                                    <div class="tab-content py-3" id="supplierTabsContent">
                                        <!-- Supplier List Tab -->
                                        <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                                            <div class="table-responsive">
                                                <table id="suppliersTable" class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Sl</th>
                                                            <th>Supplier Name</th>
                                                            <th>Address</th>
                                                            <th>Contact Number</th>
                                                            <th>Email</th>
                                                            <th>Company Name</th>
                                                            <th>Product List</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>1</td>
                                                            <td>ABC Suppliers</td>
                                                            <td>123 Business Park, City</td>
                                                            <td>+1234567890</td>
                                                            <td>contact@abcsuppliers.com</td>
                                                            <td>ABC Suppliers Inc.</td>
                                                            <td>Electronics, Components</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>2</td>
                                                            <td>XYZ Wholesale</td>
                                                            <td>456 Industrial Zone, City</td>
                                                            <td>+1987654321</td>
                                                            <td>sales@xyzwholesale.com</td>
                                                            <td>XYZ Wholesale Ltd.</td>
                                                            <td>Office Supplies, Furniture</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary"><i class="mdi mdi-pencil"></i></button>
                                                                <button class="btn btn-sm btn-danger"><i class="mdi mdi-delete"></i></button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- Create Supplier Tab -->
                                        <div class="tab-pane fade" id="create" role="tabpanel" aria-labelledby="create-tab">
                                            <form id="createSupplierForm">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="supplierName" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" id="supplierName" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="contactNumber" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                                            <input type="tel" class="form-control" id="contactNumber" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="email" class="form-label">Email</label>
                                                            <input type="email" class="form-control" id="email">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="companyName" class="form-label">Company Name</label>
                                                            <input type="text" class="form-control" id="companyName">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="productList" class="form-label">Product List</label>
                                                            <textarea class="form-control" id="productList" rows="2" placeholder="Enter products separated by commas"></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="address" class="form-label">Address</label>
                                                            <textarea class="form-control" id="address" rows="4"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex justify-content-end">
                                                    <button type="reset" class="btn btn-light me-2">Reset</button>
                                                    <button type="submit" class="btn btn-primary">Save Supplier</button>
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

    <!-- Core JS Files (MUST BE IN THIS ORDER) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/hoverable-collapse.js"></script>
    <script src="assets/js/template.js"></script>
    <script src="assets/js/settings.js"></script>
    <script src="assets/js/todolist.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
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

    <!-- Supplier Management Script -->
    <script>
        $(document).ready(function () {
            // Initialize DataTable
            $('#suppliersTable').DataTable({
                responsive: true,
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
            
            // Form submission handler
            $('#createSupplierForm').on('submit', function(e) {
                e.preventDefault();
                alert('Supplier saved successfully!');
                this.reset();
                $('#supplierTabs button[data-bs-target="#list"]').tab('show');
            });
        });
    </script>
</body>
</html>