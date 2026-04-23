<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['user_db_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication error. Please log in again.']);
    exit();
}

$user_db_id = $_SESSION['user_db_id']; // The primary key (e.g., 1, 2, 3) of the user
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // Begin a transaction
    $pdo->beginTransaction();

    // --- Update Personal Details in the 'users' table ---
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($full_name) || empty($email)) {
        throw new Exception('Full name and email cannot be empty.');
    }

    $stmt_user = $pdo->prepare(
        "UPDATE users SET full_name = :full_name, email = :email WHERE id = :id"
    );
    $stmt_user->execute([
        'full_name' => $full_name,
        'email' => $email,
        'id' => $user_db_id
    ]);


    // --- Handle Profile Picture Upload ---
    $new_photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        if (!is_dir(__DIR__ . '/../' . $upload_dir)) {
            mkdir(__DIR__ . '/../' . $upload_dir, 0775, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $unique_filename = 'user_' . $user_db_id . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $unique_filename;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../' . $target_path)) {
            $stmt_photo = $pdo->prepare("UPDATE users SET profile_picture = :path WHERE id = :id");
            $stmt_photo->execute(['path' => $target_path, 'id' => $user_db_id]);
            $new_photo_path = '../' . $target_path;
        } else {
            throw new Exception('Failed to move uploaded file. Check directory permissions.');
        }
    }

    // If everything was successful, commit the transaction
    $pdo->commit();

    // ***** THE FIX IS HERE *****
    // After successfully saving to the database, update the session variable.
    $_SESSION['student_name'] = $full_name; 

    $response = [
        'success' => true,
        'message' => 'Profile updated successfully!',
        'new_photo_path' => $new_photo_path
    ];

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == '23000') {
        $response['message'] = 'Error: The email address you entered is already in use by another account.';
    } else {
        $response['message'] = 'Database Error: ' . $e->getMessage();
    }
} catch (Exception $e) {
    $pdo->rollBack();
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);