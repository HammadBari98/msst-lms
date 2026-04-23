<?php
session_start();

// Check if the admin is logged in, otherwise redirect to the login page.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Set admin details from the session.
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// --- Dummy Teacher Data ---
$teachers = [
    [
        'id' => 'TEA-025',
        'name' => 'Ayesha Khan',
        'email' => 'ayesha.khan@example.com',
        'phone' => '0311-1234567',
        'profile_pic_url' => 'https://placehold.co/100x100/EFEFEF/AAAAAA&text=A.K',
        'subjects' => ['Physics', 'Mathematics'],
        'classes' => ['9-A', '10-B'],
        'workload' => [
            'total_classes' => 2,
            'periods_per_week' => 10,
            'labs_supervised' => 1,
        ],
        'schedule' => [
            'Monday' => ['09:00 - 09:45<br>Class 9-A (Physics)', '09:45 - 10:30<br>Class 10-B (Math)', 'Free', '12:00 - 12:45<br>Lab Duty', 'Free'],
            'Tuesday' => ['09:00 - 09:45<br>Class 10-B (Math)', '09:45 - 10:30<br>Class 9-A (Physics)', 'Free', 'Free', 'Free'],
            'Wednesday' => ['09:00 - 09:45<br>Class 9-A (Physics)', 'Free', '11:15 - 12:00<br>Class 10-B (Math)', 'Free', 'Free'],
            'Thursday' => ['09:00 - 09:45<br>Class 10-B (Math)', 'Free', '11:15 - 12:00<br>Class 9-A (Physics)', 'Free', 'Lab Duty'],
            'Friday' => ['09:00 - 09:45<br>Class 9-A (Physics)', '09:45 - 10:30<br>Class 10-B (Math)', 'Free', 'Free', 'Free'],
        ],
        'schedule_status' => 'Pending Approval'
    ],
    [
        'id' => 'TEA-026',
        'name' => 'Bilal Hassan',
        'email' => 'bilal.hassan@example.com',
        'phone' => '0322-9876543',
        'profile_pic_url' => 'https://placehold.co/100x100/EFEFEF/AAAAAA&text=B.H',
        'subjects' => ['English', 'History'],
        'classes' => ['8-C', '8-D', '7-A'],
        'workload' => [
            'total_classes' => 3,
            'periods_per_week' => 15,
            'labs_supervised' => 0,
        ],
        'schedule' => [
             'Monday' => ['Free', '09:45 - 10:30<br>Class 8-C (English)', '11:15 - 12:00<br>Class 7-A (History)', 'Free', 'Free'],
             'Tuesday' => ['09:00 - 09:45<br>Class 8-D (English)', 'Free', '11:15 - 12:00<br>Class 8-C (History)', '12:00 - 12:45<br>Class 7-A (English)', 'Free'],
             'Wednesday' => ['09:00 - 09:45<br>Class 7-A (English)', '09:45 - 10:30<br>Class 8-D (History)', 'Free', 'Free', '13:30 - 14:15<br>Class 8-C (English)'],
             'Thursday' => ['Free', '09:45 - 10:30<br>Class 7-A (History)', '11:15 - 12:00<br>Class 8-C (English)', 'Free', '13:30 - 14:15<br>Class 8-D (English)'],
             'Friday' => ['09:00 - 09:45<br>Class 8-D (History)', 'Free', '11:15 - 12:00<br>Class 7-A (English)', '12:00 - 12:45<br>Class 8-C (History)', 'Free'],
        ],
        'schedule_status' => 'Approved'
    ],
];

