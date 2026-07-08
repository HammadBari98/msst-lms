<?php
session_start();
require_once '../config/db_config.php';

if (!isset($_SESSION['teacher_logged_in']) || !isset($_POST['assessment_id'])) {
    exit('Unauthorized');
}

$assessment_id = $_POST['assessment_id'];

// =======================================================
// AUTO-PATCHER: assessments schema drift (section_id/subject_id)
// =======================================================
try {
    $existing_cols = $pdo->query("SHOW COLUMNS FROM assessments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('section_id', $existing_cols)) {
        $pdo->exec("ALTER TABLE assessments ADD COLUMN section_id INT DEFAULT 0 AFTER class_id");
    }
    if (!in_array('subject_id', $existing_cols)) {
        $pdo->exec("ALTER TABLE assessments ADD COLUMN subject_id INT DEFAULT 0 AFTER section_id");
    }
} catch (PDOException $e) { /* Ignore if already up to date */ }

$stmt_asm = $pdo->prepare("SELECT class_id, section_id FROM assessments WHERE id = ?");
$stmt_asm->execute([$assessment_id]);
$assessment = $stmt_asm->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    echo '<tr><td colspan="3" class="text-center">Assessment not found.</td></tr>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.id as student_id, u.full_name, u.user_id_string, ss.marks_obtained, ss.remarks
    FROM users u
    JOIN student_details sd ON u.id = sd.user_id
    LEFT JOIN student_scores ss ON u.id = ss.student_id AND ss.assessment_id = ?
    WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'Student' LIMIT 1)
    AND u.status = 'Active'
    AND sd.class_id = ? AND IFNULL(sd.section_id,0) = IFNULL(?,0)
    ORDER BY u.full_name ASC
");
$stmt->execute([$assessment_id, $assessment['class_id'], $assessment['section_id']]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    echo '<tr><td colspan="3" class="text-center">No active students found in this class.</td></tr>';
    exit;
}

// Generate the HTML table rows
foreach ($students as $student) {
    $marks = $student['marks_obtained'] !== null ? htmlspecialchars($student['marks_obtained']) : '';
    $remarks = $student['remarks'] !== null ? htmlspecialchars($student['remarks']) : '';
    
    echo '<tr>';
    echo '<td><strong>' . htmlspecialchars($student['full_name']) . '</strong><br><small class="text-muted">' . htmlspecialchars($student['user_id_string']) . '</small></td>';
    
    echo '<td><input type="number" name="marks[' . $student['student_id'] . ']" class="form-control" step="0.5" min="0" value="' . $marks . '" placeholder="Marks"></td>';
    
    echo '<td><input type="text" name="remarks[' . $student['student_id'] . ']" class="form-control" value="' . $remarks . '" placeholder="Good job, needs work, etc."></td>';
    echo '</tr>';
}
?>