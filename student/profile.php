<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Standard login check
if (!isset($_SESSION['student_logged_in'])) {
    header('Location: login.php');
    exit();
}

$student_name = $_SESSION['student_name'] ?? 'Student';
$student = null;
$page_error = '';

// =======================================================
// 1. ROBUST STUDENT ID TRANSLATOR (The Core Fix)
// =======================================================
$raw_session_id = $_SESSION['student_user_db_id'] ?? $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? $_SESSION['id'] ?? null;
$current_user_db_id = 0;

if ($raw_session_id) {
    if (is_numeric($raw_session_id)) { 
        $current_user_db_id = (int)$raw_session_id; 
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_string = ? LIMIT 1");
        $stmt->execute([$raw_session_id]);
        $current_user_db_id = $stmt->fetchColumn() ?: 0;
    }
}
if (!$current_user_db_id) {
    $email = $_SESSION['student_email'] ?? $_SESSION['email'] ?? '';
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $current_user_db_id = $stmt->fetchColumn() ?: 0;
    }
}

// =======================================================
// 2. DYNAMIC PROFILE FETCH
// =======================================================
if ($current_user_db_id > 0) {
    try {
        // Fetch ALL rich data from users, student_details, classes, and sections
        $sql = "SELECT 
                    u.id, u.user_id_string, u.full_name, u.email, u.status, u.profile_picture,
                    sd.department, sd.gender, sd.father_name, sd.date_of_birth, 
                    sd.cell_no, sd.phone_no, sd.address, sd.fee_category,
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
} else {
    $page_error = "Session ID is missing or invalid. Please log in again.";
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
        .profile-pic-container { position: relative; width: 160px; height: 160px; margin: 20px auto; }
        .profile-pic { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 5px solid #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .upload-btn-wrapper { position: absolute; bottom: 5px; right: 5px; }
        .upload-btn { background-color: var(--primary); color: white; border: 2px solid white; border-radius: 50%; width: 45px; height: 45px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); display: grid; place-items: center; transition: 0.2s; }
        .upload-btn:hover { background-color: #2e59d9; color: white; transform: scale(1.05); }
        .form-control[readonly] { background-color: #f8f9fc; color: #6e707e; cursor: not-allowed; border: 1px solid #e3e6f0; }
        .section-title { font-weight: 700; color: #4e73df; border-bottom: 2px solid #eaecf4; padding-bottom: 0.5rem; margin-bottom: 1.5rem; margin-top: 1.5rem; font-size: 1.1rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php' ?>
    <div id="main-content">
        <?php include 'header.php' ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                
                <?php if (!empty($page_error)): ?>
                    <div class="alert alert-warning text-center shadow-sm rounded-3 p-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h4 class="fw-bold text-dark">Profile Not Available</h4>
                        <p class="mb-0 text-muted"><?= htmlspecialchars($page_error) ?></p>
                    </div>
                <?php elseif ($student): ?>
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-lg-4 text-center border-end pe-lg-4 mb-4 mb-lg-0">
                                    <div class="profile-pic-container">
                                        <img src="<?= !empty($student['profile_picture']) ? htmlspecialchars('../' . $student['profile_picture']) : 'https://ui-avatars.com/api/?name='.urlencode($student['full_name']).'&background=4e73df&color=fff&size=160' ?>" 
                                             alt="Profile Picture" class="profile-pic" id="profile-pic-preview">
                                        <div class="upload-btn-wrapper" style="display: none;">
                                            <button class="btn upload-btn" type="button" onclick="$('#photo_upload').click();" title="Change Photo"><i class="fas fa-camera"></i></button>
                                        </div>
                                    </div>
                                    <h4 class="mt-3 fw-bold text-dark" id="display-full-name"><?= htmlspecialchars($student['full_name']) ?></h4>
                                    <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="fas fa-id-badge me-1"></i> <?= htmlspecialchars($student['user_id_string'] ?? 'N/A') ?></span>
                                    <p class="text-muted small mt-2"><i class="fas fa-circle text-success fa-xs me-1"></i> Active Student</p>
                                </div>
                                
                                <div class="col-lg-8 ps-lg-4">
                                    <div id="alert-placeholder"></div>
                                    <form id="profile-form" enctype="multipart/form-data">
                                        <input type="file" name="photo" id="photo_upload" class="d-none" accept="image/png, image/jpeg">
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="m-0 fw-bold text-dark"><i class="fas fa-user-circle me-2"></i>Profile Details</h5>
                                            <button id="edit-btn" type="button" class="btn btn-outline-primary btn-sm fw-bold shadow-sm"><i class="fas fa-pencil-alt me-1"></i> Edit Details</button>
                                        </div>

                                        <h6 class="section-title"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-4 mb-3 mb-md-0">
                                                <label class="form-label fw-bold text-secondary small">Class</label>
                                                <input type="text" class="form-control fw-bold" value="<?= htmlspecialchars($student['class_name'] ?? 'Not Assigned') ?>" readonly>
                                            </div>
                                            <div class="col-md-4 mb-3 mb-md-0">
                                                <label class="form-label fw-bold text-secondary small">Section</label>
                                                <input type="text" class="form-control fw-bold" value="<?= htmlspecialchars($student['section_name'] ?? 'Not Assigned') ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-bold text-secondary small">Program / Track</label>
                                                <input type="text" class="form-control fw-bold" value="<?= htmlspecialchars($student['fee_category'] ?? 'Not Assigned') ?>" readonly>
                                            </div>
                                        </div>

                                        <h6 class="section-title"><i class="fas fa-id-card me-2"></i>Personal Information</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-6 mb-3">
                                                <label for="full_name" class="form-label fw-bold text-secondary small">Full Name</label>
                                                <input type="text" class="form-control editable-field" id="full_name" name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" readonly>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold text-secondary small">Father's Name</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($student['father_name'] ?? 'N/A') ?>" readonly>
                                            </div>
                                            <div class="col-md-6 mb-3 mb-md-0">
                                                <label class="form-label fw-bold text-secondary small">Gender</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($student['gender'] ?? 'N/A') ?>" readonly>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold text-secondary small">Date of Birth</label>
                                                <input type="text" class="form-control" value="<?= !empty($student['date_of_birth']) ? date('d M Y', strtotime($student['date_of_birth'])) : 'N/A' ?>" readonly>
                                            </div>
                                        </div>

                                        <h6 class="section-title"><i class="fas fa-address-book me-2"></i>Contact Information</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label fw-bold text-secondary small">Email Address</label>
                                                <input type="email" class="form-control editable-field" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" readonly>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="cell_no" class="form-label fw-bold text-secondary small">Cell No.</label>
                                                <input type="text" class="form-control editable-field" id="cell_no" name="cell_no" value="<?= htmlspecialchars($student['cell_no'] ?? '') ?>" readonly>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-bold text-secondary small">Home Address</label>
                                                <textarea class="form-control" rows="2" readonly><?= htmlspecialchars($student['address'] ?? 'N/A') ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 text-end btn-group-edit" style="display: none;">
                                            <button type="button" id="cancel-btn" class="btn btn-light border fw-bold me-2">Cancel</button>
                                            <button type="submit" id="save-btn" class="btn btn-primary fw-bold shadow-sm"><i class="fas fa-save me-1"></i> Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <footer class="footer mt-auto py-3 bg-white border-top">
            <div class="container text-center text-muted small">
                 &copy; MSST LMS <?= date('Y') ?> | Student Portal
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        const form = $('#profile-form');
        // Target only explicitly editable fields (Name, Email, Cell)
        const editableFields = form.find('.editable-field');
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
            editableFields.prop('readonly', false).css({'background-color': '#fff', 'border': '1px solid #4e73df', 'color': '#000'});
            uploadBtn.fadeIn('fast');
            editBtn.hide();
            saveBtnGroup.fadeIn('fast');
            editableFields.first().focus();
        });

        function cancelEditing() {
            editableFields.prop('readonly', true).css({'background-color': '#f8f9fc', 'border': '1px solid #e3e6f0', 'color': '#6e707e'});
            uploadBtn.fadeOut('fast');
            saveBtnGroup.hide();
            editBtn.fadeIn('fast');
            photoPreview.attr('src', originalPhotoSrc);
            photoInput.val('');
            
            // Restore original text values
            editableFields.each(function() {
                $(this).val(originalValues[$(this).attr('name')]);
            });
            $('#alert-placeholder').empty();
        }
        cancelBtn.on('click', cancelEditing);

        photoInput.on('change', function(e) { 
            if(e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.attr('src', e.target.result);
                }
                reader.readAsDataURL(e.target.files[0]);
            }
        });

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
                        
                        const newName = $('input[name="full_name"]').val();
                        $('#display-full-name').text(newName);
                        $('#header-student-name').text(newName);
                        
                        originalValues['full_name'] = newName;
                        originalValues['email'] = $('input[name="email"]').val();
                        originalValues['cell_no'] = $('input[name="cell_no"]').val();
                        
                        if(response.new_photo_path) {
                            originalPhotoSrc = response.new_photo_path;
                        }
                        setTimeout(cancelEditing, 1500);
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function() { showAlert('An unexpected network error occurred.', 'danger'); },
                complete: function() {
                    $('#save-btn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
                }
            });
        });

        function showAlert(message, type) { 
            const p = $('#alert-placeholder');
            p.html(`<div class="alert alert-${type} alert-dismissible shadow-sm mb-4"><strong>Status:</strong> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
            window.scrollTo({ top: 0, behavior: 'smooth' }); 
            setTimeout(() => p.empty(), 5000);
        }
    });
    </script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('sidebarToggle');

        if (toggleBtn && sidebar && mainContent) {
            toggleBtn.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show');
                } else {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                }
            });
        }
    </script>
</body>
</html>