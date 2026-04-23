 
 <style>
       :root {
            --primary: #906833;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light-bg: #f8f9fc;
            --dark-text: #343a40;
            --accent: #cc8636 !important;
        }

.btn-primary{
    background-color: #906833 !important;
    border: none;
}
.text-primary{
    color: var(--primary) !important;
}

.btn-outline-primary{
    border: 1px solid var(--accent);
    color:var(--primary)
}
.btn-outline-primary:hover{
    background-color: var(--accent);
    border: 1px solid var(--accent);
    color: white;
}
     
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
           
        }
        .brand img{
             height:50px;      
             background:white;
            border-radius: 50%;
        }

        .sidebar.collapsed .brand span {
            display: none;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar ul li a {
            color: white;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: background 0.2s;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar ul li a i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
        }

        .sidebar.collapsed ul li a span {
            display: none;
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

        .topbar .ms-auto {
            display: flex;
            align-items: center;
        }

        .topbar .ms-auto i {
            margin-right: 0.5rem;
        }

        .content-wrapper {
            flex: 1;
            padding: 1.5rem;
        }

        .card {
            border: none;
            border-left: 4px solid var(--primary);
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            margin-bottom: 1rem;
        }

        .footer {
            text-align: center;
            padding: 1rem;
            background: white;
            font-size: 0.9rem;
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
            #main-content.show {
                margin-left: 250px;
            }
        }
 </style>
 <div class="sidebar" id="sidebar">
        <div class="brand">
           
                <img src="../assets/images/msst-logo.png">
      
        </div>
       <style>
    /* Styles for the dropdown submenu */
    .sidebar-submenu {
        list-style: none;
        padding: 0;
        background-color: rgba(0, 0, 0, 0.15); /* Darker background for the dropdown */
    }
    .sidebar-submenu li a {
        /* Indent the dropdown links to look nested */
        padding-left: 3.4rem; 
        font-size: 0.9rem;
    }
    .sidebar-submenu li a:hover {
        background-color: rgba(0, 0, 0, 0.25);
    }
    
    /* Styles for the little dropdown arrow icon */
    .sidebar ul li a .dropdown-icon {
        margin-left: auto; /* Pushes the icon to the far right */
        transition: transform 0.3s ease;
    }
    .sidebar ul li a[aria-expanded="true"] .dropdown-icon {
        /* Rotates the arrow when the dropdown is open */
        transform: rotate(90deg);
    }
</style>

<ul>
    <li><a href="dashboard.php" class=""><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
    <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
    <li><a href="enrolled_subjects.php"><i class="fas fa-book"></i> <span>Courses</span></a></li>
    <li><a href="assignments.php"><i class="fas fa-clipboard-list"></i> <span>Assignments</span></a></li>
    <li><a href="grades.php"><i class="fas fa-chart-bar"></i> <span>Grades</span></a></li>
    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
    <li><a href="fee-detail.php"><i class="fas fa-receipt"></i> <span>Fee Detail</span></a></li>
    <li><a href="notice.php"><i class="fas fa-bullhorn"></i> <span>Notices</span></a></li>
    <li><a href="timetable.php"><i class="fas fa-calendar-alt"></i> <span>Timetable</span></a></li>
    <li><a href="support.php"><i class="fas fa-life-ring"></i> <span>Support</span></a></li>
  
    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
</ul>
        
         <!--<li><a href="grades.php"><i class="fas fa-chart-bar"></i> <span>Grades</span></a></li>-->
            <!--<li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>-->
            <!--<li><a href="fees.php"><i class="fas fa-money-bill-wave"></i> <span>Fees</span></a></li>-->
            <!-- <li><a href="class-schedule.php"><i class="fas fa-money-bill-wave"></i> <span>Class Schedule</span></a></li>-->
            <!--<li><a href="teacher_feedback.php"><i class="fas fa-money-bill-wave"></i> <span>Feedback</span></a></li>-->
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
