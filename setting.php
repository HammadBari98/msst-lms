<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>LMS - Website Settings</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/typicons/typicons.css">
    <link rel="stylesheet" href="assets/vendors/simple-line-icons/css/simple-line-icons.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />

    <style>
        .file-upload {
            border: 1px dashed #ccc;
            padding: 20px;
            text-align: center;
            cursor: pointer;
        }
        .file-upload-preview {
            margin-top: 10px;
            max-width: 200px;
            max-height: 200px;
        }
    </style>
</head>

<body>
    <div class="container-scroller">
        <?php include 'header.php' ?>
        <div class="container-fluid page-body-wrapper">
            <?php include 'sidebar.php' ?>

            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="row">
                        <div class="col-md-12 grid-margin">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Website Settings</h4>
                                    <form id="websiteSettingsForm">
                                        <div class="mb-3">
                                            <label for="cmsTitle" class="form-label">CMS Title <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="cmsTitle" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="cmsUrlAlias" class="form-label">CMS Url Alias <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="cmsUrlAlias" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">CMS Frontend <span class="text-danger">*</span></label>
                                            <div class="container">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="cmsFrontend" id="cmsFrontendEnabled" value="enabled" required>
                                                    <label class="form-check-label" for="cmsFrontendEnabled">Enabled</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="cmsFrontend" id="cmsFrontendDisabled" value="disabled">
                                                    <label class="form-check-label" for="cmsFrontendDisabled">Disabled</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Online Admission <span class="text-danger">*</span></label>
                                            <div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="onlineAdmission" id="onlineAdmissionEnabled" value="enabled" required>
                                                    <label class="form-check-label" for="onlineAdmissionEnabled">Enabled</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="onlineAdmission" id="onlineAdmissionDisabled" value="disabled">
                                                    <label class="form-check-label" for="onlineAdmissionDisabled">Disabled</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="receiveEmailTo" class="form-label">Receive Email To <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="receiveEmailTo" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="captchaStatus" class="form-label">Captcha Status <span class="text-danger">*</span></label>
                                            <select class="form-select" id="captchaStatus" required>
                                                <option value="disable" selected>Disable</option>
                                                <option value="enable">Enable</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="workingHours" class="form-label">Working Hours <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="workingHours" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="logo" class="form-label">Logo <span class="text-danger">*</span></label>
                                            <div class="file-upload" id="logo-upload">
                                                Drag and drop a file here or click
                                                <input type="file" class="form-control-file d-none" id="logo-input" accept="image/*" required>
                                            </div>
                                            <div id="logo-preview-container" class="mt-2" style="display: none;">
                                                <img id="logo-preview" src="#" alt="Logo Preview" class="file-upload-preview">
                                                <button type="button" class="btn btn-sm btn-danger mt-1" id="logo-remove">Remove</button>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="favIcon" class="form-label">Fav Icon <span class="text-danger">*</span></label>
                                            <div class="file-upload" id="favicon-upload">
                                                Drag and drop a file here or click
                                                <input type="file" class="form-control-file d-none" id="favicon-input" accept="image/*" required>
                                            </div>
                                            <div id="favicon-preview-container" class="mt-2" style="display: none;">
                                                <img id="favicon-preview" src="#" alt="Favicon Preview" class="file-upload-preview">
                                                <button type="button" class="btn btn-sm btn-danger mt-1" id="favicon-remove">Remove</button>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="address" rows="3" required></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="googleAnalytics" class="form-label">Google Analytics</label>
                                            <textarea class="form-control" id="googleAnalytics" rows="3"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="theme" class="form-label">Theme <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="theme" required>
                                        </div>
                                        <hr class="my-4">
                                        <h5>Theme Options</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="primaryColor" class="form-label">Primary Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="primaryColor" value="#ffffff" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="menuBgColor" class="form-label">Menu BG Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="menuBgColor" value="#ffffff" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="buttonHoverColor" class="form-label">Button Hover Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="buttonHoverColor" value="#ffffff" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="textColor" class="form-label">Text Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="textColor" value="#000000" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="textSecondaryColor" class="form-label">Text Secondary Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="textSecondaryColor" value="#6c757d" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="footerBgColor" class="form-label">Footer BG Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="footerBgColor" value="#f8f9fa" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="footerTextColor" class="form-label">Footer Text Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="footerTextColor" value="#6c757d" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="copyrightBgColor" class="form-label">Copyright BG Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="copyrightBgColor" value="#343a40" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="copyrightTextColor" class="form-label">Copyright Text Color <span class="text-danger">*</span></label>
                                                <input type="color" class="form-control form-control-color" id="copyrightTextColor" value="#ffffff" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="borderRadius" class="form-label">Border Radius <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="borderRadius" value="5" required>
                                            </div>
                                        </div>
                                        <hr class="my-4">
                                        <h5>Contact Information</h5>
                                        <div class="mb-3">
                                            <label for="mobileNo" class="form-label">Mobile No <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="mobileNo" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="fax" class="form-label">Fax</label>
                                            <input type="text" class="form-control" id="fax">
                                        </div>
                                        <div class="mb-3">
                                            <label for="footerAboutText" class="form-label">Footer About Text <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="footerAboutText" rows="3" required></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="copyrightText" class="form-label">Copyright Text <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="copyrightText" required>
                                        </div>
                                        <hr class="my-4">
                                        <h5>Social Media Links</h5>
                                        <div class="mb-3">
                                            <label for="facebookUrl" class="form-label">Facebook Url</label>
                                            <input type="url" class="form-control" id="facebookUrl">
                                        </div>
                                        <div class="mb-3">
                                            <label for="twitterUrl" class="form-label">Twitter Url</label>
                                            <input type="url" class="form-control" id="twitterUrl">
                                        </div>
                                        <div class="mb-3">
                                            <label for="youtubeUrl" class="form-label">Youtube Url</label>
                                            <input type="url" class="form-control" id="youtubeUrl">
                                        </div>
                                        <div class="mb-3">
                                            <label for="googlePlus" class="form-label">Google Plus</label>
                                            <input type="url" class="form-control" id="googlePlus">
                                        </div>
                                        <div class="mb-3">
                                            <label for="linkedinUrl" class="form-label">Linkedin Url</label>
                                            <input type="url" class="form-control" id="linkedinUrl">
                                        </div>
                                        <div class="mb-3">
                                            <label for="pinterestUrl" class="form-label">Pinterest Url</label>
                                            <input type="url" class="form-control" id="pinterestUrl">
                                        </div>
                                        <div class="mb-3">
                                            <label for="instagramUrl" class="form-label">Instagram Url</label>
                                            <input type="url" class="form-control" id="instagramUrl">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Save Settings</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php include 'footer.php' ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/hoverable-collapse.js"></script>
    <script src="assets/js/template.js"></script>
    <script src="assets/js/settings.js"></script>
    <script src="assets/js/todolist.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#websiteSettingsForm').on('submit', function(e) {
                e.preventDefault();
                alert('Website settings saved successfully!');
            });

            // Logo upload functionality
            $('#logo-upload').on('click', function() {
                $('#logo-input').click();
            });

            $('#logo-input').on('change', function() {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#logo-preview').attr('src', e.target.result);
                        $('#logo-preview-container').show();
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });

            $('#logo-remove').on('click', function() {
                $('#logo-input').val('');
                $('#logo-preview-container').hide();
            });
        }
        
</script>

</body>
</html>