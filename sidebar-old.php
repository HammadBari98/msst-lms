  <div class="sidebar" id="sidebar">
        <div class="brand">
            <img src="assets/images/msst-logo.png" alt="School Logo">
        </div>
        
        <ul>
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="manage-users.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Manage Users</span></a></li>
            <li><a href="student_information.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Student Information</span></a></li>
            <li><a href="teacher-management.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Teacher Management</span></a></li>
            <li><a href="fee-management.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Fee Management</span></a></li>
            <li><a href="exam-management.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Exam Management</span></a></li>
            <li><a href="attendance-management.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Attendance Management</span></a></li>
            <li><a href="content-management.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Content Management</span></a></li>
            <li><a href="notice-board.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Notice Board</span></a></li>
            <li><a href="system-settings.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>System Settings</span></a></li>
            
            <!-- Student Details Dropdown -->
            <li>
                <a href="#studentDetails" data-bs-toggle="collapse" aria-expanded="false">
                    <i class="fas fa-user-graduate"></i> 
                    <span>Student Details</span>
                    <i class="fas fa-chevron-right dropdown-icon"></i>
                </a>
                <ul class="sidebar-submenu collapse" id="studentDetails">
                    <li><a href="add_student.php">Add Student</a></li>
                    <li><a href="view_students.php">Students List</a></li>
                </ul>
            </li>
            
            <!-- Employee Dropdown -->
            <li>
                <a href="#employee" data-bs-toggle="collapse" aria-expanded="false">
                    <i class="fas fa-user-tie"></i> 
                    <span>Employee</span>
                    <i class="fas fa-chevron-right dropdown-icon"></i>
                </a>
                <ul class="sidebar-submenu collapse" id="employee">
                    <li><a href="view_teacher.php">View Teachers</a></li>
                    <li><a href="add_teacher.php">Add Teacher</a></li>
                </ul>
            </li>
            
            <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
            <li><a href="fees.php"><i class="fas fa-money-bill-wave"></i> <span>Fee Management</span></a></li>
            <li><a href="notice.php"><i class="fas fa-bullhorn"></i> <span>Notices</span></a></li>
            <li><a href="timetable.php"><i class="fas fa-calendar-alt"></i> <span>Timetable</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>