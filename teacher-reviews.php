<?php
session_start();
require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/include/review_schema.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

ensure_review_schema($pdo);

// Teachers with review counts + average overall rating (across whatever criteria exist)
$stmt = $pdo->query("
    SELECT
        u.id,
        u.full_name,
        u.user_id_string,
        COUNT(DISTINCT tr.id) AS review_count,
        AVG(trr.rating) AS avg_rating
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN teacher_reviews tr ON tr.teacher_user_id = u.id
    LEFT JOIN teacher_review_ratings trr ON trr.review_id = tr.id
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

// Attach each review's per-criteria ratings (keyed by criteria_id)
$review_ids = array_column($all_reviews, 'id');
$ratings_by_review = [];
if (!empty($review_ids)) {
    $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
    $stmt = $pdo->prepare("SELECT review_id, criteria_id, rating FROM teacher_review_ratings WHERE review_id IN ($placeholders)");
    $stmt->execute($review_ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ratings_by_review[$r['review_id']][$r['criteria_id']] = (int)$r['rating'];
    }
}
foreach ($all_reviews as &$rev) {
    $rev['ratings'] = $ratings_by_review[$rev['id']] ?? [];
}
unset($rev);

$reviews_by_teacher = [];
foreach ($all_reviews as $rev) {
    $reviews_by_teacher[$rev['teacher_user_id']][] = $rev;
}

// All criteria (including inactive ones, so historical reviews on retired
// criteria still render with a real label instead of a bare id)
$stmt = $pdo->query("SELECT id, label, is_active FROM review_criteria ORDER BY sort_order ASC");
$all_criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                <tr id="reviewDetailHeadRow"></tr>
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
                    <div id="editRatingsContainer"></div>
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
        const allCriteria = <?= json_encode($all_criteria, JSON_HEX_TAG) ?>;
        const criteriaLabelById = {};
        allCriteria.forEach(c => { criteriaLabelById[c.id] = c.label; });

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

            // Show a column for every criteria referenced by at least one of this teacher's reviews
            const usedIds = new Set();
            reviews.forEach(r => Object.keys(r.ratings).forEach(cid => usedIds.add(cid)));
            const columns = allCriteria.filter(c => usedIds.has(String(c.id)));

            document.getElementById('reviewDetailHeadRow').innerHTML =
                '<th>Student</th>' +
                columns.map(c => `<th>${escapeHtml(c.label)}</th>`).join('') +
                '<th>Comment</th><th>Date</th><th class="text-center">Actions</th>';

            const tbody = document.getElementById('reviewDetailBody');
            tbody.innerHTML = reviews.map(r => `
                <tr>
                    <td>${escapeHtml(r.student_name)}<br><span class="text-muted small">${escapeHtml(r.student_code)}</span></td>
                    ${columns.map(c => `<td class="text-center">${r.ratings[c.id] ?? '-'}</td>`).join('')}
                    <td style="max-width: 260px; white-space: normal;">${escapeHtml(r.comments)}</td>
                    <td class="text-nowrap">${escapeHtml(r.updated_at)}</td>
                    <td class="text-center text-nowrap">
                        <button class="btn btn-sm btn-outline-primary edit-review-btn" data-id="${r.id}" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger delete-review-btn" data-id="${r.id}" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');

            const dateColIndex = 1 + columns.length;
            const actionsColIndex = dateColIndex + 1;

            if (reviewDetailTable) {
                reviewDetailTable.destroy();
                reviewDetailTable = null;
            }
            reviewDetailTable = $('#reviewDetailTable').DataTable({
                pageLength: 10,
                order: [[dateColIndex, 'desc']],
                columnDefs: [{ orderable: false, targets: actionsColIndex }]
            });

            new bootstrap.Modal(document.getElementById('reviewsModal')).show();
        }

        const ratingTexts = { 5: 'Excellent', 4: 'Good', 3: 'Average', 2: 'Fair', 1: 'Poor' };

        $(document).on('click', '.edit-review-btn', function() {
            const review = findReviewById($(this).data('id'));
            if (!review) return;

            document.getElementById('editReviewId').value = review.id;
            document.getElementById('editRatingsContainer').innerHTML = Object.keys(review.ratings).map(cid => {
                const label = criteriaLabelById[cid] || ('Criteria #' + cid);
                const current = review.ratings[cid];
                const options = [5, 4, 3, 2, 1].map(v =>
                    `<option value="${v}" ${v === current ? 'selected' : ''}>${v} - ${ratingTexts[v]}</option>`
                ).join('');
                return `<div class="mb-3">
                    <label class="form-label">${escapeHtml(label)}</label>
                    <select class="form-select edit-rating-input" data-criteria-id="${cid}">${options}</select>
                </div>`;
            }).join('');
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
            document.querySelectorAll('.edit-rating-input').forEach(sel => {
                formData.append('ratings[' + sel.dataset.criteriaId + ']', sel.value);
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
