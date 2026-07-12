<?php
session_start();
require_once __DIR__ . '/config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update':
        $id = $_POST['id'] ?? null;
        $rating_fields = ['rating_clarity', 'rating_knowledge', 'rating_engagement', 'rating_helpfulness', 'rating_feedback_quality'];

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Review id is required']);
            break;
        }

        foreach ($rating_fields as $field) {
            $val = $_POST[$field] ?? null;
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

        $stmt = $pdo->prepare("
            UPDATE teacher_reviews
            SET rating_clarity = ?, rating_knowledge = ?, rating_engagement = ?, rating_helpfulness = ?, rating_feedback_quality = ?, comments = ?
            WHERE id = ?
        ");
        $stmt->execute([
            (int)$_POST['rating_clarity'],
            (int)$_POST['rating_knowledge'],
            (int)$_POST['rating_engagement'],
            (int)$_POST['rating_helpfulness'],
            (int)$_POST['rating_feedback_quality'],
            $comments,
            $id
        ]);
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
