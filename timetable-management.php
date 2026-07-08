<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// =======================================================
// AUTO-PATCHER: teacher_class_assignments schema drift
// =======================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_class_assignments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_user_id INT NOT NULL,
            class_id INT NOT NULL,
            section_id INT DEFAULT NULL,
            subject_id INT DEFAULT NULL,
            program_name VARCHAR(100) DEFAULT 'General'
        )
    ");
    $existing_cols = $pdo->query("SHOW COLUMNS FROM teacher_class_assignments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('section_id', $existing_cols)) {
        $pdo->exec("ALTER TABLE teacher_class_assignments ADD COLUMN section_id INT DEFAULT NULL AFTER class_id");
    }
    if (!in_array('subject_id', $existing_cols)) {
        $pdo->exec("ALTER TABLE teacher_class_assignments ADD COLUMN subject_id INT DEFAULT NULL AFTER section_id");
    }
    if (!in_array('program_name', $existing_cols)) {
        $pdo->exec("ALTER TABLE teacher_class_assignments ADD COLUMN program_name VARCHAR(100) DEFAULT 'General'");
    }
    // Drop the legacy (teacher_user_id, class_id)-only unique key from before
    // section/subject granularity existed - it blocks multiple sections/subjects
    // for the same teacher+class with a duplicate-entry error.
    $existing_indexes = $pdo->query("SHOW INDEX FROM teacher_class_assignments")->fetchAll(PDO::FETCH_COLUMN, 2);
    if (in_array('teacher_class_unique', $existing_indexes)) {
        $pdo->exec("ALTER TABLE teacher_class_assignments DROP INDEX teacher_class_unique");
    }
} catch (PDOException $e) { /* Ignore if already up to date */ }

// =======================================================
// AUTO-PATCHER: Timetable tables
// =======================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timetable_periods (
            id INT PRIMARY KEY AUTO_INCREMENT,
            period_number INT NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_break TINYINT(1) NOT NULL DEFAULT 0,
            label VARCHAR(100) NOT NULL DEFAULT ''
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS school_days (
            id INT PRIMARY KEY AUTO_INCREMENT,
            day_name VARCHAR(20) NOT NULL UNIQUE,
            day_order INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        )
    ");
    if ((int)$pdo->query("SELECT COUNT(*) FROM school_days")->fetchColumn() === 0) {
        $default_days = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
        $ins = $pdo->prepare("INSERT INTO school_days (day_name, day_order, is_active) VALUES (?, ?, ?)");
        foreach ($default_days as $name => $order) {
            $ins->execute([$name, $order, $order <= 6 ? 1 : 0]);
        }
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timetable_entries (
            id INT PRIMARY KEY AUTO_INCREMENT,
            class_id INT NOT NULL,
            section_id INT NULL,
            day_name VARCHAR(20) NOT NULL,
            period_id INT NOT NULL,
            subject_id INT NOT NULL,
            teacher_user_id INT NOT NULL,
            room VARCHAR(100) NULL
        )
    ");
} catch (PDOException $e) { /* Ignore if already exists */ }

