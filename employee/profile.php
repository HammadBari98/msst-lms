<?php
session_start();
// 1. DATABASE CONFIGURATION:
// Make sure this path is correct and db_config.php sets up your $pdo connection.
require_once __DIR__ . '/../config/db_config.php'; // Or your actual path

// 2. AUTHENTICATION: Ensure employee is logged in
if (!isset($_SESSION['employee_logged_in']) || !isset($_SESSION['employee_id'])) {
    header('Location: employee_login.php'); // Or your employee login page
    exit();
}

$employee_db_id = $_SESSION['employee_id']; // This should be the ID used in your 'employees' table
$employee_name_session = $_SESSION['employee_name'] ?? 'Employee'; // Fallback, updated from DB
$is_admin_session = $_SESSION['is_admin'] ?? false;

$employee_data = [];
$fetch_error = '';
$update_message = '';
$update_error = '';

// 3. FETCH EMPLOYEE DATA FROM DATABASE (NEEDS TO BE UNCOMMENTED AND $pdo USED)
/*
if (isset($pdo)) { // Check if $pdo from db_config.php is available
    try {
        $stmt = $pdo->prepare(
            "SELECT employee_id, full_name, email, department, position, hire_date, phone, address 
             FROM employees 
             WHERE employee_id = ?"
        );
        $stmt->execute([$employee_db_id]);
        $employee_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee_data) {
            $fetch_error = "Employee data not found for ID: " . htmlspecialchars($employee_db_id);
        } else {
            $_SESSION['employee_name'] = $employee_data['full_name']; // Update session name
            $employee_name_session = $employee_data['full_name'];    // Update local variable
        }
    } catch (PDOException $e) {
        $fetch_error = "Database query failed: " . $e->getMessage();
        // Log error: error_log("Employee Profile Fetch Error: " . $e->getMessage());
    }
} else {
    $fetch_error = "Database connection object (\$pdo) not available. Check db_config.php.";
}
*/

// --- FALLBACK/DEMO DATA ---
// REMOVE OR COMMENT THIS OUT ONCE YOUR DATABASE CONNECTION (STEP 3) IS LIVE.
if (empty($employee_data) && empty($fetch_error)) { // If DB fetch is commented or fails, use demo
    $fetch_error = "DEMO MODE: Database fetch is not active. Showing placeholder data. Please uncomment Step 3 and configure db_config.php.";
    $employee_data = [
        'employee_id' => $employee_db_id,
        'full_name' => 'Demo Employee Name',
        'email' => 'demo.employee@example.com',
        'department' => 'Sample Department',
        'position' => 'Sample Position',
        'hire_date' => date('Y-m-d', strtotime('-1 year')), // Example hire date
        'phone' => '000-000-0000',
        'address' => '123 Demo Street, Sample City, Demo Land'
    ];
    if (isset($employee_data['full_name'])) {
        $employee_name_session = $employee_data['full_name'];
    }
}
// --- END FALLBACK/DEMO DATA ---


