 <style>
     
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
            <i class="fas fa-graduation-cap"></i>
            <span>LMS</span>
        </div>
        <ul>
            <li><a href="dashboard.php" class=""><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            <li><a href="courses.php"><i class="fas fa-book"></i> <span>Courses</span></a></li>
            <li><a href="grades.php"><i class="fas fa-chart-bar"></i> <span>Grades</span></a></li>
            <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
            <li><a href="fees.php"><i class="fas fa-money-bill-wave"></i> <span>Fees</span></a></li>
             <li><a href="class-schedule.php"><i class="fas fa-money-bill-wave"></i> <span>Class Schedule</span></a></li>
            <li><a href="teacher_feedback.php"><i class="fas fa-money-bill-wave"></i> <span>Feedback</span></a></li>
           
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>
    
