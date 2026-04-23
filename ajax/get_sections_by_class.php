<?php
session_start();
require_once '../config/db_config.php';

// Set headers to return JSON
header('Content-Type: application/json');

// Security check: Only allow logged-in admins
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode([]);
    exit;
}

// Get the requested class ID from the URL
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($class_id > 0) {
    try {
        // Fetch all sections that belong to this specific class
        $stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE class_id = ? ORDER BY section_name");
        $stmt->execute([$class_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return the sections as a JSON array
        echo json_encode($sections);
        
    } catch (Exception $e) {
        // Return an empty array if something fails so the frontend doesn't crash
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>