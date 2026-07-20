<?php
session_start();
require_once '../config/db_config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$body = isset($input['body']) ? trim((string)$input['body']) : '';
$other_id = isset($input['other_id']) ? (int)$input['other_id'] : 0;

// Login pages across roles (teacher/student/admin) share one PHP session cookie and never
// clear other roles' session flags on login, so both teacher_logged_in and student_logged_in
// can end up set at once if someone tested more than one role in the same browser without
// logging out. The caller must explicitly declare which role it's acting as, so that ambiguous
// case never gets silently resolved to the wrong one.
$role = $input['role'] ?? '';
$is_teacher = $role === 'teacher' && isset($_SESSION['teacher_logged_in']);
$is_student = $role === 'student' && isset($_SESSION['student_logged_in']);

if (!$is_teacher && !$is_student) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($body === '' || !$other_id) {
    echo json_encode(['success' => false, 'message' => 'Message text and recipient are required']);
    exit;
}

if ($is_teacher) {
    $session_id = $_SESSION['teacher_id'];
    $stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $stmt_id->execute([$session_id, $session_id]);
    $teacher_id = (int)$stmt_id->fetchColumn();
    $student_id = $other_id;
    $sender_role = 'teacher';
} else {
    $session_id = $_SESSION['student_id'];
    $stmt_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
    $stmt_id->execute([$session_id, $session_id]);
    $student_id = (int)$stmt_id->fetchColumn();
    $teacher_id = $other_id;
    $sender_role = 'student';
}

if (!$teacher_id || !$student_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
    exit;
}

// A teacher may only message a student in a class/section they are assigned to, and vice versa.
$stmt_auth = $pdo->prepare("
    SELECT 1 FROM teacher_class_assignments tca
    JOIN student_details sd ON sd.class_id = tca.class_id AND IFNULL(sd.section_id,0) = IFNULL(tca.section_id,0)
    WHERE tca.teacher_user_id = ? AND sd.user_id = ?
    LIMIT 1
");
$stmt_auth->execute([$teacher_id, $student_id]);
if (!$stmt_auth->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not authorized to message this person']);
    exit;
}

$stmt_insert = $pdo->prepare("INSERT INTO messages (teacher_id, student_id, sender_role, body) VALUES (?, ?, ?, ?)");
$stmt_insert->execute([$teacher_id, $student_id, $sender_role, $body]);

echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'created_at' => date('Y-m-d H:i:s')]);
