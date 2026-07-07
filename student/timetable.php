<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

$student_name = $_SESSION['student_name'] ?? 'Student';

$current_user_db_id = 0;
if (!empty($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
    $stmt->execute([$_SESSION['student_id']]);
    $current_user_db_id = $stmt->fetchColumn();
}
if (!$current_user_db_id && isset($_SESSION['student_user_db_id'])) {
    $current_user_db_id = (int)$_SESSION['student_user_db_id'];
}

$student_class_id = 0;
$student_section_id = 0;
if ($current_user_db_id > 0) {
    $stmt_class = $pdo->prepare("SELECT class_id, section_id FROM student_details WHERE user_id = ? LIMIT 1");
    $stmt_class->execute([$current_user_db_id]);
    $row = $stmt_class->fetch(PDO::FETCH_ASSOC);
    $student_class_id = $row['class_id'] ?? 0;
    $student_section_id = $row['section_id'] ?? 0;
}

$periods = $pdo->query("SELECT * FROM timetable_periods ORDER BY period_number")->fetchAll(PDO::FETCH_ASSOC);
$active_days = $pdo->query("SELECT * FROM school_days WHERE is_active = 1 ORDER BY day_order")->fetchAll(PDO::FETCH_ASSOC);

$schedule = [];
if ($student_class_id) {
    $stmt = $pdo->prepare("
        SELECT te.day_name, te.period_id, te.room, s.subject_name, u.full_name AS teacher_name
        FROM timetable_entries te
        JOIN subjects s ON s.id = te.subject_id
        JOIN users u ON u.id = te.teacher_user_id
        WHERE te.class_id = ? AND IFNULL(te.section_id,0) = IFNULL(?,0)
    ");
    $stmt->execute([$student_class_id, $student_section_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $schedule[$row['day_name'] . '|' . $row['period_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable | <?= htmlspecialchars($student_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'sidebar.php' ?>

<div id="main-content">
    <?php include 'header.php' ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <h1 class="h3 mb-4">My Timetable</h1>

            <?php if (empty($periods)): ?>
                <div class="alert alert-info">The timetable has not been set up yet. Please check back later.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <?php foreach ($active_days as $d): ?>
                                <th><?= htmlspecialchars($d['day_name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $p): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($p['label']) ?><br><small class="text-muted"><?= substr($p['start_time'],0,5) ?>-<?= substr($p['end_time'],0,5) ?></small></td>
                            <?php if ($p['is_break']): ?>
                                <td colspan="<?= count($active_days) ?>" class="bg-light fw-bold text-uppercase text-secondary"><?= htmlspecialchars($p['label']) ?></td>
                            <?php else: foreach ($active_days as $d):
                                $entry = $schedule[$d['day_name'] . '|' . $p['id']] ?? null; ?>
                                <td>
                                    <?php if ($entry): ?>
                                        <div class="fw-bold"><?= htmlspecialchars($entry['subject_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($entry['teacher_name']) ?></div>
                                        <?php if ($entry['room']): ?><div class="small text-muted"><?= htmlspecialchars($entry['room']) ?></div><?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