// Data for forms
$available_classes = ['6', '7', '8', '9', '10', '11', '12'];
$available_sections = ['A', 'B', 'C', 'D'];
$available_subjects = ['English', 'Urdu', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Islamiyat', 'History', 'Geography'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management | Admin Dashboard</title>
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
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Teacher Management</h1>
            </div>

            <!-- Teachers Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Teacher List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="teachersTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Teacher ID</th>
                                    <th>Name</th>
                                    <th>Subjects</th>
                                    <th>Schedule Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($teacher['id']) ?></td>
                                        <td><?= htmlspecialchars($teacher['name']) ?></td>
                                        <td><?= htmlspecialchars(implode(', ', $teacher['subjects'])) ?></td>
                                        <td>
                                            <span class="badge <?= $teacher['schedule_status'] == 'Approved' ? 'bg-success' : 'bg-warning' ?>">
                                                <?= htmlspecialchars($teacher['schedule_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" title="View Details" data-bs-toggle="modal" data-bs-target="#teacherDetailsModal" onclick='viewTeacherDetails(<?= json_encode($teacher) ?>)'>
                                                <i class="fas fa-user-edit"></i> Manage
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
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
            <p class="text-center mb-0">© <?= date('Y') ?> School Management System. All rights reserved.</p>
        </div>
    </footer>
</div>

<!-- Teacher Details Modal -->
<div class="modal fade" id="teacherDetailsModal" tabindex="-1" aria-labelledby="teacherDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="teacherDetailsModalLabel">Teacher Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="teacherDetailsForm">
                    <input type="hidden" id="teacherId" name="id">
                    <div class="row mb-4">
                        <div class="col-md-2 text-center"><img id="teacherProfilePic" src="" class="img-thumbnail rounded-circle"></div>
                        <div class="col-md-10">
                            <h3 id="teacherName" class="mb-0"></h3>
                            <p class="text-muted" id="teacherEmailPhone"></p>
                        </div>
                    </div>

                    <ul class="nav nav-tabs" id="teacherTab" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button">Schedule & Approval</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="assignment-tab" data-bs-toggle="tab" data-bs-target="#assignment" type="button">Assign Classes/Subjects</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="workload-tab" data-bs-toggle="tab" data-bs-target="#workload" type="button">Workload</button></li>
                    </ul>

                    <div class="tab-content pt-3" id="teacherTabContent">
                        <!-- Schedule Tab -->
                        <div class="tab-pane fade show active" id="schedule" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Weekly Teaching Schedule</h5>
                                <div id="scheduleApprovalContainer"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead><tr><th>Day</th><th>Period 1</th><th>Period 2</th><th>Period 3</th><th>Period 4</th><th>Period 5</th></tr></thead>
                                    <tbody id="scheduleBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Assignment Tab -->
                        <div class="tab-pane fade" id="assignment" role="tabpanel">
                            <h5>Assign Classes</h5>
                            <div id="assignedClassesContainer" class="mb-3"></div>
                            <button type="button" class="btn btn-secondary btn-sm mb-3" onclick="addAssignedClass()">+ Add Class</button>
                            
                            <h5 class="mt-4">Assign Subjects</h5>
                            <div id="subjectsContainer" class="d-flex flex-wrap">
                                <?php foreach($available_subjects as $subject): ?>
                                    <div class="form-check form-check-inline col-md-3">
                                        <input class="form-check-input" type="checkbox" name="subjects[]" value="<?= $subject ?>" id="subj_<?= str_replace(' ', '_', $subject) ?>">
                                        <label class="form-check-label" for="subj_<?= str_replace(' ', '_', $subject) ?>"><?= $subject ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Workload Tab -->
                        <div class="tab-pane fade" id="workload" role="tabpanel">
                            <h5>Teacher Workload Summary</h5>
                            <div class="row">
                                <div class="col-md-4"><div class="card text-center p-3"><h6 class="card-title">Total Classes</h6><p class="h4" id="workloadClasses"></p></div></div>
                                <div class="col-md-4"><div class="card text-center p-3"><h6 class="card-title">Periods per Week</h6><p class="h4" id="workloadPeriods"></p></div></div>
                                <div class="col-md-4"><div class="card text-center p-3"><h6 class="card-title">Labs Supervised</h6><p class="h4" id="workloadLabs"></p></div></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" form="teacherDetailsForm">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Sidebar Toggle Script ---
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    function saveSidebarState(c) { localStorage.setItem('sidebarCollapsed', c); }
    function loadSidebarState() { return localStorage.getItem('sidebarCollapsed') === 'true'; }
    if(loadSidebarState()){ sidebar.classList.add('collapsed'); mainContent.classList.add('collapsed'); }
    toggleBtn.addEventListener('click', ()=>{ sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('collapsed'); saveSidebarState(sidebar.classList.contains('collapsed')); if(window.innerWidth<768){ if(!sidebar.classList.contains('collapsed')){ sidebar.classList.add('show'); mainContent.classList.add('show-sidebar'); } else { sidebar.classList.remove('show'); mainContent.classList.remove('show-sidebar'); } } });
    document.addEventListener('click', (e) => { if (window.innerWidth < 768 && sidebar.classList.contains('show') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) { sidebar.classList.remove('show'); sidebar.classList.add('collapsed'); mainContent.classList.remove('show-sidebar'); mainContent.classList.add('collapsed'); saveSidebarState(true); } });

    // --- Page-specific Scripts ---
    const availableClasses = <?= json_encode($available_classes) ?>;
    const availableSections = <?= json_encode($available_sections) ?>;
    
    function viewTeacherDetails(teacher) {
        // Populate header
        document.getElementById('teacherId').value = teacher.id;
        document.getElementById('teacherProfilePic').src = teacher.profile_pic_url;
        document.getElementById('teacherName').textContent = teacher.name;
        document.getElementById('teacherEmailPhone').textContent = `${teacher.email} | ${teacher.phone}`;

        // Populate Schedule
        const scheduleBody = document.getElementById('scheduleBody');
        scheduleBody.innerHTML = '';
        Object.keys(teacher.schedule).forEach(day => {
            let row = `<tr><td><strong>${day}</strong></td>`;
            teacher.schedule[day].forEach(period => row += `<td>${period}</td>`);
            row += '</tr>';
            scheduleBody.innerHTML += row;
        });
        
        // Schedule Approval Button
        const approvalContainer = document.getElementById('scheduleApprovalContainer');
        if (teacher.schedule_status === 'Pending Approval') {
            approvalContainer.innerHTML = `<button type="button" class="btn btn-success" onclick="approveSchedule('${teacher.id}')"><i class="fas fa-check"></i> Approve Schedule</button>`;
        } else {
            approvalContainer.innerHTML = `<span class="badge bg-success fs-6"><i class="fas fa-check-circle"></i> Approved</span>`;
        }

        // Populate Assigned Classes
        const assignedClassesContainer = document.getElementById('assignedClassesContainer');
        assignedClassesContainer.innerHTML = '';
        teacher.classes.forEach(cls => addAssignedClass(cls));

        // Populate Subjects
        document.querySelectorAll('#subjectsContainer input').forEach(cb => cb.checked = false);
        teacher.subjects.forEach(subject => {
            const checkbox = document.getElementById(`subj_${subject.replace(' ', '_')}`);
            if(checkbox) checkbox.checked = true;
        });

        // Populate Workload
        document.getElementById('workloadClasses').textContent = teacher.workload.total_classes;
        document.getElementById('workloadPeriods').textContent = teacher.workload.periods_per_week;
        document.getElementById('workloadLabs').textContent = teacher.workload.labs_supervised;
    }

    function addAssignedClass(value = '') {
        const container = document.getElementById('assignedClassesContainer');
        const index = container.children.length;
        const [classVal, sectionVal] = value.split('-');
        
        let classOptions = '';
        availableClasses.forEach(c => classOptions += `<option value="${c}" ${c === classVal ? 'selected' : ''}>${c}</option>`);
        
        let sectionOptions = '';
        availableSections.forEach(s => sectionOptions += `<option value="${s}" ${s === sectionVal ? 'selected' : ''}>${s}</option>`);

        const div = document.createElement('div');
        div.className = 'row mb-2 align-items-center';
        div.innerHTML = `
            <div class="col-5"><select class="form-select" name="assigned_class[${index}]">${classOptions}</select></div>
            <div class="col-5"><select class="form-select" name="assigned_section[${index}]">${sectionOptions}</select></div>
            <div class="col-2"><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">X</button></div>
        `;
        container.appendChild(div);
    }
    
    function approveSchedule(teacherId){
        console.log(`Approving schedule for teacher ID: ${teacherId}`);
        alert(`Schedule for teacher ${teacherId} has been approved. (Simulation)`);
        // In a real app, update the UI or reload data.
        const teacherDetailsModal = bootstrap.Modal.getInstance(document.getElementById('teacherDetailsModal'));
        teacherDetailsModal.hide();
        location.reload();
    }
    
    document.getElementById('teacherDetailsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = {};
        for (let [key, value] of formData.entries()) {
            if (key.endsWith('[]')) {
                key = key.slice(0, -2);
                if (!data[key]) data[key] = [];
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }

        console.log("Saving teacher data:", data);
        alert(`Teacher profile for ${data.id} has been updated. (Simulation)`);
        
        bootstrap.Modal.getInstance(document.getElementById('teacherDetailsModal')).hide();
        location.reload();
    });
</script>
</body>
</html>
