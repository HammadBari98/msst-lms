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
    <title>LMS - Reference Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/typicons/typicons.css">
    <link rel="stylesheet" href="assets/vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />

    <style>
        .action-buttons {
            white-space: nowrap;
        }
        .nav-tabs .nav-link {
            font-weight: 500;
        }
        .tab-content {
            padding: 20px 0;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .empty-message {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 20px;
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
                                    <h4 class="card-title">Reference Management</h4>

                                    <ul class="nav nav-tabs" id="referenceTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="reference-tab" data-bs-toggle="tab" data-bs-target="#reference" type="button" role="tab" aria-controls="reference" aria-selected="true">
                                                <i class="mdi mdi-bookmark-outline me-1"></i> Reference
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="response-tab" data-bs-toggle="tab" data-bs-target="#response" type="button" role="tab" aria-controls="response" aria-selected="false">
                                                <i class="mdi mdi-comment-text-outline me-1"></i> Response
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="calling-purpose-tab" data-bs-toggle="tab" data-bs-target="#calling-purpose" type="button" role="tab" aria-controls="calling-purpose" aria-selected="false">
                                                <i class="mdi mdi-phone-outline me-1"></i> Calling Purpose
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="visiting-purpose-tab" data-bs-toggle="tab" data-bs-target="#visiting-purpose" type="button" role="tab" aria-controls="visiting-purpose" aria-selected="false">
                                                <i class="mdi mdi-account-outline me-1"></i> Visiting Purpose
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="complaint-type-tab" data-bs-toggle="tab" data-bs-target="#complaint-type" type="button" role="tab" aria-controls="complaint-type" aria-selected="false">
                                                <i class="mdi mdi-alert-outline me-1"></i> Complaint Type
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content py-3" id="referenceTabsContent">
                                        <!-- Reference Tab -->
                                        <div class="tab-pane fade show active" id="reference" role="tabpanel" aria-labelledby="reference-tab">
                                            <div class="mb-4">
                                                <h5 class="section-title">Add Reference</h5>
                                                <form id="addReferenceForm" class="row g-3">
                                                    <div class="col-md-8">
                                                        <label for="referenceName" class="form-label">Name <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="referenceName" required>
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-primary">Add Reference</button>
                                                    </div>
                                                </form>
                                            </div>
                                            
                                            <div>
                                                <h5 class="section-title">Reference List</h5>
                                                <div class="table-responsive">
                                                    <table id="referenceTable" class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Sl</th>
                                                                <th>Name</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="3" class="empty-message">No Information Available</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Response Tab -->
                                        <div class="tab-pane fade" id="response" role="tabpanel" aria-labelledby="response-tab">
                                            <div class="mb-4">
                                                <h5 class="section-title">Add Response</h5>
                                                <form id="addResponseForm" class="row g-3">
                                                    <div class="col-md-8">
                                                        <label for="responseName" class="form-label">Name <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="responseName" required>
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-primary">Add Response</button>
                                                    </div>
                                                </form>
                                            </div>
                                            
                                            <div>
                                                <h5 class="section-title">Response List</h5>
                                                <div class="table-responsive">
                                                    <table id="responseTable" class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Sl</th>
                                                                <th>Name</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="3" class="empty-message">No Information Available</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Calling Purpose Tab -->
                                        <div class="tab-pane fade" id="calling-purpose" role="tabpanel" aria-labelledby="calling-purpose-tab">
                                            <div class="mb-4">
                                                <h5 class="section-title">Add Calling Purpose</h5>
                                                <form id="addCallingPurposeForm" class="row g-3">
                                                    <div class="col-md-8">
                                                        <label for="callingPurposeName" class="form-label">Name <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="callingPurposeName" required>
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-primary">Add Purpose</button>
                                                    </div>
                                                </form>
                                            </div>
                                            
                                            <div>
                                                <h5 class="section-title">Calling Purpose List</h5>
                                                <div class="table-responsive">
                                                    <table id="callingPurposeTable" class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Sl</th>
                                                                <th>Name</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="3" class="empty-message">No Information Available</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Visiting Purpose Tab -->
                                        <div class="tab-pane fade" id="visiting-purpose" role="tabpanel" aria-labelledby="visiting-purpose-tab">
                                            <div class="mb-4">
                                                <h5 class="section-title">Add Visiting Purpose</h5>
                                                <form id="addVisitingPurposeForm" class="row g-3">
                                                    <div class="col-md-8">
                                                        <label for="visitingPurposeName" class="form-label">Name <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="visitingPurposeName" required>
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-primary">Add Purpose</button>
                                                    </div>
                                                </form>
                                            </div>
                                            
                                            <div>
                                                <h5 class="section-title">Visiting Purpose List</h5>
                                                <div class="table-responsive">
                                                    <table id="visitingPurposeTable" class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Sl</th>
                                                                <th>Name</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="3" class="empty-message">No Information Available</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Complaint Type Tab -->
                                        <div class="tab-pane fade" id="complaint-type" role="tabpanel" aria-labelledby="complaint-type-tab">
                                            <div class="mb-4">
                                                <h5 class="section-title">Add Complaint Type</h5>
                                                <form id="addComplaintTypeForm" class="row g-3">
                                                    <div class="col-md-8">
                                                        <label for="complaintTypeName" class="form-label">Name <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="complaintTypeName" required>
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-primary">Add Type</button>
                                                    </div>
                                                </form>
                                            </div>
                                            
                                            <div>
                                                <h5 class="section-title">Complaint Type List</h5>
                                                <div class="table-responsive">
                                                    <table id="complaintTypeTable" class="table table-striped table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Sl</th>
                                                                <th>Name</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="3" class="empty-message">No Information Available</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
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

    <!-- Edit Modal (for all types) -->
    <div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editItemModalLabel">Edit Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editItemForm">
                        <input type="hidden" id="editItemId">
                        <input type="hidden" id="editItemType">
                        <div class="mb-3">
                            <label for="editItemName" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editItemName" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveEditItem">Save Changes</button>
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

    <script>
        $(document).ready(function () {
            // Initialize all DataTables
            $('table[id$="Table"]').DataTable({
                responsive: true,
                paging: false,
                searching: true,
                info: false,
                ordering: true
            });

            // Sample data for demonstration
            const sampleData = {
                reference: [],
                response: [],
                callingPurpose: [],
                visitingPurpose: [],
                complaintType: []
            };

            // Form submission handlers
            $('#addReferenceForm').on('submit', function(e) {
                e.preventDefault();
                addItem('reference');
            });

            $('#addResponseForm').on('submit', function(e) {
                e.preventDefault();
                addItem('response');
            });

            $('#addCallingPurposeForm').on('submit', function(e) {
                e.preventDefault();
                addItem('callingPurpose');
            });

            $('#addVisitingPurposeForm').on('submit', function(e) {
                e.preventDefault();
                addItem('visitingPurpose');
            });

            $('#addComplaintTypeForm').on('submit', function(e) {
                e.preventDefault();
                addItem('complaintType');
            });

            // Edit button click handler (delegated)
            $(document).on('click', '.edit-btn', function() {
                const type = $(this).data('type');
                const id = $(this).data('id');
                const name = $(this).data('name');
                
                $('#editItemType').val(type);
                $('#editItemId').val(id);
                $('#editItemName').val(name);
                $('#editItemModalLabel').text(`Edit ${typeToTitle(type)}`);
                
                $('#editItemModal').modal('show');
            });

            // Save edit handler
            $('#saveEditItem').on('click', function() {
                const type = $('#editItemType').val();
                const id = $('#editItemId').val();
                const newName = $('#editItemName').val();
                
                if (!newName) {
                    alert('Please enter a name');
                    return;
                }
                
                // In a real app, you would update the data in your database
                // For this demo, we'll just update our sample data
                const index = sampleData[type].findIndex(item => item.id == id);
                if (index !== -1) {
                    sampleData[type][index].name = newName;
                    updateTable(type);
                }
                
                $('#editItemModal').modal('hide');
                alert(`${typeToTitle(type)} updated successfully!`);
            });

            // Delete button click handler (delegated)
            $(document).on('click', '.delete-btn', function() {
                if (confirm('Are you sure you want to delete this item?')) {
                    const type = $(this).data('type');
                    const id = $(this).data('id');
                    
                    // In a real app, you would delete from your database
                    // For this demo, we'll just remove from our sample data
                    sampleData[type] = sampleData[type].filter(item => item.id != id);
                    updateTable(type);
                    
                    alert(`${typeToTitle(type)} deleted successfully!`);
                }
            });

            // Helper function to add an item
            function addItem(type) {
                const nameField = $(`#${type}Name`);
                const name = nameField.val().trim();
                
                if (!name) {
                    alert('Please enter a name');
                    return;
                }
                
                // In a real app, you would save to your database
                // For this demo, we'll just add to our sample data
                const newItem = {
                    id: Date.now(), // Using timestamp as ID for demo
                    name: name,
                    sl: sampleData[type].length + 1
                };
                
                sampleData[type].push(newItem);
                updateTable(type);
                
                nameField.val(''); // Clear the input
                alert(`${typeToTitle(type)} added successfully!`);
            }

            // Helper function to update a table
            function updateTable(type) {
                const table = $(`#${type}Table`).DataTable();
                table.clear();
                
                if (sampleData[type].length === 0) {
                    table.row.add([
                        '',
                        '<div class="empty-message">No Information Available</div>',
                        ''
                    ]).node().id = 'empty-row';
                } else {
                    sampleData[type].forEach((item, index) => {
                        table.row.add([
                            index + 1,
                            item.name,
                            `<div class="action-buttons">
                                <button class="btn btn-sm btn-primary edit-btn" data-type="${type}" data-id="${item.id}" data-name="${item.name}">
                                    <i class="mdi mdi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" data-type="${type}" data-id="${item.id}">
                                    <i class="mdi mdi-delete"></i> Delete
                                </button>
                            </div>`
                        ]);
                    });
                }
                
                table.draw();
            }

            // Helper function to convert type to title
            function typeToTitle(type) {
                return type.replace(/([A-Z])/g, ' $1')
                    .replace(/^./, str => str.toUpperCase())
                    .replace('Purpose', ' Purpose')
                    .replace('Type', ' Type');
            }
        });
    </script>
</body>
</html>