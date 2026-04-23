<?php
session_start();
if (!isset($_SESSION['employee_logged_in'])) {
    header('Location: employee_login.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'];
$is_admin = $_SESSION['is_admin'] ?? false;

// Placeholder data for dashboard cards and lists
// In a real application, you would fetch this data from a database
$active_projects_count = 2; // Example
$pending_approvals_count = $is_admin ? 5 : 1; // Example
$overdue_tasks_count = 1; // Example

$recent_activities = [
    ['title' => 'Submitted quarterly performance review', 'time' => '3 hours ago', 'description' => 'Awaiting manager feedback.'],
    ['title' => 'New company policy on remote work updated', 'time' => '1 day ago', 'description' => 'Please review the new guidelines in the policy section.'],
    ['title' => 'Leave request for next week approved', 'time' => '2 days ago', 'description' => 'Your leave from DD/MM to DD/MM has been approved.'],
];

$upcoming_deadlines = [
    ['title' => 'Project Alpha - Phase 2 delivery', 'due' => 'Due in 3 days', 'description' => 'Ensure all modules are integrated and tested.', 'urgency_class' => 'text-danger'],
    ['title' => 'Team meeting: Project Beta planning', 'due' => 'Tomorrow at 11:00 AM', 'description' => 'Agenda includes resource allocation and timeline.', 'urgency_class' => 'text-warning'],
    ['title' => 'Submit monthly expense report', 'due' => 'Due in 1 week', 'description' => 'Include all receipts and justifications.', 'urgency_class' => 'text-info'],
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($employee_name) ?> | Employee Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #007bff; /* Changed primary color for a more corporate look */
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light-bg: #f8f9fc;
            --dark-text: #343a40;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            overflow-x: hidden;
        }

        .admin-badge {
            background-color: var(--warning);
            color: var(--dark-text);
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 10px;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--primary);
            color: white;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar .brand {
            padding: 1rem;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            color: white;
        }

        .sidebar.collapsed .brand span {
            display: none;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar ul li a {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar ul li a i {
            width: 20px;
            text-align: center;
            margin-right: 0.75rem; /* Slightly increased margin */
        }

        .sidebar.collapsed ul li a span {
            display: none;
        }
        .sidebar.collapsed ul li a i {
            margin-right: 0;
        }


        #main-content {
            margin-left: 250px;
            transition: all 0.3s;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        #main-content.collapsed {
            margin-left: 70px;
        }

        .topbar {
            height: 70px;
            background: white;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            padding: 0 1rem;
        }
        .topbar #sidebarToggle {
            color: var(--dark-text);
        }

        .topbar .ms-auto {
            display: flex;
            align-items: center;
        }

        .topbar .ms-auto i {
            margin-right: 0.5rem;
            color: var(--secondary);
        }
        .topbar .ms-auto span {
            color: var(--dark-text);
        }


        .content-wrapper {
            flex: 1;
            padding: 1.5rem;
        }

        .card {
            border: none;
            border-left: 4px solid var(--primary);
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1.5rem; /* Increased margin */
        }
        .card-success { border-left-color: var(--success); }
        .card-info { border-left-color: var(--info); }
        .card-warning { border-left-color: var(--warning); }


        .footer {
            text-align: center;
            padding: 1rem;
            background: white;
            font-size: 0.9rem;
            color: var(--secondary);
            border-top: 1px solid #e3e6f0;
        }

        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }
            .sidebar.show {
                left: 0;
            }
            #main-content {
                margin-left: 0;
            }
            #main-content.show-sidebar { /* Renamed for clarity */
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>

  <?php include 'sidebar.php'?>

    <div id="main-content">
        <nav class="topbar">
            <button class="btn" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="ms-auto">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($employee_name) ?></span>
                 <?php if ($is_admin): ?>
                    <span class="admin-badge ms-2">ADMIN</span>
                <?php endif; ?>
            </div>
        </nav>

        <div class="content-wrapper">
            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800">
                    Welcome, <?= htmlspecialchars($employee_name) ?>!
                </h1>

                <div class="row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Employee ID</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($employee_id) ?></div>
                                    </div>
                                    <div class="ms-auto">
                                        <i class="fas fa-id-card fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card card-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Active Projects</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $active_projects_count ?></div>
                                    </div>
                                    <div class="ms-auto">
                                        <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card card-info">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Pending Approvals</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $pending_approvals_count ?></div>
                                    </div>
                                    <div class="ms-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card card-warning">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Overdue Tasks</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $overdue_tasks_count ?></div>
                                    </div>
                                    <div class="ms-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php if (!empty($recent_activities)): ?>
                                        <?php foreach ($recent_activities as $activity): ?>
                                        <a href="#" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                                <small><?= htmlspecialchars($activity['time']) ?></small>
                                            </div>
                                            <p class="mb-1 small text-muted"><?= htmlspecialchars($activity['description']) ?></p>
                                        </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-center text-muted">No recent activities.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Upcoming Deadlines</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                     <?php if (!empty($upcoming_deadlines)): ?>
                                        <?php foreach ($upcoming_deadlines as $deadline): ?>
                                        <a href="#" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?= htmlspecialchars($deadline['title']) ?></h6>
                                                <small class="<?= htmlspecialchars($deadline['urgency_class']) ?>"><?= htmlspecialchars($deadline['due']) ?></small>
                                            </div>
                                            <p class="mb-1 small text-muted"><?= htmlspecialchars($deadline['description']) ?></p>
                                        </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-center text-muted">No upcoming deadlines.</p>
                                     <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <footer class="footer">
            &copy; Your Company Name <?= date('Y') ?>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');

        // Function to save sidebar state
        function saveSidebarState(collapsed) {
            localStorage.setItem('sidebarCollapsed', collapsed);
        }

        // Function to load sidebar state
        function loadSidebarState() {
            return localStorage.getItem('sidebarCollapsed') === 'true';
        }

        // Apply initial state
        if (loadSidebarState()) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
        }

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
            saveSidebarState(sidebar.classList.contains('collapsed'));

            // For mobile view, manage a different class for showing/hiding
            if (window.innerWidth < 768) {
                 // If sidebar is not collapsed (meaning it's trying to show fully on mobile)
                if (!sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('show');
                    mainContent.classList.add('show-sidebar');
                } else {
                    sidebar.classList.remove('show');
                    mainContent.classList.remove('show-sidebar');
                }
            }
        });

        // Optional: Close mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (window.innerWidth < 768 && sidebar.classList.contains('show')) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggler = toggleBtn.contains(event.target);
                if (!isClickInsideSidebar && !isClickOnToggler) {
                    sidebar.classList.remove('show');
                    sidebar.classList.add('collapsed'); // Ensure it's marked as collapsed
                    mainContent.classList.remove('show-sidebar');
                    mainContent.classList.add('collapsed');
                    saveSidebarState(true);
                }
            }
        });

        // Set active link based on current page (simple example)
        document.addEventListener("DOMContentLoaded", function() {
            const currentPath = window.location.pathname.split("/").pop();
            const navLinks = sidebar.querySelectorAll("ul li a");

            navLinks.forEach(link => {
                if (link.getAttribute("href") === currentPath) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
            // Ensure dashboard is active by default if path is root or index
             if (currentPath === 'employee_dashboard.php' || currentPath === '') {
                const dashboardLink = sidebar.querySelector('a[href="employee_dashboard.php"]');
                if(dashboardLink) dashboardLink.classList.add('active');
            }
        });

    </script>

</body>
</html>