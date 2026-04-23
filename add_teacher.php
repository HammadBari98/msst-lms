<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
// MODIFIED: Correct path to config file (removed ../)
require_once __DIR__ . '/config/db_config.php'; 

// MODIFIED: Using the EXACT session check from your add_student.php
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php'); 
    exit();
}

// Initialize variables
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- GATHER ALL FORM DATA ---
    $teacher_name = trim($_POST['teacher_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $date_of_joining = $_POST['date_of_joining'] ?? date('Y-m-d');
    
    $profile_picture_path = null; 

    // Basic Validation
    if (empty($teacher_name) || empty($email) || empty($cnic) || empty($designation) || empty($department)) {
        $error = "❌ Please fill all required fields marked with *";
    } else {

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            // This path works because the uploads folder is inside the /teacher/ directory
            $upload_dir = 'teacher/uploads/avatars/'; 
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $unique_filename = 'teacher_' . preg_replace('/[^a-zA-Z0-9]/', '', $cnic) . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                $profile_picture_path = $target_path;
            } else {
                 $error = "❌ Failed to move uploaded profile picture. Check folder permissions.";
            }
        }
        
        if (empty($error)) {
            try {
                // Generate a UNIQUE 5-digit random ID
                do {
                    $teacher_id = rand(10000, 99999);
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM teacher WHERE teacher_id = :id");
                    $stmt_check->execute(['id' => $teacher_id]);
                    $id_exists = $stmt_check->fetchColumn();
                } while ($id_exists);

                // Auto-generate password and HASH it securely
                $raw_password = 'Tch' . rand(1000, 9999);
                $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

                // Final INSERT Query for the 'teacher' table
                $sql = "INSERT INTO teacher (teacher_id, teacher_name, email, password, cnic, phone, address, gender, designation, department, date_of_joining, profile_picture_path)
                        VALUES (:teacher_id, :teacher_name, :email, :password, :cnic, :phone, :address, :gender, :designation, :department, :date_of_joining, :profile_picture_path)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'teacher_id' => $teacher_id,
                    'teacher_name' => $teacher_name,
                    'email' => $email,
                    'password' => $hashed_password,
                    'cnic' => $cnic,
                    'phone' => $phone,
                    'address' => $address,
                    'gender' => $gender,
                    'designation' => $designation,
                    'department' => $department,
                    'date_of_joining' => $date_of_joining,
                    'profile_picture_path' => $profile_picture_path
                ]);

                $success = "✅ Teacher added successfully!<br>Teacher ID: <strong>$teacher_id</strong><br>Temporary Password: <strong>$raw_password</strong>";

            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                     $error = "❌ Error: A teacher with this Email or CNIC already exists.";
                } else {
                    $error = "❌ Database Error: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>LMS - Add Teacher</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png">
</head>
<body>
    <div class="container-scroller">
        <?php include 'header.php'; ?>
        <div class="container-fluid page-body-wrapper">
            <?php include 'sidebar.php'; ?>
            
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="row">
                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Add New Teacher</h4>
                                    
                                    <?php if (!empty($success)): ?>
                                        <div class="alert alert-success"><?php echo $success; ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($error)): ?>
                                        <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>

                                    <form method="POST" action="" class="row g-3" enctype="multipart/form-data">
                                        <div class="col-md-6">
                                            <label for="teacher_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" id="teacher_name" name="teacher_name" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" id="email" name="email" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cnic" class="form-label">CNIC <span class="text-danger">*</span></label>
                                            <input type="text" id="cnic" name="cnic" class="form-control" placeholder="e.g., 42201-1234567-1" required>
                                        </div>
                                         <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                            <input type="text" id="phone" name="phone" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                            <select id="gender" name="gender" class="form-select" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                         <div class="col-md-6">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea id="address" name="address" class="form-control" rows="1"></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                            <select id="department" name="department" class="form-select" required>
                                                 <option value="">Select Department</option>
                                                 <option value="Computer Science">Computer Science</option>
                                                 <option value="Business Administration">Business Administration</option>
                                                 <option value="Electrical Engineering">Electrical Engineering</option>
                                                 <option value="Mathematics">Mathematics</option>
                                                 <option value="Physics">Physics</option>
                                                 <option value="Social Sciences">Social Sciences</option>
                                             </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="designation" class="form-label">Designation <span class="text-danger">*</span></label>
                                            <select id="designation" name="designation" class="form-select" required>
                                                <option value="">Select Designation</option>
                                                <option value="Lecturer">Lecturer</option>
                                                <option value="Assistant Professor">Assistant Professor</option>
                                                <option value="Associate Professor">Associate Professor</option>
                                                <option value="Professor">Professor</option>
                                                <option value="Lab Instructor">Lab Instructor</option>
                                                <option value="Visiting Faculty">Visiting Faculty</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="date_of_joining" class="form-label">Date of Joining</label>
                                            <input type="text" id="date_of_joining" name="date_of_joining" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="profile_picture" class="form-label">Profile Picture</label>
                                            <input type="file" id="profile_picture" name="profile_picture" class="form-control">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary float-end mt-3">
                                                <i class="mdi mdi-account-plus me-2"></i> Add Teacher
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php include 'footer.php'; ?>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>