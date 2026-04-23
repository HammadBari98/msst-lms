<?php
session_start();
// Make sure the path to your db_config.php is correct
// If login.php is inside a 'student' folder, this path should be correct.
require_once __DIR__ . '/../config/db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'] ?? ''; // This is the user_id_string e.g., 'STU-101'
    $password = $_POST['password'] ?? '';

    if (empty($student_id) || empty($password)) {
        $error = '❌ Student ID and Password are required!';
    } else {
        // 1. Select the user from the NEW 'users' table
        // 2. JOIN with 'roles' to make sure the user is a 'Student'
        $sql = "SELECT u.*, r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.user_id_string = :user_id_string";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id_string' => $student_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Verify the user exists, the role is 'Student', AND the password is correct using password_verify()
        if ($user && $user['role_name'] == 'Student' && password_verify($password, $user['password'])) {
            
            // Check if the account is Active
            if ($user['status'] == 'Inactive') {
                $error = '❌ Your account is inactive. Please contact the administration.';
            } else {
                // Login successful
                $_SESSION['student_logged_in'] = true;
                $_SESSION['user_db_id'] = $user['id']; // The actual database primary key
                $_SESSION['student_id'] = $user['user_id_string']; // The 'STU-101' ID
                $_SESSION['student_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role_name'];

                // Redirect to the student dashboard
                header('Location: dashboard.php');
                exit();
            }

        } else {
            // Login failed
            $error = '❌ Invalid Student ID or Password!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Student Login | LMS</title>
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
        }
        .btn-primary{
            background: #906833 !important;
            border: none;
        }
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            overflow: hidden;
        }
        .login-header {
            background: var(--primary);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .login-header h2 {
            margin: 0;
            font-weight: 600;
        }
        .login-body {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #6e707e;
            background: #fff;
            background-clip: padding-box;
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            box-sizing: border-box;
        }
        .form-control:focus {
            color: #6e707e;
            background: #fff;
            border-color: #bac8f3;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            color: #fff;
            background: var(--primary);
            border: none;
            border-radius: 0.35rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn:hover {
            background: #7a552a; /* Darker shade for hover */
            transform: translateY(-1px);
        }
        .btn:active {
            transform: translateY(0);
        }
        .alert {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: 0.35rem;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .brand-icon {
            margin-bottom: 1rem;
        }
        .brand-icon img {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            padding: 5px;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="brand-icon">
                <img src="../assets/images/msst-logo.png" alt="Logo">
            </div>
            <h2>Student Portal</h2>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="student_id">Student ID</label>
                    <input type="text" class="form-control" id="student_id" name="student_id" placeholder="e.g., STU-001" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
    </div>
</body>
</html>