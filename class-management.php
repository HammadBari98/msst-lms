<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// --- AUTO-PATCHER: Create Tables for Dynamic Subjects ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id INT PRIMARY KEY AUTO_INCREMENT,
            subject_name VARCHAR(100) NOT NULL UNIQUE
        );
        CREATE TABLE IF NOT EXISTS program_subjects (
            id INT PRIMARY KEY AUTO_INCREMENT,
            program_id INT NOT NULL,  
            subject_id INT NOT NULL,
            UNIQUE KEY unique_mapping (program_id, subject_id)
        );
    ");
} catch (PDOException $e) { /* Ignore if already exists */ }

// --- ALL-IN-ONE AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        // --- 1. Subject Management ---
        if ($_POST['action'] === 'get_subjects') {
            $pid = intval($_POST['program_id']);
            $stmt = $pdo->prepare("SELECT ps.id as mapping_id, s.subject_name FROM program_subjects ps JOIN subjects s ON ps.subject_id = s.id WHERE ps.program_id = ? ORDER BY s.subject_name");
            $stmt->execute([$pid]);
            echo json_encode(['success' => true, 'subjects' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }
        if ($_POST['action'] === 'add_subject') {
            $pid = intval($_POST['program_id']);
            $sub_name = trim($_POST['subject_name']);
            
            $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_name = ?");
            $stmt->execute([$sub_name]);
            $sub_id = $stmt->fetchColumn();
            
            if (!$sub_id) {
                $pdo->prepare("INSERT INTO subjects (subject_name) VALUES (?)")->execute([$sub_name]);
                $sub_id = $pdo->lastInsertId();
            }
            $pdo->prepare("INSERT IGNORE INTO program_subjects (program_id, subject_id) VALUES (?, ?)")->execute([$pid, $sub_id]);
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'remove_subject') {
            $pdo->prepare("DELETE FROM program_subjects WHERE id = ?")->execute([$_POST['mapping_id']]);
            echo json_encode(['success' => true]); exit;
        }

        // --- 2. Class Management ---
        if ($_POST['action'] === 'add_class') {
            $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)")->execute([trim($_POST['class_name'])]);
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'edit_class') {
            $pdo->prepare("UPDATE classes SET class_name = ? WHERE id = ?")->execute([trim($_POST['class_name']), $_POST['class_id']]);
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'delete_class') {
            $class_id = $_POST['class_id'];
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM program_subjects WHERE program_id IN (SELECT id FROM sections WHERE class_id = ?)")->execute([$class_id]);
            $pdo->prepare("DELETE FROM sections WHERE class_id = ?")->execute([$class_id]);
            $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$class_id]);
            $pdo->commit();
            echo json_encode(['success' => true]); exit;
        }

        // --- 3. Program (Section) Management ---
        if ($_POST['action'] === 'add_section') {
            $pdo->prepare("INSERT INTO sections (class_id, section_name) VALUES (?, ?)")->execute([$_POST['class_id'], trim($_POST['section_name'])]);
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'edit_section') {
            $pdo->prepare("UPDATE sections SET section_name = ? WHERE id = ?")->execute([trim($_POST['section_name']), $_POST['section_id']]);
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'delete_section') {
            $sec_id = $_POST['id'];
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM program_subjects WHERE program_id = ?")->execute([$sec_id]);
            $pdo->prepare("DELETE FROM sections WHERE id = ?")->execute([$sec_id]);
            $pdo->commit();
            echo json_encode(['success' => true]); exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

try {
    $stmt = $pdo->query("
        SELECT c.id, c.class_name, COUNT(s.id) as total_sections
        FROM classes c
        LEFT JOIN sections s ON c.id = s.class_id
        GROUP BY c.id
        ORDER BY 
            CASE 
                WHEN c.class_name REGEXP '^[0-9]+$' THEN CAST(c.class_name AS UNSIGNED)
                WHEN c.class_name LIKE '8th%' THEN 8
                WHEN c.class_name LIKE 'Class%' AND c.class_name REGEXP 'Class [0-9]+' THEN CAST(SUBSTRING(c.class_name, 7) AS UNSIGNED)
                WHEN c.class_name LIKE 'First Year%' THEN 11
                WHEN c.class_name LIKE 'Second Year%' THEN 12
                ELSE 999
            END, c.class_name
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Class & Program Management | MSST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .class-card { transition: transform 0.2s, box-shadow 0.2s; border-left: 4px solid var(--primary); }
        .class-card:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important; }
        .section-badge { font-size: 0.9rem; padding: 0.5rem 1rem; margin: 0.25rem; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; display: flex; align-items: center;}
        .section-actions { opacity: 0; transition: opacity 0.2s; margin-left: 10px; }
        .section-badge-container:hover .section-actions { opacity: 1; }
        .btn-delete-section { color: #dc3545; cursor: pointer; background: none; border: none; padding: 0 0 0 8px; }
        .btn-delete-section:hover { color: #a11; }
        .subject-tag { display: inline-block; background: #e7f3ff; color: #0d6efd; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; margin: 4px; border: 1px solid #b6d4fe; }
        .subject-tag button { background: none; border: none; color: #dc3545; margin-left: 6px; cursor: pointer; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper p-4">
        <div class="container-fluid">
            
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Class & Program Management</h1>
                <div>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
                        <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Add New Class
                    </button>
                </div>
            </div>

            <div id="alert-placeholder"></div>

            <div class="row">
                <?php if (empty($classes)): ?>
                    <div class="col-12">
                        <div class="alert bg-white shadow-sm border border-info text-center p-5 rounded-3">
                            <i class="fas fa-layer-group fa-4x text-info mb-3 opacity-50"></i>
                            <h4 class="fw-bold text-dark">Your Curriculum is Empty</h4>
                            <p class="text-muted mb-4 fs-5">Get started by clicking <strong>"Add New Class"</strong> at the top right to build your programs and subjects manually.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($classes as $class): ?>
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card class-card shadow h-100 py-2">
                            <div class="card-body d-flex flex-column">
                                <div class="row no-gutters align-items-center mb-3 border-bottom pb-2">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Class Category</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800 d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($class['class_name']) ?>
                                            <div>
                                                <button class="btn btn-sm btn-light border text-primary" onclick="openEditClassModal(<?= $class['id'] ?>, '<?= htmlspecialchars(addslashes($class['class_name'])) ?>')" title="Edit Class Name"><i class="fas fa-edit"></i></button>
                                                <button class="btn btn-sm btn-light border text-danger" onclick="openDeleteClassModal(<?= $class['id'] ?>, '<?= htmlspecialchars(addslashes($class['class_name'])) ?>')" title="Delete Class"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="sections-container mb-3 flex-grow-1" id="sections-<?= $class['id'] ?>">
                                    <?php if (isset($sections_by_class[$class['id']])): ?>
                                        <div class="d-flex flex-wrap">
                                            <?php foreach ($sections_by_class[$class['id']] as $section): ?>
                                            <div class="section-badge-container badge section-badge text-dark shadow-sm">
                                                <span class="fw-bold"><?= htmlspecialchars($section['section_name']) ?></span>
                                                <div class="section-actions">
                                                    <button class="btn btn-sm btn-info py-0 px-2 text-white" onclick="manageSubjects(<?= $section['id'] ?>, '<?= htmlspecialchars(addslashes($section['section_name'])) ?>', '<?= htmlspecialchars(addslashes($class['class_name'])) ?>')" title="Manage Subjects"><i class="fas fa-book"></i></button>
                                                    <button class="btn btn-sm btn-warning py-0 px-2 text-dark" onclick="openEditSectionModal(<?= $section['id'] ?>, '<?= htmlspecialchars(addslashes($section['section_name'])) ?>')" title="Edit Program Name"><i class="fas fa-edit"></i></button>
                                                    <button class="btn-delete-section" onclick="deleteSection(<?= $section['id'] ?>, '<?= htmlspecialchars(addslashes($section['section_name'])) ?>', <?= $class['id'] ?>, event)" title="Delete Program"><i class="fas fa-times"></i></button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted small mb-0 font-italic">No programs added yet.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto pt-3 border-top text-end">
                                    <button class="btn btn-sm btn-outline-success" onclick="openAddSectionModal(<?= $class['id'] ?>, '<?= htmlspecialchars(addslashes($class['class_name'])) ?>')">
                                        <i class="fas fa-plus me-1"></i> Add Program
                                    </button>
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

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form id="addClassForm"><input type="hidden" name="action" value="add_class">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Add New Class</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="mb-3"><label class="form-label fw-bold">Class Name (e.g., 9, 10, First Year)</label><input type="text" class="form-control" name="class_name" required></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="saveClassBtn">Save Class</button></div>
        </form>
    </div></div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form id="editClassForm"><input type="hidden" name="action" value="edit_class"><input type="hidden" name="class_id" id="edit_class_id">
            <div class="modal-header bg-warning"><h5 class="modal-title">Edit Class Name</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="mb-3"><label class="form-label fw-bold">Class Name</label><input type="text" class="form-control" name="class_name" id="edit_class_name" required></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning" id="updateClassBtn">Update Class</button></div>
        </form>
    </div></div>
</div>

<!-- Delete Class Modal -->
<div class="modal fade" id="deleteClassModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header bg-danger text-white"><h5 class="modal-title">Confirm Deletion</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">Are you sure you want to delete <strong id="delClassName"></strong>?<br><br><small class="text-white bg-danger p-2 rounded d-block"><i class="fas fa-exclamation-triangle"></i> WARNING: This will also delete all programs, subjects, and associated records!</small></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteClassBtn">Delete Everything</button></div>
    </div></div>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form id="addSectionForm"><input type="hidden" name="action" value="add_section"><input type="hidden" name="class_id" id="section_class_id">
            <div class="modal-header bg-success text-white"><h5 class="modal-title">Add Program to <span id="sectionClassName"></span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="mb-3"><label class="form-label fw-bold">Program Name (e.g., ICS, Pre-Medical, Computer Group)</label><input type="text" class="form-control" name="section_name" required></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success" id="saveSectionBtn">Add Program</button></div>
        </form>
    </div></div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form id="editSectionForm"><input type="hidden" name="action" value="edit_section"><input type="hidden" name="section_id" id="edit_section_id">
            <div class="modal-header bg-warning"><h5 class="modal-title">Edit Program Name</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="mb-3"><label class="form-label fw-bold">Program Name</label><input type="text" class="form-control" name="section_name" id="edit_section_name" required></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning" id="updateSectionBtn">Update Program</button></div>
        </form>
    </div></div>
</div>

<!-- Manage Subjects Modal -->
<div class="modal fade" id="manageSubjectsModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-info text-white">
            <h5 class="modal-title fw-bold"><i class="fas fa-book me-2"></i>Subjects for: <span id="subjProgramName"></span></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body bg-light p-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form id="addSubjectForm" class="d-flex align-items-end">
                        <input type="hidden" id="subjProgramId">
                        <div class="flex-grow-1 me-2">
                            <label class="form-label fw-bold text-secondary small">Add New Subject</label>
                            <input type="text" id="newSubjectInput" class="form-control border-info fw-bold" placeholder="e.g., Computer Science" required>
                        </div>
                        <button type="submit" class="btn btn-info text-white fw-bold"><i class="fas fa-plus me-1"></i> Add Subject</button>
                    </form>
                </div>
            </div>
            
            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Assigned Subjects</h6>
            <div id="subjectsContainer" class="p-3 bg-white rounded shadow-sm border min-vh-25">
                <div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i> Loading subjects...</div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar Toggle
const toggleBtn = document.getElementById('sidebarToggle');
if(toggleBtn){ toggleBtn.addEventListener('click', () => { document.getElementById('sidebar').classList.toggle('collapsed'); document.getElementById('main-content').classList.toggle('collapsed'); }); }

let deleteClassId = null;

// Modals Open Functions
function openEditClassModal(id, name) { document.getElementById('edit_class_id').value = id; document.getElementById('edit_class_name').value = name; new bootstrap.Modal(document.getElementById('editClassModal')).show(); }
function openDeleteClassModal(id, name) { deleteClassId = id; document.getElementById('delClassName').textContent = name; new bootstrap.Modal(document.getElementById('deleteClassModal')).show(); }
function openAddSectionModal(classId, className) { document.getElementById('addSectionForm').reset(); document.getElementById('section_class_id').value = classId; document.getElementById('sectionClassName').textContent = className; new bootstrap.Modal(document.getElementById('addSectionModal')).show(); }
function openEditSectionModal(id, name) { document.getElementById('edit_section_id').value = id; document.getElementById('edit_section_name').value = name; new bootstrap.Modal(document.getElementById('editSectionModal')).show(); }

// --- AJAX Form Handlers ---
async function handleAjaxForm(e, btnId, loadingText, modalId) {
    e.preventDefault(); const btn = document.getElementById(btnId); const originalText = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = loadingText;
    try { 
        const res = await fetch('class-management.php', { method: 'POST', body: new FormData(e.target) }); 
        const data = await res.json(); 
        if(data.success) location.reload(); 
        else { bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide(); showAlert(data.message, 'danger'); } 
    } catch(err) { bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide(); showAlert('Network error.', 'danger'); } 
    finally { btn.disabled = false; btn.innerHTML = originalText; }
}

document.getElementById('addClassForm').addEventListener('submit', (e) => handleAjaxForm(e, 'saveClassBtn', 'Saving...', 'addClassModal'));
document.getElementById('editClassForm').addEventListener('submit', (e) => handleAjaxForm(e, 'updateClassBtn', 'Updating...', 'editClassModal'));
document.getElementById('addSectionForm').addEventListener('submit', (e) => handleAjaxForm(e, 'saveSectionBtn', 'Saving...', 'addSectionModal'));
document.getElementById('editSectionForm').addEventListener('submit', (e) => handleAjaxForm(e, 'updateSectionBtn', 'Updating...', 'editSectionModal'));

// --- DELETES ---
document.getElementById('confirmDeleteClassBtn').addEventListener('click', async (e) => {
    const btn = e.target; btn.disabled = true; btn.innerHTML = 'Deleting...';
    try { const fd = new FormData(); fd.append('action', 'delete_class'); fd.append('class_id', deleteClassId); const res = await fetch('class-management.php', { method: 'POST', body: fd }); const data = await res.json(); if(data.success) location.reload(); else showAlert(data.message, 'danger'); } 
    catch(err) { showAlert('Network error.', 'danger'); } finally { btn.disabled = false; btn.innerHTML = 'Delete Everything'; }
});

async function deleteSection(id, name, classId, event) {
    if(!confirm(`Are you sure you want to delete Program: ${name}?`)) return;
    try { const fd = new FormData(); fd.append('action', 'delete_section'); fd.append('id', id); const res = await fetch('class-management.php', { method: 'POST', body: fd }); const data = await res.json(); if(data.success) { event.target.closest('.section-badge-container').remove(); showAlert('Program deleted successfully.', 'success'); } else { showAlert(data.message, 'danger'); } } 
    catch(err) { showAlert('Network error.', 'danger'); }
}

// --- DYNAMIC SUBJECT ENGINE ---
function manageSubjects(programId, programName, className) {
    document.getElementById('subjProgramId').value = programId;
    document.getElementById('subjProgramName').textContent = className + ' - ' + programName;
    new bootstrap.Modal(document.getElementById('manageSubjectsModal')).show();
    loadSubjects(programId);
}

async function loadSubjects(programId) {
    const container = document.getElementById('subjectsContainer');
    container.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i> Loading subjects...</div>';
    
    const fd = new FormData(); fd.append('action', 'get_subjects'); fd.append('program_id', programId);
    try {
        const res = await fetch('class-management.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success && data.subjects.length > 0) {
            let html = '';
            data.subjects.forEach(sub => { html += `<div class="subject-tag">${sub.subject_name} <button onclick="removeSubject(${sub.mapping_id}, ${programId})" title="Remove Subject"><i class="fas fa-times-circle"></i></button></div>`; });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-muted text-center mb-0 mt-2">No subjects assigned yet. Add some above!</p>';
        }
    } catch(err) { container.innerHTML = '<p class="text-danger text-center">Failed to load subjects.</p>'; }
}

document.getElementById('addSubjectForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const programId = document.getElementById('subjProgramId').value;
    const subjectName = document.getElementById('newSubjectInput').value;
    
    const fd = new FormData(); fd.append('action', 'add_subject'); fd.append('program_id', programId); fd.append('subject_name', subjectName);
    try {
        const res = await fetch('class-management.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) { document.getElementById('newSubjectInput').value = ''; loadSubjects(programId); } 
        else { alert("Failed to add subject: " + data.message); }
    } catch(err) { alert('Network error adding subject.'); }
});

async function removeSubject(mappingId, programId) {
    if(!confirm("Remove this subject from the program?")) return;
    const fd = new FormData(); fd.append('action', 'remove_subject'); fd.append('mapping_id', mappingId);
    try {
        const res = await fetch('class-management.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) { loadSubjects(programId); }
    } catch(err) { alert('Network error removing subject.'); }
}

function showAlert(msg, type) {
    const p = document.getElementById('alert-placeholder');
    p.innerHTML = `<div class="alert alert-${type} alert-dismissible shadow-sm"><strong>System:</strong> ${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' }); 
    setTimeout(() => p.innerHTML = '', 5000);
}
</script>
</body>
</html>