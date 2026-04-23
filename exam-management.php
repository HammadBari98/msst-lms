<?php
session_start();

// Check if the admin is logged in.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Set admin details from the session.
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// --- Dummy Exam Data ---
$exams = [
    ['id' => 'EXM-M-10P', 'title' => 'Mid Term - Physics', 'class' => '10', 'total_marks' => 100, 'date' => '2025-05-15', 'status' => 'Results Pending'],
    ['id' => 'EXM-F-09E', 'title' => 'Final Term - English', 'class' => '9', 'total_marks' => 100, 'date' => '2025-06-20', 'status' => 'Published'],
    ['id' => 'EXM-Q-10M', 'title' => 'Quiz 1 - Mathematics', 'class' => '10', 'total_marks' => 20, 'date' => '2025-04-30', 'status' => 'Published'],
];

$grade_criteria = [
    ['grade' => 'A+', 'min_percent' => 90, 'max_percent' => 100],
    ['grade' => 'A', 'min_percent' => 80, 'max_percent' => 89],
    ['grade' => 'B', 'min_percent' => 70, 'max_percent' => 79],
    ['grade' => 'C', 'min_percent' => 60, 'max_percent' => 69],
    ['grade' => 'D', 'min_percent' => 50, 'max_percent' => 59],
    ['grade' => 'F', 'min_percent' => 0, 'max_percent' => 49],
];

// Dummy results for one exam (EXM-M-10P)
$exam_results = [
    ['student_id' => 'STU-101', 'name' => 'Kamran Ahmed', 'marks' => null],
    ['student_id' => 'STU-103', 'name' => 'Danish Khan', 'marks' => null],
];

