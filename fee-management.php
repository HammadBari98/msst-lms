<?php
session_start();
// Ensure this path matches your MSST database configuration
require_once __DIR__ . '/config/db_config.php';

// Check for session messages
$action_msg = '';
if (isset($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}

// Check if the admin is logged in.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}


$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure column exists
try {
    $pdo->exec("ALTER TABLE student_details ADD COLUMN IF NOT EXISTS one_time_fees_cleared TINYINT(1) DEFAULT 0");
} catch (PDOException $e) { /* Column already exists */ }

// --- Database Functions ---
function getAllStudents($pdo) {
    try {
        $stmt = $pdo->query("
           SELECT 
                u.id, u.user_id_string, u.full_name, u.email, u.status,
                sd.gender, sd.father_name, sd.date_of_birth, sd.mother_name, 
                sd.family_monthly_income, sd.guardian_name, sd.father_cnic, 
                sd.cell_no, sd.mother_cnic, sd.phone_no, sd.domicile_district, 
                sd.father_occupation, sd.address, sd.postal_address, 
                sd.previous_school, sd.physical_deformity, sd.awards, 
                sd.extracurricular_expertise, sd.special_care_areas, 
                sd.class_id, sd.section_id, sd.fee_category,
                c.class_name, s.section_name,
                (sd.one_time_fees_cleared = 0 OR sd.one_time_fees_cleared IS NULL) AS is_first_month
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN student_details sd ON u.id = sd.user_id
            LEFT JOIN classes c ON sd.class_id = c.id
            LEFT JOIN sections s ON sd.section_id = s.id
            WHERE r.role_name = 'Student'
            AND u.status = 'Active'
            ORDER BY c.class_name, s.section_name, u.full_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getFeeCategories($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS fee_categories (
            id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50) NOT NULL,
            tuition_amount DECIMAL(10,2) NOT NULL DEFAULT 0, description VARCHAR(255), is_active TINYINT(1) DEFAULT 1
        )");
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM fee_categories");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO fee_categories (name, tuition_amount, description) VALUES 
                ('Class 9', 4000, 'Matric Science'),
                ('Class 10', 4500, 'Matric Science'),
                ('First Year (Pre Eng.)', 6000, 'Intermediate'),
                ('First Year (Pre Med.)', 6000, 'Intermediate'),
                ('First Year (ICS)', 5500, 'Intermediate'),
                ('First Year (Arts)', 5000, 'Intermediate'),
                ('Second Year (Pre Eng.)', 6000, 'Intermediate'),
                ('Second Year (Pre Med.)', 6000, 'Intermediate'),
                ('Second Year (ICS)', 5500, 'Intermediate'),
                ('Second Year (Arts)', 5000, 'Intermediate')");
        }
        return $pdo->query("SELECT * FROM fee_categories WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

function getFeeComponents($pdo, $active_only = true) {
    try {
        $sql = "SELECT * FROM fee_components";
        if ($active_only) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY type, name";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

function ensureFeeSlipComponentsTable($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS fee_slip_components (
                id INT PRIMARY KEY AUTO_INCREMENT, slip_no VARCHAR(50) NOT NULL, component_id INT NOT NULL,
                component_name VARCHAR(100) NOT NULL, amount DECIMAL(10,2) NOT NULL, component_type VARCHAR(50),
                INDEX idx_slip_no (slip_no), FOREIGN KEY (slip_no) REFERENCES fee_slips(slip_no) ON DELETE CASCADE
            )
        ");
        return true;
    } catch (Exception $e) { return false; }
}

function getAllClasses($pdo) {
    try {
        return $pdo->query("SELECT id, class_name FROM classes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

function getGeneratedFeeSlips($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS fee_slips (
                id INT PRIMARY KEY AUTO_INCREMENT, slip_no VARCHAR(50) UNIQUE NOT NULL, student_id INT NOT NULL,
                month_year DATE NOT NULL, amount DECIMAL(10,2) NOT NULL, due_date DATE NOT NULL,
                status ENUM('Pending', 'Paid', 'Overdue') DEFAULT 'Pending', fee_category VARCHAR(50) DEFAULT 'Class 9',
                paid_on DATE NULL, payment_method VARCHAR(50), transaction_id VARCHAR(100), generated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
       $stmt = $pdo->prepare("
            SELECT fs.*, u.full_name, u.user_id_string, c.class_name, s.section_name, sd.fee_category, sd.father_name, sd.cell_no,
                   (SELECT component_name FROM fee_slip_components fsc WHERE fsc.slip_no = fs.slip_no AND fsc.component_name LIKE 'Base Monthly Tuition%' LIMIT 1) as base_tuition_name
            FROM fee_slips fs
            LEFT JOIN users u ON fs.student_id = u.id
            LEFT JOIN student_details sd ON u.id = sd.user_id
            LEFT JOIN classes c ON sd.class_id = c.id
            LEFT JOIN sections s ON sd.section_id = s.id
            ORDER BY fs.month_year DESC, fs.generated_date DESC LIMIT 1500
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

function hasPreviousSlips($pdo, $student_id) {
    try {
        $stmt = $pdo->prepare("SELECT one_time_fees_cleared FROM student_details WHERE user_id = ?");
        $stmt->execute([$student_id]);
        return $stmt->fetchColumn() == 1;
    } catch (Exception $e) { return false; }
}

function generateSlipNumber($class_id, $student_id, $target_month = null) {
    $ym = $target_month ? str_replace('-', '', $target_month) : date('Ym');
    return "FS-" . $ym . "-" . $class_id . "-" . $student_id . "-" . rand(1000, 9999);
}

// --- Init Data ---
$current_month_year = date('F Y');
$all_students = getAllStudents($pdo);
$fee_categories = getFeeCategories($pdo);
$fee_components = getFeeComponents($pdo, false);
$active_components = array_filter($fee_components, function($comp) { return $comp['is_active']; });
$available_classes = getAllClasses($pdo);
$fee_records = getGeneratedFeeSlips($pdo);

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. GENERATE FEE SLIP FOR SINGLE STUDENT
    if (isset($_POST['generate_slip_for_student'])) {
        $student_id = $_POST['student_id'];
        $selected_student = null;
        foreach ($all_students as $student) {
            if ($student['id'] == $student_id) { $selected_student = $student; break; }
        }
        
        if ($selected_student) {
            try {
                $target_month_check = $_POST['target_month'] ?? date('Y-m');
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM fee_slips WHERE student_id = ? AND DATE_FORMAT(month_year, '%Y-%m') = ?");
                $stmt_check->execute([$student_id, $target_month_check]);
                
                if ($stmt_check->fetchColumn() > 0) {
                    $_SESSION['action_msg'] = '<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i>Fee slip already exists for ' . htmlspecialchars($selected_student['full_name']) . ' in this month.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    header('Location: ' . $_SERVER['PHP_SELF']); exit;
                } else {
                    ensureFeeSlipComponentsTable($pdo);
                    $is_first_month = !hasPreviousSlips($pdo, $student_id);
                    
                    $comp_stmt = $pdo->prepare("SELECT * FROM fee_components WHERE is_active = 1 AND is_optional = 0" . ($is_first_month ? "" : " AND type = 'recurring'") . " ORDER BY type, name");
                    $comp_stmt->execute();
                    $components = $comp_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($_POST['optional_components'])) {
                        $opt_ids = array_map('intval', $_POST['optional_components']);
                        $placeholders = implode(',', array_fill(0, count($opt_ids), '?'));
                        $opt_stmt = $pdo->prepare("SELECT * FROM fee_components WHERE id IN ($placeholders) AND is_active = 1 AND is_optional = 1");
                        $opt_stmt->execute($opt_ids);
                        $components = array_merge($components, $opt_stmt->fetchAll(PDO::FETCH_ASSOC));
                    }
                    
                    $tuition_amount = 4000;
                    $student_cat = $selected_student['fee_category'] ?? 'Class 9';
                    foreach($fee_categories as $cat) {
                        if(strcasecmp($cat['name'], $student_cat) == 0) { $tuition_amount = $cat['tuition_amount']; break; }
                    }
                    
                    $target_month = $_POST['target_month'] ?? date('Y-m');
                    $target_date = $target_month . '-01'; 
                    $installments = isset($_POST['installments']) ? max(1, intval($_POST['installments'])) : 1;
                    $installment_days = isset($_POST['installment_days']) ? max(1, intval($_POST['installment_days'])) : 15;
                    $installment_charge = isset($_POST['installment_charge']) ? floatval($_POST['installment_charge']) : 0;

                    $total_fee = $tuition_amount;
                    $component_data = [];

                    $stmt_find_base = $pdo->prepare("SELECT id FROM fee_components WHERE name = 'Base Monthly Tuition' LIMIT 1");
                    $stmt_find_base->execute();
                    $base_tuition_comp_id = $stmt_find_base->fetchColumn();

                    if (!$base_tuition_comp_id) {
                        $pdo->exec("INSERT INTO fee_components (name, amount, type, description, is_optional, is_active) VALUES ('Base Monthly Tuition', 0, 'recurring', 'System managed base fee', 0, 0)");
                        $base_tuition_comp_id = $pdo->lastInsertId();
                    }

                    $component_data[] = ['id' => $base_tuition_comp_id, 'name' => 'Base Monthly Tuition', 'amount' => $tuition_amount, 'type' => 'recurring'];

                    foreach ($components as $comp) {
                        $compNameLower = strtolower(trim($comp['name']));
                        if ($comp['id'] == $base_tuition_comp_id || strpos($compNameLower, 'tuition') !== false || strpos($compNameLower, 'monthly') !== false) continue; 
                        
                        $total_fee += (float)$comp['amount'];
                        $component_data[] = ['id' => $comp['id'], 'name' => $comp['name'], 'amount' => (float)$comp['amount'], 'type' => $comp['type']];
                    }
                    
                    $installment_amount = $total_fee / $installments;
                    $base_slip_no = generateSlipNumber($selected_student['class_id'], $selected_student['id'], $target_month_check);
                    
                    $pdo->beginTransaction();
                    $charge_comp_id = null;
                    if ($installments > 1 && $installment_charge > 0) {
                        $stmt_find = $pdo->prepare("SELECT id FROM fee_components WHERE name = 'Installment Charges' LIMIT 1");
                        $stmt_find->execute();
                        $charge_comp_id = $stmt_find->fetchColumn();
                        if (!$charge_comp_id) {
                            $pdo->exec("INSERT INTO fee_components (name, amount, type, description, is_optional, is_active) VALUES ('Installment Charges', 0, 'recurring', 'Processing fee for installments', 0, 1)");
                            $charge_comp_id = $pdo->lastInsertId();
                        }
                    }
                    
                    for ($i = 0; $i < $installments; $i++) {
                        $slip_no = $installments > 1 ? $base_slip_no . "-" . ($i + 1) : $base_slip_no;
                        $days_to_add = $i * $installment_days;
                        $slip_total = $installment_amount + ($installments > 1 ? $installment_charge : 0);
                        
                        $pdo->prepare("INSERT INTO fee_slips (slip_no, student_id, month_year, amount, due_date, status, fee_category, generated_date) VALUES (?, ?, ?, ?, DATE_ADD(?, INTERVAL ? DAY), 'Pending', ?, NOW())")->execute([$slip_no, $student_id, $target_date, $slip_total, $target_date, $days_to_add + 10, $student_cat]);
                        
                        $comp_insert = $pdo->prepare("INSERT INTO fee_slip_components (slip_no, component_id, component_name, amount, component_type) VALUES (?, ?, ?, ?, ?)");
                        foreach ($component_data as $comp) {
                            $comp_insert->execute([$slip_no, $comp['id'], $comp['name'], $comp['amount'] / $installments, $comp['type']]);
                        }
                        if ($installments > 1 && $installment_charge > 0 && $charge_comp_id) {
                            $comp_insert->execute([$slip_no, $charge_comp_id, 'Installment Charges', $installment_charge, 'recurring']);
                        }
                    }
                    
                    $pdo->commit();
                    $pdo->prepare("UPDATE student_details SET one_time_fees_cleared = 1 WHERE user_id = ?")->execute([$student_id]);
                    $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Fee slip generated for ' . htmlspecialchars($selected_student['full_name']) . '!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    header('Location: ' . $_SERVER['PHP_SELF']); exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle me-2"></i>Error: ' . htmlspecialchars($e->getMessage()) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                header('Location: ' . $_SERVER['PHP_SELF']); exit;
            }
        }
    }
    
    // 2. GENERATE FEE SLIPS FOR ENTIRE CLASS
    if (isset($_POST['generate_slips_for_class'])) {
        $class_id = $_POST['class_id'];
        $generated_count = 0; $skipped_count = 0; $errors = [];
        ensureFeeSlipComponentsTable($pdo);
        $class_students = array_filter($all_students, function($student) use ($class_id) { return $student['class_id'] == $class_id; });
        $target_month = $_POST['target_month'] ?? date('Y-m');
        $target_date = $target_month . '-01'; 
        
        $stmt_find_base = $pdo->prepare("SELECT id FROM fee_components WHERE name = 'Base Monthly Tuition' LIMIT 1");
        $stmt_find_base->execute();
        $base_tuition_comp_id = $stmt_find_base->fetchColumn();
        if (!$base_tuition_comp_id) {
            $pdo->exec("INSERT INTO fee_components (name, amount, type, description, is_optional, is_active) VALUES ('Base Monthly Tuition', 0, 'recurring', 'System managed base fee', 0, 0)");
            $base_tuition_comp_id = $pdo->lastInsertId();
        }

        foreach ($class_students as $student) {
            try {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM fee_slips WHERE student_id = ? AND DATE_FORMAT(month_year, '%Y-%m') = ?");
                $stmt_check->execute([$student['id'], $target_month]);
                if ($stmt_check->fetchColumn() > 0) { $skipped_count++; continue; }
                
                $is_first_month = !hasPreviousSlips($pdo, $student['id']);
                $comp_stmt = $pdo->prepare("SELECT * FROM fee_components WHERE is_active = 1 AND is_optional = 0" . ($is_first_month ? "" : " AND type = 'recurring'") . " ORDER BY type, name");
                $comp_stmt->execute();
                $components = $comp_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $tuition_amount = 4000; 
                $student_cat = $student['fee_category'] ?? 'Class 9';
                foreach($fee_categories as $cat) {
                    if(strcasecmp($cat['name'], $student_cat) == 0) { $tuition_amount = $cat['tuition_amount']; break; }
                }
                
                $installments = isset($_POST['installments']) ? max(1, intval($_POST['installments'])) : 1;
                $installment_days = isset($_POST['installment_days']) ? max(1, intval($_POST['installment_days'])) : 15;
                $installment_charge = isset($_POST['installment_charge']) ? floatval($_POST['installment_charge']) : 0;

                $total_fee = $tuition_amount;
                $component_data = [['id' => $base_tuition_comp_id, 'name' => 'Base Monthly Tuition', 'amount' => $tuition_amount, 'type' => 'recurring']];

                foreach ($components as $comp) {
                    $compNameLower = strtolower(trim($comp['name']));
                    if ($comp['id'] == $base_tuition_comp_id || strpos($compNameLower, 'tuition') !== false || strpos($compNameLower, 'monthly') !== false) continue; 
                    
                    $total_fee += (float)$comp['amount'];
                    $component_data[] = ['id' => $comp['id'], 'name' => $comp['name'], 'amount' => (float)$comp['amount'], 'type' => $comp['type']];
                }

                $installment_amount = $total_fee / $installments;
                $base_slip_no = generateSlipNumber($class_id, $student['id'], $target_month);
                
                $pdo->beginTransaction();
                $charge_comp_id = null;
                if ($installments > 1 && $installment_charge > 0) {
                    $stmt_find = $pdo->prepare("SELECT id FROM fee_components WHERE name = 'Installment Charges' LIMIT 1");
                    $stmt_find->execute();
                    $charge_comp_id = $stmt_find->fetchColumn() ?: $pdo->exec("INSERT INTO fee_components (name, amount, type, description, is_optional, is_active) VALUES ('Installment Charges', 0, 'recurring', 'Processing fee for installments', 0, 1)");
                }

                for ($i = 0; $i < $installments; $i++) {
                    $slip_no = $installments > 1 ? $base_slip_no . "-" . ($i + 1) : $base_slip_no;
                    $slip_total = $installment_amount + ($installments > 1 ? $installment_charge : 0);
                    $pdo->prepare("INSERT INTO fee_slips (slip_no, student_id, month_year, amount, due_date, status, fee_category, generated_date) VALUES (?, ?, ?, ?, DATE_ADD(?, INTERVAL ? DAY), 'Pending', ?, NOW())")->execute([$slip_no, $student['id'], $target_date, $slip_total, $target_date, ($i * $installment_days) + 10, $student_cat]);
                    
                    $comp_insert = $pdo->prepare("INSERT INTO fee_slip_components (slip_no, component_id, component_name, amount, component_type) VALUES (?, ?, ?, ?, ?)");
                    foreach ($component_data as $comp) { $comp_insert->execute([$slip_no, $comp['id'], $comp['name'], $comp['amount'] / $installments, $comp['type']]); }
                    if ($installments > 1 && $installment_charge > 0 && $charge_comp_id) { $comp_insert->execute([$slip_no, $charge_comp_id, 'Installment Charges', $installment_charge, 'recurring']); }
                }
                $pdo->commit();
                $pdo->prepare("UPDATE student_details SET one_time_fees_cleared = 1 WHERE user_id = ?")->execute([$student['id']]);
                $generated_count++;
            } catch (Exception $e) { if($pdo->inTransaction()) $pdo->rollBack(); $errors[] = "{$student['full_name']}: " . $e->getMessage(); }
        }
        
        $message = $generated_count > 0 ? '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Generated ' . $generated_count . ' slips' . ($skipped_count > 0 ? " (skipped $skipped_count)" : '') . '!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : ($skipped_count > 0 ? '<div class="alert alert-info alert-dismissible fade show"><i class="fas fa-info-circle me-2"></i>No new slips generated. All ' . $skipped_count . ' students already have slips.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>' : '');
        if (!empty($errors)) $message .= '<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i>Errors: ' . implode('<br>', array_slice($errors, 0, 5)) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        $_SESSION['action_msg'] = $message;
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
    
    // 3. MANAGE FEE COMPONENTS
    if (isset($_POST['fee_component_action'])) {
        $action = $_POST['fee_component_action'];
        try {
            if ($action == 'add') {
                $pdo->prepare("INSERT INTO fee_components (name, amount, type, description, is_optional, is_active) VALUES (?, ?, ?, ?, ?, ?)")->execute([$_POST['comp_name'], $_POST['comp_amount'], $_POST['comp_type'] ?? 'recurring', $_POST['comp_desc'] ?? '', isset($_POST['comp_optional']) ? 1 : 0, isset($_POST['comp_active']) ? 1 : 0]);
                $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Component added!</div>';
            } elseif ($action == 'edit') {
                $pdo->prepare("UPDATE fee_components SET name=?, amount=?, type=?, description=?, is_optional=?, is_active=? WHERE id=?")->execute([$_POST['comp_name'], $_POST['comp_amount'], $_POST['comp_type'], $_POST['comp_desc'], isset($_POST['comp_optional']) ? 1 : 0, isset($_POST['comp_active']) ? 1 : 0, $_POST['comp_id']]);
                $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Component updated!</div>';
            } elseif ($action == 'delete') {
                $pdo->prepare("DELETE FROM fee_components WHERE id=?")->execute([$_POST['comp_id']]);
                $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Component deleted!</div>';
            }
        } catch (Exception $e) { $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>'; }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
    
    // 4. RECORD PAYMENT
    if (isset($_POST['record_payment_action'])) {
        try {
            $transaction_id = $_POST['transaction_id'] ?? 'TXN-' . time() . '-' . rand(100, 999);
            $pdo->prepare("UPDATE fee_slips SET status = 'Paid', paid_on = ?, payment_method = ?, transaction_id = ? WHERE slip_no = ?")->execute([$_POST['payment_date'], $_POST['payment_method'], $transaction_id, $_POST['slip_no']]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Payment recorded!</div>';
        } catch (Exception $e) { $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>'; }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
    
    // 5. DELETE FEE SLIP
    if (isset($_POST['delete_slip_action'])) {
        try {
            $pdo->prepare("DELETE FROM fee_slips WHERE slip_no = ?")->execute([$_POST['slip_no']]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Slip deleted!</div>';
        } catch (Exception $e) { $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>'; }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    // 6. EDIT CATEGORY
    if (isset($_POST['edit_category_action'])) {
        try {
            $pdo->prepare("UPDATE fee_categories SET tuition_amount = ? WHERE id = ?")->execute([floatval($_POST['tuition_amount']), intval($_POST['cat_id'])]);
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Program fee updated!</div>';
        } catch (Exception $e) { $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>'; }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    // 7. APPLY SCHOLARSHIP
    if (isset($_POST['apply_scholarship_action'])) {
        try {
            $pdo->beginTransaction();
            $slip_no = $_POST['slip_no']; $percent = max(0, min(100, floatval($_POST['scholarship_percent'])));
            $stmt = $pdo->prepare("SELECT sd.fee_category FROM fee_slips fs LEFT JOIN student_details sd ON fs.student_id = sd.user_id WHERE fs.slip_no = ?");
            $stmt->execute([$slip_no]); 
            $fee_category = $stmt->fetchColumn() ?: 'Class 9';

            $stmt_cat = $pdo->prepare("SELECT tuition_amount FROM fee_categories WHERE name = ?");
            $stmt_cat->execute([$fee_category]); $full_base_tuition = (float)$stmt_cat->fetchColumn();

            $parts = explode('-', $slip_no); $installments = 1;
            if (count($parts) >= 6) {
                $stmt_inst = $pdo->prepare("SELECT COUNT(*) FROM fee_slips WHERE slip_no LIKE ?");
                $stmt_inst->execute([implode('-', array_slice($parts, 0, 5)) . '%']);
                $installments = max(1, (int)$stmt_inst->fetchColumn());
            }

            $discounted_tuition = ($full_base_tuition / $installments) * ((100 - $percent) / 100);
            $component_name = $percent > 0 ? "Base Monthly Tuition ($percent% Scholarship)" : "Base Monthly Tuition";

            $pdo->prepare("UPDATE fee_slip_components SET amount = ?, component_name = ? WHERE slip_no = ? AND component_name LIKE 'Base Monthly Tuition%'")->execute([$discounted_tuition, $component_name, $slip_no]);
            $pdo->prepare("UPDATE fee_slips fs SET amount = (SELECT COALESCE(SUM(amount), 0) FROM fee_slip_components WHERE slip_no = fs.slip_no) WHERE slip_no = ?")->execute([$slip_no]);
            
            $pdo->commit();
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i>Scholarship applied!</div>';
        } catch (Exception $e) { $pdo->rollBack(); $_SESSION['action_msg'] = '<div class="alert alert-danger alert-dismissible"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>'; }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
}

$active_components_json = json_encode(array_values($active_components));
$fee_categories_json = json_encode(array_values($fee_categories));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Management | MSST Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .program-badge { font-size: 0.75rem; padding: 4px 8px; background-color: #0d6efd; color: white; border-radius: 6px; display: inline-block; font-weight: 500;}
        .student-list-item { cursor: pointer; transition: background-color 0.2s; }
        .student-list-item:hover { background-color: #f8f9fa; }
        .student-list-item.selected { background-color: #e7f3ff; border-left: 4px solid #0d6efd !important; }
        
        /* 3-Part MSST Grid Voucher Styles (Screen View) */
        .voucher-page-content { background: white; padding: 15px; display: flex; justify-content: space-between; gap: 15px; }
        .voucher-page-content > div { flex: 1; width: 32%; }
        .voucher-instance { background-color: #fff; page-break-inside: avoid; border: none; padding: 0; height: 100%; }
        .voucher-container { border: 1px dashed #666; padding: 10px; background-color: #fff; height: 100%; }
        .header-section { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
        .logo-container { display: flex; align-items: center; }
        .logo { width: 70px; height: auto; margin-right: 10px; border: 1px solid #ddd; padding: 3px; }
        .school-info { font-size: 0.7rem; line-height: 1.2; }
        .school-info strong { font-size: 0.8rem; display: block; margin-bottom: 2px; }
        .affiliated-copy-type { text-align: right; font-size: 0.75rem; }
        .affiliated-copy-type span { display: block; border: 1px solid #000; padding: 3px 7px; margin-bottom: 4px; min-width: 120px; text-align: center; }
        .copy-type-label { font-weight: bold; background-color: #f0f0f0; }
        .bank-details { text-align: center; font-size: 0.8rem; font-weight: bold; margin-bottom: 8px; padding: 4px; border: 1px solid #000; }
        
        .student-info-table, .fees-table { width: 100%; margin-bottom: 8px; font-size: 0.65rem; border-collapse: collapse; }
        .student-info-table th, .student-info-table td, .fees-table th, .fees-table td { border: 1px solid #000; padding: 3px 3px; vertical-align: middle; text-align: left; }
        .student-info-table th, .fees-table th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .student-info-table td.student-name-value { font-weight: bold; text-align: left !important; }
        .student-info-table td.value-cell, .fees-table td { text-align: center !important; font-weight: bold; }
        .due-date-cell { color: #fff !important; background-color: #dc3545 !important; font-weight: bold; text-align: center !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        .total-payable-section { display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.7rem; gap:5px; }
        .total-payable-section div { border: 1px solid #000; padding: 4px 8px; text-align:center; flex:1; background-color: #f8f9fa; font-weight: bold; }
        .bank-fill-note { font-size: 0.65rem; text-align: right; margin-top: 5px; margin-bottom: 5px; font-style: italic;}
        .late-payment-note { font-size: 0.7rem; margin-top: 5px; border: 1px solid #000; padding: 3px; font-weight: bold; }
        .footer-note { font-size: 0.7rem; margin-top: 5px; border-top: 1px solid #000; padding-top: 4px; }
        .website-link { font-weight: bold; font-size: 0.75rem; float: right; }
        
        @media print {
            body * { visibility: hidden; }
            .voucher-page-content, .voucher-page-content * { visibility: visible; }
            .voucher-page-content { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; display: flex; flex-direction: row; justify-content: space-between; gap: 10px; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Fee Management</h1>
            </div>

            <?= $action_msg ?>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#generateSlipModal"><i class="fas fa-file-invoice-dollar me-2"></i> Generate Single Slip</button>
                            <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#generateClassSlipsModal"><i class="fas fa-users me-2"></i> Generate for Entire Class</button>
                            <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#manageFeeComponentsModal"><i class="fas fa-cog me-2"></i> Manage Fee Components</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Generated Fee Slips & Installments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="feeSlipsTable" width="100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Slip #</th>
                                    <th>Student Name</th>
                                    <th>Class/Section</th>
                                    <th>Program</th>
                                    <th>Amount (PKR)</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                               <?php foreach ($fee_records as $record): 
                                    $current_scholarship = '';
                                    if (!empty($record['base_tuition_name']) && preg_match('/\((\d+(?:\.\d+)?)% Scholarship\)/', $record['base_tuition_name'], $matches)) {
                                        $current_scholarship = $matches[1];
                                    }
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['slip_no']) ?></td>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($record['full_name']) ?></td>
                                        <td><?= htmlspecialchars(($record['class_name'] ?? '') . '-' . ($record['section_name'] ?? '')) ?></td>
                                        <td><span class="program-badge"><?= htmlspecialchars($record['fee_category'] ?? 'Class 9') ?></span></td>
                                        <td class="fw-bold text-success">PKR <?= number_format($record['amount'], 2) ?></td>
                                        <td><?= date('d/M/Y', strtotime($record['due_date'])) ?></td>
                                        <td><span class="badge <?= $record['status'] == 'Paid' ? 'bg-success' : ($record['status'] == 'Pending' ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= htmlspecialchars($record['status']) ?></span></td>
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v text-muted"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                    <li><a class="dropdown-item py-2" href="#" onclick="viewStudentHistory(<?= $record['student_id'] ?>, '<?= htmlspecialchars($record['full_name'], ENT_QUOTES) ?>')"><i class="fas fa-history text-secondary me-2"></i> Payment History</a></li>
                                                    <li><a class="dropdown-item py-2" href="#" onclick="viewDetailedVoucher('<?= htmlspecialchars($record['slip_no']) ?>')"><i class="fas fa-file-invoice text-info me-2"></i> View Voucher</a></li>
                                                    <?php if ($record['status'] != 'Paid'): ?>
                                                    <li><a class="dropdown-item py-2" href="#" onclick="preparePayment('<?= htmlspecialchars($record['slip_no']) ?>', '<?= htmlspecialchars($record['full_name'], ENT_QUOTES) ?>', <?= $record['amount'] ?>)"><i class="fas fa-cash-register text-success me-2"></i> Record Payment</a></li>
                                                    <li><a class="dropdown-item py-2" href="#" onclick="applyScholarshipModal('<?= htmlspecialchars($record['slip_no']) ?>', '<?= htmlspecialchars($record['full_name'], ENT_QUOTES) ?>', '<?= $current_scholarship ?>')"><i class="fas fa-percent text-warning me-2"></i> Apply Scholarship</a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="post" class="m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this fee slip?')">
                                                            <input type="hidden" name="delete_slip_action" value="1"><input type="hidden" name="slip_no" value="<?= htmlspecialchars($record['slip_no']) ?>">
                                                            <button type="submit" class="dropdown-item text-danger py-2"><i class="fas fa-trash me-2"></i> Delete Slip</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> School Management System. All rights reserved.</p></div></footer>
</div>

<div class="modal fade" id="generateSlipModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient bg-success text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Generate Fee Slip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <div class="row g-0 h-100">
                    <div class="col-md-5 col-lg-4 border-end bg-white d-flex flex-column" style="height: 75vh;">
                        <div class="p-3 border-bottom shadow-sm z-1">
                            <input type="text" class="form-control bg-light" id="studentSearch" placeholder="Search name or ID..." autocomplete="off">
                        </div>
                        <div class="list-group list-group-flush flex-grow-1 overflow-auto" id="studentList">
                            <?php foreach ($all_students as $student): ?>
                                <a href="#" class="list-group-item list-group-item-action student-list-item py-3 border-bottom" data-student-id="<?= $student['id'] ?>" data-name="<?= htmlspecialchars($student['full_name'], ENT_QUOTES) ?>" data-id-str="<?= htmlspecialchars($student['user_id_string'] ?? '', ENT_QUOTES) ?>" onclick="selectStudent(<?= htmlspecialchars(json_encode($student)) ?>, event)">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div><h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($student['full_name'] ?? '') ?></h6><div class="small text-muted mb-0"><?= htmlspecialchars($student['user_id_string'] ?? 'N/A') ?></div></div>
                                        <div class="text-end"><span class="program-badge mb-1"><?= htmlspecialchars($student['fee_category'] ?? 'Class 9') ?></span><div class="small text-muted">Class <?= htmlspecialchars($student['class_name'] ?? '') ?>-<?= htmlspecialchars($student['section_name'] ?? '') ?></div></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-7 col-lg-8 p-4 overflow-auto" style="height: 75vh;">
                        <div id="noStudentSelected" class="h-100 d-flex flex-column justify-content-center align-items-center text-center">
                            <h4 class="fw-bold text-dark">Select a Student</h4><p class="text-muted">Click on a student from the list on the left to calculate their fee slip.</p>
                        </div>
                        <div id="selectedStudentInfo" style="display: none;">
                            <div class="card border-0 shadow-sm rounded-3 mb-4"><div class="card-body bg-white p-4"><h5 class="fw-bold text-primary border-bottom pb-2 mb-3">Student Details</h5><div id="studentDetailsContent"></div></div></div>
                            <form method="post" id="generateSlipForm">
                                <input type="hidden" name="generate_slip_for_student" value="1"><input type="hidden" name="student_id" id="selectedStudentId">
                                <div class="row g-2 mb-3 px-3 mt-2">
                                    <div class="col-md-12"><label class="form-label small fw-bold">Target Month</label><input type="month" class="form-control" name="target_month" value="<?= date('Y-m') ?>" required></div>
                                    <div class="col-md-12 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableSingleInstallments"><label class="form-check-label fw-bold small text-primary" for="enableSingleInstallments">Enable Installment Plan</label></div></div>
                                </div>
                                <div class="row g-2 mb-3 px-3 installment-fields-single" style="display: none;">
                                    <div class="col-md-4"><label class="form-label small fw-bold text-primary">No. of Installments</label><input type="number" class="form-control border-primary" name="installments" id="singleInstallments" value="1" min="1" max="4"></div>
                                    <div class="col-md-4"><label class="form-label small fw-bold text-primary">Days Between</label><input type="number" class="form-control border-primary" name="installment_days" value="15" min="1"></div>
                                    <div class="col-md-4"><label class="form-label small fw-bold text-primary">Extra Charge/Slip</label><div class="input-group"><span class="input-group-text border-primary bg-primary text-white">PKR</span><input type="number" class="form-control border-primary" name="installment_charge" value="0" min="0" step="0.01"></div></div>
                                </div>
                                <div class="card border-0 shadow-sm rounded-3 mb-4">
                                    <div class="card-header bg-white border-bottom py-3"><h6 class="fw-bold mb-0 text-dark">Fee Breakdown</h6></div>
                                    <div class="card-body p-4">
                                        <div class="border rounded-3 p-3 mb-4 bg-light" id="componentsList"></div>
                                        <div class="d-flex justify-content-between align-items-center bg-light text-white p-3 rounded-3 shadow-sm mb-4"><h5 class="mb-0 text-dark">Total Payable:</h5><h2 class="mb-0 fw-bold text-success" id="totalFeeAmount">PKR 0.00</h2></div>
                                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm rounded-3"><i class="fas fa-check-circle me-2"></i> Confirm & Generate Fee Slip</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="generateClassSlipsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post"><input type="hidden" name="generate_slips_for_class" value="1">
                <div class="modal-header"><h5 class="modal-title">Generate Fee Slips for Class</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Class</label>
                        <select class="form-select" name="class_id" required><option value="" selected disabled>Choose a class...</option>
                            <?php foreach($available_classes as $class): ?>
                                <?php $student_count = count(array_filter($all_students, function($s) use ($class) { return $s['class_id'] == $class['id']; })); ?>
                                <option value="<?= $class['id'] ?>">Class <?= htmlspecialchars($class['class_name']) ?> (<?= $student_count ?> students)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3"><div class="col-md-12"><label class="form-label small fw-bold">Target Month</label><input type="month" class="form-control" name="target_month" value="<?= date('Y-m') ?>" required></div>
                        <div class="col-md-12 mt-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="enableClassInstallments"><label class="form-check-label fw-bold small text-primary" for="enableClassInstallments">Enable Installment Plan</label></div></div>
                    </div>
                    <div class="row g-2 mb-3 installment-fields-class" style="display: none;">
                        <div class="col-md-4"><label class="form-label small fw-bold">Installments</label><input type="number" class="form-control" name="installments" id="classInstallments" value="1" min="1" max="4"></div>
                        <div class="col-md-4"><label class="form-label small fw-bold">Days Between</label><input type="number" class="form-control" name="installment_days" value="15" min="1"></div>
                        <div class="col-md-4"><label class="form-label small fw-bold">Extra Charge</label><input type="number" class="form-control" name="installment_charge" value="0" min="0" step="0.01"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Generate for Class</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="manageFeeComponentsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white border-0 py-3"><h5 class="modal-title fw-bold">Configure Fee Structure</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body bg-light p-4">
                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom py-3"><h6 class="mb-0 fw-bold text-primary">Add Component</h6></div>
                            <div class="card-body">
                                <form method="post"><input type="hidden" name="fee_component_action" value="add">
                                    <div class="mb-3"><label class="form-label small fw-bold">Name *</label><input type="text" class="form-control form-control-sm" name="comp_name" required></div>
                                    <div class="mb-3"><label class="form-label small fw-bold">Amount (PKR) *</label><input type="number" class="form-control form-control-sm" name="comp_amount" required min="0" step="0.01"></div>
                                    <div class="mb-3"><label class="form-label small fw-bold">Type</label><select class="form-select form-select-sm" name="comp_type"><option value="recurring">Recurring (Monthly)</option><option value="one_time">One Time</option><option value="refundable">Refundable</option></select></div>
                                    <div class="mb-3"><label class="form-label small fw-bold">Description</label><textarea class="form-control form-control-sm" name="comp_desc" rows="2"></textarea></div>
                                    <div class="d-flex justify-content-between mb-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="comp_optional" id="compOptional"><label class="form-check-label small" for="compOptional">Optional</label></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="comp_active" id="compActive" checked><label class="form-check-label small" for="compActive">Active</label></div></div>
                                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Save Component</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom py-3"><h6 class="mb-0 fw-bold text-dark">Active Components</h6></div>
                            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover table-borderless align-middle mb-0"><thead class="table-light small"><tr><th class="ps-4">Name</th><th>Amount</th><th>Type</th><th>Settings</th><th class="text-end pe-4">Action</th></tr></thead><tbody>
                                <?php foreach ($fee_components as $comp): ?>
                                <tr class="border-bottom"><td class="ps-4 fw-bold"><?= htmlspecialchars($comp['name']) ?></td><td class="text-success fw-bold">PKR <?= number_format($comp['amount'], 0) ?></td><td><span class="badge bg-primary bg-opacity-10 text-primary px-2 rounded-pill"><?= ucfirst(str_replace('_', ' ', $comp['type'])) ?></span></td><td><?= $comp['is_optional'] ? '<span class="badge bg-warning text-dark me-1">Opt</span>' : '' ?><?= !$comp['is_active'] ? '<span class="badge bg-danger">Off</span>' : '' ?></td><td class="text-end pe-4">
                                    <button class="btn btn-light btn-sm text-primary border me-1" onclick="editComponent(<?= $comp['id'] ?>, '<?= htmlspecialchars($comp['name'], ENT_QUOTES) ?>', <?= $comp['amount'] ?>, '<?= $comp['type'] ?>', '<?= htmlspecialchars($comp['description'] ?? '', ENT_QUOTES) ?>', <?= $comp['is_optional'] ? 'true' : 'false' ?>, <?= $comp['is_active'] ? 'true' : 'false' ?>)"><i class="fas fa-edit"></i></button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="fee_component_action" value="delete"><input type="hidden" name="comp_id" value="<?= $comp['id'] ?>"><button type="submit" class="btn btn-light btn-sm text-danger border"><i class="fas fa-trash"></i></button></form>
                                </td></tr>
                                <?php endforeach; ?>
                            </tbody></table></div></div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4"><div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-white border-bottom py-3"><h6 class="mb-0 fw-bold text-dark">Program Base Fees (Monthly)</h6></div>
                    <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th class="ps-4">Program</th><th>Base Fee (PKR)</th><th>Description</th><th class="text-end pe-4">Action</th></tr></thead><tbody>
                        <?php foreach ($fee_categories as $cat): ?>
                        <tr><td class="ps-4"><span class="program-badge"><?= htmlspecialchars($cat['name']) ?></span></td><td class="fw-bold text-primary fs-5">PKR <?= number_format($cat['tuition_amount'], 2) ?></td><td><?= htmlspecialchars($cat['description']) ?></td><td class="text-end pe-4"><button class="btn btn-warning btn-sm" onclick="editCategoryFee(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>', <?= $cat['tuition_amount'] ?>)"><i class="fas fa-edit me-1"></i> Edit</button></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div></div>
                </div></div></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="record_payment_action" value="1"><input type="hidden" id="paymentSlipNo" name="slip_no">
        <div class="modal-header"><h5 class="modal-title">Record Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p>Recording payment for <strong id="paymentStudentName"></strong>.</p>
            <div class="mb-3"><label class="form-label">Amount Paid (PKR)</label><input type="number" class="form-control" id="paymentAmount" readonly></div>
            <div class="mb-3"><label class="form-label">Date</label><input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="mb-3"><label class="form-label">Method</label><select class="form-select" name="payment_method" required><option>Cash</option><option>Bank Transfer</option><option>Online</option><option>Cheque</option></select></div>
            <div class="mb-3"><label class="form-label">Transaction ID (Optional)</label><input type="text" class="form-control" name="transaction_id"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Record Payment</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="detailedVoucherModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen"><div class="modal-content">
        <div class="modal-header no-print"><h5 class="modal-title">Fee Voucher Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-0" id="detailedVoucherContent"></div>
        <div class="modal-footer no-print"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" onclick="printVoucher()"><i class="fas fa-print"></i> Print All Copies</button></div>
    </div></div>
</div>

<div class="modal fade" id="editComponentModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-warning"><h5 class="modal-title">Edit Component</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post"><input type="hidden" name="fee_component_action" value="edit"><input type="hidden" name="comp_id" id="editCompId">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name</label><input type="text" class="form-control" name="comp_name" id="editCompName" required></div>
                <div class="mb-3"><label class="form-label">Amount</label><input type="number" class="form-control" name="comp_amount" id="editCompAmount" required min="0" step="0.01"></div>
                <div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="comp_type" id="editCompType"><option value="recurring">Recurring (Monthly)</option><option value="one_time">One Time</option><option value="refundable">Refundable</option></select></div>
                <div class="mb-3"><label class="form-label">Description</label><input type="text" class="form-control" name="comp_desc" id="editCompDesc"></div>
                <div class="row mb-3"><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="comp_optional" id="editCompOptional"><label class="form-check-label">Optional</label></div></div><div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="comp_active" id="editCompActive"><label class="form-check-label">Active</label></div></div></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-warning">Update Component</button></div>
        </form></div></div>
</div>

<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-secondary text-white"><h5 class="modal-title">Payment History - <span id="historyStudentName"></span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="table-responsive"><table class="table table-sm table-striped table-bordered" id="historyTable" width="100%"><thead><tr><th>Slip #</th><th>Month</th><th>Amount Paid</th><th>Paid On</th><th>Method</th></tr></thead><tbody id="historyTableBody"><tr><td colspan="5" class="text-center">Loading history...</td></tr></tbody></table></div></div>
    </div></div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Edit <span id="editCatNameDisplay"></span> Fee</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <form method="post"><input type="hidden" name="edit_category_action" value="1"><input type="hidden" name="cat_id" id="editCatId">
            <div class="modal-body text-center"><label class="form-label">New Monthly Fee (PKR)</label><input type="number" class="form-control form-control-lg text-center" name="tuition_amount" id="editCatAmount" required min="0" step="0.01"></div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Save Changes</button></div>
        </form></div></div>
</div>

<div class="modal fade" id="applyScholarshipModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-warning"><h5 class="modal-title">Apply Scholarship</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post"><input type="hidden" name="apply_scholarship_action" value="1"><input type="hidden" id="scholarshipSlipNo" name="slip_no">
            <div class="modal-body text-center"><p>Student: <strong id="scholarshipStudentName"></strong></p><label class="form-label">Scholarship Amount (%)</label><div class="input-group"><input type="number" class="form-control text-center" name="scholarship_percent" id="scholarshipPercent" required min="0" max="100" step="0.01"><span class="input-group-text">%</span></div></div>
            <div class="modal-footer"><button type="submit" class="btn btn-warning w-100">Apply Discount</button></div>
        </form></div></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// --- Sidebar Script ---
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('main-content');
const toggleBtn = document.getElementById('sidebarToggle');
if(sidebar && mainContent && toggleBtn) {
    function saveSidebarState(c){localStorage.setItem('sidebarCollapsed',c);}
    function loadSidebarState(){return localStorage.getItem('sidebarCollapsed')==='true';}
    if(loadSidebarState()){sidebar.classList.add('collapsed');mainContent.classList.add('collapsed');}
    toggleBtn.addEventListener('click',()=>{sidebar.classList.toggle('collapsed');mainContent.classList.toggle('collapsed');saveSidebarState(sidebar.classList.contains('collapsed'));if(window.innerWidth<768){if(!sidebar.classList.contains('collapsed')){sidebar.classList.add('show');mainContent.classList.add('show-sidebar');}else{sidebar.classList.remove('show');mainContent.classList.remove('show-sidebar');}}});
}

// --- JS Logic ---
const activeComponents = <?= $active_components_json ?>;
const feeCategories = <?= $fee_categories_json ?>;

$(document).ready(function() {
    $('#feeSlipsTable').DataTable({"pageLength": 10, "order": [[0, 'desc']], "columnDefs": [{"orderable": false, "targets": 7}]});
    setTimeout(() => $('.alert').alert('close'), 5000);
});

$('#studentSearch').on('input', function() {
    const term = $(this).val().trim().toLowerCase();
    if (term === '') { $('.student-list-item').show(); return; }
    $('.student-list-item').each(function() {
        const txt = String($(this).data('name') || '') + ' ' + String($(this).data('id-str') || '');
        $(this).toggle(txt.toLowerCase().includes(term));
    });
});

let selectedStudent = null;
function selectStudent(student, event) {
    if (event) { event.preventDefault(); event.stopPropagation(); }
    selectedStudent = student;
    $('.student-list-item').removeClass('bg-primary-subtle border-primary border-start border-4');
    $(`.student-list-item[data-student-id="${student.id}"]`).addClass('bg-primary-subtle border-primary border-start border-4');
    
    $('#noStudentSelected').removeClass('d-flex').hide(); 
    $('#selectedStudentInfo').show();
    calculateFeeForStudent(student);
    
    $('#studentDetailsContent').html(`
        <div class="row g-3">
            <div class="col-sm-6"><div class="text-muted small">Student Name</div><div class="fw-bold">${student.full_name}</div></div>
            <div class="col-sm-6"><div class="text-muted small">Student ID</div><div class="fw-bold">${student.user_id_string || 'N/A'}</div></div>
            <div class="col-sm-6"><div class="text-muted small">Class & Section</div><div class="fw-bold">${student.class_name || 'N/A'}-${student.section_name || 'N/A'}</div></div>
            <div class="col-sm-6"><div class="text-muted small">Program</div><span class="program-badge">${student.fee_category || 'Class 9'}</span></div>
        </div>
    `);
    $('#selectedStudentId').val(student.id);
}

function calculateFeeForStudent(student) {
    let tuitionAmount = 4000; 
    const studentCatName = student.fee_category || 'Class 9';
    const catData = feeCategories.find(c => c.name.toLowerCase() === studentCatName.toLowerCase());
    if (catData) tuitionAmount = parseFloat(catData.tuition_amount);
    const isFirstMonth = (student.is_first_month == 1);
    let componentsHtml = `<div class="mb-2 p-2 border-bottom d-flex justify-content-between bg-primary bg-opacity-10 rounded"><span>Base Monthly Fee (${studentCatName})</span><strong class="text-primary">PKR ${tuitionAmount.toFixed(2)}</strong></div>`;
    let mandatoryTotal = tuitionAmount;

    if (activeComponents && activeComponents.length > 0) {
        activeComponents.forEach(comp => {
            const lower = comp.name.toLowerCase();
            if (lower.includes('installment charge') || lower.includes('tuition') || lower.includes('monthly')) return; 
            let amt = parseFloat(comp.amount);
            if (comp.is_optional == 1) {
                componentsHtml += `<div class="mb-2 form-check border rounded p-2 bg-white d-flex"><input class="form-check-input optional-fee-cb ms-1" type="checkbox" name="optional_components[]" value="${comp.id}" data-amount="${amt}" id="opt${comp.id}"><label class="form-check-label w-100 ms-2" for="opt${comp.id}"><span class="badge bg-warning text-dark me-2">Optional</span>${comp.name}: <strong class="text-success">PKR ${amt.toFixed(2)}</strong></label></div>`;
            } else if (isFirstMonth || comp.type === 'recurring') {
                mandatoryTotal += amt;
                componentsHtml += `<div class="mb-2 p-2 border-bottom d-flex justify-content-between"><span><span class="badge bg-info me-2">${comp.type}</span> ${comp.name}</span><strong>PKR ${amt.toFixed(2)}</strong></div>`;
            }
        });
    }

    const refreshTotal = () => {
        let currentTotal = mandatoryTotal;
        const inst = parseInt($('#singleInstallments').val()) || 1;
        const charge = parseFloat($('input[name="installment_charge"]').val()) || 0;
        $('.optional-fee-cb:checked').each(function() { currentTotal += parseFloat($(this).data('amount')); });
        let finalAmount = (currentTotal / inst);
        let extra = '';
        if (inst > 1) { finalAmount += charge; extra = `<div class="mb-2 p-2 border-bottom bg-danger bg-opacity-10 text-danger rounded d-flex justify-content-between"><span>Installment Fee</span><strong>+ PKR ${charge.toFixed(2)}</strong></div>`; }
        $('#totalFeeAmount').text('PKR ' + finalAmount.toLocaleString(undefined, {minimumFractionDigits: 2}));
        $('#installmentRow').remove();
        if (extra) $('#componentsList').append(`<div id="installmentRow">${extra}</div>`);
    };

    $('#componentsList').html(componentsHtml);
    $('.optional-fee-cb').on('change', refreshTotal);
    refreshTotal();
}

// ---- SMART MSST GRID VOUCHER MAPPING ----
// ---- DYNAMIC VOUCHER MAPPING (Al-Abbas Layout + MSST Details) ----
function generateVoucherHTML(record, components, copyType) {
    let breakdownHtml = '';
    let total = 0;
    
    if (components && components.length > 0) {
        components.forEach(component => {
            const amount = parseFloat(component.amount) || 0;
            total += amount;
            breakdownHtml += `
                <tr>
                    <td>${component.component_name || 'Fee Component'}</td>
                    <td class="text-end" style="text-align: right;">${amount.toFixed(0)}</td>
                </tr>
            `;
        });
    } else {
        breakdownHtml = '<tr><td colspan="2" class="text-center">No components found</td></tr>';
    }
    
    const recordAmount = parseFloat(record.amount) || 0;
    let dueDate = 'N/A', generatedDate = 'N/A', monthYear = 'N/A', validityDate = 'N/A';
    try {
        if (record.due_date) {
            let d = new Date(record.due_date);
            dueDate = d.toLocaleDateString('en-GB');
            let v = new Date(d); v.setDate(v.getDate() + 10); 
            validityDate = v.toLocaleDateString('en-GB');
        }
        if (record.generated_date) {
            generatedDate = new Date(record.generated_date).toLocaleDateString('en-GB');
        }
        if (record.month_year) {
            monthYear = new Date(record.month_year).toLocaleDateString('en-GB', {month: 'short', year: 'numeric'});
        }
    } catch (e) {}

    let classSec = (record.class_name || 'N/A');
    let section = record.section_name || 'N/A';
    let program = record.fee_category || 'N/A';

    let installmentBadge = '';
    const installMatch = (record.slip_no || '').match(/-(\d+)$/);
    if (installMatch && parseInt(installMatch[1]) < 10) {
        installmentBadge = `
            <div style="background-color: #ffc107; text-align: center; font-weight: bold; font-size: 11px; padding: 4px; margin-bottom: 6px; border: 1px solid #000; letter-spacing: 1px;">
                INSTALLMENT ${installMatch[1]}
            </div>
        `;
    }

    return `
        <div class="voucher-instance">
            <div class="voucher-container">
                <div class="header-section">
                    <div class="logo-container">
                        <img src="http://localhost/msst/lms/assets/images/msst-logo.png" alt="School Logo" class="logo">
                        <div class="school-info">
                            <strong>Muhaddisa School of Science and Technology</strong><br>
                            Head Office : Kushmara Toq, Near Fatima Jinnah Girls HSS, Quaidabad Skardu<br>
                            Contact: Cell# 0317-9174495 , 03555851351 , 03554201394<br>
                            E-mail:fees@msstskardu.com,Web:www.msstskardu.com
                        </div>
                    </div>
                    <div class="affiliated-copy-type">
                        <span>Affiliated with FBISE</span><br>
                        <span class="copy-type-label">${copyType}</span>
                    </div>
                </div>
                
                <div class="bank-details">
                    Bank Al Habib : Ghulam Abbas, AC #: 20440981003831012
                </div>
                
                ${installmentBadge}
                
                <table class="student-info-table" style="table-layout: fixed; width: 100%;">
                    <tr>
                        <th style="width: 22%;">NAME</th>
                        <td colspan="3" class="student-name-value" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${record.full_name || 'N/A'}</td>
                    </tr>
                    <tr>
                        <th style="width: 22%;">ID</th>
                        <td class="value-cell" style="width: 28%;">${record.user_id_string || 'N/A'}</td>
                        <th style="width: 22%;">CLASS</th>
                        <td class="value-cell" style="width: 28%;">${classSec}-${section}</td>
                    </tr>
                    <tr>
                        <th>PROGRAM</th>
                        <td class="value-cell">${program}</td>
                        <th>PERIOD</th>
                        <td class="value-cell">${monthYear}</td>
                    </tr>
                    <tr>
                        <th>SLIP #</th>
                        <td class="value-cell">${record.slip_no || 'N/A'}</td>
                        <th>ISSUED</th>
                        <td class="value-cell">${generatedDate}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="due-date-cell align-middle" style="padding: 4px; font-size: 11px; text-align: center; background-color: #dc3545; color: white;">
                            <strong>DUE DATE: ${dueDate}</strong>
                        </td>
                    </tr>
                </table>
                
                <table class="fees-table" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th>Fee Component</th>
                            <th style="width: 40%; text-align: right;">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${breakdownHtml}
                        <tr>
                            <th class="text-end" style="text-align: right;">TOTAL</th>
                            <td class="text-end" style="text-align: right;"><strong>${recordAmount.toFixed(0)}</strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="total-payable-section">
                    <div>BEFORE DUE DATE<br><strong style="font-size: 10px;">PKR ${recordAmount.toFixed(0)}</strong></div>
                    <div>AFTER DUE DATE<br><strong style="font-size: 10px;">PKR ${(recordAmount + 500).toFixed(0)}</strong></div>
                </div>
                
                <div class="bank-fill-note">To be filled by the bank</div>
                
                <div class="late-payment-note">
                    LATE PAYMENT : After due date Rs 25/- will be charged per day.<br>
                    <span style="display:block; margin-top: 3px;">Duplicate fee will be charged Rs. 50/-</span>
                </div>
                
                <div class="footer-note">
                    Note: Rs. 550/- will be charged as a Summer Task, if current dues will not pay in the same month.<br>
                    Status: <strong>${record.status || 'N/A'}</strong>
                    ${record.status === 'Paid' && record.paid_on ? `| Paid: ${new Date(record.paid_on).toLocaleDateString('en-GB')}` : ''}
                </div>
            </div>
        </div>
    `;
}
function viewDetailedVoucher(slipNo) {
    $('#detailedVoucherContent').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><p>Loading...</p></div>');
    new bootstrap.Modal(document.getElementById('detailedVoucherModal')).show();
    $.ajax({
        url: 'ajax/get_fee_voucher_details.php', method: 'POST', dataType: 'json', data: { slip_no: slipNo },
        success: function(data) {
            if (data.success && data.record && data.components) {
                $('#detailedVoucherContent').html(`<div class="print-button-container no-print text-center my-3"><button class="btn btn-primary" onclick="printVoucher()"><i class="fas fa-print"></i> Print Copies</button></div><div class="voucher-page-content"><div id="parent-copy">${generateVoucherHTML(data.record, data.components, 'PARENT COPY')}</div><div id="hostel-copy">${generateVoucherHTML(data.record, data.components, 'HOSTEL COPY')}</div><div id="bank-copy">${generateVoucherHTML(data.record, data.components, 'BANK COPY')}</div></div>`);
            } else { $('#detailedVoucherContent').html('<div class="alert alert-danger m-3">Failed to load details.</div>'); }
        }, error: () => $('#detailedVoucherContent').html('<div class="alert alert-danger m-3">Error loading details. Ensure ajax/get_fee_voucher_details.php is correctly created.</div>')
    });
}

function printVoucher() {
    const parentCopy = document.getElementById('parent-copy').innerHTML;
    const hostelCopy = document.getElementById('hostel-copy').innerHTML;
    const bankCopy = document.getElementById('bank-copy').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
    <html><head><title>Fee Slip - MSST</title>
    <style>
        @page { size: A4 landscape; margin: 3mm; }
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; background: #fff; }
        .voucher-page { display: flex; flex-direction: row; justify-content: space-between; width: 100%; height: 195mm; box-sizing: border-box; }
        .voucher-col { width: 32%; height: 100%; box-sizing: border-box; }
        .voucher-instance { height: 100%; }
        .voucher-container { border: 1px dashed #000; height: 100%; padding: 5px; display: flex; flex-direction: column; box-sizing: border-box; }
        .header-section { display: flex; justify-content: space-between; border-bottom: 1px solid #000; padding-bottom: 3px; margin-bottom: 3px; }
        .logo-container { display: flex; align-items: center; }
        .logo { width: 45px; height: auto; margin-right: 5px; border: 1px solid #ddd; padding: 2px; }
        .school-info { font-size: 7px; line-height: 1.1; }
        .school-info strong { font-size: 10px; margin-bottom: 1px; display: block; }
        .affiliated-copy-type { text-align: right; font-size: 7px; }
        .affiliated-copy-type span { display: block; border: 1px solid #000; padding: 1px 3px; margin-bottom: 1px; }
        .copy-type-label { font-weight: bold; background-color: #e9ecef !important; font-size: 9px !important;}
        .bank-details { text-align: center; font-size: 8px; font-weight: bold; border: 1px solid #000; padding: 2px; margin-bottom: 3px; background-color: #f8f9fa !important; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 3px; font-size: 7px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 2px; text-align: left; overflow: hidden; white-space: nowrap; }
        th { background-color: #f2f2f2 !important; font-weight: bold; text-align: center; }
        td { text-align: center; }
        .student-name-value { font-weight: bold; text-align: left !important; }
        .value-cell { font-weight: bold; text-align: center !important; }
        .due-date-cell { background-color: #dc3545 !important; color: white !important; font-weight: bold; text-align: center !important; font-size: 9px !important; vertical-align: middle !important;}
        .total-payable-section { display: flex; justify-content: space-between; margin-top: auto; font-size: 8px; gap: 3px;}
        .total-payable-section div { border: 1px solid #000; padding: 3px; font-weight: bold; background-color: #f8f9fa !important; flex: 1; text-align: center; }
        .bank-fill-note { font-size: 6px; text-align: right; margin-top: 3px; font-style: italic; }
        .late-payment-note { font-size: 7px; margin-top: 2px; padding: 2px; border: 1px solid #000; font-weight: bold; text-align: left; }
        .footer-note { font-size: 7px; margin-top: 3px; border-top: 1px solid #000; padding-top: 3px; text-align: left; }
    </style></head>
    <body><div class="voucher-page"><div class="voucher-col">${parentCopy}</div><div class="voucher-col">${hostelCopy}</div><div class="voucher-col">${bankCopy}</div></div></body></html>`);
    printWindow.document.close(); printWindow.focus(); setTimeout(() => { printWindow.print(); printWindow.close(); }, 300);
}

function editComponent(id, name, amount, type, desc, optional, active) {
    $('#editCompId').val(id); $('#editCompName').val(name); $('#editCompAmount').val(amount); $('#editCompType').val(type); $('#editCompDesc').val(desc); $('#editCompOptional').prop('checked', optional); $('#editCompActive').prop('checked', active);
    new bootstrap.Modal(document.getElementById('editComponentModal')).show();
}

function viewStudentHistory(studentId, studentName) {
    $('#historyStudentName').text(studentName); 
    new bootstrap.Modal(document.getElementById('historyModal')).show();
    
    if ($.fn.DataTable.isDataTable('#historyTable')) {
        $('#historyTable').DataTable().destroy();
    }
    $('#historyTableBody').html('<tr><td colspan="5" class="text-center">Loading history...</td></tr>');
    
    $.ajax({
        url: 'ajax/get_student_history.php', 
        method: 'POST', 
        data: { student_id: studentId },
        dataType: 'json', // Explicitly tell jQuery to expect JSON
        success: function(data) {
            let html = '';
            
            if(data && data.length > 0) {
                data.forEach(row => { 
                    // Safely format dates and amounts
                    let monthStr = row.month_year ? new Date(row.month_year).toLocaleDateString('en-GB',{month:'short',year:'numeric'}) : 'N/A';
                    let paidStr = row.paid_on ? new Date(row.paid_on).toLocaleDateString('en-GB') : 'N/A';
                    let amountStr = parseFloat(row.amount).toLocaleString();
                    
                    html += `
                        <tr>
                            <td>${row.slip_no}</td>
                            <td>${monthStr}</td>
                            <td class="fw-bold">PKR ${amountStr}</td>
                            <td class="text-success fw-bold">${paidStr}</td>
                            <td>${row.payment_method || 'N/A'}</td>
                        </tr>
                    `; 
                }); 
            } else {
                html = '<tr><td colspan="5" class="text-center">No past payments found.</td></tr>';
            }
            
            $('#historyTableBody').html(html); 
            $('#historyTable').DataTable({
                "order": [[3, "desc"]],
                "pageLength": 5,
                "language": {"emptyTable": "No past payments found."}
            });
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", error);
            $('#historyTableBody').html('<tr><td colspan="5" class="text-center text-danger">Error loading history from server.</td></tr>');
        }
    });
}

function preparePayment(slipNo, studentName, amount) {
    $('#paymentSlipNo').val(slipNo); $('#paymentStudentName').text(studentName); $('#paymentAmount').val(amount);
    new bootstrap.Modal(document.getElementById('recordPaymentModal')).show();
}
function editCategoryFee(id, name, amount) {
    $('#editCatId').val(id); $('#editCatNameDisplay').text(name); $('#editCatAmount').val(amount);
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}
function applyScholarshipModal(slipNo, studentName, currentPercent) {
    $('#scholarshipSlipNo').val(slipNo); $('#scholarshipStudentName').text(studentName); $('#scholarshipPercent').val(currentPercent);
    new bootstrap.Modal(document.getElementById('applyScholarshipModal')).show();
}

$('#enableSingleInstallments').on('change', function() {
    if ($(this).is(':checked')) { $('.installment-fields-single').slideDown(); $('#singleInstallments').val(1); const c = activeComponents.find(x => x.name.toLowerCase().includes('installment charge')); if(c) $('input[name="installment_charge"]').val(parseFloat(c.amount).toFixed(2)); } else { $('.installment-fields-single').slideUp(); $('#singleInstallments').val(1); }
});
$('#enableClassInstallments').on('change', function() {
    if ($(this).is(':checked')) { $('.installment-fields-class').slideDown(); $('#classInstallments').val(1); const c = activeComponents.find(x => x.name.toLowerCase().includes('installment charge')); if(c) $('#generateClassSlipsModal input[name="installment_charge"]').val(parseFloat(c.amount).toFixed(2)); } else { $('.installment-fields-class').slideUp(); $('#classInstallments').val(1); }
});
$('#singleInstallments, input[name="installment_charge"]').on('change keyup', () => { if (selectedStudent) calculateFeeForStudent(selectedStudent); });
</script>
</body>
</html>