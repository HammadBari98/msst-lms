<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

// Check if the admin is logged in, otherwise redirect to the login page.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Set admin details from the session.
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    // Fetch all students with ALL their details from the database
    $stmt = $pdo->query(
        "SELECT DISTINCT 
            u.id,
            u.user_id_string,
            u.full_name,
            u.email,
            u.status,
            u.profile_picture,
            r.role_name,
            sd.gender,
            sd.father_name,
            sd.date_of_birth,
            sd.mother_name,
            sd.family_monthly_income,
            sd.guardian_name,
            sd.father_cnic,
            sd.cell_no,
            sd.mother_cnic,
            sd.phone_no,
            sd.domicile_district,
            sd.father_occupation,
            sd.address,
            sd.postal_address,
            sd.previous_school,
            sd.physical_deformity,
            sd.awards,
            sd.extracurricular_expertise,
            sd.special_care_areas,
            sd.fee_category,
            sd.class_id,
            sd.section_id,
            c.class_name,
            sec.section_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN classes c ON sd.class_id = c.id
        LEFT JOIN sections sec ON sd.section_id = sec.id
        WHERE r.role_name = 'Student'
        ORDER BY 
            CASE 
                WHEN c.class_name REGEXP '^[0-9]+$' THEN CAST(c.class_name AS UNSIGNED)
                WHEN c.class_name LIKE 'First Year%' THEN 11
                WHEN c.class_name LIKE 'Second Year%' THEN 12
                WHEN c.class_name LIKE 'Third Year%' THEN 13
                WHEN c.class_name LIKE 'Fourth Year%' THEN 14
                WHEN c.class_name LIKE 'Fifth Year%' THEN 15
                ELSE 999
            END,
            c.class_name, u.full_name"
    );
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch available classes - standardized
    $available_classes = $pdo->query("
        SELECT id, class_name 
        FROM classes 
        WHERE class_name IS NOT NULL 
        AND class_name != '' 
        ORDER BY 
            CASE 
                WHEN class_name LIKE 'Class%' AND class_name REGEXP 'Class [0-9]+' 
                THEN CAST(SUBSTRING(class_name, 7) AS UNSIGNED)
                WHEN class_name LIKE 'First Year%' THEN 11
                WHEN class_name LIKE 'Second Year%' THEN 12
                WHEN class_name LIKE 'Third Year%' THEN 13
                WHEN class_name LIKE 'Fourth Year%' THEN 14
                WHEN class_name LIKE 'Fifth Year%' THEN 15
                ELSE 999
            END
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Fee Categories / Programs dynamically
    $fee_categories = $pdo->query("
        SELECT * FROM fee_categories 
        WHERE is_active = 1 
        ORDER BY id
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Information | MSST Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Student Information</h1>
                <div>
                    <button class="btn btn-success shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-file-excel fa-sm text-white-50"></i> Import Students
                    </button>
                    <button class="btn btn-info shadow-sm text-white" onclick="exportStudents()">
                        <i class="fas fa-download fa-sm text-white-50"></i> Export Students
                    </button>
                </div>
            </div>

            <div id="alert-placeholder"></div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Student List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="studentsTable" width="100%" cellspacing="0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Father Name</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($student['user_id_string']) ?></strong></td>
                                        <td class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></td>
                                        <td><?= htmlspecialchars($student['father_name'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?></span></td>
                                        <td><?= htmlspecialchars($student['section_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($student['email']) ?></td>
                                        <td>
                                            <span class="badge <?= $student['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= htmlspecialchars($student['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-primary btn-sm text-white" title="View Profile" 
                                                        data-bs-toggle="modal" data-bs-target="#studentProfileModal" 
                                                        onclick='viewProfile(<?= json_encode($student) ?>)'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-info btn-sm text-white" title="Edit" 
                                                        data-bs-toggle="modal" data-bs-target="#studentProfileModal" 
                                                        onclick='editStudent(<?= json_encode($student) ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" title="Delete" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                        onclick='prepareDeleteStudent(<?= $student["id"] ?>, "<?= htmlspecialchars(addslashes($student["full_name"])) ?>")'>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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

    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> Muhaddisa School of Science and Technology.</p></div></footer>
</div>

<div class="modal fade" id="studentProfileModal" tabindex="-1" aria-labelledby="studentProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="studentProfileModalLabel">Student Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form id="studentProfileForm">
                    <input type="hidden" id="studentId" name="id">
                    <input type="hidden" name="action" value="update_student">
                    
                    <div class="row mb-4 align-items-center bg-white p-3 rounded-3 shadow-sm mx-1">
                        <div class="col-md-12">
                            <h3 id="studentName" class="mb-1 fw-bold text-dark"></h3>
                            <p id="studentClassInfo" class="text-primary fw-bold mb-2"></p>
                            <p id="studentEmail" class="text-muted small mb-0"></p>
                        </div>
                    </div>
                    
                    <ul class="nav nav-pills mb-4 px-2" id="studentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill px-4 fw-bold" id="personal-tab" data-bs-toggle="pill" data-bs-target="#personal" type="button" role="tab">Personal Info</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-4 fw-bold ms-2" id="academic-tab" data-bs-toggle="pill" data-bs-target="#academic" type="button" role="tab">Academic</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-4 fw-bold ms-2" id="family-tab" data-bs-toggle="pill" data-bs-target="#family" type="button" role="tab">Family</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-4 fw-bold ms-2" id="additional-tab" data-bs-toggle="pill" data-bs-target="#additional" type="button" role="tab">Additional</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content bg-white p-4 rounded-3 shadow-sm mx-1 mb-2" id="studentTabContent">
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fullName" class="form-label fw-bold">Full Name</label>
                                    <input type="text" class="form-control bg-light" id="fullName" name="full_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="studentStatus" class="form-label fw-bold">Status</label>
                                    <select class="form-select bg-light" id="studentStatus" name="status">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label fw-bold">Gender</label>
                                    <select class="form-select bg-light" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label fw-bold">Date of Birth</label>
                                    <input type="date" class="form-control bg-light" id="date_of_birth" name="date_of_birth">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="cell_no" class="form-label fw-bold">Cell No.</label>
                                    <input type="tel" class="form-control bg-light" id="cell_no" name="cell_no">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone_no" class="form-label fw-bold">Phone No.</label>
                                    <input type="tel" class="form-control bg-light" id="phone_no" name="phone_no">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="domicile_district" class="form-label fw-bold">Domicile District</label>
                                    <input type="text" class="form-control bg-light" id="domicile_district" name="domicile_district">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="previous_school" class="form-label fw-bold">School Name Last Attended</label>
                                    <input type="text" class="form-control bg-light" id="previous_school" name="previous_school">
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="address" class="form-label fw-bold">Village, UC, Tehsil, District (Address)</label>
                                    <textarea class="form-control bg-light" id="address" name="address" rows="2"></textarea>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="postal_address" class="form-label fw-bold">Postal Address</label>
                                    <textarea class="form-control bg-light" id="postal_address" name="postal_address" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="academic" role="tabpanel">
                            <div class="row">
                                <div class="col-md-4 mb-3"> 
                                    <label for="class_id" class="form-label fw-bold text-primary">Class</label>
                                    <select class="form-select border-primary" id="class_id" name="class_id" onchange="loadSectionsForModal(this.value)">
                                        <option value="">Select Class</option>
                                        <?php foreach($available_classes as $class): ?>
                                            <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3"> 
                                    <label for="section_id" class="form-label fw-bold text-primary">Section</label>
                                    <select class="form-select border-primary" id="section_id" name="section_id">
                                        <option value="">First select a class</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="fee_category" class="form-label fw-bold text-primary">Program / Track</label>
                                    <select class="form-select border-primary" id="fee_category" name="fee_category">
                                        <?php if(!empty($fee_categories)): ?>
                                            <?php foreach($fee_categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="Class 9">Class 9</option>
                                            <option value="First Year (Pre Eng.)">First Year (Pre Eng.)</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="family" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="father_name" class="form-label fw-bold">Father Name</label>
                                    <input type="text" class="form-control bg-light" id="father_name" name="father_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mother_name" class="form-label fw-bold">Mother Name</label>
                                    <input type="text" class="form-control bg-light" id="mother_name" name="mother_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="family_monthly_income" class="form-label fw-bold">Family Monthly Income</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">PKR</span>
                                        <input type="number" step="0.01" class="form-control bg-light" id="family_monthly_income" name="family_monthly_income">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="guardian_name" class="form-label fw-bold">Guardian's Name (if any)</label>
                                    <input type="text" class="form-control bg-light" id="guardian_name" name="guardian_name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="father_cnic" class="form-label fw-bold">Father CNIC</label>
                                    <input type="text" class="form-control bg-light" id="father_cnic" name="father_cnic" placeholder="XXXXX-XXXXXXX-X">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mother_cnic" class="form-label fw-bold">Mother CNIC</label>
                                    <input type="text" class="form-control bg-light" id="mother_cnic" name="mother_cnic" placeholder="XXXXX-XXXXXXX-X">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="father_occupation" class="form-label fw-bold">Occupation/Profession of Father/Guardian</label>
                                    <input type="text" class="form-control bg-light" id="father_occupation" name="father_occupation">
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="additional" role="tabpanel">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="physical_deformity" class="form-label fw-bold">Physical deformity or disease under treatment</label>
                                    <textarea class="form-control bg-light" id="physical_deformity" name="physical_deformity" rows="3"></textarea>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="awards" class="form-label fw-bold">Awards in competition</label>
                                    <textarea class="form-control bg-light" id="awards" name="awards" rows="3"></textarea>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="extracurricular_expertise" class="form-label fw-bold">Extra-curricular quality or expertise</label>
                                    <textarea class="form-control bg-light" id="extracurricular_expertise" name="extracurricular_expertise" rows="3"></textarea>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="special_care_areas" class="form-label fw-bold">Areas where child needs special care</label>
                                    <textarea class="form-control bg-light" id="special_care_areas" name="special_care_areas" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-white">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary shadow-sm" id="saveStudentBtn"><i class="fas fa-save me-1"></i> Save Changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="importModalLabel"><i class="fas fa-file-excel me-2"></i>Import Students from CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="importForm" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="import_students">
                    <p class="text-muted mb-4">Select a CSV file to import student data. The system is smart and will automatically skip title rows and map your columns.</p>
                    <div class="mb-4 bg-white p-3 rounded border">
                        <label for="excelFile" class="form-label fw-bold text-dark">Upload CSV File <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" id="excelFile" name="file" accept=".csv, .xlsx, .xls" required>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 pt-0 pb-3 pe-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" id="importBtn">
                        <i class="fas fa-upload me-2"></i>Upload and Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong id="studentToDeleteName"></strong>? 
                <br><br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone and will permanently remove the student record.</small>
                <input type="hidden" id="studentToDeleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Student</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#studentsTable').DataTable({
            pageLength: 10,
            responsive: true,
            order: [[0, 'desc']]
        });
    });

    // Sidebar Toggle Script
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');

    function saveSidebarState(collapsed) { localStorage.setItem('sidebarCollapsed', collapsed); }
    function loadSidebarState() { return localStorage.getItem('sidebarCollapsed') === 'true'; }

    if (loadSidebarState()) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('collapsed');
    }

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
        saveSidebarState(sidebar.classList.contains('collapsed'));
        if (window.innerWidth < 768) {
            if (!sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('show');
                mainContent.classList.add('show-sidebar');
            } else {
                sidebar.classList.remove('show');
                mainContent.classList.remove('show-sidebar');
            }
        }
    });

    // --- Page-specific Scripts ---

    // Dynamic Section Loader for the Modal
    function loadSectionsForModal(classId, selectedSectionId = null) {
        const secSelect = document.getElementById('section_id');
        if (!classId) { 
            secSelect.innerHTML = '<option value="">First select a class</option>'; 
            return; 
        }
        secSelect.innerHTML = '<option value="">Loading sections...</option>';
        fetch(`ajax/get_sections_by_class.php?class_id=${classId}`)
            .then(r => r.json())
            .then(data => {
                let opts = '<option value="">Select Section...</option>'; 
                if(data.length === 0) {
                    opts = '<option value="" disabled>No sections available for this class</option>';
                } else {
                    data.forEach(s => {
                        const isSelected = (selectedSectionId && s.id == selectedSectionId) ? 'selected' : '';
                        opts += `<option value="${s.id}" ${isSelected}>${s.section_name}</option>`;
                    }); 
                }
                secSelect.innerHTML = opts;
            })
            .catch(() => secSelect.innerHTML = '<option value="">Error loading sections</option>');
    }

    function viewProfile(student) {
        populateStudentForm(student);
        document.querySelectorAll('#studentProfileForm input, #studentProfileForm select, #studentProfileForm textarea').forEach(field => {
            field.disabled = true;
        });
        document.getElementById('studentProfileModalLabel').textContent = 'View Student Profile';
        document.getElementById('saveStudentBtn').style.display = 'none';
    }

    function editStudent(student) {
        populateStudentForm(student);
        document.querySelectorAll('#studentProfileForm input, #studentProfileForm select, #studentProfileForm textarea').forEach(field => {
            field.disabled = false;
        });
        document.getElementById('studentProfileModalLabel').textContent = 'Edit Student Profile';
        document.getElementById('saveStudentBtn').style.display = 'block';
    }

    function populateStudentForm(student) {
        document.getElementById('studentName').textContent = student.full_name;
        document.getElementById('studentClassInfo').textContent = 
            `Class: ${student.class_name || 'N/A'} | Section: ${student.section_name || 'N/A'} | Program: ${student.fee_category || 'N/A'}`;
        document.getElementById('studentEmail').textContent = student.email || 'No email provided';
        
        document.getElementById('studentId').value = student.id;
        document.getElementById('fullName').value = student.full_name || '';
        document.getElementById('studentStatus').value = student.status || 'Active';
        document.getElementById('gender').value = student.gender || '';
        
        if (student.date_of_birth) {
            const dob = new Date(student.date_of_birth);
            document.getElementById('date_of_birth').value = dob.toISOString().split('T')[0];
        } else {
            document.getElementById('date_of_birth').value = '';
        }
        
        document.getElementById('cell_no').value = student.cell_no || '';
        document.getElementById('phone_no').value = student.phone_no || '';
        document.getElementById('domicile_district').value = student.domicile_district || '';
        document.getElementById('previous_school').value = student.previous_school || '';
        document.getElementById('address').value = student.address || '';
        document.getElementById('postal_address').value = student.postal_address || '';
        
        // Handle Class, Section, and Fee Category
        document.getElementById('class_id').value = student.class_id || '';
        if (student.class_id) {
            loadSectionsForModal(student.class_id, student.section_id);
        } else {
            document.getElementById('section_id').innerHTML = '<option value="">First select a class</option>';
        }
        document.getElementById('fee_category').value = student.fee_category || '';
        
        document.getElementById('father_name').value = student.father_name || '';
        document.getElementById('mother_name').value = student.mother_name || '';
        document.getElementById('family_monthly_income').value = student.family_monthly_income || '';
        document.getElementById('guardian_name').value = student.guardian_name || '';
        document.getElementById('father_cnic').value = student.father_cnic || '';
        document.getElementById('mother_cnic').value = student.mother_cnic || '';
        document.getElementById('father_occupation').value = student.father_occupation || '';
        document.getElementById('physical_deformity').value = student.physical_deformity || '';
        document.getElementById('awards').value = student.awards || '';
        document.getElementById('extracurricular_expertise').value = student.extracurricular_expertise || '';
        document.getElementById('special_care_areas').value = student.special_care_areas || '';
        
        // Activate first tab
        document.querySelector('#personal-tab').click();
    }

    function prepareDeleteStudent(id, name) {
        document.getElementById('studentToDeleteId').value = id;
        document.getElementById('studentToDeleteName').textContent = name;
    }

    document.getElementById('saveStudentBtn').addEventListener('click', async function(e) {
        e.preventDefault();
        const formData = new FormData(document.getElementById('studentProfileForm'));
        const saveBtn = document.getElementById('saveStudentBtn');
        
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        
        try {
            const response = await fetch('api_students.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (response.ok && result.success) {
                showAlert(result.message, 'success');
                const studentProfileModal = bootstrap.Modal.getInstance(document.getElementById('studentProfileModal'));
                studentProfileModal.hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(result.message || 'An error occurred.', 'danger');
            }
        } catch (error) {
            showAlert('A network error occurred.', 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
        }
    });

    document.getElementById('importForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const importBtn = document.getElementById('importBtn');
        
        importBtn.disabled = true;
        importBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
        
        try {
            const response = await fetch('api_students.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (response.ok && result.success) {
                showAlert(result.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(result.message || 'Import failed.', 'danger');
            }
        } catch (error) {
            showAlert('A network error occurred during import.', 'danger');
        } finally {
            importBtn.disabled = false;
            importBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload and Import';
        }
    });

    document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
        const id = document.getElementById('studentToDeleteId').value;
        const deleteBtn = this;
        
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Deleting...';
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_student');
            formData.append('id', id);
            
            const response = await fetch('api_students.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (response.ok && result.success) {
                bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                showAlert(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(result.message || 'An error occurred while deleting.', 'danger');
            }
        } catch (error) {
            showAlert('A network error occurred.', 'danger');
        } finally {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = 'Delete Student';
        }
    });

    function exportStudents() {
        showAlert('Preparing student data for export...', 'info');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api_students.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'export_students';
        form.appendChild(actionInput);
        
        const exportType = document.createElement('input');
        exportType.type = 'hidden';
        exportType.name = 'format';
        exportType.value = 'csv';
        form.appendChild(exportType);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function showAlert(message, type = 'success') {
        const placeholder = document.getElementById('alert-placeholder');
        placeholder.innerHTML = '';
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `<div class="alert alert-${type} alert-dismissible shadow-sm" role="alert">
            <div>${message}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        placeholder.append(wrapper);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => { if (wrapper.parentNode === placeholder) wrapper.remove(); }, 5000);
    }
</script>
</body>
</html>