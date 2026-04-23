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

// --- Dummy System Settings Data ---
$system_settings = [
    'school_name' => 'Muhaddisa School of Science and Technology',
    'school_logo_url' => 'assets/images/msst-logo.png',
    'current_theme' => '#0d6efd', // Default Bootstrap Primary
    'academic_session_start' => '2024-08-01',
    'academic_session_end' => '2025-06-30',
];

$roles = ['Admin', 'Teacher', 'Student', 'Staff', 'Parent'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .theme-color-dot {
            width: 25px; height: 25px; border-radius: 50%;
            display: inline-block; cursor: pointer; border: 2px solid #ddd;
        }
        .theme-color-dot.active { border-color: #000; }
        #logoPreview { max-width: 200px; max-height: 80px; margin-top: 10px; border: 1px solid #ddd; padding: 5px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">System Settings</h1>
            </div>
            
            <form id="systemSettingsForm">
                <div class="row">
                    <!-- Academic Session & Permissions -->
                    <div class="col-lg-6">
                        <!-- Academic Session Card -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Academic Session</h6></div>
                            <div class="card-body">
                                <p class="text-muted">Define the start and end dates for the current academic year.</p>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sessionStart" class="form-label">Session Start Date</label>
                                        <input type="date" id="sessionStart" class="form-control" value="<?= htmlspecialchars($system_settings['academic_session_start']) ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="sessionEnd" class="form-label">Session End Date</label>
                                        <input type="date" id="sessionEnd" class="form-control" value="<?= htmlspecialchars($system_settings['academic_session_end']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Permissions Card -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Roles & Permissions</h6></div>
                            <div class="card-body">
                                <p class="text-muted">Manage user roles and their access levels across the system.</p>
                                <strong>Defined Roles:</strong>
                                <div>
                                    <?php foreach($roles as $role): ?>
                                        <span class="badge bg-secondary me-1"><?= htmlspecialchars($role) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <a href="manage-users.php" class="btn btn-outline-primary mt-3">Manage Permissions</a>
                            </div>
                        </div>
                    </div>

                    <!-- LMS Branding -->
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">LMS Branding</h6></div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="schoolName" class="form-label">School Name</label>
                                    <input type="text" class="form-control" id="schoolName" value="<?= htmlspecialchars($system_settings['school_name']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="schoolLogo" class="form-label">School Logo</label>
                                    <input class="form-control" type="file" id="schoolLogo" accept="image/png, image/jpeg">
                                    <img id="logoPreview" src="<?= htmlspecialchars($system_settings['school_logo_url']) ?>" alt="Logo Preview">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Theme Color</label>
                                    <div>
                                        <span class="theme-color-dot active" style="background-color: #0d6efd;" data-color="#0d6efd"></span>
                                        <span class="theme-color-dot" style="background-color: #6f42c1;" data-color="#6f42c1"></span>
                                        <span class="theme-color-dot" style="background-color: #d63384;" data-color="#d63384"></span>
                                        <span class="theme-color-dot" style="background-color: #198754;" data-color="#198754"></span>
                                        <span class="theme-color-dot" style="background-color: #dc3545;" data-color="#dc3545"></span>
                                        <input type="color" id="customColorPicker" value="<?= htmlspecialchars($system_settings['current_theme']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-success btn-lg shadow-sm"><i class="fas fa-save me-2"></i>Save All Settings</button>
                </div>
            </form>
        </div>
    </div>
    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> School Management System. All rights reserved.</p></div></footer>
</div>

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
    document.getElementById('schoolLogo').addEventListener('change', function(event) {
        const logoPreview = document.getElementById('logoPreview');
        if (event.target.files && event.target.files[0]) {
            logoPreview.src = URL.createObjectURL(event.target.files[0]);
            logoPreview.onload = () => URL.revokeObjectURL(logoPreview.src); // Free memory
        }
    });

    document.querySelectorAll('.theme-color-dot').forEach(dot => {
        dot.addEventListener('click', function() {
            document.querySelectorAll('.theme-color-dot').forEach(d => d.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('customColorPicker').value = this.dataset.color;
        });
    });

    document.getElementById('systemSettingsForm').addEventListener('submit', (e) => {
        e.preventDefault();
        alert('System settings have been saved successfully. (Simulation)');
    });
</script>
</body>
</html>
