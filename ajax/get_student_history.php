<?php
session_start();
require_once '../config/db_config.php';

// Set headers to return JSON
header('Content-Type: application/json');

// Security check: Only allow admins
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if student_id is provided
$student_id = $_POST['student_id'] ?? '';

if (empty($student_id)) {
    echo json_encode([]);
    exit;
}

try {
    // Fetch all PAID slips for this specific student
    $stmt = $pdo->prepare("
        SELECT slip_no, month_year, amount, paid_on, payment_method, transaction_id 
        FROM fee_slips 
        WHERE student_id = ? AND status = 'Paid' 
        ORDER BY paid_on DESC, month_year DESC
    ");
    $stmt->execute([$student_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the data as JSON for the modal to display
    echo json_encode($history);

} catch (Exception $e) {
    // Return empty array on error so it doesn't crash the frontend
    echo json_encode([]);
}
?>