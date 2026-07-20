<?php
session_start();
require_once '../config/db_config.php';
header('Content-Type: application/json');

$is_teacher = isset($_SESSION['teacher_logged_in']);
$is_student = isset($_SESSION['student_logged_in']);

if (!$is_teacher && !$is_student) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$other_id = isset($_GET['other_id']) ? (int)$_GET['other_id'] : 0;
$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

if ($is_teacher) {
    $session_id = $_SESSION['teacher_id'];
    $stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $stmt_id->execute([$session_id, $session_id]);
    $teacher_id = (int)$stmt_id->fetchColumn();
    $student_id = $other_id;
    $own_role = 'teacher';
} else {
    $session_id = $_SESSION['student_id'];
    $stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $stmt_id->execute([$session_id, $session_id]);
    $student_id = (int)$stmt_id->fetchColumn();
    $teacher_id = $other_id;
    $own_role = 'student';
}

if (!$teacher_id || !$student_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
    exit;
}

$stmt_msgs = $pdo->prepare("
    SELECT id, sender_role, body, created_at
    FROM messages
    WHERE teacher_id = ? AND student_id = ? AND id > ?
    ORDER BY id ASC
");
$stmt_msgs->execute([$teacher_id, $student_id, $after_id]);
$rows = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);

// Mark as read any messages sent by the other party that we just fetched.
$stmt_read = $pdo->prepare("
    UPDATE messages SET is_read = 1
    WHERE teacher_id = ? AND student_id = ? AND sender_role != ? AND is_read = 0
");
$stmt_read->execute([$teacher_id, $student_id, $own_role]);

echo json_encode(['success' => true, 'messages' => $rows]);
