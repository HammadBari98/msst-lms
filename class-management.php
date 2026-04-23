<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    // Fetch all classes with section counts smartly sorted
    $stmt = $pdo->query("
        SELECT 
            c.id, c.class_name, COUNT(s.id) as total_sections
        FROM classes c
        LEFT JOIN sections s ON c.id = s.class_id
        GROUP BY c.id
        ORDER BY 
            CASE 
                WHEN c.class_name REGEXP '^[0-9]+$' THEN CAST(c.class_name AS UNSIGNED)
                WHEN c.class_name LIKE 'Class%' AND c.class_name REGEXP 'Class [0-9]+' THEN CAST(SUBSTRING(c.class_name, 7) AS UNSIGNED)
                WHEN c.class_name LIKE 'First Year%' THEN 11
                WHEN c.class_name LIKE 'Second Year%' THEN 12
                ELSE 999
            END, c.class_name
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all sections grouped by class
    $sections_by_class = [];
    $sections_query = $pdo->query("SELECT s.*, c.class_name FROM sections s JOIN classes c ON s.class_id = c.id ORDER BY s.section_name");
    while ($row = $sections_query->fetch(PDO::FETCH_ASSOC)) {
        $sections_by_class[$row['class_id']][] = $row;
    }
} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Management | MSST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .class-card { transition: transform 0.2s, box-shadow 0.2s; border-left: 4px solid var(--primary); }
        .class-card:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important; }
        .section-badge { font-size: 0.9rem; padding: 0.5rem 1rem; margin: 0.25rem; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; }
        .section-actions { opacity: 0; transition: opacity 0.2s; }
        .section-badge-container:hover .section-actions { opacity: 1; }
        .btn-delete-section { color: #dc3545; cursor: pointer; background: none; border: none; padding: 0 0 0 8px; }
        .btn-delete-section:hover { color: #a11; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper p-4">
        <div class="container-fluid">
            
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Class & Section Management</h1>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Add New Class
                </button>
            </div>

            <div id="alert-placeholder"></div>

            <div class="row">
                <?php if (empty($classes)): ?>
                    <div class="col-12"><div class="alert alert-info shadow-sm"><i class="fas fa-info-circle me-2"></i> No classes found. Create a class to get started.</div></div>
                <?php else: ?>
                    <?php foreach ($classes as $class): ?>
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card class-card shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center mb-3">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Class Name</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800 d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($class['class_name']) ?>
                                            <span class="badge bg-primary rounded-pill"><?= $class['total_sections'] ?> Sections</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="sections-container mb-3" id="sections-<?= $class['id'] ?>">
                                    <?php if (isset($sections_by_class[$class['id']])): ?>
                                        <div class="d-flex flex-wrap">
                                            <?php foreach ($sections_by_class[$class['id']] as $section): ?>
                                            <div class="d-flex align-items-center section-badge-container badge section-badge text-dark">
                                                Sec: <?= htmlspecialchars($section['section_name']) ?>
                                                <div class="section-actions">
                                                    <button class="btn-delete-section" onclick="deleteSection(<?= $section['id'] ?>, '<?= htmlspecialchars(addslashes($section['section_name'])) ?>', <?= $class['id'] ?>, event)" title="Delete Section"><i class="fas fa-times"></i></button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted small mb-0 font-italic">No sections added yet.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto pt-3 border-top d-flex justify-content-between">
                                    <button class="btn btn-sm btn-outline-success" onclick="openAddSectionModal(<?= $class['id'] ?>, '<?= htmlspecialchars(addslashes($class['class_name'])) ?>')">
                                        <i class="fas fa-plus me-1"></i> Add Section
                                    </button>
                                    <div>
                                        <button class="btn btn-sm btn-light border text-primary" onclick="openEditClassModal(<?= $class['id'] ?>, '<?= htmlspecialchars(addslashes($class['class_name'])) ?>')" title="Edit Class"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-light border text-danger" onclick="openDeleteClassModal(<?= $class['id'] ?>, '<?= htmlspecialchars(addslashes($class['class_name'])) ?>')" title="Delete Class"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form id="addClassForm">
            <input type="hidden" name="action" value="add_class">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Add New Class</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="mb-3"><label class="form-label fw-bold">Class Name (e.g., 9, 10, First Year)</label><input type="text" class="form-control" name="class_name" required></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="saveClassBtn">Save Class</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form id="editClassForm">
            <input type="hidden" name="action" value="edit_class"><input type="hidden" name="class_id" id="edit_class_id">
            <div class="modal-header bg-warning"><h5 class="modal-title">Edit Class Name</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="mb-3"><label class="form-label fw-bold">Class Name</label><input type="text" class="form-control" name="class_name" id="edit_class_name" required></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning" id="updateClassBtn">Update Class</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="deleteClassModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">Are you sure you want to delete <strong id="delClassName"></strong>?<br><br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> This will also delete all sections and associated records!</small></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteClassBtn">Delete</button></div>
    </div></div>
</div>

<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form id="addSectionForm">
            <input type="hidden" name="action" value="add_section"><input type="hidden" name="class_id" id="section_class_id">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Add Section to <span id="sectionClassName"></span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="mb-3"><label class="form-label fw-bold">Section Name (e.g., A, B, C)</label><input type="text" class="form-control" name="section_name" required></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success" id="saveSectionBtn">Add Section</button></div>
        </form>
    </div></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar Toggle
const toggleBtn = document.getElementById('sidebarToggle');
if(toggleBtn){ 
    toggleBtn.addEventListener('click', () => { 
        document.getElementById('sidebar').classList.toggle('collapsed'); 
        document.getElementById('main-content').classList.toggle('collapsed'); 
    }); 
}

// Global Variables & Modal Instances
let deleteClassId = null;

// Modals Open Functions
function openEditClassModal(id, name) { 
    document.getElementById('edit_class_id').value = id; 
    document.getElementById('edit_class_name').value = name; 
    new bootstrap.Modal(document.getElementById('editClassModal')).show(); 
}

function openDeleteClassModal(id, name) { 
    deleteClassId = id; 
    document.getElementById('delClassName').textContent = name; 
    new bootstrap.Modal(document.getElementById('deleteClassModal')).show(); 
}

function openAddSectionModal(classId, className) { 
    // FIX: Reset the form FIRST, then set the hidden ID so it doesn't get wiped out!
    document.getElementById('addSectionForm').reset(); 
    document.getElementById('section_class_id').value = classId; 
    document.getElementById('sectionClassName').textContent = className; 
    
    new bootstrap.Modal(document.getElementById('addSectionModal')).show(); 
}

// Form Submissions with Error Handling
document.getElementById('addClassForm').addEventListener('submit', async (e) => {
    e.preventDefault(); 
    const btn = document.getElementById('saveClassBtn');
    btn.disabled = true; btn.innerHTML = 'Saving...';
    try {
        const fd = new FormData(e.target);
        const res = await fetch('api_classes.php', { method: 'POST', body: fd }); 
        const data = await res.json();
        if(data.success) { location.reload(); } else { showAlert(data.message, 'danger'); }
    } catch(err) { showAlert('Network error.', 'danger'); }
    finally { btn.disabled = false; btn.innerHTML = 'Save Class'; }
});

document.getElementById('editClassForm').addEventListener('submit', async (e) => {
    e.preventDefault(); 
    const btn = document.getElementById('updateClassBtn');
    btn.disabled = true; btn.innerHTML = 'Updating...';
    try {
        const fd = new FormData(e.target);
        const res = await fetch('api_classes.php', { method: 'POST', body: fd }); 
        const data = await res.json();
        if(data.success) { location.reload(); } else { showAlert(data.message, 'danger'); }
    } catch(err) { showAlert('Network error.', 'danger'); }
    finally { btn.disabled = false; btn.innerHTML = 'Update Class'; }
});

document.getElementById('confirmDeleteClassBtn').addEventListener('click', async (e) => {
    const btn = e.target;
    btn.disabled = true; btn.innerHTML = 'Deleting...';
    try {
        const fd = new FormData(); fd.append('action', 'delete_class'); fd.append('class_id', deleteClassId);
        const res = await fetch('api_classes.php', { method: 'POST', body: fd }); 
        const data = await res.json();
        if(data.success) { location.reload(); } else { showAlert(data.message, 'danger'); }
    } catch(err) { showAlert('Network error.', 'danger'); }
    finally { btn.disabled = false; btn.innerHTML = 'Delete'; }
});

document.getElementById('addSectionForm').addEventListener('submit', async (e) => {
    e.preventDefault(); 
    const btn = document.getElementById('saveSectionBtn');
    btn.disabled = true; btn.innerHTML = 'Saving...';
    try {
        const fd = new FormData(e.target);
        const res = await fetch('api_classes.php', { method: 'POST', body: fd }); 
        const data = await res.json();
        if(data.success) { 
            location.reload(); 
        } else { 
            bootstrap.Modal.getInstance(document.getElementById('addSectionModal')).hide();
            showAlert(data.message, 'danger'); 
        }
    } catch(err) { 
        bootstrap.Modal.getInstance(document.getElementById('addSectionModal')).hide();
        showAlert('Network error.', 'danger'); 
    }
    finally { btn.disabled = false; btn.innerHTML = 'Add Section'; }
});

async function deleteSection(id, name, classId, event) {
    if(!confirm(`Are you sure you want to delete Section ${name}?`)) return;
    try {
        const fd = new FormData(); fd.append('action', 'delete_section'); fd.append('id', id);
        const res = await fetch('api_classes.php', { method: 'POST', body: fd }); 
        const data = await res.json();
        if(data.success) { 
            event.target.closest('.section-badge-container').remove();
            showAlert(data.message, 'success');
        } else { showAlert(data.message, 'danger'); }
    } catch(err) { showAlert('Network error.', 'danger'); }
}

function showAlert(msg, type) {
    const p = document.getElementById('alert-placeholder');
    p.innerHTML = `<div class="alert alert-${type} alert-dismissible shadow-sm"><strong>System:</strong> ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' }); // Scroll to top so you can see the error
    setTimeout(() => p.innerHTML = '', 5000);
}
</script>
</body>
</html>