<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403); 
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    exit;
}

$response = ['status' => 'error', 'message' => 'Invalid request.'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? null;

if ($method !== 'POST' || !$action) {
    http_response_code(400); 
    echo json_encode($response);
    exit;
}

try {
    switch ($action) {
        case 'add':
            $pdo->beginTransaction();
            
            $role_id = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);
            $full_name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
            $raw_email = trim($_POST['email'] ?? '');
            $email = $raw_email === '' ? null : filter_var($raw_email, FILTER_VALIDATE_EMAIL);
            $status = isset($_POST['status']) && in_array($_POST['status'], ['Active', 'Inactive']) ? $_POST['status'] : null;
            $password = $_POST['password'] ?? '';

            if (!$role_id || !$full_name || !$status || empty($password)) {
                throw new Exception("Required fields are missing.");
            }

            $stmt_role_name = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
            $stmt_role_name->execute([$role_id]);
            $role_name = $stmt_role_name->fetchColumn();

            $prefix_map = ['Student' => 'STU', 'Teacher' => 'TEA', 'Staff' => 'STF', 'Admin' => 'ADM'];
            $prefix = $prefix_map[$role_name] ?? 'USR';
            do {
                $user_id_string = $prefix . '-' . rand(10000, 99999);
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id_string = ?");
                $stmt_check->execute([$user_id_string]);
            } while ($stmt_check->fetchColumn() > 0);

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (user_id_string, full_name, email, password, role_id, status) VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql_user)->execute([$user_id_string, $full_name, $email, $hashed_password, $role_id, $status]);
            $new_user_id = $pdo->lastInsertId();

            if ($role_name === 'Student') {
                $gender = $_POST['gender'] ?? null;
                $father_name = $_POST['father_name'] ?? '';
                $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                $mother_name = $_POST['mother_name'] ?? '';
                $family_monthly_income = $_POST['family_monthly_income'] ?? null;
                $guardian_name = $_POST['guardian_name'] ?? '';
                $father_cnic = $_POST['father_cnic'] ?? '';
                $cell_no = $_POST['cell_no'] ?? '';
                $mother_cnic = $_POST['mother_cnic'] ?? '';
                $phone_no = $_POST['phone_no'] ?? '';
                $domicile_district = $_POST['domicile_district'] ?? '';
                $father_occupation = $_POST['father_occupation'] ?? '';
                $address = $_POST['address'] ?? '';
                $postal_address = $_POST['postal_address'] ?? '';
                $previous_school = $_POST['previous_school'] ?? '';
                $physical_deformity = $_POST['physical_deformity'] ?? '';
                $awards = $_POST['awards'] ?? '';
                $extracurricular_expertise = $_POST['extracurricular_expertise'] ?? '';
                $special_care_areas = $_POST['special_care_areas'] ?? '';
                
                // Re-added Class, Section, and Program binding
                $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
                $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
                $fee_category = !empty($_POST['fee_category']) ? trim($_POST['fee_category']) : null;
                
                $stmt_details = $pdo->prepare("INSERT INTO student_details 
                    (user_id, gender, father_name, date_of_birth, mother_name, family_monthly_income, 
                     guardian_name, father_cnic, cell_no, mother_cnic, phone_no, domicile_district, 
                     father_occupation, address, postal_address, previous_school, physical_deformity, 
                     awards, extracurricular_expertise, special_care_areas, class_id, section_id, fee_category) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt_details->execute([
                    $new_user_id, $gender, $father_name, $date_of_birth, $mother_name, $family_monthly_income,
                    $guardian_name, $father_cnic, $cell_no, $mother_cnic, $phone_no, $domicile_district,
                    $father_occupation, $address, $postal_address, $previous_school, $physical_deformity,
                    $awards, $extracurricular_expertise, $special_care_areas, $class_id, $section_id, $fee_category
                ]);
            } elseif ($role_name === 'Teacher') {
                $phone = $_POST['phone'] ?? ''; $cnic = $_POST['cnic'] ?? ''; $address = $_POST['address'] ?? '';
                $pdo->prepare("INSERT INTO teacher_details (user_id, phone, cnic, address) VALUES (?, ?, ?, ?)")->execute([$new_user_id, $phone, $cnic, $address]);
                
                if (!empty($_POST['assigned_classes'])) {
                    $stmt_assign = $pdo->prepare("INSERT INTO teacher_class_assignments (teacher_user_id, class_id) VALUES (?, ?)");
                    foreach ((array)$_POST['assigned_classes'] as $cid) { $stmt_assign->execute([$new_user_id, (int)$cid]); }
                }
            }

            $pdo->commit();
            $response = ['status' => 'success', 'message' => "User '{$full_name}' created successfully."];
            break;

        case 'update':
            $pdo->beginTransaction();

            $user_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $role_id = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);
            $full_name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
            $raw_email = trim($_POST['email'] ?? '');
            $email = $raw_email === '' ? null : filter_var($raw_email, FILTER_VALIDATE_EMAIL);
            $status = isset($_POST['status']) && in_array($_POST['status'], ['Active', 'Inactive']) ? $_POST['status'] : 'Inactive';
            $password = $_POST['password'] ?? '';
            
            if (!$user_id || !$role_id || !$full_name) { throw new Exception("Required fields are missing."); }

            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET full_name=?, email=?, password=?, role_id=?, status=? WHERE id=?")->execute([$full_name, $email, $hashed_password, $role_id, $status, $user_id]);
            } else {
                $pdo->prepare("UPDATE users SET full_name=?, email=?, role_id=?, status=? WHERE id=?")->execute([$full_name, $email, $role_id, $status, $user_id]);
            }

            $role_name = $pdo->query("SELECT role_name FROM roles WHERE id = $role_id")->fetchColumn();

            if ($role_name === 'Student') {
                $gender = $_POST['gender'] ?? null;
                $father_name = $_POST['father_name'] ?? '';
                $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                $mother_name = $_POST['mother_name'] ?? '';
                $family_monthly_income = $_POST['family_monthly_income'] ?? null;
                $guardian_name = $_POST['guardian_name'] ?? '';
                $father_cnic = $_POST['father_cnic'] ?? '';
                $cell_no = $_POST['cell_no'] ?? '';
                $mother_cnic = $_POST['mother_cnic'] ?? '';
                $phone_no = $_POST['phone_no'] ?? '';
                $domicile_district = $_POST['domicile_district'] ?? '';
                $father_occupation = $_POST['father_occupation'] ?? '';
                $address = $_POST['address'] ?? '';
                $postal_address = $_POST['postal_address'] ?? '';
                $previous_school = $_POST['previous_school'] ?? '';
                $physical_deformity = $_POST['physical_deformity'] ?? '';
                $awards = $_POST['awards'] ?? '';
                $extracurricular_expertise = $_POST['extracurricular_expertise'] ?? '';
                $special_care_areas = $_POST['special_care_areas'] ?? '';
                
                $class_id = !empty($_POST['class_id']) ? intval($_POST['class_id']) : null;
                $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
                $fee_category = !empty($_POST['fee_category']) ? trim($_POST['fee_category']) : null;
                
                $stmt_details = $pdo->prepare(
                    "INSERT INTO student_details 
                    (user_id, gender, father_name, date_of_birth, mother_name, family_monthly_income, 
                     guardian_name, father_cnic, cell_no, mother_cnic, phone_no, domicile_district, 
                     father_occupation, address, postal_address, previous_school, physical_deformity, 
                     awards, extracurricular_expertise, special_care_areas, class_id, section_id, fee_category) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE 
                     gender=VALUES(gender), father_name=VALUES(father_name), date_of_birth=VALUES(date_of_birth), 
                     mother_name=VALUES(mother_name), family_monthly_income=VALUES(family_monthly_income), 
                     guardian_name=VALUES(guardian_name), father_cnic=VALUES(father_cnic), cell_no=VALUES(cell_no), 
                     mother_cnic=VALUES(mother_cnic), phone_no=VALUES(phone_no), domicile_district=VALUES(domicile_district),
                     father_occupation=VALUES(father_occupation), address=VALUES(address), postal_address=VALUES(postal_address), 
                     previous_school=VALUES(previous_school), physical_deformity=VALUES(physical_deformity), 
                     awards=VALUES(awards), extracurricular_expertise=VALUES(extracurricular_expertise), 
                     special_care_areas=VALUES(special_care_areas), class_id=VALUES(class_id), 
                     section_id=VALUES(section_id), fee_category=VALUES(fee_category)"
                );
                
                $stmt_details->execute([
                    $user_id, $gender, $father_name, $date_of_birth, $mother_name, $family_monthly_income,
                    $guardian_name, $father_cnic, $cell_no, $mother_cnic, $phone_no, $domicile_district,
                    $father_occupation, $address, $postal_address, $previous_school, $physical_deformity,
                    $awards, $extracurricular_expertise, $special_care_areas, $class_id, $section_id, $fee_category
                ]);
            } elseif ($role_name === 'Teacher') {
                $phone = $_POST['phone'] ?? ''; $cnic = $_POST['cnic'] ?? ''; $address = $_POST['address'] ?? '';
                $pdo->prepare("INSERT INTO teacher_details (user_id, phone, cnic, address) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE phone=VALUES(phone), cnic=VALUES(cnic), address=VALUES(address)")->execute([$user_id, $phone, $cnic, $address]);
                $pdo->prepare("DELETE FROM teacher_class_assignments WHERE teacher_user_id = ?")->execute([$user_id]);
                
                if (!empty($_POST['assigned_classes'])) {
                    $stmt_assign = $pdo->prepare("INSERT INTO teacher_class_assignments (teacher_user_id, class_id) VALUES (?, ?)");
                    foreach ((array)$_POST['assigned_classes'] as $cid) { $stmt_assign->execute([$user_id, (int)$cid]); }
                }
            }
            
            $pdo->commit();
            $response = ['status' => 'success', 'message' => "User '{$full_name}' updated successfully."];
            break;
            
        case 'delete':
            $user_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$user_id) { throw new Exception("Invalid User ID."); }
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            $response = ['status' => 'success', 'message' => 'User deleted successfully.'];
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(400); 
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);
?>