<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'] ?? 'ADM001';

// Dummy data for the dashboard
$user_counts = ['students' => 357, 'teachers' => 25, 'staff' => 12, 'total' => 394];
$user_activity = ['active' => 375, 'inactive' => 19];
$fee_summary = ['total_receivable' => 1250000, 'total_collected' => 1175000, 'total_due' => 75000];
$fee_collection_percentage = round(($fee_summary['total_collected'] / ($fee_summary['total_receivable'] ?: 1)) * 100);
$latest_logins = [
    ['name' => 'Ahmed Ali (Student)', 'time_ago' => '2 minutes ago', 'status' => 'Success'],
    ['name' => 'Ms. Fatima (Teacher)', 'time_ago' => '5 minutes ago', 'status' => 'Success'],
];
$system_alerts = [
    ['type' => 'danger', 'icon' => 'fa-exclamation-triangle', 'message' => 'Database backup failed last night.'],
    ['type' => 'warning', 'icon' => 'fa-user-clock', 'message' => '19 users have been inactive for over 30 days.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($admin_name) ?> | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php' ?>

    <div id="main-content">
       <?php include 'header.php'?>
        <div class="content-wrapper">
            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800">
                    Welcome, <?= htmlspecialchars($admin_name) ?>!
                </h1>

                <div class="row">
                   

                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="card card-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Students</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $user_counts['students'] ?></div>
                                    </div>
                                    <div class="ms-auto">
                                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="card card-info">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Teachers</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $user_counts['teachers'] ?></div>
                                    </div>
                                    <div class="ms-auto">
                                        <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card card-warning">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Total Staff</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $user_counts['staff'] ?></div>
                                    </div>
                                    <div class="ms-auto">
                                        <i class="fas fa-users-cog fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> -->
                </div>

                <div class="row">
                    <!-- Fee Collection Summary -->
                    <div class="col-lg-7 mb-4">
                      <div class="card shadow mb-4" style="height: 340px;">
                            <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold  text-primary">Fee Collection Summary</h6>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="text-center mb-3">
                                <p class="text-muted mb-1">This month's collection progress</p>
                                <div class="position-relative d-inline-block">
                                    <div class="progress-circle" 
                                        data-percent="<?= $fee_collection_percentage ?>"
                                        data-color="primary">
                                        <svg class="progress-circle-svg" viewBox="0 0 100 100">
                                            <circle class="progress-circle-track" cx="50" cy="50" r="45"></circle>
                                            <circle class="progress-circle-bar" cx="50" cy="50" r="45"></circle>
                                        </svg>
                                        <div class="progress-circle-text">
                                            <span class="h4"><?= $fee_collection_percentage ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
        
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Collected:</span>
                                    <span class="font-weight-bold text-success">PKR <?= number_format($fee_summary['total_collected']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Pending:</span>
                                    <span class="font-weight-bold text-warning">PKR <?= number_format($fee_summary['total_due']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Total:</span>
                                    <span class="font-weight-bold text-primary">PKR <?= number_format($fee_summary['total_receivable']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>

                    <!-- User Activity Chart -->
                    <div class="col-lg-5 mb-4">
                        <div class="card shadow mb-4" style="height: 340px;">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">User Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="userActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Login Activity -->
                    <div class="col-lg-7 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Login Activity</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($latest_logins as $login): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($login['name']) ?></strong>
                                            <small class="d-block text-muted"><?= $login['time_ago'] ?></small>
                                        </div>
                                        <span class="badge <?= $login['status'] == 'Success' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $login['status'] ?>
                                        </span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- System Alerts -->
                    <div class="col-lg-5 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">System Alerts</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($system_alerts as $alert): ?>
                                <div class="alert alert-<?= $alert['type'] ?> mb-2" role="alert">
                                    <i class="fas <?= $alert['icon'] ?> me-2"></i>
                                    <?= htmlspecialchars($alert['message']) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php include 'footer.php'?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

            // For mobile view
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

        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (window.innerWidth < 768 && sidebar.classList.contains('show')) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggler = toggleBtn.contains(event.target);
                if (!isClickInsideSidebar && !isClickOnToggler) {
                    sidebar.classList.remove('show');
                    sidebar.classList.add('collapsed');
                    mainContent.classList.remove('show-sidebar');
                    mainContent.classList.add('collapsed');
                    saveSidebarState(true);
                }
            }
        });

        // Initialize Chart.js
        document.addEventListener("DOMContentLoaded", function() {
            const userActivityCtx = document.getElementById('userActivityChart');
            if (userActivityCtx) {
                new Chart(userActivityCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Active Users', 'Inactive Users'],
                        datasets: [{
                            data: [<?= $user_activity['active'] ?>, <?= $user_activity['inactive'] ?>],
                            backgroundColor: ['#28a745', '#dc3545'],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 20
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }
            
            // Set active link based on current page
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
            if (currentPath === 'dashboard.php' || currentPath === '') {
                const dashboardLink = sidebar.querySelector('a[href="dashboard.php"]');
                if(dashboardLink) dashboardLink.classList.add('active');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                // On desktop, ensure proper state
                if (sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
                if (mainContent.classList.contains('show-sidebar')) {
                    mainContent.classList.remove('show-sidebar');
                }
            }
        });
    </script>
    
<script>
    // Initialize circular progress indicators
    document.addEventListener('DOMContentLoaded', function() {
        const circles = document.querySelectorAll('.progress-circle');
        circles.forEach(circle => {
            const percent = parseInt(circle.getAttribute('data-percent'));
            const color = circle.getAttribute('data-color') || 'primary';
            const bar = circle.querySelector('.progress-circle-bar');
            
            // Calculate the dash offset
            const circumference = 2 * Math.PI * 45;
            const offset = circumference - (percent / 100) * circumference;
            
            // Apply styles
            bar.style.strokeDasharray = circumference;
            bar.style.strokeDashoffset = offset;
            bar.style.stroke = `var(--${color})`;
        });
    });
</script>
</body>
</html>