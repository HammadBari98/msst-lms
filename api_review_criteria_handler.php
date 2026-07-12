<?php
session_start();
require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/include/review_schema.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

ensure_review_schema($pdo);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        $label = trim($_POST['label'] ?? '');
        if ($label === '') {
            echo json_encode(['success' => false, 'message' => 'Label is required']);
            break;
        }
        $max_order = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM review_criteria")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO review_criteria (label, sort_order, is_active) VALUES (?, ?, 1)");
        $stmt->execute([$label, $max_order + 1]);
        echo json_encode(['success' => true, 'message' => 'Criteria added']);
        break;

    case 'update':
        $id = $_POST['id'] ?? null;
        $label = trim($_POST['label'] ?? '');
        if (!$id || $label === '') {
            echo json_encode(['success' => false, 'message' => 'Id and label are required']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE review_criteria SET label = ? WHERE id = ?");
        $stmt->execute([$label, $id]);
        echo json_encode(['success' => true, 'message' => 'Criteria updated']);
        break;

    case 'toggle_active':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Id is required']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE review_criteria SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Status updated']);
        break;

    case 'delete':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Id is required']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM review_criteria WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Criteria deleted']);
        break;

    case 'move':
        $id = $_POST['id'] ?? null;
        $direction = $_POST['direction'] ?? '';
        if (!$id || !in_array($direction, ['up', 'down'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            break;
        }

        $all = $pdo->query("SELECT id, sort_order FROM review_criteria ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
        $index = null;
        foreach ($all as $i => $row) {
            if ($row['id'] == $id) { $index = $i; break; }
        }
        if ($index === null) {
            echo json_encode(['success' => false, 'message' => 'Criteria not found']);
            break;
        }

        $swap_index = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swap_index < 0 || $swap_index >= count($all)) {
            echo json_encode(['success' => true, 'message' => 'No change']);
            break;
        }

        $a = $all[$index];
        $b = $all[$swap_index];
        $pdo->beginTransaction();
        $upd = $pdo->prepare("UPDATE review_criteria SET sort_order = ? WHERE id = ?");
        $upd->execute([$b['sort_order'], $a['id']]);
        $upd->execute([$a['sort_order'], $b['id']]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Reordered']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
