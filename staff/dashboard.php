<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['staff_logged_in']) || !isset($_SESSION['user_db_id'])) {
    header('Location: login.php');
    exit();
}

$staff_name = $_SESSION['staff_name'] ?? 'Staff';
$current_user_db_id = (int)$_SESSION['user_db_id'];

$received_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shared_file_recipients WHERE recipient_type = 'user' AND recipient_ref_id = ?");
    $stmt->execute([$current_user_db_id]);
    $received_count = (int)$stmt->fetchColumn();
} catch (PDOException $e) { /* Table may not exist yet until shared-files.php has been visited once */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($staff_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <h1 class="h3 mb-4">Welcome, <?= htmlspecialchars($staff_name) ?></h1>
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="text-xs fw-bold text-uppercase text-muted mb-1">Files Received</div>
                            <div class="h5 mb-0 fw-bold"><?= $received_count ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
