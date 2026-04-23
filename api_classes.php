<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

header('Content-Type: application/json');

// Check authorization
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        
        case 'add_class':
            $class_name = trim($_POST['class_name'] ?? '');
            if (empty($class_name)) throw new Exception('Class name cannot be empty.');
            
            // Check for duplicates
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_name = ?");
            $stmt->execute([$class_name]);
            if ($stmt->fetchColumn() > 0) throw new Exception('This class already exists.');
            
            $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)")->execute([$class_name]);
            echo json_encode(['success' => true, 'message' => 'Class added successfully.']);
            break;
            
        case 'edit_class':
            $class_id = intval($_POST['class_id'] ?? 0);
            $class_name = trim($_POST['class_name'] ?? '');
            
            if ($class_id <= 0 || empty($class_name)) throw new Exception('Invalid data provided.');
            
            $pdo->prepare("UPDATE classes SET class_name = ? WHERE id = ?")->execute([$class_name, $class_id]);
            echo json_encode(['success' => true, 'message' => 'Class updated successfully.']);
            break;
            
        case 'delete_class':
            $class_id = intval($_POST['class_id'] ?? 0);
            if ($class_id <= 0) throw new Exception('Invalid class ID.');
            
            // Note: Since you likely have ON DELETE CASCADE in your DB setup, deleting the class will delete associated sections.
            $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$class_id]);
            echo json_encode(['success' => true, 'message' => 'Class and its sections deleted successfully.']);
            break;
            
        case 'add_section':
            $class_id = intval($_POST['class_id'] ?? 0);
            $section_name = trim($_POST['section_name'] ?? '');
            
            if ($class_id <= 0 || empty($section_name)) throw new Exception('Invalid data provided.');
            
            // Check for duplicate sections within the SAME class
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE class_id = ? AND section_name = ?");
            $stmt->execute([$class_id, $section_name]);
            if ($stmt->fetchColumn() > 0) throw new Exception("Section {$section_name} already exists for this class.");
            
            $pdo->prepare("INSERT INTO sections (class_id, section_name) VALUES (?, ?)")->execute([$class_id, $section_name]);
            echo json_encode(['success' => true, 'message' => 'Section added successfully.']);
            break;
            
        case 'delete_section':
            $section_id = intval($_POST['id'] ?? 0);
            if ($section_id <= 0) throw new Exception('Invalid section ID.');
            
            $pdo->prepare("DELETE FROM sections WHERE id = ?")->execute([$section_id]);
            echo json_encode(['success' => true, 'message' => 'Section removed successfully.']);
            break;
            
        default:
            throw new Exception('Unknown action requested.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>