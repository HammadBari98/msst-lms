<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../include/review_schema.php';

if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

ensure_review_schema($pdo);

$student_user_id = $_SESSION['student_user_db_id'];

// Teachers assigned to this student's class only
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.full_name, u.user_id_string
    FROM teacher_class_assignments tca
    JOIN users u ON u.id = tca.teacher_user_id
    JOIN student_details sd ON sd.class_id = tca.class_id
    WHERE sd.user_id = ?
    ORDER BY u.full_name
");
$stmt->execute([$student_user_id]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active review criteria, admin-configurable
$stmt = $pdo->query("SELECT id, label FROM review_criteria WHERE is_active = 1 ORDER BY sort_order ASC");
$criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rating_scale = [
    5 => 'Excellent',
    4 => 'Good',
    3 => 'Average',
    2 => 'Fair',
    1 => 'Poor'
];

$feedback_success_msg = '';
$feedback_error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $selected_teacher_id = $_POST['teacher_id'] ?? null;
    $ratings = $_POST['ratings'] ?? [];
    $comments = trim($_POST['comments'] ?? '');

    $valid_teacher_ids = array_column($teachers, 'id');

    if (empty($selected_teacher_id) || !in_array((int)$selected_teacher_id, $valid_teacher_ids)) {
        $feedback_error_msg = "Please select a valid teacher.";
    } elseif (empty($criteria)) {
        $feedback_error_msg = "No review criteria are configured yet. Please contact the administration.";
    } elseif (count($ratings) < count($criteria)) {
        $feedback_error_msg = "Please provide ratings for all aspects.";
    } elseif (empty($comments)) {
        $feedback_error_msg = "Please provide your valuable comments.";
    } else {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO teacher_reviews (student_user_id, teacher_user_id, comments)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE comments = VALUES(comments), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$student_user_id, $selected_teacher_id, $comments]);

        $review_id_stmt = $pdo->prepare("SELECT id FROM teacher_reviews WHERE student_user_id = ? AND teacher_user_id = ?");
        $review_id_stmt->execute([$student_user_id, $selected_teacher_id]);
        $review_id = $review_id_stmt->fetchColumn();

        $rating_stmt = $pdo->prepare("
            INSERT INTO teacher_review_ratings (review_id, criteria_id, rating)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating)
        ");
        foreach ($criteria as $c) {
            $val = $ratings[$c['id']] ?? null;
            if ($val !== null) {
                $rating_stmt->execute([$review_id, $c['id'], (int)$val]);
            }
        }

        $pdo->commit();

        $selected_name = '';
        foreach ($teachers as $t) {
            if ($t['id'] == $selected_teacher_id) { $selected_name = $t['full_name']; break; }
        }
        $feedback_success_msg = "Thank you! Your feedback for " . htmlspecialchars($selected_name) . " has been saved.";
    }
}

