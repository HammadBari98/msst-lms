<?php
session_start();
require_once '../config/db_config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staff_id_string = $_POST['staff_id'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($staff_id_string) || empty($password)) {
        $error = '❌ Staff ID and Password are required!';
    } else {
        $sql = "SELECT u.*, r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.user_id_string = :user_id_string";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id_string' => $staff_id_string]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['role_name'] == 'Staff' && password_verify($password, $user['password'])) {
            if ($user['status'] == 'Inactive') {
                $error = '❌ Your account is inactive. Please contact the administration.';
            } else {
                $_SESSION['staff_logged_in'] = true;
                $_SESSION['user_db_id'] = $user['id'];
                $_SESSION['staff_id'] = $user['user_id_string'];
                $_SESSION['staff_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role_name'];

                header('Location: dashboard.php');
                exit();
            }
        } else {
            $error = '❌ Invalid Staff ID or Password!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Staff Login | LMS</title>
    <style>
        :root { --primary: #5a3e85; --secondary: #6c757d; --light-bg: #f8f9fc; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%); height: 100vh; display: flex; justify-content: center; align-items: center; }
        .login-container { width: 100%; max-width: 400px; background: white; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); overflow: hidden; }
        .login-header { background: var(--primary); color: white; padding: 1.5rem; text-align: center; }
        .login-header h2 { margin: 0; }
        .login-body { padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; font-size: 1rem; border: 1px solid #d1d3e2; border-radius: 0.35rem; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 0.75rem; font-weight: 600; color: #fff; background: var(--primary); border: none; border-radius: 0.35rem; cursor: pointer; }
        .alert-danger { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 0.75rem 1.25rem; margin-bottom: 1.5rem; border-radius: 0.35rem; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header"><h2>Staff Portal</h2></div>
        <div class="login-body">
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="staff_id">Staff ID</label>
                    <input type="text" class="form-control" id="staff_id" name="staff_id" placeholder="e.g., STF-12345" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
        </div>
    </div>
</body>
</html>
