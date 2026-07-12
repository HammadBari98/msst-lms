<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Teachers with review counts + average overall rating
$stmt = $pdo->query("
    SELECT
        u.id,
        u.full_name,
        u.user_id_string,
        COUNT(tr.id) AS review_count,
        AVG((tr.rating_clarity + tr.rating_knowledge + tr.rating_engagement + tr.rating_helpfulness + tr.rating_feedback_quality) / 5) AS avg_rating
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN teacher_reviews tr ON tr.teacher_user_id = u.id
    WHERE r.role_name = 'Teacher'
    GROUP BY u.id
    ORDER BY review_count DESC, u.full_name
");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// All individual reviews, grouped client-side by teacher_user_id
$stmt = $pdo->query("
    SELECT tr.*, s.full_name AS student_name, s.user_id_string AS student_code
    FROM teacher_reviews tr
    JOIN users s ON s.id = tr.student_user_id
    ORDER BY tr.updated_at DESC
");
$all_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
$reviews_by_teacher = [];
foreach ($all_reviews as $rev) {
    $reviews_by_teacher[$rev['teacher_user_id']][] = $rev;
}

$aspect_labels = [
    'rating_clarity' => 'Clarity of Explanation',
    'rating_knowledge' => 'Subject Knowledge',
    'rating_engagement' => 'Engagement & Interaction',
    'rating_helpfulness' => 'Helpfulness & Support',
    'rating_feedback_quality' => 'Quality of Feedback',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Reviews | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .rating-stars { color: #f6c23e; white-space: nowrap; }
        .rating-stars .fa-star.empty { color: #dee2e6; }
        .review-item {
            border: 1px solid #eaecf4;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .review-item .aspect-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #5a5c69;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div id="main-content">
        <?php include 'header.php'; ?>

        <div class="content-wrapper">
            <div class="container-fluid">

                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Teacher Reviews</h1>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Reviews by Teacher</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="reviewsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Teacher ID</th>
                                        <th>Name</th>
                                        <th>Reviews</th>
                                        <th>Average Rating</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($teacher['user_id_string']) ?></td>
                                            <td><?= htmlspecialchars($teacher['full_name']) ?></td>
                                            <td><?= (int)$teacher['review_count'] ?></td>
                                            <td>
                                                <?php if ($teacher['review_count'] > 0): ?>
                                                    <?php $avg = round($teacher['avg_rating'], 1); ?>
                                                    <span class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?= $i <= round($avg) ? '' : ' empty' ?>"></i>
                                                        <?php endfor; ?>
                                                    </span>
                                                    <span class="ms-1"><?= $avg ?> / 5</span>
                                                <?php else: ?>
                                                    <span class="text-muted small">No reviews yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-primary" <?= $teacher['review_count'] > 0 ? '' : 'disabled' ?>
                                                    onclick="showReviews(<?= $teacher['id'] ?>, '<?= htmlspecialchars($teacher['full_name'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-eye me-1"></i> View Reviews
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include 'footer.php'; ?>
    </div>

    <div class="modal fade" id="reviewsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-comments me-2"></i>Reviews for <span id="reviewsTeacherName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light" id="reviewsModalBody" style="max-height: 70vh; overflow-y: auto;">
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#reviewsTable').DataTable({
                "pageLength": 10,
                "columnDefs": [
                    { "orderable": false, "targets": 4 }
                ]
            });
        });

        const reviewsByTeacher = <?= json_encode($reviews_by_teacher, JSON_HEX_TAG) ?>;
        const aspectLabels = <?= json_encode($aspect_labels) ?>;

        function starsHtml(value) {
            let html = '<span class="rating-stars">';
            for (let i = 1; i <= 5; i++) {
                html += `<i class="fas fa-star${i <= value ? '' : ' empty'}"></i>`;
            }
            return html + '</span>';
        }

        function showReviews(teacherId, teacherName) {
            document.getElementById('reviewsTeacherName').textContent = teacherName;
            const reviews = reviewsByTeacher[teacherId] || [];
            const body = document.getElementById('reviewsModalBody');

            if (reviews.length === 0) {
                body.innerHTML = '<p class="text-muted text-center mb-0">No reviews yet.</p>';
            } else {
                body.innerHTML = reviews.map(r => {
                    const aspectRows = Object.keys(aspectLabels).map(key =>
                        `<div class="aspect-row"><span>${aspectLabels[key]}</span>${starsHtml(r[key])}</div>`
                    ).join('');
                    return `
                        <div class="review-item">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>${r.student_name} <span class="text-muted small">(${r.student_code})</span></strong>
                                <span class="text-muted small">${r.updated_at}</span>
                            </div>
                            ${aspectRows}
                            <p class="mt-2 mb-0">${r.comments.replace(/</g, '&lt;')}</p>
                        </div>
                    `;
                }).join('');
            }

            new bootstrap.Modal(document.getElementById('reviewsModal')).show();
        }

        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show-sidebar');
                }
            });
        }
    </script>
</body>
</html>
