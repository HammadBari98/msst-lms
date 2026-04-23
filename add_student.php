<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once __DIR__ . '/config/db_config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Fetch Classes and Sections for the dropdowns
try {
    $classes_stmt = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC");
    $classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

    $sections_stmt = $pdo->query("SELECT * FROM sections ORDER BY section_name ASC");
    $sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: Could not fetch classes and sections. Please ensure tables exist. " . $e->getMessage());
}

// Initialize variables
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve all form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    // --- UPDATED: Trim the department name to prevent whitespace issues ---
    $department = trim($_POST['department'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $class_id = $_POST['class_id'] ?? '';
    $section_id = $_POST['section_id'] ?? '';
    $joining_year = date('Y');

    // Basic validation for class and section
    if (empty($name) || empty($email) || empty($department) || empty($class_id) || empty($section_id)) {
        $error = "❌ Please fill in all required fields, including Class and Section.";
    } else {
        // Department code prefixes
        $prefixes = [
            'F.Sc. Pre-Medical' => 'PM',
            'F.Sc. Pre-Engineering' => 'PE',
            'ICS (Intermediate in Computer Science)' => 'ICS',
            'General Science Group' => 'GS',
            'Humanities Group' => 'HU'
        ];
        $prefix = $prefixes[$department] ?? 'ST';

        try {
            // --- UPDATED: More reliable student ID generation logic ---
            $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE :prefix ORDER BY student_id DESC LIMIT 1");
            $stmt->execute(['prefix' => $prefix . '%']);
            $last_id_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $number = 1;
            if ($last_id_result) {
                // Extract the number part from the last ID and increment it
                $last_number = (int) substr($last_id_result['student_id'], strlen($prefix));
                $number = $last_number + 1;
            }
            $student_id = $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);

            // Auto-generate plain text password
            $raw_password = $prefix . rand(100, 999);

            // Insert student with class_id and section_id
            $sql = "INSERT INTO students (student_id, full_name, email, phone, department, address, joining_year, password, class_id, section_id)
                    VALUES (:student_id, :full_name, :email, :phone, :department, :address, :joining_year, :password, :class_id, :section_id)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'student_id' => $student_id,
                'full_name' => $name,
                'email' => $email,
                'phone' => $phone,
                'department' => $department,
                'address' => $address,
                'joining_year' => $joining_year,
                'password' => $raw_password,
                'class_id' => $class_id,
                'section_id' => $section_id
            ]);

            $success = "✅ Student added successfully!<br>Student ID: <strong>$student_id</strong><br>Password: <strong>$raw_password</strong>";
        } catch (PDOException $e) {
            // Check if it's the same duplicate entry error
            if ($e->getCode() == '23000') {
                 $error = "❌ Error: A student with ID '$student_id' might already exist. Please check your data. " . $e->getMessage();
            } else {
                 $error = "❌ Error: " . $e->getMessage();
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
    <title>LMS - Add Student</title>
    
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
                                    <h4 class="card-title">Add New Student</h4>
                                    
                                    <?php if (!empty($success)): ?>
                                        <div class="alert alert-success"><?php echo $success; ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($error)): ?>
                                        <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>

                                    <form method="POST" action="" class="row g-3">
                                        <div class="col-md-6">
                                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" id="name" name="name" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" id="email" name="email" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="text" id="phone" name="phone" class="form-control">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                            <select id="department" name="department" class="form-select" required>
                                                <option value="">Select Department</option>
                                                <option value="F.Sc. Pre-Medical">F.Sc. Pre-Medical</option>
                                                <option value="F.Sc. Pre-Engineering">F.Sc. Pre-Engineering</option>
                                                <option value="ICS (Intermediate in Computer Science)">ICS (Intermediate in Computer Science)</option>
                                                <option value="General Science Group">General Science Group</option>
                                                <option value="Humanities Group">Humanities Group</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                                            <select id="class_id" name="class_id" class="form-select" required>
                                                <option value="">Select Class</option>
                                                <?php foreach ($classes as $class): ?>
                                                    <option value="<?= htmlspecialchars($class['id']) ?>">
                                                        <?= htmlspecialchars($class['class_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="section_id" class="form-label">Section <span class="text-danger">*</span></label>
                                            <select id="section_id" name="section_id" class="form-select" required>
                                                <option value="">Select Section</option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?= htmlspecialchars($section['id']) ?>">
                                                        <?= htmlspecialchars($section['section_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-12">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea id="address" name="address" class="form-control" rows="2"></textarea>
                                        </div>

                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary float-end mt-3">
                                                <i class="mdi mdi-account-plus me-2"></i> Add Student
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