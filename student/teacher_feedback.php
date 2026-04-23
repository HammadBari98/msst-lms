<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Uncomment if you'll connect to DB

// Ensure student is logged in to provide feedback
if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php'); // Adjust to your student login page
    exit();
}

// --- Placeholder Data for Form ---
$teachers = [
    // In a real app, fetch from a 'teachers' table: teacher_id => teacher_name
    1 => "Mr. Arshad Khan (Mathematics)",
    2 => "Ms. Bushra Sharma (Physics)",
    3 => "Mrs. Deepa Singh (English)",
    4 => "Mr. Ebrahim David (Chemistry)",
    5 => "Ms. Ghazala Fatima (Biology)",
];

// You might have a more complex course list, possibly filtered by teacher selection later
$courses_taught_by_teacher = [ // Example: teacher_id => [course_id => course_name]
    1 => [101 => "Mathematics - Grade 10", 102 => "Advanced Algebra - Grade 11"],
    2 => [201 => "Physics - Grade 10", 202 => "Mechanics - Grade 11"],
    3 => [301 => "English Literature - Grade 10", 302 => "English Language - Grade 10"],
    // ... and so on
];


$feedback_aspects = [
    'clarity' => 'Clarity of Explanation',
    'knowledge' => 'Subject Knowledge',
    'engagement' => 'Engagement & Interaction',
    'helpfulness' => 'Helpfulness & Support',
    'feedback_quality' => 'Quality of Feedback on Assignments',
];

$rating_scale = [ // value => label
    5 => 'Excellent',
    4 => 'Good',
    3 => 'Average',
    2 => 'Fair',
    1 => 'Poor'
];

$feedback_success_msg = '';
$feedback_error_msg = '';

