<?php
session_start();
require_once '../config/db_config.php';

if (!isset($_SESSION['teacher_logged_in']) || !isset($_POST['class_id']) || !isset($_POST['assessment_id'])) {
    exit('Unauthorized');
}

$class_id = $_POST['class_id'];
$assessment_id = $_POST['assessment_id'];

// Get all students in this class and their existing scores (if any)
$stmt = $pdo->prepare("
    SELECT u.id as student_id, u.full_name, u.user_id_string, ss.marks_obtained, ss.remarks
    FROM users u
    JOIN student_details sd ON u.id = sd.user_id
    LEFT JOIN student_scores ss ON u.id = ss.student_id AND ss.assessment_id = ?
    WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'Student' LIMIT 1) 
    AND u.status = 'Active' 
    AND sd.class_id = ?
    ORDER BY u.full_name ASC
");
$stmt->execute([$assessment_id, $class_id]);
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