// =======================================================
// AJAX HANDLERS: Period & School Day settings
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['action'] === 'save_period') {
            $id = $_POST['period_id'] ?? '';
            $period_number = (int)$_POST['period_number'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $is_break = isset($_POST['is_break']) ? 1 : 0;
            $label = trim($_POST['label']);

            $pdo->beginTransaction();
            if ($id !== '') {
                $pdo->prepare("UPDATE timetable_periods SET period_number=?, start_time=?, end_time=?, is_break=?, label=? WHERE id=?")
                    ->execute([$period_number, $start_time, $end_time, $is_break, $label, (int)$id]);
                if ($is_break) {
                    $pdo->prepare("DELETE FROM timetable_entries WHERE period_id = ?")->execute([(int)$id]);
                }
            } else {
                $pdo->prepare("INSERT INTO timetable_periods (period_number, start_time, end_time, is_break, label) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$period_number, $start_time, $end_time, $is_break, $label]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'delete_period') {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM timetable_periods WHERE id = ?")->execute([(int)$_POST['period_id']]);
            $pdo->prepare("DELETE FROM timetable_entries WHERE period_id = ?")->execute([(int)$_POST['period_id']]);
            $pdo->commit();
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'toggle_day') {
            $pdo->prepare("UPDATE school_days SET is_active = ? WHERE id = ?")
                ->execute([(int)$_POST['is_active'], (int)$_POST['day_id']]);
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'save_entry') {
            $class_id = (int)$_POST['class_id'];
            $section_id = $_POST['section_id'] !== '' ? (int)$_POST['section_id'] : null;
            $day_name = $_POST['day_name'];
            $period_id = (int)$_POST['period_id'];
            $subject_id = (int)$_POST['subject_id'];
            $teacher_user_id = (int)$_POST['teacher_user_id'];
            $room = trim($_POST['room'] ?? '') ?: null;

            $stmt_valid_day = $pdo->prepare("SELECT COUNT(*) FROM school_days WHERE day_name = ? AND is_active = 1");
            $stmt_valid_day->execute([$day_name]);
            if (!$stmt_valid_day->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Invalid or inactive school day.']); exit;
            }

            $pdo->beginTransaction();
            $del = $pdo->prepare("DELETE FROM timetable_entries WHERE class_id = ? AND IFNULL(section_id,0) = IFNULL(?,0) AND day_name = ? AND period_id = ?");
            $del->execute([$class_id, $section_id, $day_name, $period_id]);
            $ins = $pdo->prepare("INSERT INTO timetable_entries (class_id, section_id, day_name, period_id, subject_id, teacher_user_id, room) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$class_id, $section_id, $day_name, $period_id, $subject_id, $teacher_user_id, $room]);
            $pdo->commit();

            $conflict = null;
            $stmt_conflict = $pdo->prepare("
                SELECT c.class_name, sec.section_name
                FROM timetable_entries te
                JOIN classes c ON c.id = te.class_id
                LEFT JOIN sections sec ON sec.id = te.section_id
                WHERE te.teacher_user_id = ? AND te.day_name = ? AND te.period_id = ?
                  AND NOT (te.class_id = ? AND IFNULL(te.section_id,0) = IFNULL(?,0))
            ");
            $stmt_conflict->execute([$teacher_user_id, $day_name, $period_id, $class_id, $section_id]);
            $conflict_row = $stmt_conflict->fetch(PDO::FETCH_ASSOC);
            if ($conflict_row) {
                $conflict_class_label = stripos($conflict_row['class_name'], 'class') === 0
                    ? $conflict_row['class_name']
                    : "Class {$conflict_row['class_name']}";
                $conflict = "This teacher is already teaching {$conflict_class_label}" .
                    ($conflict_row['section_name'] ? " ({$conflict_row['section_name']})" : "") .
                    " at this same day and period.";
            }

            echo json_encode(['success' => true, 'conflict' => $conflict]); exit;
        }
        if ($_POST['action'] === 'delete_entry') {
            $class_id = (int)$_POST['class_id'];
            $section_id = $_POST['section_id'] !== '' ? (int)$_POST['section_id'] : null;
            $pdo->prepare("DELETE FROM timetable_entries WHERE class_id = ? AND IFNULL(section_id,0) = IFNULL(?,0) AND day_name = ? AND period_id = ?")
                ->execute([$class_id, $section_id, $_POST['day_name'], (int)$_POST['period_id']]);
            echo json_encode(['success' => true]); exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
    }
}

// =======================================================
// FETCH DATA FOR THE UI
// =======================================================
$periods = $pdo->query("SELECT * FROM timetable_periods ORDER BY period_number")->fetchAll(PDO::FETCH_ASSOC);
$school_days = $pdo->query("SELECT * FROM school_days ORDER BY day_order")->fetchAll(PDO::FETCH_ASSOC);

// Class+Section list for the picker (only sections actually tied to a real class)
$class_sections = $pdo->query("
    SELECT c.id AS class_id, c.class_name, sec.id AS section_id, sec.section_name
    FROM classes c
    JOIN sections sec ON sec.class_id = c.id
    ORDER BY c.class_name, sec.section_name
")->fetchAll(PDO::FETCH_ASSOC);

[$selected_class, $selected_section] = array_pad(explode('|', $_GET['class_section'] ?? ''), 2, null);
$selected_section = $selected_section !== null && $selected_section !== '' ? (int)$selected_section : null;

$grid_entries = [];       // keyed "day|period_id" => entry row
$section_subjects = [];   // [{id, subject_name}]
$teachers_by_subject = []; // subject_id => [{id, full_name}]

if ($selected_class) {
    $stmt_subjects = $pdo->prepare("
        SELECT s.id, s.subject_name FROM program_subjects ps
        JOIN subjects s ON s.id = ps.subject_id
        WHERE ps.program_id = ?
        ORDER BY s.subject_name
    ");
    $stmt_subjects->execute([$selected_section]);
    $section_subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);

    $stmt_teachers = $pdo->prepare("
        SELECT tca.subject_id, u.id, u.full_name
        FROM teacher_class_assignments tca
        JOIN users u ON u.id = tca.teacher_user_id
        WHERE tca.class_id = ? AND IFNULL(tca.section_id,0) = IFNULL(?,0) AND tca.subject_id IS NOT NULL
        ORDER BY u.full_name
    ");
    $stmt_teachers->execute([(int)$selected_class, $selected_section]);
    foreach ($stmt_teachers->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teachers_by_subject[$row['subject_id']][] = ['id' => $row['id'], 'full_name' => $row['full_name']];
    }

    $stmt_entries = $pdo->prepare("
        SELECT te.*, s.subject_name, u.full_name AS teacher_name
        FROM timetable_entries te
        JOIN subjects s ON s.id = te.subject_id
        JOIN users u ON u.id = te.teacher_user_id
        WHERE te.class_id = ? AND IFNULL(te.section_id,0) = IFNULL(?,0)
    ");
    $stmt_entries->execute([(int)$selected_class, $selected_section]);
    foreach ($stmt_entries->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grid_entries[$row['day_name'] . '|' . $row['period_id']] = $row;
    }
}

$active_days = array_filter($school_days, fn($d) => $d['is_active']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management | MSST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <div id="alert-placeholder"></div>
            <h1 class="h3 mb-4">Timetable Management</h1>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary">Period Settings</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered align-middle" id="periodsTable">
                        <thead class="table-light">
                            <tr><th>#</th><th>Start</th><th>End</th><th>Label</th><th>Break?</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periods as $p): ?>
                            <tr>
                                <td><input type="number" class="form-control form-control-sm period-number" value="<?= (int)$p['period_number'] ?>" style="width:70px"></td>
                                <td><input type="time" class="form-control form-control-sm period-start" value="<?= htmlspecialchars($p['start_time']) ?>"></td>
                                <td><input type="time" class="form-control form-control-sm period-end" value="<?= htmlspecialchars($p['end_time']) ?>"></td>
                                <td><input type="text" class="form-control form-control-sm period-label" value="<?= htmlspecialchars($p['label']) ?>"></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input period-break" <?= $p['is_break'] ? 'checked' : '' ?>></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="savePeriod(this, <?= (int)$p['id'] ?>)"><i class="fas fa-save"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="deletePeriod(<?= (int)$p['id'] ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td><input type="number" class="form-control form-control-sm period-number" value="<?= count($periods) + 1 ?>" style="width:70px"></td>
                                <td><input type="time" class="form-control form-control-sm period-start" value="08:00"></td>
                                <td><input type="time" class="form-control form-control-sm period-end" value="08:45"></td>
                                <td><input type="text" class="form-control form-control-sm period-label" placeholder="e.g. Period 1 or Lunch"></td>
                                <td class="text-center"><input type="checkbox" class="form-check-input period-break"></td>
                                <td><button class="btn btn-sm btn-success" onclick="savePeriod(this, null)"><i class="fas fa-plus"></i> Add</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary">Active School Days</h6>
                </div>
                <div class="card-body d-flex flex-wrap gap-3">
                    <?php foreach ($school_days as $d): ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="day_<?= (int)$d['id'] ?>" <?= $d['is_active'] ? 'checked' : '' ?>
                               onchange="toggleDay(<?= (int)$d['id'] ?>, this.checked)">
                        <label class="form-check-label" for="day_<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['day_name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary">Weekly Timetable</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-4">
                        <label class="form-label fw-bold">Class / Section</label>
                        <select name="class_section" class="form-select" style="max-width:400px" onchange="this.form.submit()">
                            <option value="">-- Select a class/section --</option>
                            <?php foreach ($class_sections as $cs): $key = $cs['class_id'] . '|' . $cs['section_id']; ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= ($selected_class == $cs['class_id'] && $selected_section == $cs['section_id']) ? 'selected' : '' ?>>
                                    Class <?= htmlspecialchars($cs['class_name']) ?> (<?= htmlspecialchars($cs['section_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if (!$selected_class): ?>
                        <div class="alert alert-info">Select a class/section above to view or edit its timetable.</div>
                    <?php elseif (empty($periods)): ?>
                        <div class="alert alert-warning">No periods defined yet — add periods above first.</div>
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
                                            $entry = $grid_entries[$d['day_name'] . '|' . $p['id']] ?? null; ?>
                                            <td>
                                                <select class="form-select form-select-sm mb-1 cell-subject" data-day="<?= htmlspecialchars($d['day_name']) ?>" data-period="<?= (int)$p['id'] ?>">
                                                    <option value="">--</option>
                                                    <?php foreach ($section_subjects as $sub): ?>
                                                        <option value="<?= (int)$sub['id'] ?>" <?= ($entry && $entry['subject_id'] == $sub['id']) ? 'selected' : '' ?>><?= htmlspecialchars($sub['subject_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select class="form-select form-select-sm mb-1 cell-teacher">
                                                    <option value="">--</option>
                                                    <?php if ($entry): ?>
                                                        <option value="<?= (int)$entry['teacher_user_id'] ?>" selected><?= htmlspecialchars($entry['teacher_name']) ?></option>
                                                    <?php endif; ?>
                                                </select>
                                                <input type="text" class="form-control form-control-sm mb-1 cell-room" placeholder="Room" value="<?= htmlspecialchars($entry['room'] ?? '') ?>">
                                                <button class="btn btn-sm btn-primary w-100" onclick="saveCell(this)"><i class="fas fa-save"></i></button>
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showAlert(msg, type) {
    const p = document.getElementById('alert-placeholder');
    p.innerHTML = `<div class="alert alert-${type} alert-dismissible shadow-sm">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    setTimeout(() => p.innerHTML = '', 5000);
}

async function savePeriod(btn, periodId) {
    const row = btn.closest('tr');
    const fd = new FormData();
    fd.append('action', 'save_period');
    if (periodId !== null) fd.append('period_id', periodId);
    fd.append('period_number', row.querySelector('.period-number').value);
    fd.append('start_time', row.querySelector('.period-start').value);
    fd.append('end_time', row.querySelector('.period-end').value);
    fd.append('label', row.querySelector('.period-label').value);
    if (row.querySelector('.period-break').checked) fd.append('is_break', '1');

    const res = await fetch('timetable-management.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { location.reload(); } else { showAlert(data.message, 'danger'); }
}

async function deletePeriod(periodId) {
    if (!confirm('Delete this period? Any timetable entries using it will also be removed.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_period');
    fd.append('period_id', periodId);
    const res = await fetch('timetable-management.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { location.reload(); } else { showAlert(data.message, 'danger'); }
}

async function toggleDay(dayId, isActive) {
    const fd = new FormData();
    fd.append('action', 'toggle_day');
    fd.append('day_id', dayId);
    fd.append('is_active', isActive ? '1' : '0');
    const res = await fetch('timetable-management.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) showAlert(data.message, 'danger');
}

const teachersBySubject = <?= json_encode($teachers_by_subject) ?>;
const selectedClass = <?= json_encode($selected_class) ?>;
const selectedSection = <?= json_encode($selected_section) ?>;

document.querySelectorAll('.cell-subject').forEach(sel => {
    sel.addEventListener('change', () => {
        const teacherSelect = sel.closest('td').querySelector('.cell-teacher');
        const list = teachersBySubject[sel.value] || [];
        teacherSelect.innerHTML = '<option value="">--</option>' +
            list.map(t => `<option value="${t.id}">${t.full_name}</option>`).join('');
    });
});

async function saveCell(btn) {
    const td = btn.closest('td');
    const daySelectEl = td.querySelector('.cell-subject');
    const subjectId = daySelectEl.value;
    const teacherId = td.querySelector('.cell-teacher').value;
    const room = td.querySelector('.cell-room').value;

    if (!subjectId || !teacherId) { showAlert('Select both a subject and a teacher before saving.', 'warning'); return; }

    const fd = new FormData();
    fd.append('action', 'save_entry');
    fd.append('class_id', selectedClass);
    fd.append('section_id', selectedSection ?? '');
    fd.append('day_name', daySelectEl.dataset.day);
    fd.append('period_id', daySelectEl.dataset.period);
    fd.append('subject_id', subjectId);
    fd.append('teacher_user_id', teacherId);
    fd.append('room', room);

    const res = await fetch('timetable-management.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        showAlert(data.conflict ? data.conflict : 'Saved.', data.conflict ? 'warning' : 'success');
    } else {
        showAlert(data.message, 'danger');
    }
}
</script>
</body>
</html>
