<?php
session_start();
require_once '../config/db_config.php';

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$slip_no = $_POST['slip_no'] ?? '';
if (empty($slip_no)) {
    echo json_encode(['success' => false, 'error' => 'Slip number missing']);
    exit;
}

try {
    // Fetch the main slip record including Class, Section, and Program (fee_category)
    $stmt = $pdo->prepare("
        SELECT fs.*, u.full_name, u.user_id_string, 
               c.class_name, s.section_name, sd.fee_category
        FROM fee_slips fs
        LEFT JOIN users u ON fs.student_id = u.id
        LEFT JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN classes c ON sd.class_id = c.id
        LEFT JOIN sections s ON sd.section_id = s.id
        WHERE fs.slip_no = ?
    ");
    $stmt->execute([$slip_no]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => false, 'error' => 'Voucher not found']);
        exit;
    }

    // Fetch the breakdown components (Tuition, Lab Fee, Transport, etc.)
    $stmt_comp = $pdo->prepare("SELECT component_name, amount FROM fee_slip_components WHERE slip_no = ? ORDER BY id ASC");
    $stmt_comp->execute([$slip_no]);
    $components = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'record' => $record,
        'components' => $components
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>