// 4. HANDLE PROFILE UPDATE (NEEDS TO BE UNCOMMENTED AND $pdo USED)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Retrieve and sanitize form data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $department = trim($_POST['department'] ?? $employee_data['department']); // Keep old if not submitted
    $position = trim($_POST['position'] ?? $employee_data['position']);     // Keep old if not submitted

    if (empty($full_name) || empty($email)) {
        $update_error = "Full name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_error = "Invalid email format.";
    } else {
        /*
        if (isset($pdo)) { // Check for $pdo connection
            try {
                $update_stmt = $pdo->prepare(
                    "UPDATE employees 
                     SET full_name = ?, email = ?, phone = ?, address = ?, department = ?, position = ? 
                     WHERE employee_id = ?"
                );
                $success = $update_stmt->execute([
                    $full_name, $email, $phone, $address, $department, $position, $employee_db_id
                ]);

                if ($success) {
                    $update_message = "Profile updated successfully!";
                    // Re-fetch data to show updated info
                    $stmt = $pdo->prepare("SELECT employee_id, full_name, email, department, position, hire_date, phone, address FROM employees WHERE employee_id = ?");
                    $stmt->execute([$employee_db_id]);
                    $employee_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    if($employee_data) { // Check if re-fetch was successful
                        $_SESSION['employee_name'] = $employee_data['full_name'];
                        $employee_name_session = $employee_data['full_name'];
                    }
                } else {
                    $update_error = "Failed to update profile (no changes or error).";
                }
            } catch (PDOException $e) {
                $update_error = "Database error during update: " . $e->getMessage();
                // Log error: error_log("Employee Profile Update Error: " . $e->getMessage());
            }
        } else {
            $update_error = "Database connection not available for update.";
        }
        */
        // --- DEMO UPDATE ---
        // REMOVE OR COMMENT THIS OUT ONCE YOUR DATABASE UPDATE (STEP 4) IS LIVE.
        if (strpos($fetch_error, "DEMO MODE") !== false || !isset($pdo)) {
             $update_message = "Profile update (simulated for DEMO MODE) successful! DB integration needed for permanent save.";
            $employee_data['full_name'] = $full_name;
            $employee_data['email'] = $email;
            $employee_data['phone'] = $phone;
            $employee_data['address'] = $address;
            $employee_data['department'] = $department;
            $employee_data['position'] = $position;
            $_SESSION['employee_name'] = $full_name;
            $employee_name_session = $full_name;
        }
         // --- END DEMO UPDATE ---
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile | <?= htmlspecialchars($employee_name_session) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Styles from your provided student voucher page */
        :root {
            --primary: #4e73df; /* You can change this to employee dashboard theme if different */
            --secondary: #1cc88a;
            --accent: #36b9cc;
            --light-bg: #f8f9fc;
            --dark-text: #5a5c69;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            overflow-x: hidden;
        }
        /* These .card styles are generic enough to be reused */
        .card {
            border: none; border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .card-header { /* This will be overridden by .card-header-profile for profile cards */
            background-color: var(--primary); color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }

        /* Employee Profile Specific Styles (can be expanded) */
        .profile-avatar-container {
            width: 150px; height: 150px; border-radius: 50%;
            background-color: #e9ecef; display: flex;
            align-items: center; justify-content: center; margin: 0 auto 1.5rem;
            border: 3px solid var(--primary);
        }
        .profile-avatar-container i { font-size: 5rem; color: var(--primary); }
        .card-header-profile { /* Specific for profile page cards */
            background-color: var(--primary) !important; /* Ensure it overrides generic .card-header */
            color: white !important;
             border-radius: 0.5rem 0.5rem 0 0 !important; /* Match student voucher card header */
        }
        .form-label { font-weight: 500; }

        /* Voucher specific styles (will not apply to new content, can be cleaned up later) */
        /* .voucher-page-content .voucher-instance { ... etc ... } */

        /* Sidebar and Main Content styles (assuming they come from your includes or a shared CSS) */
        /* If not, you might need to define them or ensure sidebar.php and header.php are styled */
        /* For demonstration, adding minimal structural styles if includes are not fully styled: */
        .sidebar { width: 250px; height: 100vh; position: fixed; top: 0; left: 0; background: var(--primary); color: white; transition: all 0.3s; z-index: 1000; }
        .sidebar.collapsed { width: 70px; }
        .sidebar .brand { padding: 1rem; font-size: 1.2rem; font-weight: bold; text-align: center; }
        .sidebar.collapsed .brand span { display: none; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar ul li a { color: rgba(255,255,255,0.8); padding: 0.75rem 1rem; display: flex; align-items: center; text-decoration: none; transition: background 0.2s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.1); color:white; }
        .sidebar ul li a i { width: 20px; text-align: center; margin-right: 0.75rem; }
        .sidebar.collapsed ul li a span { display: none; }
        .sidebar.collapsed ul li a i { margin-right: 0; }
        #main-content { margin-left: 250px; transition: all 0.3s; min-height: 100vh; display: flex; flex-direction: column; }
        #main-content.collapsed { margin-left: 70px; }
        .topbar { height: 70px; background: white; box-shadow: 0 1px 4px rgba(0,0,0,0.1); display: flex; align-items: center; padding: 0 1rem; }
        .topbar #sidebarToggle { color: var(--dark-text); } /* Assuming #sidebarToggle is in header.php */
        .admin-badge { background-color: #f39c12; color: white; padding: 3px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: bold; margin-left: 10px;}
        .topbar .ms-auto { display: flex; align-items: center; }
        .topbar .ms-auto i { margin-right: 0.5rem; color: var(--dark-text); }
        @media (max-width: 768px) { .sidebar { left: -250px; } .sidebar.show { left: 0; } #main-content { margin-left: 0; } #main-content.show-sidebar { margin-left: 250px; } }

    </style>
</head>
<body>

    <?php // These includes should point to your employee dashboard's sidebar and header
          // For now, I'll put placeholder structures based on previous discussions.
    ?>
    <div class="sidebar" id="sidebar"> <div class="brand">
            <i class="fas fa-briefcase"></i> <span>Employee Portal</span>
        </div>
        <ul>
            <li><a href="employee_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="employee_profile.php" class="active"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <div id="main-content">
        <nav class="topbar"> <button class="btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div class="ms-auto">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($employee_name_session) ?></span>
                 <?php if ($is_admin_session): ?><span class="admin-badge">ADMIN</span><?php endif; ?>
            </div>
        </nav>

        <div class="content-wrapper">
            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800 page-title-h1">My Employee Profile</h1>

                <?php if ($fetch_error): ?><div class="alert alert-warning"><?= htmlspecialchars($fetch_error) ?></div><?php endif; ?>
                <?php if ($update_message): ?><div class="alert alert-success"><?= htmlspecialchars($update_message) ?></div><?php endif; ?>
                <?php if ($update_error): ?><div class="alert alert-danger"><?= htmlspecialchars($update_error) ?></div><?php endif; ?>

                <?php if (!empty($employee_data) || strpos($fetch_error, "DEMO MODE") !== false ): // Show form if data exists or in demo mode ?>
                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header card-header-profile text-center">
                                <h5 class="mb-0">Employee Details</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="profile-avatar-container">
                                    <i class="fas fa-user"></i> </div>
                                <h4><?= htmlspecialchars($employee_data['full_name'] ?? 'N/A') ?></h4>
                                <p class="text-muted mb-1"><?= htmlspecialchars($employee_data['position'] ?? 'N/A') ?></p>
                                <p class="text-muted mb-0"><?= htmlspecialchars($employee_data['department'] ?? 'N/A') ?></p>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Employee ID: <strong><?= htmlspecialchars($employee_data['employee_id'] ?? 'N/A') ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Hire Date: <strong><?= isset($employee_data['hire_date']) ? htmlspecialchars(date("M d, Y", strtotime($employee_data['hire_date']))) : 'N/A' ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Email: <strong class="text-break"><?= htmlspecialchars($employee_data['email'] ?? 'N/A') ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Phone: <strong><?= htmlspecialchars($employee_data['phone'] ?? 'N/A') ?></strong>
                                </li>
                                <li class="list-group-item">
                                    Address: <br><strong><?= nl2br(htmlspecialchars($employee_data['address'] ?? 'N/A')) ?></strong>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-8 mb-4">
                        <div class="card">
                             <div class="card-header card-header-profile">
                                <h5 class="mb-0">Edit Your Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="employee_profile.php">
                                    <div class="mb-3">
                                        <label for="fullName" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="fullName" name="full_name" value="<?= htmlspecialchars($employee_data['full_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($employee_data['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($employee_data['phone'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($employee_data['department'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="position" class="form-label">Position</label>
                                        <input type="text" class="form-control" id="position" name="position" value="<?= htmlspecialchars($employee_data['position'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($employee_data['address'] ?? '') ?></textarea>
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header card-header-profile">
                                <h5 class="mb-0">Account Security</h5>
                            </div>
                            <div class="card-body">
                                <p>To change your password, please proceed to the <a href="employee_change_password.php">Change Password page</a>.</p>
                                </div>
                        </div>
                    </div>
                </div>
                <?php elseif (!$fetch_error) : ?>
                    <div class="alert alert-info">Loading data or no employee profile to display.</div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="footer mt-auto py-3 bg-white">
            <div class="container-fluid">
                <div class="text-center">
                    <span class="text-muted">© Your Company Name <?= date('Y') ?></span>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle script (from your student voucher page, adapted for employee dashboard IDs)
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle'); // Assuming your header.php has this ID

        function saveSidebarState(collapsed) { localStorage.setItem('sidebarCollapsed', collapsed); }
        function loadSidebarState() { return localStorage.getItem('sidebarCollapsed') === 'true'; }

        if (sidebar && mainContent && toggleBtn) { // Check elements exist
            if (loadSidebarState()) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('collapsed');
            }
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                saveSidebarState(sidebar.classList.contains('collapsed'));

                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show'); // For mobile view
                    // mainContent.classList.toggle('show-sidebar'); // If you have specific style for this
                }
            });
             // Mobile sidebar auto-close
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 768 && sidebar.classList.contains('show')) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggler = toggleBtn.contains(event.target);
                    if (!isClickInsideSidebar && !isClickOnToggler) {
                        sidebar.classList.remove('show');
                        // Optionally, also mark as collapsed for consistency if that's your mobile design
                        // sidebar.classList.add('collapsed');
                        // mainContent.classList.remove('show-sidebar');
                        // saveSidebarState(sidebar.classList.contains('collapsed'));
                    }
                }
            });
        } else {
            console.error("Sidebar toggle elements not found. Check IDs: 'sidebar', 'main-content', 'sidebarToggle'.");
        }
        // Active sidebar link
        document.addEventListener("DOMContentLoaded", function() {
            const currentPath = window.location.pathname.split("/").pop();
            const navLinks = document.querySelectorAll("#sidebar ul li a");
            navLinks.forEach(link => {
                link.classList.remove("active");
                if (link.getAttribute("href") === currentPath) { link.classList.add("active"); }
            });
        });
    </script>
</body>
</html>