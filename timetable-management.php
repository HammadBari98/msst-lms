<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

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

            if ($id !== '') {
                $pdo->prepare("UPDATE timetable_periods SET period_number=?, start_time=?, end_time=?, is_break=?, label=? WHERE id=?")
                    ->execute([$period_number, $start_time, $end_time, $is_break, $label, (int)$id]);
            } else {
                $pdo->prepare("INSERT INTO timetable_periods (period_number, start_time, end_time, is_break, label) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$period_number, $start_time, $end_time, $is_break, $label]);
            }
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'delete_period') {
            $pdo->prepare("DELETE FROM timetable_periods WHERE id = ?")->execute([(int)$_POST['period_id']]);
            $pdo->prepare("DELETE FROM timetable_entries WHERE period_id = ?")->execute([(int)$_POST['period_id']]);
            echo json_encode(['success' => true]); exit;
        }
        if ($_POST['action'] === 'toggle_day') {
            $pdo->prepare("UPDATE school_days SET is_active = ? WHERE id = ?")
                ->execute([(int)$_POST['is_active'], (int)$_POST['day_id']]);
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
</script>
</body>
</html>
