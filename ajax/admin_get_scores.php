<?php
session_start();
require_once '../config/db_config.php';

// Check if Admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !isset($_POST['assessment_id'])) {
    exit('<tr><td colspan="4" class="text-center text-danger">Unauthorized</td></tr>');
}

$assessment_id = $_POST['assessment_id'];

// Get assessment total marks
$stmt_asm = $pdo->prepare("SELECT total_marks FROM assessments WHERE id = ?");
$stmt_asm->execute([$assessment_id]);
$total_marks = $stmt_asm->fetchColumn();

// Get the actual scores
$stmt = $pdo->prepare("
    SELECT u.full_name, u.user_id_string, ss.marks_obtained, ss.remarks
    FROM student_scores ss
    JOIN users u ON ss.student_id = u.id
    WHERE ss.assessment_id = ?
    ORDER BY ss.marks_obtained DESC
");
$stmt->execute([$assessment_id]);
$scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($scores)) {
    echo '<tr><td colspan="4" class="text-center text-muted">No scores have been submitted for this assessment yet.</td></tr>';
    exit;
}

// Generate the HTML table rows
foreach ($scores as $score) {
    $marks = $score['marks_obtained'];
    $pct = $total_marks > 0 ? round(($marks / $total_marks) * 100, 1) : 0;
    $badge_color = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning text-dark' : 'danger');
    
    echo '<tr>';
    echo '<td><strong>' . htmlspecialchars($score['full_name']) . '</strong><br><small class="text-muted">' . htmlspecialchars($score['user_id_string']) . '</small></td>';
    echo '<td class="fw-bold text-center">' . htmlspecialchars($marks) . ' / ' . $total_marks . '</td>';
    echo '<td class="text-center"><span class="badge bg-' . $badge_color . '">' . $pct . '%</span></td>';
    echo '<td class="text-muted small">' . htmlspecialchars($score['remarks'] ?: '-') . '</td>';
    echo '</tr>';
}
?>