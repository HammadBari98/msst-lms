<?php
session_start();
require_once __DIR__ . '/../config/db_config.php';

// Standard login check for teachers
if (!isset($_SESSION['teacher_logged_in']) || !isset($_SESSION['user_db_id'])) {
    header('Location: login.php');
    exit();
}

// Set default values
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';
$teacher = null;
$assigned_classes = [];
$page_error = '';

try {
    $current_user_db_id = $_SESSION['user_db_id'];

    // Query 1: Get all personal and professional details by joining users and teacher_details
    $sql_teacher = "SELECT 
                        u.id, u.user_id_string, u.full_name, u.email, u.profile_picture,
                        td.designation, td.cnic, td.phone, td.address
                    FROM users u
                    LEFT JOIN teacher_details td ON u.id = td.user_id
                    WHERE u.id = :user_db_id AND u.status = 'Active'";
    $stmt_teacher = $pdo->prepare($sql_teacher);
    $stmt_teacher->execute(['user_db_id' => $current_user_db_id]);
    $teacher = $stmt_teacher->fetch(PDO::FETCH_ASSOC);

    if ($teacher) {
        // Ensure the header variable is the most current name from the DB
        $teacher_name = $teacher['full_name'];

        // Query 2: Get all assigned classes for this teacher
        $sql_classes = "SELECT c.class_name, c.id AS class_id
                        FROM classes c
                        JOIN teacher_class_assignments tca ON c.id = tca.class_id
                        WHERE tca.teacher_user_id = :user_db_id
                        ORDER BY c.class_name";
        $stmt_classes = $pdo->prepare($sql_classes);
        $stmt_classes->execute(['user_db_id' => $current_user_db_id]);
        $assigned_classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $page_error = "Your profile data could not be loaded. Please contact the administration.";
    }
} catch (PDOException $e) {
    $page_error = "A database error occurred while loading your profile.";
    error_log("Teacher Profile Page Error: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($teacher_name) ?> | Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../style.css"> 
    <style>
        .profile-pic-container { position: relative; width: 150px; height: 150px; margin: -75px auto 20px auto; }
        .profile-pic { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 5px solid #fff; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }
        .profile-header { background: linear-gradient(45deg, var(--primary), var(--accent)); color: white; padding: 2.5rem; border-radius: 0.5rem; text-align: center; }
        .upload-btn-wrapper { position: absolute; bottom: 5px; right: 5px; }
        .upload-btn { background-color: white; color: var(--accent); border: none; border-radius: 50%; width: 40px; height: 40px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); display: grid; place-items: center; }
        .form-control[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .nav-tabs .nav-link.active { color: var(--primary); border-color: #dee2e6 #dee2e6 #fff; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div id="main-content">
        <?php include 'header.php'; ?>
        <div class="content-wrapper p-4">
            <div class="container-fluid">
                <?php if (!empty($page_error)): ?>
                    <div class="alert alert-danger text-center"><?= htmlspecialchars($page_error) ?></div>
                <?php elseif ($teacher): ?>
                    <div class="profile-header">
                        <h2 id="display-full-name"><?= htmlspecialchars($teacher['full_name']) ?></h2>
                        <p class="mb-0 lead"><?= htmlspecialchars($teacher['designation'] ?? 'Teacher') ?></p>
                    </div>
                    <div class="profile-pic-container">
                        <img src="<?= !empty($teacher['profile_picture']) ? htmlspecialchars('../' . $teacher['profile_picture']) : 'https://ui-avatars.com/api/?name='.urlencode($teacher['full_name']).'&background=6f42c1&color=fff&size=150' ?>" alt="Profile Picture" class="profile-pic" id="profile-pic-preview">
                        <div class="upload-btn-wrapper" style="display: none;">
                            <button class="btn upload-btn" type="button" onclick="$('#photo_upload').click();"><i class="fas fa-camera"></i></button>
                        </div>
                    </div>
                    
                    <ul class="nav nav-tabs mt-2" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile">Profile Details</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#classes">Classes Assigned</button></li>
                    </ul>

                    <div class="tab-content card" id="profileTabsContent">
                        <div class="tab-pane fade show active p-4" id="profile" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="m-0">Personal Information</h5>
                                <button id="edit-btn" type="button" class="btn btn-primary btn-sm"><i class="fas fa-pencil-alt me-1"></i> Edit Profile</button>
                            </div>
                            <div id="alert-placeholder"></div>
                            <form id="profile-form" enctype="multipart/form-data">
                                <input type="file" name="photo" id="photo_upload" class="d-none" accept="image/png, image/jpeg">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($teacher['full_name']) ?>" readonly></div>
                                    <div class="col-md-6"><label class="form-label">Email Address</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($teacher['email']) ?>" readonly></div>
                                    <div class="col-md-6"><label class="form-label">Phone Number</label><input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>" readonly></div>
                                    <div class="col-md-6"><label class="form-label">CNIC</label><input type="text" class="form-control" name="cnic" value="<?= htmlspecialchars($teacher['cnic'] ?? '') ?>" readonly></div>
                                    <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2" readonly><?= htmlspecialchars($teacher['address'] ?? '') ?></textarea></div>
                                </div>
                                <div class="mt-4 text-end btn-group-edit" style="display: none;">
                                    <button type="button" id="cancel-btn" class="btn btn-secondary me-2">Cancel</button>
                                    <button type="submit" id="save-btn" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade p-4" id="classes" role="tabpanel">
                            <h5 class="mb-3">My Classes</h5>
                            <?php if (!empty($assigned_classes)): ?>
                                <div class="list-group">
                                    <?php foreach ($assigned_classes as $class): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($class['class_name']) ?>
                                            <span class="badge bg-primary rounded-pill">ID: <?= htmlspecialchars($class['class_id']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="fas fa-chalkboard fa-3x mb-3"></i>
                                    <p>You have not been assigned to any classes yet.</p>
                                </div>
                            <?php endif; ?>
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
        // Updated selector to include all editable fields
        const editableFields = form.find('input[name="full_name"], input[name="email"], input[name="phone"], input[name="cnic"], textarea[name="address"]');
        
        const editBtn = $('#edit-btn');
        const saveBtnGroup = $('.btn-group-edit');
        const cancelBtn = $('#cancel-btn');
        const uploadBtn = $('.upload-btn-wrapper');

        let originalValues = {};
        editableFields.each(function() { originalValues[$(this).attr('name')] = $(this).val(); });
        let originalPhotoSrc = $('#profile-pic-preview').attr('src');

        editBtn.on('click', function() {
            editableFields.prop('readonly', false);
            uploadBtn.show();
            editBtn.hide();
            saveBtnGroup.show();
        });

        function cancelEditing() {
            editableFields.each(function() { $(this).val(originalValues[$(this).attr('name')]); });
            editableFields.prop('readonly', true);
            uploadBtn.hide();
            saveBtnGroup.hide();
            editBtn.show();
            $('#profile-pic-preview').attr('src', originalPhotoSrc);
            $('#photo_upload').val('');
            $('#alert-placeholder').empty();
        }
        cancelBtn.on('click', cancelEditing);

        // Form submission via AJAX
        form.on('submit', function(e) {
            e.preventDefault();
            $('#save-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
            $.ajax({
                type: 'POST',
                url: 'update_teacher_profile_ajax.php', // The backend file to save changes
                data: new FormData(this),
                dataType: 'json',
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.success) {
                        showAlert('Profile updated successfully!', 'success');
                        const newName = $('input[name="full_name"]').val();
                        $('#display-full-name').text(newName);
                        $('#header-teacher-name').text(newName); // Instantly update header name
                        
                        editableFields.each(function() { originalValues[$(this).attr('name')] = $(this).val(); });
                        if(response.new_photo_path) { originalPhotoSrc = response.new_photo_path; }
                        setTimeout(cancelEditing, 2000);
                    } else {
                        showAlert('Error: ' + response.message, 'danger');
                    }
                },
                error: function() { showAlert('An unexpected network error occurred.', 'danger'); },
                complete: function() { $('#save-btn').prop('disabled', false).html('Save Changes'); }
            });
        });
        
        function showAlert(message, type) { /* ... Unchanged ... */ }
    });

    // Sidebar Toggle Script
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) { /* ... Unchanged ... */ }
    </script>

    
</body>
</html>