// Load any existing reviews by this student, keyed by teacher_id, for pre-filling the form via JS
$stmt = $pdo->prepare("
    SELECT tr.teacher_user_id, tr.comments, trr.criteria_id, trr.rating
    FROM teacher_reviews tr
    LEFT JOIN teacher_review_ratings trr ON trr.review_id = tr.id
    WHERE tr.student_user_id = ?
");
$stmt->execute([$student_user_id]);
$existing_reviews = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $tid = $row['teacher_user_id'];
    if (!isset($existing_reviews[$tid])) {
        $existing_reviews[$tid] = ['comments' => $row['comments'], 'ratings' => []];
    }
    if ($row['criteria_id']) {
        $existing_reviews[$tid]['ratings'][$row['criteria_id']] = (int)$row['rating'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Feedback | <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'LMS'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #4e73df; --secondary: #1cc88a; --accent: #36b9cc;
            --light-bg: #f8f9fc; --dark-text: #5a5c69;
        }
        body {
            font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg);
            overflow-x: hidden;
        }
        .card {
            border: none; border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .card-header {
            background-color: var(--primary); color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        .feedback-form-container .card-body {
            padding: 2rem;
        }
        .feedback-form-container .rating-group {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #efefef;
        }
        .feedback-form-container .rating-group:last-of-type {
            border-bottom: none;
            margin-bottom: 0.5rem;
        }
        .feedback-form-container .rating-group label.aspect-label {
            font-weight: 500;
            color: var(--dark-text);
            margin-bottom: 0.75rem;
            display: block;
        }
        .feedback-form-container .rating-options {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        .feedback-form-container .rating-options .form-check {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        .feedback-form-container .rating-options .form-check-label {
            font-size: 0.9rem;
        }
        #editingNotice { display: none; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div id="main-content">
        <?php include 'header.php'; ?>

        <div class="content-wrapper">
            <div class="container-fluid">
                <h1 class="h3 mb-4 text-gray-800 page-main-title">Teacher Feedback</h1>

                <?php if ($feedback_success_msg): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($feedback_success_msg) ?></div>
                <?php endif; ?>
                <?php if ($feedback_error_msg): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($feedback_error_msg) ?></div>
                <?php endif; ?>

                <?php if (empty($teachers)): ?>
                    <div class="alert alert-info">No teachers are currently assigned to your class, so there's nothing to review yet.</div>
                <?php elseif (empty($criteria)): ?>
                    <div class="alert alert-info">The review form isn't set up yet. Please check back later.</div>
                <?php else: ?>
                <div class="card shadow mb-4 feedback-form-container">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold" style="color:white;">Submit Your Feedback</h6>
                    </div>
                    <div class="card-body">
                        <div id="editingNotice" class="alert alert-warning">
                            <i class="fas fa-info-circle"></i> You've already reviewed this teacher. Submitting again will update your previous review.
                        </div>
                        <form action="teacher_feedback.php" method="POST" id="feedbackForm">
                            <div class="mb-4">
                                <label for="teacher_id" class="form-label fs-5">Select Teacher:</label>
                                <select class="form-select form-select-lg" id="teacher_id" name="teacher_id" required>
                                    <option value="" selected disabled>-- Choose a Teacher --</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3">Rate the following aspects:</h5>

                            <?php foreach ($criteria as $c): ?>
                            <div class="rating-group">
                                <label class="aspect-label"><?= htmlspecialchars($c['label']) ?>:</label>
                                <div class="rating-options">
                                    <?php foreach (array_reverse($rating_scale, true) as $value => $text): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ratings[<?= $c['id'] ?>]" id="rating_<?= $c['id'] ?>_<?= $value ?>" value="<?= $value ?>" required>
                                        <label class="form-check-label" for="rating_<?= $c['id'] ?>_<?= $value ?>"><?= htmlspecialchars($text) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <hr class="my-4">

                            <div class="mb-3">
                                <label for="comments" class="form-label fs-5">Comments & Suggestions:</label>
                                <textarea class="form-control" id="comments" name="comments" rows="5" placeholder="Please provide detailed feedback, suggestions for improvement, or specific examples." required minlength="10"></textarea>
                            </div>

                            <button type="submit" name="submit_feedback" class="btn btn-primary btn-lg mt-3"><i class="fas fa-paper-plane"></i> Submit Feedback</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <footer class="footer mt-auto py-3 bg-white">
            <div class="container-fluid">
                <div class="text-center">
                    <span class="text-muted">© LMS <?= date('Y') ?></span>
                </div>
            </div>
        </footer>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');
        if (sidebar && mainContent && toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            });
            toggleBtn.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show');
                }
            });
        }

        // Pre-fill form when a teacher the student already reviewed is selected
        const existingReviews = <?= json_encode($existing_reviews, JSON_HEX_TAG) ?>;
        const teacherSelect = document.getElementById('teacher_id');
        const editingNotice = document.getElementById('editingNotice');

        teacherSelect.addEventListener('change', function() {
            const review = existingReviews[this.value];
            document.querySelectorAll('#feedbackForm input[type="radio"]').forEach(r => r.checked = false);
            document.getElementById('comments').value = '';

            if (review) {
                editingNotice.style.display = 'block';
                Object.keys(review.ratings).forEach(criteriaId => {
                    const val = review.ratings[criteriaId];
                    const radio = document.getElementById('rating_' + criteriaId + '_' + val);
                    if (radio) radio.checked = true;
                });
                document.getElementById('comments').value = review.comments || '';
            } else {
                editingNotice.style.display = 'none';
            }
        });
    </script>
</body>
</html>
