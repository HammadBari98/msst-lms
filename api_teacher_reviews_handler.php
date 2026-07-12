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
    case 'update':
        $id = $_POST['id'] ?? null;
        $ratings = $_POST['ratings'] ?? [];

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Review id is required']);
            break;
        }

        if (empty($ratings)) {
            echo json_encode(['success' => false, 'message' => 'At least one rating is required']);
            break;
        }

        foreach ($ratings as $val) {
            if (!is_numeric($val) || $val < 1 || $val > 5) {
                echo json_encode(['success' => false, 'message' => 'All ratings must be between 1 and 5']);
                exit;
            }
        }

        $comments = trim($_POST['comments'] ?? '');
        if ($comments === '') {
            echo json_encode(['success' => false, 'message' => 'Comment is required']);
            break;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE teacher_reviews SET comments = ? WHERE id = ?");
        $stmt->execute([$comments, $id]);

        $rating_stmt = $pdo->prepare("UPDATE teacher_review_ratings SET rating = ? WHERE review_id = ? AND criteria_id = ?");
        foreach ($ratings as $criteria_id => $val) {
            $rating_stmt->execute([(int)$val, $id, (int)$criteria_id]);
        }
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Review updated']);
        break;

    case 'delete':
        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Review id is required']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM teacher_reviews WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Review deleted']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
