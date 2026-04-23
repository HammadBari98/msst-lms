<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit();
}

// Get the student ID from the query string
if (!isset($_GET['student_id'])) {
    header('Location: view_students.php');
    exit();
}

$student_id = $_GET['student_id'];

// Database connection
$host = 'localhost';
$dbname = 'lms_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the student record
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = :student_id");
    $stmt->execute(['student_id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header('Location: view_students.php');
        exit();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $department = $_POST['department'];
        $joining_year = $_POST['joining_year'];

        // Update the student record
        $stmt = $pdo->prepare("UPDATE students SET name = :name, email = :email, phone = :phone, department = :department, joining_year = :joining_year WHERE student_id = :student_id");
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'department' => $department,
            'joining_year' => $joining_year,
            'student_id' => $student_id
        ]);

        $success = "Student updated successfully!";
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>LMS - Edit Student</title>

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap @5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css ">

  <!-- Vendor CSS -->
  <link rel="stylesheet" href="assets/vendors/feather/feather.css">
  <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="assets/vendors/typicons/typicons.css">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="shortcut icon" href="assets/images/favicon.png" />
</head>

<body>
  <div class="container-scroller">
    <?php include 'header.php'; ?>
    <div class="container-fluid page-body-wrapper">
      <?php include 'sidebar.php'; ?>

      <!-- Main Content -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Edit Student</h4>
                  <?php if (isset($success)) echo "<p class='text-success'>$success</p>"; ?>
                  <?php if (isset($error)) echo "<p class='text-danger'>$error</p>"; ?>
                  <form method="POST" action="" class="forms-sample">
                    <div class="form-group">
                      <label for="name">Full Name:</label>
                      <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($student['name']) ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="email">Email:</label>
                      <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" required>
                    </div>
                    <div class="form-group">
                      <label for="phone">Phone Number:</label>
                      <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($student['phone']) ?>">
                    </div>
                    <div class="form-group">
                      <label for="department">Department:</label>
                      <select id="department" name="department" class="form-control" required>
                        <option value="F.Sc. Pre-Medical" <?= ($student['department'] == 'F.Sc. Pre-Medical') ? 'selected' : '' ?>>F.Sc. Pre-Medical</option>
                        <option value="F.Sc. Pre-Engineering" <?= ($student['department'] == 'F.Sc. Pre-Engineering') ? 'selected' : '' ?>>F.Sc. Pre-Engineering</option>
                        <option value="ICS (Intermediate in Computer Science)" <?= ($student['department'] == 'ICS (Intermediate in Computer Science)') ? 'selected' : '' ?>>ICS (Intermediate in Computer Science)</option>
                        <option value="General Science Group" <?= ($student['department'] == 'General Science Group') ? 'selected' : '' ?>>General Science Group</option>
                        <option value="Humanities Group" <?= ($student['department'] == 'Humanities Group') ? 'selected' : '' ?>>Humanities Group</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label for="joining_year">Joining Year:</label>
                      <input type="number" id="joining_year" name="joining_year" class="form-control" value="<?= htmlspecialchars($student['joining_year']) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary me-2">Update Student</button>
                    <a href="view_students.php" class="btn btn-secondary">Cancel</a>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <!-- Core JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Bootstrap Datepicker -->
  <script src="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>

  <!-- Custom JS -->
  <script src="assets/js/template.js"></script>

  <!-- Your dashboard script here -->
  <script>
    $(document).ready(function () {
      // Custom JS (sidebar toggle, menu behavior, chart init, etc.)
    });
  </script>

  </body>
</html>