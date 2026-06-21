<?php
require_once __DIR__ . '/../config/db_config.php';
header('Content-Type: application/json');

if (isset($_GET['class_id']) && is_numeric($_GET['class_id'])) {
    $class_id = (int) $_GET['class_id'];
    
    try {
        // Find ALL unique subjects assigned to ANY program (section) inside this specific class
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.subject_name 
            FROM subjects s
            JOIN program_subjects ps ON s.id = ps.subject_id
            JOIN sections sec ON ps.program_id = sec.id
            WHERE sec.class_id = ?
            ORDER BY s.subject_name ASC
        ");
        $stmt->execute([$class_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode($subjects);
    } catch (PDOException $e) {
        // Return empty array if table doesn't exist or errors out
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>