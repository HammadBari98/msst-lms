<?php
session_start();
require_once '../config/db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // This is the Teacher ID from the form, e.g., 'TEA-12345'
    $teacher_id_string = $_POST['teacher_id'] ?? ''; 
    $password = $_POST['password'] ?? '';

    if (empty($teacher_id_string) || empty($password)) {
        $error = '❌ Teacher ID and Password are required!';
    } else {
        // 1. Select the user from the NEW 'users' table
        // 2. JOIN with 'roles' to get the role name
        $sql = "SELECT u.*, r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.user_id_string = :user_id_string";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id_string' => $teacher_id_string]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Verify the user exists, is a 'Teacher', AND the password is correct
        if ($user && $user['role_name'] == 'Teacher' && password_verify($password, $user['password'])) {
            
            // Check if the account is Active
            if ($user['status'] == 'Inactive') {
                $error = '❌ Your account is inactive. Please contact the administration.';
            } else {
                // Login successful - Set the correct session variables
                $_SESSION['teacher_logged_in'] = true;
                $_SESSION['teacher_user_db_id'] = $user['id']; // The actual database primary key (e.g., 5)
                $_SESSION['teacher_id'] = $user['user_id_string']; // The 'TEA-12345' ID
                $_SESSION['teacher_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role_name'];

                // Redirect to the teacher dashboard
                header('Location: dashboard.php');
                exit();
            }

        } else {
            // Login failed
            $error = '❌ Invalid Teacher ID or Password!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Teacher Login | LMS</title>
    <style>
        :root {
            --primary: #906833;
            --secondary: #6c757d;
            --light-bg: #f8f9fc;
        }
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
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
        .login-header h2 { margin: 0; }
        .login-body { padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
            box-sizing: border-box;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
            color: #fff;
            background: var(--primary);
            border: none;
            border-radius: 0.35rem;
            cursor: pointer;
        }
        .alert-danger {
            color: #721c24;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 0.35rem;
        }
        .brand-icon img {
            background-color: white;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            padding: 5px;
            margin-bottom: 1rem;
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
            <h2>Teacher Portal</h2>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="teacher_id">Teacher ID</label>
                    <input type="text" class="form-control" id="teacher_id" name="teacher_id" placeholder="e.g., TEA-12345" required>
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


<!-- 

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal | Coming Soon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .coming-soon-card {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 40px 30px;
            text-align: center;
            max-width: 450px;
            width: 90%;
            border-top: 5px solid #1cc88a; /* Teacher Green */
        }
        .logo { width: 80px; margin-bottom: 20px; }
        .icon-container {
            font-size: 3.5rem; color: #1cc88a; margin-bottom: 20px;
            display: inline-block; animation: float 3s ease-in-out infinite;
        }
        @keyframes float { 
            0% { transform: translateY(0px); } 
            50% { transform: translateY(-10px); } 
            100% { transform: translateY(0px); } 
        }
    </style>
</head>
<body>
    <div class="coming-soon-card">
        <img src="../assets/images/msst-logo.png" alt="MSST Logo" class="logo" onerror="this.src='https://ui-avatars.com/api/?name=MSST&background=1cc88a&color=fff&rounded=true&size=80'">
        
    
        
        <h2 class="fw-bold text-dark mb-1">Teacher Portal</h2>
        <h5 class="text-success mb-3">Coming Soon</h5>
        
        <p class="text-muted mb-4 small">
            We are building a brand new dashboard for our faculty. The development team is actively working on it to provide you with the best tools. Thank you for your patience!
        </p>
        
        <div class="d-flex justify-content-center gap-2">
            <a href="#" class="btn btn-success rounded-pill px-4 shadow-sm" onclick="location.reload();"><i class="fas fa-sync-alt me-2"></i>Refresh</a>
        </div>
    </div>
</body>
</html> -->