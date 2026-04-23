<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>LMS</title>
  
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    <!-- Vendor CSS (non-conflicting) -->
    <link rel="stylesheet" href="assets/vendors/feather/feather.css">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/ti-icons/css/themify-icons.css">
    <link rel="stylesheet" href="assets/vendors/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/vendors/typicons/typicons.css">
  
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="shortcut icon" href="assets/images/favicon.png" />
  
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  </head>

<body>
  <div class="container-scroller">
    <?php include 'header.php' ?>
    <div class="container-fluid page-body-wrapper">
      <?php include 'sidebar.php' ?>

      <!-- Main Content -->
      <div class="main-panel">
        <div class="content-wrapper">
          <!-- Your dynamic content goes here -->
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php' ?>

  <!-- Core JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Bootstrap Datepicker -->
  <script src="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>

  <!-- Custom JS -->
  <script src="assets/js/template.js"></script>

  <!-- Your dashboard script here -->
  <script>
    $(document).ready(function () {
      // Custom JS (sidebar toggle, menu behavior, chart init, etc.)
    });
  </script>
</body>
</html>
