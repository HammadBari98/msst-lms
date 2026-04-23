<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Standard login check
if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['user_db_id'])) {
    header('Location: login.php');
    exit();
}

// Set the student name for the header right away from the session
$student_name = $_SESSION['student_name'] ?? 'Student';
$student = null;
$page_error = '';

try {
    // Then, query the database for the most current profile details
    $current_user_db_id = $_SESSION['user_db_id'];

    $sql = "SELECT 
                u.id, u.user_id_string, u.full_name, u.email, u.status, u.profile_picture,
                sd.department,
                c.class_name,
                sec.section_name
            FROM users u
            LEFT JOIN student_details sd ON u.id = sd.user_id
            LEFT JOIN classes c ON sd.class_id = c.id
            LEFT JOIN sections sec ON sd.section_id = sec.id
            WHERE u.id = :user_db_id AND u.status = 'Active'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_db_id' => $current_user_db_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $page_error = "Your profile data could not be loaded. Please contact the administration.";
    }

} catch (PDOException $e) {
    $page_error = "A database error occurred while loading your profile.";
    error_log("Profile Page Error: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($student_name) ?> | Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        .profile-pic-container { position: relative; width: 150px; height: 150px; margin: 20px auto; }
        .profile-pic { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .upload-btn-wrapper { position: absolute; bottom: 5px; right: 5px; }
        .upload-btn { background-color: white; color: #906833; border: none; border-radius: 50%; width: 40px; height: 40px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); display: grid; place-items: center; }
        .form-control[readonly] { background-color: #e9ecef; cursor: not-allowed; }
    </style>
</head>
<body>
    <?php include 'sidebar.php' ?>
    <div id="main-content">
        <?php include 'header.php' ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <?php if (!empty($page_error)): ?>
                    <div class="alert alert-warning text-center">
                        <h4>Profile Not Available</h4>
                        <p><?= htmlspecialchars($page_error) ?></p>
                    </div>
                <?php elseif ($student): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <div class="profile-pic-container">
                                        <img src="<?= !empty($student['profile_picture']) ? htmlspecialchars('../' . $student['profile_picture']) : 'https://ui-avatars.com/api/?name='.urlencode($student['full_name']).'&background=906833&color=fff&size=150' ?>" 
                                             alt="Profile Picture" class="profile-pic" id="profile-pic-preview">
                                        <div class="upload-btn-wrapper" style="display: none;">
                                            <button class="btn upload-btn" type="button" onclick="$('#photo_upload').click();"><i class="fas fa-camera"></i></button>
                                        </div>
                                    </div>
                                    <h4 class="mt-2" id="display-full-name"><?= htmlspecialchars($student['full_name']) ?></h4>
                                    <p class="text-muted">Roll Number: <?= htmlspecialchars($student['user_id_string'] ?? 'N/A') ?></p>
                                </div>
                                <div class="col-md-8">
                                    <div id="alert-placeholder"></div>
                                    <form id="profile-form" enctype="multipart/form-data">
                                        <input type="file" name="photo" id="photo_upload" class="d-none" accept="image/png, image/jpeg">
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="m-0">Profile Details</h5>
                                            <button id="edit-btn" type="button" class="btn btn-outline-primary btn-sm"><i class="fas fa-pencil-alt me-1"></i> Edit Profile</button>
                                        </div>

                                        <h6>Academic Information</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-6"><label class="form-label">Class</label><input type="text" class="form-control" value="<?= htmlspecialchars($student['class_name'] ?? 'Not Assigned') ?>" readonly></div>
                                            <div class="col-md-6"><label class="form-label">Section</label><input type="text" class="form-control" value="<?= htmlspecialchars($student['section_name'] ?? 'Not Assigned') ?>" readonly></div>
                                        </div>

                                        <h6>Contact Information</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="full_name" class="form-label">Full Name</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" readonly>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 text-end btn-group-edit" style="display: none;">
                                            <button type="button" id="cancel-btn" class="btn btn-secondary me-2">Cancel</button>
                                            <button type="submit" id="save-btn" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        const form = $('#profile-form');
        const editableFields = form.find('input[name="full_name"], input[name="email"]');
        const editBtn = $('#edit-btn');
        const saveBtnGroup = $('.btn-group-edit');
        const cancelBtn = $('#cancel-btn');
        const uploadBtn = $('.upload-btn-wrapper');
        const photoInput = $('#photo_upload');
        const photoPreview = $('#profile-pic-preview');

        let originalValues = {};
        editableFields.each(function() {
            originalValues[$(this).attr('name')] = $(this).val();
        });
        let originalPhotoSrc = photoPreview.attr('src');

        editBtn.on('click', function() {
            editableFields.prop('readonly', false).css('background-color', '');
            uploadBtn.show();
            editBtn.hide();
            saveBtnGroup.show();
        });

        function cancelEditing() {
            editableFields.prop('readonly', true).css('background-color', '#e9ecef');
            uploadBtn.hide();
            saveBtnGroup.hide();
            editBtn.show();
            photoPreview.attr('src', originalPhotoSrc);
            photoInput.val('');
            $('#alert-placeholder').empty();
        }
        cancelBtn.on('click', cancelEditing);

        photoInput.on('change', function() { /* ... unchanged ... */ });

        form.on('submit', function(e) {
            e.preventDefault();
            $('#save-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
            
            $.ajax({
                type: 'POST',
                url: 'update_profile_ajax.php',
                data: new FormData(this),
                dataType: 'json',
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.success) {
                        showAlert('Profile updated successfully!', 'success');
                        
                        // --- THIS IS THE FIX FOR THE VISUALS ---
                        const newName = $('input[name="full_name"]').val();
                        // 1. Update the main name heading on the page
                        $('#display-full-name').text(newName);
                        // 2. Update the name in the header
                        $('#header-student-name').text(newName);
                        // --- END OF FIX ---
                        
                        originalValues['full_name'] = newName;
                        originalValues['email'] = $('input[name="email"]').val();
                        if(response.new_photo_path) {
                            originalPhotoSrc = response.new_photo_path;
                        }
                        setTimeout(cancelEditing, 2000);
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function() { showAlert('An unexpected network error occurred.', 'danger'); },
                complete: function() {
                    $('#save-btn').prop('disabled', false).html('Save Changes');
                }
            });
        });

        function showAlert(message, type) { /* ... unchanged ... */ }
    });
    </script>
    <script>
         // --- SIDEBAR TOGGLE SCRIPT (from your dashboard) ---
        // This is the script that was missing. It's plain JavaScript and works with the jQuery above.
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');

        if (toggleBtn && sidebar && mainContent) {
            toggleBtn.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('show');
                } else {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                }
            });
        }
    </script>
</body>
</html>