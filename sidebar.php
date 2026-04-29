<div class="sidebar" id="sidebar">
    <div class="brand">
        <!-- You can place your school logo here -->
        <img src="assets/images/msst-logo.png" alt="School Logo" onerror="this.style.display='none'">
 
    </div>
    
    <ul>
        <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt fa-fw me-2"></i> <span>Dashboard</span></a></li>
        <li><a href="manage-users.php"><i class="fas fa-users-cog fa-fw me-2"></i> <span>Manage Users</span></a></li>
        <li><a href="student-information.php"><i class="fas fa-user-graduate fa-fw me-2"></i> <span>Student Information</span></a></li>
        <li><a href="id-card-generator.php"><i class="fas fa-id-card fa-fw me-2"></i> <span>ID Card Generator</span></a></li>
        <li><a href="teacher-management.php"><i class="fas fa-chalkboard-teacher fa-fw me-2"></i> <span>Teacher Management</span></a></li>
        <li><a href="fee-management.php"><i class="fas fa-file-invoice-dollar fa-fw me-2"></i> <span>Fee Management</span></a></li>
        <li><a href="class-management.php"><i class="fas fa-chalkboard"></i> <span>Classes & Sections</span></a></li>
        <li><a href="exam-management.php"><i class="fas fa-pen-alt fa-fw me-2"></i> <span>Exam Management</span></a></li>
        <li><a href="attendance-management.php"><i class="fas fa-user-check fa-fw me-2"></i> <span>Attendance Management</span></a></li>
        <li><a href="content-management.php"><i class="fas fa-folder-open fa-fw me-2"></i> <span>Content Management</span></a></li>
        <li><a href="notice-board.php"><i class="fas fa-bullhorn fa-fw me-2"></i> <span>Notice Board</span></a></li>
        <li><a href="leads.php"><i class="fas fa-user"></i> <span>College Leads</span></a></li>
        <li><a href="school_leads.php"><i class="fas fa-school"></i> <span>School Leads</span></a></li>
        <li><a href="it_course_leads.php"><i class="fas fa-school"></i> <span>Register Leads</span></a></li>
        <li><a href="system-settings.php"><i class="fas fa-cogs fa-fw me-2"></i> <span>System Settings</span></a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> <span>Logout</span></a></li>
    </ul>
</div>

<script>
// This script will automatically set the 'active' class on the correct link
// based on the current page URL.
document.addEventListener("DOMContentLoaded", function() {
    const currentPath = window.location.pathname.split("/").pop();
    const sidebarLinks = document.querySelectorAll("#sidebar ul li a");

    sidebarLinks.forEach(link => {
        const linkPath = link.getAttribute("href");
        // Remove active class from all links first
        link.classList.remove("active");
        
        // Add active class if the link's href matches the current page
        if (linkPath === currentPath) {
            link.classList.add("active");
        }
    });

    // Special case for the dashboard if the URL is empty or just the root
    if (currentPath === '' || currentPath === 'index.php' || currentPath === 'dashboard.php') {
        const dashboardLink = document.querySelector('#sidebar a[href="dashboard.php"]');
        if (dashboardLink) {
             sidebarLinks.forEach(link => link.classList.remove("active"));
             dashboardLink.classList.add('active');
        }
    }
});
</script>
