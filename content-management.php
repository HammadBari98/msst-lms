<?php
session_start();

// Check if the admin is logged in.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Set admin details from the session.
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
date_default_timezone_set('Asia/Karachi');

// --- Dummy Content Data ---
$content_items = [
    [
        'id' => 'CNT-001', 'title' => 'Chapter 5: Quadratics - Notes', 'type' => 'PDF',
        'uploader' => 'Ayesha Khan', 'upload_date' => '2025-06-15',
        'access' => ['Class 10-A', 'Class 10-B'], 'file_name' => 'math_ch5_notes.pdf'
    ],
    [
        'id' => 'CNT-002', 'title' => 'Lab Safety Video', 'type' => 'Video',
        'uploader' => 'Admin', 'upload_date' => '2025-06-10',
        'access' => ['All Teachers', 'All Students'], 'file_name' => 'lab_safety.mp4'
    ],
    [
        'id' => 'CNT-003', 'title' => 'History of Pakistan - Slideshow', 'type' => 'PPT',
        'uploader' => 'Bilal Hassan', 'upload_date' => '2025-06-05',
        'access' => ['Class 8-C'], 'file_name' => 'pak_history.pptx'
    ],
];

// Data for forms
$available_access_groups = ['Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B', 'All Teachers', 'All Students', 'Staff'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .access-tag {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            margin: 2px;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Page Heading & Actions -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Content Management</h1>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#contentModal" onclick="prepareAddModal()">
                    <i class="fas fa-upload fa-sm text-white-50"></i> Upload New Content
                </button>
            </div>

            <!-- Content List Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Uploaded Materials</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead><tr><th>Title</th><th>Type</th><th>Uploaded By</th><th>Upload Date</th><th>Access Rights</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($content_items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['title']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($item['type']) ?></span></td>
                                        <td><?= htmlspecialchars($item['uploader']) ?></td>
                                        <td><?= htmlspecialchars($item['upload_date']) ?></td>
                                        <td>
                                            <?php foreach($item['access'] as $access): ?>
                                                <span class="access-tag bg-light text-dark border"><?= htmlspecialchars($access) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-info btn-sm" title="Edit" data-bs-toggle="modal" data-bs-target="#contentModal" onclick='prepareEditModal(<?= json_encode($item) ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="prepareDeleteModal('<?= htmlspecialchars($item['id']) ?>', '<?= htmlspecialchars($item['title']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> School Management System. All rights reserved.</p></div></footer>
</div>

<!-- Add/Edit Content Modal -->
<div class="modal fade" id="contentModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="contentModalLabel">Upload New Content</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form id="contentForm">
            <input type="hidden" id="contentId" name="id">
            <div class="mb-3"><label class="form-label">Content Title</label><input type="text" class="form-control" id="contentTitle" required></div>
            <div class="mb-3"><label class="form-label">Upload File</label><input class="form-control" type="file" id="contentFile"></div>
            <hr>
            <h5>Access Rights</h5>
            <p class="text-muted">Select which groups can view this content.</p>
            <div id="accessRightsContainer" class="d-flex flex-wrap">
                <?php foreach($available_access_groups as $group): ?>
                    <div class="form-check form-check-inline col-md-3">
                        <input class="form-check-input" type="checkbox" name="access[]" value="<?= $group ?>" id="access_<?= str_replace([' ', '-'], '_', $group) ?>">
                        <label class="form-check-label" for="access_<?= str_replace([' ', '-'], '_', $group) ?>"><?= $group ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" form="contentForm" class="btn btn-primary">Save Content</button></div>
</div></div></div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">Are you sure you want to delete the content: <strong id="contentToDeleteName"></strong>?</div>
    <div class="modal-footer"><input type="hidden" id="contentToDeleteId"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" onclick="deleteContent()">Delete</button></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Sidebar Script ---
    const sidebar = document.getElementById('sidebar'), mainContent = document.getElementById('main-content'), toggleBtn = document.getElementById('sidebarToggle');
    function saveSidebarState(c){localStorage.setItem('sidebarCollapsed',c);}
    function loadSidebarState(){return localStorage.getItem('sidebarCollapsed')==='true';}
    if(loadSidebarState()){sidebar.classList.add('collapsed');mainContent.classList.add('collapsed');}
    toggleBtn.addEventListener('click',()=>{sidebar.classList.toggle('collapsed');mainContent.classList.toggle('collapsed');saveSidebarState(sidebar.classList.contains('collapsed'));if(window.innerWidth<768){if(!sidebar.classList.contains('collapsed')){sidebar.classList.add('show');mainContent.classList.add('show-sidebar');}else{sidebar.classList.remove('show');mainContent.classList.remove('show-sidebar');}}});
    document.addEventListener('click',(e)=>{if(window.innerWidth<768&&sidebar.classList.contains('show')&&!sidebar.contains(e.target)&&!toggleBtn.contains(e.target)){sidebar.classList.remove('show');sidebar.classList.add('collapsed');mainContent.classList.remove('show-sidebar');mainContent.classList.add('collapsed');saveSidebarState(true);}});

    // --- Page-specific Scripts ---
    const accessRightsContainer = document.getElementById('accessRightsContainer');
    
    function prepareAddModal() {
        document.getElementById('contentForm').reset();
        document.getElementById('contentModalLabel').textContent = 'Upload New Content';
        document.getElementById('contentId').value = '';
    }

    function prepareEditModal(item) {
        document.getElementById('contentForm').reset();
        document.getElementById('contentModalLabel').textContent = `Edit Content: ${item.title}`;
        document.getElementById('contentId').value = item.id;
        document.getElementById('contentTitle').value = item.title;
        
        // Uncheck all boxes first
        accessRightsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        
        // Check relevant boxes
        item.access.forEach(group => {
            const checkboxId = `access_${group.replace(/[\\s-]/g, '_')}`;
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) checkbox.checked = true;
        });
    }
    
    document.getElementById('contentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('contentId').value;
        const action = id ? 'updated' : 'uploaded';
        alert(`Content has been ${action} successfully. (Simulation)`);
        bootstrap.Modal.getInstance(document.getElementById('contentModal')).hide();
    });

    function prepareDeleteModal(id, title) {
        document.getElementById('contentToDeleteId').value = id;
        document.getElementById('contentToDeleteName').textContent = title;
    }

    function deleteContent() {
        const id = document.getElementById('contentToDeleteId').value;
        alert(`Content ID ${id} has been deleted. (Simulation)`);
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
    }
</script>
</body>
</html>
