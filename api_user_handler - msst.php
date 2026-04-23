<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

// Set content type to JSON for all responses
header('Content-Type: application/json');

// --- Security Check ---
// Ensure the user is logged in as an admin
if (!isset($_SESSION['admin_logged_in'])) {
    // Set HTTP response code for unauthorized access
    http_response_code(403); 
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You are not logged in.']);
    exit;
}

// Prepare default error response
$response = ['status' => 'error', 'message' => 'Invalid request.'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? null;

// --- Request Validation ---
// Only allow POST requests with a valid action
if ($method !== 'POST' || !$action) {
    // Set HTTP response code for a bad request
    http_response_code(400); 
    echo json_encode($response);
    exit;
}

try {
    // --- Main Logic Switch ---
    // Handle different actions based on the 'action' POST parameter
    switch ($action) {
        // --- ADD USER CASE ---
        case 'add':
            // Start a database transaction to ensure all or no queries succeed
            $pdo->beginTransaction();
            
            // --- Sanitize and Validate Inputs ---
            $role_id = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);
            $full_name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
            $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
            $status = in_array($_POST['status'], ['Active', 'Inactive']) ? $_POST['status'] : null;
            $password = $_POST['password'] ?? '';

            // Check for required fields
            if (!$role_id || !$full_name || !$email || !$status || empty($password)) {
                throw new Exception("A required field is missing or invalid for adding a user.");
            }

            // --- Get Role Name from Role ID ---
            $stmt_role_name = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
            $stmt_role_name->execute([$role_id]);
            $role_name = $stmt_role_name->fetchColumn();
            if (!$role_name) { throw new Exception("Invalid role selected."); }

            // --- Generate a Unique User ID String ---
            $prefix_map = ['Student' => 'STU', 'Teacher' => 'TEA', 'Staff' => 'STF', 'Admin' => 'ADM'];
            $prefix = $prefix_map[$role_name] ?? 'USR';
            do {
                $user_id_string = $prefix . '-' . rand(10000, 99999);
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id_string = ?");
                $stmt_check->execute([$user_id_string]);
            } while ($stmt_check->fetchColumn() > 0);

            // --- Insert into 'users' table ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (user_id_string, full_name, email, password, role_id, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$user_id_string, $full_name, $email, $hashed_password, $role_id, $status]);
            $new_user_id = $pdo->lastInsertId();

            // --- Insert Role-Specific Details ---
            if ($role_name === 'Student') {
                $class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
                $section_id = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
                if ($class_id && $section_id) {
                    $stmt_details = $pdo->prepare("INSERT INTO student_details (user_id, class_id, section_id) VALUES (?, ?, ?)");
                    $stmt_details->execute([$new_user_id, $class_id, $section_id]);
                }
            } elseif ($role_name === 'Teacher') {
                $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
                $cnic = filter_input(INPUT_POST, 'cnic', FILTER_SANITIZE_STRING);
                $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
                $stmt_details = $pdo->prepare("INSERT INTO teacher_details (user_id, phone, cnic, address) VALUES (?, ?, ?, ?)");
                $stmt_details->execute([$new_user_id, $phone, $cnic, $address]);

                // Handle class assignments for the new teacher
                $assigned_classes = $_POST['assigned_classes'] ?? [];
                if (!empty($assigned_classes)) {
                    $stmt_assign = $pdo->prepare("INSERT INTO teacher_class_assignments (teacher_user_id, class_id) VALUES (?, ?)");
                    foreach ($assigned_classes as $class_id) {
                        $stmt_assign->execute([$new_user_id, (int)$class_id]);
                    }
                }
            }

            // Commit the transaction if everything was successful
            $pdo->commit();
            $response = ['status' => 'success', 'message' => "User '{$full_name}' created successfully."];
            break;

        // --- UPDATE USER CASE ---
        case 'update':
            // Start a database transaction
            $pdo->beginTransaction();

            // --- Sanitize and Validate Inputs ---
            $user_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $role_id = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);
            $full_name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
            $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
            $status = in_array($_POST['status'], ['Active', 'Inactive']) ? $_POST['status'] : 'Inactive';
            $password = $_POST['password'] ?? '';
            
            // Check for required fields for an update
            if (!$user_id || !$role_id || !$full_name || !$email) {
                throw new Exception("A required field is missing or invalid for updating a user.");
            }

            // --- Update 'users' table ---
            $sql_user = "UPDATE users SET full_name = ?, email = ?, role_id = ?, status = ? WHERE id = ?";
            $params = [$full_name, $email, $role_id, $status, $user_id];
            
            // Update password only if a new one is provided
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_user = "UPDATE users SET full_name = ?, email = ?, password = ?, role_id = ?, status = ? WHERE id = ?";
                $params = [$full_name, $email, $hashed_password, $role_id, $status, $user_id];
            }

            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute($params);
            
            // --- Get Role Name from Role ID ---
            $stmt_role_name = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
            $stmt_role_name->execute([$role_id]);
            $role_name = $stmt_role_name->fetchColumn();
            if (!$role_name) { throw new Exception("Invalid role selected for update."); }

            // --- Update Role-Specific Details ---
            if ($role_name === 'Student') {
                $class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
                $section_id = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
                
                // Use INSERT...ON DUPLICATE KEY UPDATE (UPSERT)
                // This will create or update the student_details record as needed.
                // Assumes `user_id` in `student_details` is a UNIQUE key.
                $stmt_details = $pdo->prepare(
                    "INSERT INTO student_details (user_id, class_id, section_id) VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), section_id = VALUES(section_id)"
                );
                $stmt_details->execute([$user_id, $class_id, $section_id]);

            } elseif ($role_name === 'Teacher') {
                $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
                $cnic = filter_input(INPUT_POST, 'cnic', FILTER_SANITIZE_STRING);
                $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);

                // UPSERT teacher details
                // Assumes `user_id` in `teacher_details` is a UNIQUE key.
                $stmt_details = $pdo->prepare(
                    "INSERT INTO teacher_details (user_id, phone, cnic, address) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE phone = VALUES(phone), cnic = VALUES(cnic), address = VALUES(address)"
                );
                $stmt_details->execute([$user_id, $phone, $cnic, $address]);

                // --- Update Class Assignments ---
                // First, remove all existing assignments for this teacher
                $stmt_delete_assign = $pdo->prepare("DELETE FROM teacher_class_assignments WHERE teacher_user_id = ?");
                $stmt_delete_assign->execute([$user_id]);
                
                // Then, insert the new set of assignments
                $assigned_classes = $_POST['assigned_classes'] ?? [];
                if (!empty($assigned_classes)) {
                    $stmt_assign = $pdo->prepare("INSERT INTO teacher_class_assignments (teacher_user_id, class_id) VALUES (?, ?)");
                    foreach ($assigned_classes as $class_id) {
                        $stmt_assign->execute([$user_id, (int)$class_id]);
                    }
                }
            }
            
            // Commit the transaction
            $pdo->commit();
            $response = ['status' => 'success', 'message' => "User '{$full_name}' updated successfully."];
            break;
            
        // --- DELETE USER CASE ---
        case 'delete':
            $user_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$user_id) { throw new Exception("Invalid User ID for deletion."); }
            
            // Note: FOREIGN KEY constraints with ON DELETE CASCADE in your database schema
            // are the best way to handle cleaning up related details.
            // If not set, you would need to manually delete from child tables here.
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                $response = ['status' => 'success', 'message' => 'User deleted successfully.'];
            } else {
                throw new Exception("User could not be found or already deleted.");
            }
            break;
    }
} catch (Exception $e) {
    // If an error occurs, roll back any changes from the transaction
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Set HTTP error code and create an error response
    http_response_code(400); 
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// Output the final response as a JSON string
echo json_encode($response);
