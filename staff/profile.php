<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['staff_logged_in']) || !isset($_SESSION['staff_user_db_id'])) {
    header('Location: login.php');
    exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Staff';
$current_user_db_id = (int)$_SESSION['staff_user_db_id'];

$stmt = $pdo->prepare("SELECT user_id_string, full_name, email, status FROM users WHERE id = ?");
$stmt->execute([$current_user_db_id]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | <?= htmlspecialchars($staff_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <h1 class="h3 mb-4">My Profile</h1>
            <div class="card shadow-sm border-0" style="max-width:500px;">
                <div class="card-body">
                    <p><strong>Staff ID:</strong> <?= htmlspecialchars($staff['user_id_string'] ?? '') ?></p>
                    <p><strong>Full Name:</strong> <?= htmlspecialchars($staff['full_name'] ?? '') ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($staff['email'] ?? '') ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($staff['status'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
