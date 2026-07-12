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
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-comments me-2"></i>Reviews for <span id="reviewsTeacherName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="reviewDetailTable" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Clarity</th>
                                    <th>Knowledge</th>
                                    <th>Engagement</th>
                                    <th>Helpfulness</th>
                                    <th>Feedback</th>
                                    <th>Comment</th>
                                    <th>Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reviewDetailBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editReviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Review</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" id="editReviewId">
                    <?php foreach ($aspect_labels as $key => $label): ?>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($label) ?></label>
                        <select class="form-select" id="edit_<?= $key ?>">
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Average</option>
                            <option value="2">2 - Fair</option>
                            <option value="1">1 - Poor</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                    <div class="mb-3">
                        <label class="form-label">Comment</label>
                        <textarea class="form-control" id="editComments" rows="4" required minlength="10"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditReviewBtn"><i class="fas fa-save me-2"></i>Save Changes</button>
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

        let reviewDetailTable = null;
        let currentTeacherId = null;
        let currentTeacherName = '';

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        }

        function findReviewById(id) {
            for (const teacherId in reviewsByTeacher) {
                const found = reviewsByTeacher[teacherId].find(r => String(r.id) === String(id));
                if (found) return found;
            }
            return null;
        }

        function showReviews(teacherId, teacherName) {
            currentTeacherId = teacherId;
            currentTeacherName = teacherName;
            document.getElementById('reviewsTeacherName').textContent = teacherName;
            const reviews = reviewsByTeacher[teacherId] || [];
            const tbody = document.getElementById('reviewDetailBody');

            tbody.innerHTML = reviews.map(r => `
                <tr>
                    <td>${escapeHtml(r.student_name)}<br><span class="text-muted small">${escapeHtml(r.student_code)}</span></td>
                    <td class="text-center">${r.rating_clarity}</td>
                    <td class="text-center">${r.rating_knowledge}</td>
                    <td class="text-center">${r.rating_engagement}</td>
                    <td class="text-center">${r.rating_helpfulness}</td>
                    <td class="text-center">${r.rating_feedback_quality}</td>
                    <td style="max-width: 260px; white-space: normal;">${escapeHtml(r.comments)}</td>
                    <td class="text-nowrap">${escapeHtml(r.updated_at)}</td>
                    <td class="text-center text-nowrap">
                        <button class="btn btn-sm btn-outline-primary edit-review-btn" data-id="${r.id}" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger delete-review-btn" data-id="${r.id}" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');

            if (reviewDetailTable) {
                reviewDetailTable.destroy();
                reviewDetailTable = null;
            }
            reviewDetailTable = $('#reviewDetailTable').DataTable({
                pageLength: 10,
                order: [[7, 'desc']],
                columnDefs: [{ orderable: false, targets: 8 }]
            });

            new bootstrap.Modal(document.getElementById('reviewsModal')).show();
        }

        $(document).on('click', '.edit-review-btn', function() {
            const review = findReviewById($(this).data('id'));
            if (!review) return;

            document.getElementById('editReviewId').value = review.id;
            Object.keys(aspectLabels).forEach(key => {
                document.getElementById('edit_' + key).value = review[key];
            });
            document.getElementById('editComments').value = review.comments;

            const reviewsModalInstance = bootstrap.Modal.getInstance(document.getElementById('reviewsModal'));
            if (reviewsModalInstance) reviewsModalInstance.hide();

            new bootstrap.Modal(document.getElementById('editReviewModal')).show();
        });

        document.getElementById('editReviewModal').addEventListener('hidden.bs.modal', function() {
            if (currentTeacherId !== null) {
                showReviews(currentTeacherId, currentTeacherName);
            }
        });

        document.getElementById('saveEditReviewBtn').addEventListener('click', async function() {
            const id = document.getElementById('editReviewId').value;
            const comments = document.getElementById('editComments').value.trim();

            if (comments.length < 10) {
                alert('Comment must be at least 10 characters.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('id', id);
            Object.keys(aspectLabels).forEach(key => {
                formData.append(key, document.getElementById('edit_' + key).value);
            });
            formData.append('comments', comments);

            try {
                const response = await fetch('api_teacher_reviews_handler.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    setTimeout(() => location.reload(), 300);
                } else {
                    alert(result.message || 'Failed to update review');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        $(document).on('click', '.delete-review-btn', async function() {
            const id = $(this).data('id');
            if (!confirm('Delete this review? This cannot be undone.')) return;

            try {
                const response = await fetch('api_teacher_reviews_handler.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'delete', id })
                });
                const result = await response.json();
                if (result.success) {
                    setTimeout(() => location.reload(), 300);
                } else {
                    alert(result.message || 'Failed to delete review');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

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