// --- Handle Form Submission (Basic Example) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    // Sanitize and validate inputs (essential in a real application)
    $selected_teacher_id = $_POST['teacher_id'] ?? null;
    // $selected_course_id = $_POST['course_id'] ?? null; // If you add course selection
    $ratings = $_POST['ratings'] ?? [];
    $comments = trim($_POST['comments'] ?? '');

    if (empty($selected_teacher_id)) {
        $feedback_error_msg = "Please select a teacher.";
    } elseif (empty($ratings) || count($ratings) < count($feedback_aspects)) {
        $feedback_error_msg = "Please provide ratings for all aspects.";
    } elseif (empty($comments)) {
        $feedback_error_msg = "Please provide your valuable comments.";
    } else {
        // In a real application:
        // 1. Connect to the database.
        // 2. Prepare an SQL statement to insert the feedback.
        //    (e.g., student_id, teacher_id, aspect, rating, comment, submission_date)
        // 3. Execute the statement.
        // For now, just a success message:
        $feedback_success_msg = "Thank you! Your feedback for " . htmlspecialchars($teachers[$selected_teacher_id] ?? 'the selected teacher') . " has been submitted successfully.";
        // You might want to clear the form or redirect after successful submission.
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
        /* TEMPLATE STYLES: Copied EXACTLY from your working profile.php <style> block */
        :root {
            --primary: #4e73df; --secondary: #1cc88a; --accent: #36b9cc;
            --light-bg: #f8f9fc; --dark-text: #5a5c69;
        }
        body {
            font-family: 'Segoe UI', sans-serif; background-color: var(--light-bg);
            overflow-x: hidden; /* Essential for sidebar functionality */
        }
        .profile-img { /* Kept for theme consistency if cards are used elsewhere */
            width: 120px; height: 120px; border-radius: 50%; background-color: #e9ecef;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;
        }
        .profile-img i { font-size: 3rem; color: var(--primary); }
        .card { /* Global card style from your profile.php */
            border: none; border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .card-header { /* Global card-header style from your profile.php */
            background-color: var(--primary); color: white;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        /* If your profile.php has more styles in its <style> block for main layout, copy them here. */
        /* END OF COPIED TEMPLATE STYLES */

        /* FEEDBACK FORM SPECIFIC STYLES */
        .feedback-form-container .card-body {
            padding: 2rem; /* More padding inside the card */
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
            justify-content: space-around; /* Evenly space rating options */
            flex-wrap: wrap; /* Allow wrapping on small screens */
        }
        .feedback-form-container .rating-options .form-check {
            margin-right: 10px; /* Space between radio buttons */
            margin-bottom: 5px;
        }
        .feedback-form-container .rating-options .form-check-label {
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus { /* Boostrap focus glow */
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.25); /* Assuming you have --primary-rgb for BS5 focus */
        }
        /* For the above to work, you might need --primary-rgb in :root if not already there for BS compatibility */
        /* :root { --primary-rgb: 78, 115, 223; } Example for #4e73df */


        /* PRINT STYLES - Mostly to hide dashboard, form itself isn't usually printed */
        @media print {
            body {
                background-color: #fff !important; padding: 0 !important; margin: 0 !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            #sidebar,
            #main-content > header, /* Or more specific selector for your header output */
            .dashboard-top-header, /* Example if header has this class */
            #main-content > footer.footer,
            .page-main-title,
            .feedback-form-container .btn-primary /* Hide submit button in print */
            {
                display: none !important;
            }
            #main-content {
                margin-left: 0 !important; padding: 10mm !important; /* Add some padding for printed form if needed */
                width: 100% !important; border: none !important; box-shadow: none !important; float: none !important;
            }
            .content-wrapper, .container-fluid { padding: 0 !important; margin: 0 !important; }
            .feedback-form-container .card { box-shadow: none !important; border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; // Creates #sidebar. Path relative to this file. ?>

    <div id="main-content"> <?php include 'header.php'; // Creates #sidebarToggle. Path relative to this file. ?>

        <div class="content-wrapper"> <div class="container-fluid"> <h1 class="h3 mb-4 text-gray-800 page-main-title">Teacher Feedback</h1>

                <?php if ($feedback_success_msg): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($feedback_success_msg) ?></div>
                <?php endif; ?>
                <?php if ($feedback_error_msg): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($feedback_error_msg) ?></div>
                <?php endif; ?>

                <div class="card shadow mb-4 feedback-form-container">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold" style="color:white;">Submit Your Feedback</h6>
                        </div>
                    <div class="card-body">
                        <form action="teacher_feedback.php" method="POST">
                            <div class="mb-4">
                                <label for="teacher_id" class="form-label fs-5">Select Teacher:</label>
                                <select class="form-select form-select-lg" id="teacher_id" name="teacher_id" required>
                                    <option value="" selected disabled>-- Choose a Teacher --</option>
                                    <?php foreach ($teachers as $id => $name): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3">Rate the following aspects:</h5>

                            <?php foreach ($feedback_aspects as $key => $label): ?>
                            <div class="rating-group">
                                <label class="aspect-label"><?= htmlspecialchars($label) ?>:</label>
                                <div class="rating-options">
                                    <?php foreach (array_reverse($rating_scale, true) as $value => $text): // Show Excellent first ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="ratings[<?= $key ?>]" id="rating_<?= $key ?>_<?= $value ?>" value="<?= $value ?>" required>
                                        <label class="form-check-label" for="rating_<?= $key ?>_<?= $value ?>"><?= htmlspecialchars($text) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <hr class="my-4">

                            <div class="mb-3">
                                <label for="comments" class="form-label fs-5">Comments & Suggestions:</label>
                                <textarea class="form-control" id="comments" name="comments" rows="5" placeholder="Please provide detailed feedback, suggestions for improvement, or specific examples. (Min. 20 characters recommended)" required minlength="10"></textarea>
                                <div class="form-text">Your feedback is valuable and will be kept confidential where appropriate.</div>
                            </div>

                            <button type="submit" name="submit_feedback" class="btn btn-primary btn-lg mt-3"><i class="fas fa-paper-plane"></i> Submit Feedback</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <footer class="footer mt-auto py-3 bg-white">
            <div class="container-fluid">
                <div class="text-center">
                    <span class="text-muted">© LMS <?= date('Y') ?></span>
                </div>
            </div>
        </footer>
    </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // This script is an exact copy from your profile.php for sidebar toggling.
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');

        // Debug: Check these in your browser console (F12)
        console.log('Teacher Feedback Page JS - Looking for elements:');
        console.log('Sidebar Element (#sidebar):', sidebar);
        console.log('Main Content Element (#main-content):', mainContent);
        console.log('Toggle Button Element (#sidebarToggle):', toggleBtn);

        if (sidebar && mainContent && toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            });
            toggleBtn.addEventListener('click', () => { // Second listener from your profile.php
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show');
                }
            });
            console.log('Sidebar toggle event listeners have been attached.');
        } else {
            let missing = [];
            if (!sidebar) missing.push("#sidebar"); if (!mainContent) missing.push("#main-content"); if (!toggleBtn) missing.push("#sidebarToggle");
            console.error("LAYOUT/TOGGLE SCRIPT ERROR: Critical element(s) not found: " + missing.join(', ') + ". Verify IDs and includes.");
        }

        // Optional: JS for dynamic course dropdown based on teacher (more advanced)
        // document.getElementById('teacher_id')?.addEventListener('change', function() {
        //     const teacherId = this.value;
        //     const courseSelect = document.getElementById('course_id');
        //     // Clear existing options
        //     courseSelect.innerHTML = '<option value="" selected disabled>-- Select course --</option>';
        //     if (teacherId && coursesByTeacher[teacherId]) { // coursesByTeacher would be a JS object
        //         coursesByTeacher[teacherId].forEach(course => {
        //             let option = new Option(course.name, course.id);
        //             courseSelect.add(option);
        //         });
        //     }
        // });
    </script>
</body>
</html>