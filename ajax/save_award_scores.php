<?php
session_start();
require_once '../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['teacher_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$teacher_session_id = $_SESSION['teacher_id'];
$stmt_get_id = $pdo->prepare("SELECT id FROM users WHERE id = ? OR user_id_string = ? LIMIT 1");
$stmt_get_id->execute([$teacher_session_id, $teacher_session_id]);
$teacher_id = $stmt_get_id->fetchColumn();

$input = json_decode(file_get_contents('php://input'), true);
$scores = $input['scores'] ?? [];

if (!$teacher_id || !is_array($scores) || empty($scores)) {
    echo json_encode(['success' => false, 'message' => 'No scores submitted']);
    exit;
}

// Load the assessments referenced by this submission so we know each one's
// max marks and which class/section it belongs to (for the authorization check below).
$assessment_ids = array_values(array_unique(array_map(fn($s) => (int)($s['assessment_id'] ?? 0), $scores)));
$assessment_ids = array_filter($assessment_ids);
if (empty($assessment_ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid assessments in submission']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($assessment_ids), '?'));
$stmt_asm = $pdo->prepare("SELECT id, total_marks, class_id, section_id FROM assessments WHERE id IN ($placeholders)");
$stmt_asm->execute($assessment_ids);
$assessments = [];
foreach ($stmt_asm->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $assessments[$row['id']] = $row;
}

// A teacher may only save marks for a class/section they are actually assigned to.
$assigned_pairs = [];
$stmt_assigned = $pdo->prepare("SELECT class_id, IFNULL(section_id,0) AS section_id FROM teacher_class_assignments WHERE teacher_user_id = ?");
$stmt_assigned->execute([$teacher_id]);
foreach ($stmt_assigned->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $assigned_pairs[$row['class_id'] . '|' . $row['section_id']] = true;
}

try {
    $pdo->beginTransaction();

    $stmt_upsert = $pdo->prepare("
        INSERT INTO student_scores (assessment_id, student_id, marks_obtained) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained)
    ");
    $stmt_delete = $pdo->prepare("DELETE FROM student_scores WHERE assessment_id = ? AND student_id = ?");

    $saved = 0;
    $skipped = 0;

    foreach ($scores as $entry) {
        $assessment_id = (int)($entry['assessment_id'] ?? 0);
        $student_id = (int)($entry['student_id'] ?? 0);
        $marks = isset($entry['marks']) ? trim((string)$entry['marks']) : '';

        if (!$student_id || !isset($assessments[$assessment_id])) { $skipped++; continue; }
        $asm = $assessments[$assessment_id];

        $pair_key = $asm['class_id'] . '|' . $asm['section_id'];
        if (!isset($assigned_pairs[$pair_key])) { $skipped++; continue; }

        if ($marks === '') {
            // Blank input means the student is marked Absent for this subject.
            $stmt_delete->execute([$assessment_id, $student_id]);
            $saved++;
            continue;
        }

        if (!is_numeric($marks) || $marks < 0 || $marks > $asm['total_marks']) { $skipped++; continue; }

        $stmt_upsert->execute([$assessment_id, $student_id, $marks]);
        $saved++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'saved' => $saved, 'skipped' => $skipped]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
