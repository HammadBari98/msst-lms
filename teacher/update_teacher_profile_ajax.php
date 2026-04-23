<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['user_db_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication error. Please log in again.']);
    exit();
}

$user_db_id = $_SESSION['user_db_id'];
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    $pdo->beginTransaction();

    // 1. Get data for the 'users' table
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // 2. Get data for the 'teacher_details' table
    $phone = trim($_POST['phone'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        throw new Exception('Full name and email cannot be empty.');
    }

    // 3. Update the 'users' table
    $stmt_user = $pdo->prepare("UPDATE users SET full_name = :full_name, email = :email WHERE id = :id");
    $stmt_user->execute(['full_name' => $full_name, 'email' => $email, 'id' => $user_db_id]);

    // 4. Update (or Insert) the 'teacher_details' table ("UPSERT" logic)
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM teacher_details WHERE user_id = ?");
    $stmt_check->execute([$user_db_id]);
    
    if ($stmt_check->fetchColumn() > 0) {
        // Details exist, so UPDATE them
        $stmt_details = $pdo->prepare("UPDATE teacher_details SET phone = ?, cnic = ?, address = ? WHERE user_id = ?");
        $stmt_details->execute([$phone, $cnic, $address, $user_db_id]);
    } else {
        // No details exist, so INSERT them
        $stmt_details = $pdo->prepare("INSERT INTO teacher_details (user_id, phone, cnic, address) VALUES (?, ?, ?, ?)");
        $stmt_details->execute([$user_db_id, $phone, $cnic, $address]);
    }

    // 5. Handle file upload (logic is the same as student profile)
    $new_photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir(__DIR__ . '/../' . $upload_dir)) { mkdir(__DIR__ . '/../' . $upload_dir, 0775, true); }
        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $unique_filename = 'user_' . $user_db_id . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $unique_filename;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../' . $target_path)) {
            $stmt_photo = $pdo->prepare("UPDATE users SET profile_picture = :path WHERE id = :id");
            $stmt_photo->execute(['path' => $target_path, 'id' => $user_db_id]);
            $new_photo_path = '../' . $target_path;
        }
    }

    $pdo->commit();

    // 6. IMPORTANT: Update the session to keep the header name consistent
    $_SESSION['teacher_name'] = $full_name; 

    $response = ['success' => true, 'message' => 'Profile updated successfully!', 'new_photo_path' => $new_photo_path];

} catch (Exception $e) {
    $pdo->rollBack();
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);