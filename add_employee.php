<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once __DIR__ . '/config/db_config.php';

// Redirect to login if not logged in as admin
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Initialize variables
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $department = $_POST['department'] ?? '';
    $position = $_POST['position'] ?? ''; // Added position field
    $address = $_POST['address'] ?? '';
    $hire_date = date('Y-m-d'); // Current date for hiring

    // You might want to define prefixes for employee IDs based on department or a general prefix
    $prefix = 'EMP'; // General prefix for employees

    try {
        // Generate unique employee ID
        $stmt = $pdo->prepare("SELECT MAX(employee_id) AS last_id FROM employees WHERE employee_id LIKE :prefix_wildcard");
        $stmt->execute(['prefix_wildcard' => $prefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $last_id = $result['last_id'] ?? '';
        if ($last_id && strpos($last_id, $prefix) === 0) {
            $number = intval(substr($last_id, strlen($prefix))) + 1;
        } else {
            $number = 1;
        }
        $employee_id = $prefix . str_pad($number, 4, '0', STR_PAD_LEFT); // Using 4 digits for employee ID

        // Auto-generate password
        $raw_password = 'Emp' . rand(1000, 9999) . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 2);
        $hashed_password = password_hash($raw_password, PASSWORD_BCRYPT);

        // Insert employee into the 'employees' table
        $stmt = $pdo->prepare("INSERT INTO employees (employee_id, full_name, email, password, department, position, hire_date, phone, address)
                               VALUES (:employee_id, :full_name, :email, :password, :department, :position, :hire_date, :phone, :address)");
        $stmt->execute([
            'employee_id' => $employee_id,
            'full_name' => $full_name,
            'email' => $email,
            'password' => $hashed_password,
            'department' => $department,
            'position' => $position, // Added position
            'hire_date' => $hire_date,
            'phone' => $phone,
            'address' => $address
        ]);

        $success = "✅ Employee added successfully!<br>Employee ID: <strong>$employee_id</strong><br>Temp Password: <strong>$raw_password</strong>";
    } catch (PDOException $e) {
        // Check for duplicate email error
        if ($e->getCode() == '23000' && strpos($e->getMessage(), 'email') !== false) {
            $error = "❌ Error: An employee with this email already exists.";
        } else {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>LMS - Add Employee</title>
    
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
                                    <h4 class="card-title">Add New Employee</h4>
                                    
                                    <?php if (!empty($success)): ?>
                                        <div class="alert alert-success"><?php echo $success; ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($error)): ?>
                                        <div class="alert alert-danger"><?php echo $error; ?></div>
                                    <?php endif; ?>

                                    <form method="POST" action="" class="row g-3">
                                        <div class="col-md-6">
                                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" id="full_name" name="full_name" class="form-control" required>
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
                                                <option value="Administration">Administration</option>
                                                <option value="Academics">Academics</option>
                                                <option value="Human Resources">Human Resources</option>
                                                <option value="Finance">Finance</option>
                                                <option value="IT Support">IT Support</option>
                                                <option value="Marketing">Marketing</option>
                                                </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                                            <input type="text" id="position" name="position" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea id="address" name="address" class="form-control" rows="2"></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="hire_date" class="form-label">Hire Date</label>
                                            <input type="text" id="hire_date" name="hire_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary float-end mt-3">
                                                <i class="mdi mdi-account-plus me-2"></i> Add Employee
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