$available_classes = ['9', '10', '11', '12'];
$exam_types = ['Mid Term', 'Final Term', 'Quiz', 'Monthly Test'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Management | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Page Heading & Actions -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Exam Management</h1>
                <div>
                    <button class="btn btn-primary shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#addExamModal">
                        <i class="fas fa-plus fa-sm text-white-50"></i> Add New Exam
                    </button>
                    <button class="btn btn-secondary shadow-sm" data-bs-toggle="modal" data-bs-target="#gradeCriteriaModal">
                        <i class="fas fa-ruler-combined fa-sm text-white-50"></i> Set Grading Criteria
                    </button>
                </div>
            </div>

            <!-- Exams List Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Scheduled Exams</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead><tr><th>Exam Title</th><th>Class</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($exams as $exam): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($exam['title']) ?></td>
                                        <td>Class <?= htmlspecialchars($exam['class']) ?></td>
                                        <td><?= htmlspecialchars($exam['date']) ?></td>
                                        <td><span class="badge <?= $exam['status'] == 'Published' ? 'bg-success' : 'bg-warning' ?>"><?= htmlspecialchars($exam['status']) ?></span></td>
                                        <td>
                                            <button class="btn btn-info btn-sm" title="Manage Results" data-bs-toggle="modal" data-bs-target="#manageResultsModal" onclick='manageResults(<?= json_encode($exam) ?>, <?= json_encode($exam_results) ?>, <?= json_encode($grade_criteria) ?>)'>
                                                <i class="fas fa-edit"></i> Manage Results
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

<!-- Add Exam Modal -->
<div class="modal fade" id="addExamModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Add New Exam</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form id="addExamForm">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Exam Type</label><select class="form-select" required><?php foreach($exam_types as $type): echo "<option>$type</option>"; endforeach; ?></select></div>
                <div class="col-md-6 mb-3"><label class="form-label">Subject</label><input type="text" class="form-control" required placeholder="e.g., Physics"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Class</label><select class="form-select" required><?php foreach($available_classes as $class): echo "<option>Class $class</option>"; endforeach; ?></select></div>
                <div class="col-md-6 mb-3"><label class="form-label">Total Marks</label><input type="number" class="form-control" value="100" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Exam Date</label><input type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            </div>
        </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary" form="addExamForm">Save Exam</button></div>
</div></div></div>

<!-- Grade Criteria Modal -->
<div class="modal fade" id="gradeCriteriaModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Set Grading Criteria</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form id="gradeCriteriaForm">
            <table class="table table-sm"><thead><tr><th>Grade</th><th>Min Percentage (%)</th><th>Max Percentage (%)</th></tr></thead><tbody>
            <?php foreach($grade_criteria as $grade): ?>
                <tr>
                    <td><input type="text" class="form-control" value="<?= htmlspecialchars($grade['grade']) ?>" readonly></td>
                    <td><input type="number" class="form-control" value="<?= htmlspecialchars($grade['min_percent']) ?>"></td>
                    <td><input type="number" class="form-control" value="<?= htmlspecialchars($grade['max_percent']) ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="gradeCriteriaForm" class="btn btn-primary">Save Criteria</button></div>
</div></div></div>

<!-- Manage Results Modal -->
<div class="modal fade" id="manageResultsModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="manageResultsModalLabel">Manage Exam Results</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form id="manageResultsForm">
            <input type="hidden" id="examIdForResults" name="exam_id">
            <div class="table-responsive">
                <table class="table table-striped"><thead><tr><th>Student ID</th><th>Name</th><th>Marks Obtained</th><th>Grade</th></tr></thead><tbody id="resultsTableBody"></tbody></table>
            </div>
        </form>
    </div>
    <div class="modal-footer justify-content-between">
        <div id="publishStatusContainer"></div>
        <div><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" form="manageResultsForm" class="btn btn-primary me-2">Save Results</button><button type="button" id="publishBtn" class="btn btn-success" onclick="publishResults()">Publish Results</button></div>
    </div>
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
    document.getElementById('addExamForm').addEventListener('submit', (e) => { e.preventDefault(); alert('New exam has been added. (Simulation)'); bootstrap.Modal.getInstance(document.getElementById('addExamModal')).hide(); });
    document.getElementById('gradeCriteriaForm').addEventListener('submit', (e) => { e.preventDefault(); alert('Grading criteria has been updated. (Simulation)'); bootstrap.Modal.getInstance(document.getElementById('gradeCriteriaModal')).hide(); });
    document.getElementById('manageResultsForm').addEventListener('submit', (e) => { e.preventDefault(); alert('Student results have been saved. (Simulation)'); bootstrap.Modal.getInstance(document.getElementById('manageResultsModal')).hide(); });

    let currentGradeCriteria = [];
    
    function manageResults(exam, results, gradeCriteria) {
        currentGradeCriteria = gradeCriteria;
        document.getElementById('manageResultsModalLabel').textContent = `Manage Results for: ${exam.title}`;
        document.getElementById('examIdForResults').value = exam.id;
        const resultsTableBody = document.getElementById('resultsTableBody');
        resultsTableBody.innerHTML = '';
        
        results.forEach(student => {
            const row = document.createElement('tr');
            const grade = calculateGrade(student.marks, exam.total_marks);
            row.innerHTML = `
                <td>${student.student_id}</td>
                <td>${student.name}</td>
                <td><input type="number" class="form-control form-control-sm marks-input" value="${student.marks || ''}" max="${exam.total_marks}" data-total-marks="${exam.total_marks}" oninput="updateGrade(this)"></td>
                <td class="grade-cell">${grade}</td>
            `;
            resultsTableBody.appendChild(row);
        });

        const publishStatusContainer = document.getElementById('publishStatusContainer');
        const publishBtn = document.getElementById('publishBtn');
        if(exam.status === 'Published'){
            publishStatusContainer.innerHTML = `<span class="badge bg-success fs-6"><i class="fas fa-check-circle"></i> Results Published</span>`;
            publishBtn.style.display = 'none';
        } else {
            publishStatusContainer.innerHTML = `<span class="badge bg-warning fs-6"><i class="fas fa-clock"></i> Results Pending</span>`;
            publishBtn.style.display = 'inline-block';
        }
    }

    function calculateGrade(marks, totalMarks) {
        if (marks === null || marks === '' || totalMarks === 0) return 'N/A';
        const percentage = (marks / totalMarks) * 100;
        for (const criteria of currentGradeCriteria) {
            if (percentage >= criteria.min_percent && percentage <= criteria.max_percent) {
                return criteria.grade;
            }
        }
        return 'N/A';
    }

    function updateGrade(inputElement) {
        const marks = inputElement.value;
        const totalMarks = inputElement.dataset.totalMarks;
        const gradeCell = inputElement.parentElement.nextElementSibling;
        gradeCell.textContent = calculateGrade(marks, totalMarks);
    }

    function publishResults() {
        const examId = document.getElementById('examIdForResults').value;
        if(confirm('Are you sure you want to publish these results? This action cannot be undone.')){
             alert(`Results for exam ${examId} have been published. (Simulation)`);
             bootstrap.Modal.getInstance(document.getElementById('manageResultsModal')).hide();
             location.reload();
        }
    }
</script>
</body>
</html>
