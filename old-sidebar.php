<style>
    /* Custom styling for right-aligned menu arrows */
    .sidebar .nav .nav-item > .nav-link {
      position: relative;
      padding-right: 2.5rem; /* Make space for the arrow on the right */
    }
    
    .sidebar .nav .nav-item > .nav-link .menu-arrow {
      position: absolute;
      right: 0rem;
      top: 50%;
      transform: translateY(-50%);
      transition: transform 0.3s ease;
    }
    
    .sidebar .nav .nav-item > .nav-link[aria-expanded="true"] .menu-arrow {
      transform: translateY(-50%) rotate(90deg);
    }
    
    /* Adjust sub-menu items to maintain proper padding */
    .sidebar .nav .nav-item .nav-link {
      padding-right: 1.5rem;
    }
  </style>
         <!-- https://ashuftah-001-site1.ntempurl.com/ -->
  
  <nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="mdi mdi-grid-large menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>

        <!-- Inventory -->
      
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#inventory" aria-expanded="false" aria-controls="inventory">
                <i class="mdi mdi-dolly menu-icon"></i>
                <span class="menu-title">Inventory</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="inventory">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"><a class="nav-link" href="product.php">Product</a></li>
                    <li class="nav-item"><a class="nav-link" href="category.php">Category</a></li>
                    <li class="nav-item"><a class="nav-link" href="store.php">Store</a></li>
                    <li class="nav-item"><a class="nav-link" href="supplier.php">Supplier</a></li>
                    <li class="nav-item"><a class="nav-link" href="unit.php">Unit</a></li>
                    <li class="nav-item"><a class="nav-link" href="purchase.php">Purchase</a></li>
                    <li class="nav-item"><a class="nav-link" href="issue.php">Issue</a></li>
                </ul>
            </div>
        </li>

        <!-- Frontend -->
      
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#frontend" aria-expanded="false" aria-controls="frontend">
                <i class="mdi mdi-web menu-icon"></i>
                <span class="menu-title">Frontend</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="frontend">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"><a class="nav-link" href="setting.php">Setting</a></li>
                    <li class="nav-item"><a class="nav-link" href="menu.php">Menu</a></li>
                    <!--<li class="nav-item"><a class="nav-link" href="section/index.php">Page Section</a></li>-->
                    <!--<li class="nav-item"><a class="nav-link" href="content/index.php">Manage Page</a></li>-->
                    <!--<li class="nav-item"><a class="nav-link" href="slider.php">Slider</a></li>-->
                    <!--<li class="nav-item"><a class="nav-link" href="features.php">Features</a></li>-->
                    <!--<li class="nav-item"><a class="nav-link" href="testimonial.php">Testimonial</a></li>-->
                    <!--<li class="nav-item"><a class="nav-link" href="services.php">Service</a></li>-->
                    <!--<li class="nav-item"><a class="nav-link" href="faq/index".php>Faq</a></li>-->
                    <!--<li class="nav-item"><a class="nav-link" href="gallery/category.php">Gallery Category</a></li>-->
                    <!--<li class="nav-item"><a class="nav-link" href="gallery/index.php">Gallery</a></li>-->
                </ul>
            </div>
        </li>

        <!-- Reception -->
      
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#reception" aria-expanded="false" aria-controls="reception">
                <i class="mdi mdi-account-group menu-icon"></i>
                <span class="menu-title">Reception</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="reception">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"><a class="nav-link" href="admission-enquiry.php">Admission Enquiry</a></li>
                    <li class="nav-item"><a class="nav-link" href="postal-record.php">Postal Record</a></li>
                    <li class="nav-item"><a class="nav-link" href="call_log.php">Call Log</a></li>
                    <li class="nav-item"><a class="nav-link" href="visitor_log.php">Visitor Log</a></li>
                    <li class="nav-item"><a class="nav-link" href="complaint.php">Complaint</a></li>
                    <!--<li class="nav-item"><a class="nav-link" href="config-reception.php">Config Reception</a></li>-->
                </ul>
            </div>
        </li>

        <!-- Admission -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#admission" aria-expanded="false" aria-controls="admission">-->
        <!--        <i class="mdi mdi-pencil-box menu-icon"></i>-->
        <!--        <span class="menu-title">Admission</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="admission">-->
        <!--        <ul class="nav flex-column sub-menu">-->
                   
        <!--            <li class="nav-item"><a class="nav-link" href="student/add">Create Admission</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="online_admission/index">Online Admission</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="student/csv_import">Multiple Import</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="student/category">Category</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Student Details -->
      
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#studentDetails" aria-expanded="false" aria-controls="studentDetails">
                <i class="mdi mdi-school menu-icon"></i>
                <span class="menu-title">Student Details</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="studentDetails">
                <ul class="nav flex-column sub-menu">
                     <li class="nav-item"><a class="nav-link" href="leads.php">Leads List</a></li>
                     <li class="nav-item"><a class="nav-link" href="add_student.php">Add Student</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_students.php">Students List</a></li>
                    <!--<li class="nav-item"><a class="nav-link" href="student/disable_authentication">Login Deactivate</a></li>-->
                </ul>
            </div>
        </li>
        
        
       

        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#employee" aria-expanded="false" aria-controls="employee">
                <i class="mdi mdi-account-tie menu-icon"></i>
                <span class="menu-title">Employee</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="employee">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item"><a class="nav-link" href="view_teacher.php">View Teachers </a></li>
                    <li class="nav-item"><a class="nav-link" href="add_teacher.php">Add Teacher</a></li>
                </ul>
            </div>
        </li>
        
        
        
        <!-- Parents -->

      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#parents" aria-expanded="false" aria-controls="parents">-->
        <!--        <i class="mdi mdi-account-multiple menu-icon"></i>-->
        <!--        <span class="menu-title">Parents</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="parents">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link" href="parents/view">Parents List</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="parents/add">Add Parent</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="parents/disable_authentication">Login Deactivate</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Employee -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#employee" aria-expanded="false" aria-controls="employee">-->
        <!--        <i class="mdi mdi-account-tie menu-icon"></i>-->
        <!--        <span class="menu-title">Employee</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="employee">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link" href="employee/view">Employee List</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="employee/department">Add Department</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="employee/designation">Add Designation</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="employee/add">Add Employee</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="employee/disable_authentication">Login Deactivate</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Card Management -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#cardManagement" aria-expanded="false" aria-controls="cardManagement">-->
        <!--        <i class="mdi mdi-card-account-details menu-icon"></i>-->
        <!--        <span class="menu-title">Card Management</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="cardManagement">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link" href="card_manage/id_card_templete">Id Card Templete</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="card_manage/generate_student_idcard">Student Id Card</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="card_manage/generate_employee_idcard">Employee Id Card</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="card_manage/admit_card_templete">Admit Card Templete</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="card_manage/generate_student_admitcard">Generate Admit Card</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Certificate -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#certificate" aria-expanded="false" aria-controls="certificate">-->
        <!--        <i class="mdi mdi-certificate menu-icon"></i>-->
        <!--        <span class="menu-title">Certificate</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="certificate">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link" href="certificate">Certificate Templete</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="certificate/generate_student">Generate Student</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="certificate/generate_employee">Generate Employee</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Human Resource -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#hr" aria-expanded="false" aria-controls="hr">-->
        <!--        <i class="mdi mdi-human-greeting menu-icon"></i>-->
        <!--        <span class="menu-title">Human Resource</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="hr">-->
        <!--        <ul class="nav flex-column sub-menu">-->
                    <!-- Payroll -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#payroll" aria-expanded="false" aria-controls="payroll">-->
        <!--                    <i class="mdi mdi-credit-card menu-icon"></i>-->
        <!--                    <span>Payroll</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="payroll">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link" href="payroll/salary_template">Salary Template</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link" href="payroll/salary_assign">Salary Assign</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link" href="payroll">Salary Payment</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Advance Salary -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#advanceSalary" aria-expanded="false" aria-controls="advanceSalary">-->
        <!--                    <i class="mdi mdi-cash-fast menu-icon"></i>-->
        <!--                    <span>Advance Salary</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="advanceSalary">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link" href="advance_salary/request">My Application</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link" href="advance_salary">Manage Application</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Leave -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#leave" aria-expanded="false" aria-controls="leave">-->
        <!--                    <i class="mdi mdi-beach menu-icon"></i>-->
        <!--                    <span>Leave</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="leave">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link" href="leave/category">Category</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link" href="leave/request">My Application</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link" href="leave">Manage Application</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
        <!--            <li class="nav-item"><a class="nav-link" href="award">Award</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Academic -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#academic" aria-expanded="false" aria-controls="academic">-->
        <!--        <i class="mdi mdi-book-open-variant menu-icon"></i>-->
        <!--        <span class="menu-title">Academic</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="academic">-->
        <!--        <ul class="nav flex-column sub-menu">-->
                    <!-- Class & Section -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#classSection" aria-expanded="false" aria-controls="classSection">-->
        <!--                    <i class="mdi mdi-google-classroom menu-icon"></i>-->
        <!--                    <span>Class & Section</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="classSection">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link" href="classes">Control Classes</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link" href="classes/teacher_allocation">Assign Class Teacher</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Subject -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#subject" aria-expanded="false" aria-controls="subject">-->
        <!--                    <i class="mdi mdi-book-open-page-variant menu-icon"></i>-->
        <!--                    <span>Subject</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="subject">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link" href="subject/index">Subject</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link" href="subject/class_assign">Class Assign</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
        <!--            <li class="nav-item"><a class="nav-link" href="timetable/viewclass">Class Schedule</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="timetable/teacherview">Teacher Schedule</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="student_promotion">Promotion</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Live Class Rooms -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#liveClass" aria-expanded="false" aria-controls="liveClass">-->
        <!--        <i class="mdi mdi-video menu-icon"></i>-->
        <!--        <span class="menu-title">Live Class Rooms</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="liveClass">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link" href="live_class">Live Class Rooms</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="live_class/reports">Live Class Reports</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Attachments Book -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#attachments" aria-expanded="false" aria-controls="attachments">-->
        <!--        <i class="mdi mdi-cloud-upload menu-icon"></i>-->
        <!--        <span class="menu-title">Attachments Book</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="attachments">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link" href="attachments">Upload Content</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link" href="attachments/type">Attachment Type</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#homework" aria-expanded="false" aria-controls="homework">-->
        <!--        <i class="mdi mdi-notebook menu-icon"></i>-->
        <!--        <span class="menu-title">Homework</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="homework">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link">Homework</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Evaluation Report</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Exam Master -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#examMaster" aria-expanded="false" aria-controls="examMaster">-->
        <!--        <i class="mdi mdi-book-open-page-variant menu-icon"></i>-->
        <!--        <span class="menu-title">Exam Master</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="examMaster">-->
        <!--        <ul class="nav flex-column sub-menu">-->
                    <!-- Exam -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#exam" aria-expanded="false" aria-controls="exam">-->
        <!--                    <i class="mdi mdi-flask menu-icon"></i>-->
        <!--                    <span>Exam</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="exam">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Exam Term</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Exam Hall</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Distribution</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Exam Setup</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Exam Schedule -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#examSchedule" aria-expanded="false" aria-controls="examSchedule">-->
        <!--                    <i class="mdi mdi-calendar-clock menu-icon"></i>-->
        <!--                    <span>Exam Schedule</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="examSchedule">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Schedule</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Add Schedule</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Marks -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#marks" aria-expanded="false" aria-controls="marks">-->
        <!--                    <i class="mdi mdi-marker menu-icon"></i>-->
        <!--                    <span>Marks</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="marks">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Mark Entries</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Grades Range</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Online Exam -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#onlineExam" aria-expanded="false" aria-controls="onlineExam">-->
        <!--        <i class="mdi mdi-monitor menu-icon"></i>-->
        <!--        <span class="menu-title">Online Exam</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="onlineExam">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link">Online Exam</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Question Bank</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Question Group</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Position Generate</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Exam Result</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Supervision -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#supervision" aria-expanded="false" aria-controls="supervision">-->
        <!--        <i class="mdi mdi-eye menu-icon"></i>-->
        <!--        <span class="menu-title">Supervision</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="supervision">-->
        <!--        <ul class="nav flex-column sub-menu">-->
                    <!-- Hostel -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#hostel" aria-expanded="false" aria-controls="hostel">-->
        <!--                    <i class="mdi mdi-home-modern menu-icon"></i>-->
        <!--                    <span>Hostel</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="hostel">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Hostel Master</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Hostel Room</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Category</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Allocation Report</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Transport -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#transport" aria-expanded="false" aria-controls="transport">-->
        <!--                    <i class="mdi mdi-bus menu-icon"></i>-->
        <!--                    <span>Transport</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="transport">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Route Master</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Vehicle Master</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Stoppage</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Assign Vehicle</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Allocation Report</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Attendance -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#attendance" aria-expanded="false" aria-controls="attendance">-->
        <!--        <i class="mdi mdi-calendar-check menu-icon"></i>-->
        <!--        <span class="menu-title">Attendance</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="attendance">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link">Student</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Employee</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Exam</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Library -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#library" aria-expanded="false" aria-controls="library">-->
        <!--        <i class="mdi mdi-library menu-icon"></i>-->
        <!--        <span class="menu-title">Library</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="library">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link">Books</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Books Category</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">My Issued Book</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Book Issue/return</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Events -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#events" aria-expanded="false" aria-controls="events">-->
        <!--        <i class="mdi mdi-calendar-star menu-icon"></i>-->
        <!--        <span class="menu-title">Events</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="events">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link">Event Type</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Events</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Bulk SMS And Email -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#smsEmail" aria-expanded="false" aria-controls="smsEmail">-->
        <!--        <i class="mdi mdi-email-send menu-icon"></i>-->
        <!--        <span class="menu-title">Bulk Sms And Email</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="smsEmail">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link">Send Sms / Email</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Sms / Email Report</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Sms Template</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Email Template</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Student Birthday Wishes</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Staff Birthday Wishes</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Student Accounting -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#studentAccounting" aria-expanded="false" aria-controls="studentAccounting">-->
        <!--        <i class="mdi mdi-calculator menu-icon"></i>-->
        <!--        <span class="menu-title">Student Accounting</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="studentAccounting">-->
        <!--        <ul class="nav flex-column sub-menu">-->
                    <!-- Offline Payments -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#offlinePayments" aria-expanded="false" aria-controls="offlinePayments">-->
        <!--                    <i class="mdi mdi-cash-multiple menu-icon"></i>-->
        <!--                    <span>Offline Payments</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="offlinePayments">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Payments Type</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
        <!--            <li class="nav-item"><a class="nav-link">Fees Type</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Fees Group</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Fine Setup</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Fees Allocation</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Fees Pay / Invoice</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Due Fees Invoice</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Fees Reminder</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Office Accounting -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#officeAccounting" aria-expanded="false" aria-controls="officeAccounting">-->
        <!--        <i class="mdi mdi-credit-card-settings menu-icon"></i>-->
        <!--        <span class="menu-title">Office Accounting</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="officeAccounting">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link">Account</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">New Deposit</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">New Expense</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">All Transactions</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Voucher Head</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Message -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link">-->
        <!--        <i class="mdi mdi-email-open menu-icon"></i>-->
        <!--        <span class="menu-title">Message</span>-->
        <!--    </a>-->
        <!--</li>-->

        <!-- Reports -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#reports" aria-expanded="false" aria-controls="reports">-->
        <!--        <i class="mdi mdi-chart-pie menu-icon"></i>-->
        <!--        <span class="menu-title">Reports</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="reports">-->
        <!--        <ul class="nav flex-column sub-menu">-->
                    <!-- Student Reports -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#studentReports" aria-expanded="false" aria-controls="studentReports">-->
        <!--                    <i class="mdi mdi-printer menu-icon"></i>-->
        <!--                    <span>Student Reports</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="studentReports">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Login Credential</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Admission Report</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Class & Section Report</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Fees Reports -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#feesReports" aria-expanded="false" aria-controls="feesReports">-->
        <!--                    <i class="mdi mdi-printer menu-icon"></i>-->
        <!--                    <span>Fees Reports</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="feesReports">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Fees Report</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Receipts Report</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Due Fees Report</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Fine Report</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Financial Reports -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#financialReports" aria-expanded="false" aria-controls="financialReports">-->
        <!--                    <i class="mdi mdi-printer menu-icon"></i>-->
        <!--                    <span>Financial Reports</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="financialReports">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Account Statement</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Income Repots</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Expense Repots</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Transitions Reports</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Balance Sheet</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Income Vs Expense</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Attendance Reports -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#attendanceReports" aria-expanded="false" aria-controls="attendanceReports">-->
        <!--                    <i class="mdi mdi-printer menu-icon"></i>-->
        <!--                    <span>Attendance Reports</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="attendanceReports">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Student Reports</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Student Daily Reports</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Employee Reports</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Exam Reports</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Human Resource -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#hrReports" aria-expanded="false" aria-controls="hrReports">-->
        <!--                    <i class="mdi mdi-printer menu-icon"></i>-->
        <!--                    <span>Human Resource</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="hrReports">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Payroll Summary</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Leave Reports</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Examination -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#examinationReports" aria-expanded="false" aria-controls="examinationReports">-->
        <!--                    <i class="mdi mdi-printer menu-icon"></i>-->
        <!--                    <span>Examination</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="examinationReports">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Report Card</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Tabulation Sheet</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Progress Reports</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
                    
                    <!-- Inventory -->
        <!--            <li class="nav-item">-->
        <!--                <a class="nav-link" data-bs-toggle="collapse" href="#inventoryReports" aria-expanded="false" aria-controls="inventoryReports">-->
        <!--                    <i class="mdi mdi-printer menu-icon"></i>-->
        <!--                    <span>Inventory</span>-->
        <!--                    <i class="menu-arrow"></i>-->
        <!--                </a>-->
        <!--                <div class="collapse" id="inventoryReports">-->
        <!--                    <ul class="nav flex-column sub-menu">-->
        <!--                        <li class="nav-item"><a class="nav-link">Stock Report</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Purchase Report</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Sales Report</a></li>-->
        <!--                        <li class="nav-item"><a class="nav-link">Issues Report</a></li>-->
        <!--                    </ul>-->
        <!--                </div>-->
        <!--            </li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->

        <!-- Settings -->
      
        <!--<li class="nav-item">-->
        <!--    <a class="nav-link" data-bs-toggle="collapse" href="#settings" aria-expanded="false" aria-controls="settings">-->
        <!--        <i class="mdi mdi-settings menu-icon"></i>-->
        <!--        <span class="menu-title">Settings</span>-->
        <!--        <i class="menu-arrow"></i>-->
        <!--    </a>-->
        <!--    <div class="collapse" id="settings">-->
        <!--        <ul class="nav flex-column sub-menu">-->
        <!--            <li class="nav-item"><a class="nav-link">School Settings</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Translations</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Cron Job</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">System Student Field</a></li>-->
        <!--            <li class="nav-item"><a class="nav-link">Custom Field</a></li>-->
        <!--        </ul>-->
        <!--    </div>-->
        <!--</li>-->
   
    </ul>
</nav>