<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = $_POST['id'] ?? 0;
    $type = $_POST['type'] ?? '';

    if (!$lead_id || !$type) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
        exit;
    }

    try {
        // --- 1. THE AUTO-PATCHER FOR STUDENT DETAILS ---
        $required_columns = [
            'father_name' => 'VARCHAR(255) DEFAULT NULL',
            'gender' => 'VARCHAR(50) DEFAULT NULL',
            'dob' => 'DATE DEFAULT NULL',
            'phone_number' => 'VARCHAR(50) DEFAULT NULL',
            'current_address' => 'TEXT DEFAULT NULL'
        ];

        foreach ($required_columns as $col => $col_type) {
            try {
                $pdo->query("SELECT {$col} FROM student_details LIMIT 1");
            } catch (PDOException $e) {
                $pdo->exec("ALTER TABLE student_details ADD COLUMN {$col} {$col_type}");
            }
        }
        // -----------------------------------------------

        $pdo->beginTransaction();

        // 2. Determine which table to pull from
        $table = ($type === 'school') ? 'school_admissions' : 'admissions';
        
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lead) {
            throw new Exception("Lead not found.");
        }

        // 3. Get the 'Student' Role ID
        $roleStmt = $pdo->query("SELECT id FROM roles WHERE role_name = 'Student' LIMIT 1");
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
        $role_id = $role ? $role['id'] : 3; // Fallback to 3 if missing

        // 4. Generate User Credentials (UPDATED FORMAT: STU-XXXXX)
        $random_digits = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        $user_id_string = "STU-{$random_digits}";
        
        // Ensure unique ID
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ?");
        $checkStmt->execute([$user_id_string]);
        while ($checkStmt->fetch()) {
            $random_digits = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            $user_id_string = "STU-{$random_digits}";
            $checkStmt->execute([$user_id_string]);
        }
        
        $email = !empty($lead['email']) ? $lead['email'] : strtolower($user_id_string) . '@msst.edu.pk';
        $password = password_hash('msst123', PASSWORD_DEFAULT); // Default password

        // Fix column mapping variations between the two forms
        $fullName = $lead['full_name'] ?? ($lead['name'] ?? 'Unknown');
        $phone = $lead['parent_contact'] ?? ($lead['mobile'] ?? 'N/A');

        // 5. Insert into `users` table
        $userStmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role_id, user_id_string, status) VALUES (?, ?, ?, ?, ?, 'Active')");
        $userStmt->execute([$fullName, $email, $password, $role_id, $user_id_string]);
        
        $new_user_id = $pdo->lastInsertId();

        // 6. Insert into `student_details` table (Columns are now guaranteed to exist)
        $detailsStmt = $pdo->prepare("INSERT INTO student_details (user_id, father_name, gender, dob, phone_number, current_address) VALUES (?, ?, ?, ?, ?, ?)");
        
        // Handle empty dob by passing NULL so the database doesn't crash on empty strings
        $formatted_dob = !empty($lead['dob']) ? $lead['dob'] : null;

        $detailsStmt->execute([
            $new_user_id,
            $lead['father_name'] ?? 'N/A',
            $lead['gender'] ?? 'N/A',
            $formatted_dob,
            $phone,
            $lead['current_address'] ?? 'N/A'
        ]);

        // 7. Mark Lead as Approved
        $updateStmt = $pdo->prepare("UPDATE {$table} SET admission_status = 'Approved' WHERE id = ?");
        $updateStmt->execute([$lead_id]);

        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Student enrolled successfully!', 
            'student_id' => $user_id_string
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Send the exact error back to the UI for debugging if needed
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>