<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    // Clever query to find all fee slips that have a scholarship applied
    // It joins the users, student_details, classes, and fee_categories to get all context
    $stmt = $pdo->prepare("
        SELECT 
            fs.slip_no,
            fs.month_year,
            u.user_id_string,
            u.full_name,
            sd.father_name,
            c.class_name,
            s.section_name,
            sd.fee_category,
            fsc.component_name,
            fsc.amount AS discounted_tuition,
            fc.tuition_amount AS original_tuition
        FROM fee_slips fs
        JOIN fee_slip_components fsc ON fs.slip_no = fsc.slip_no
        JOIN users u ON fs.student_id = u.id
        LEFT JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN classes c ON sd.class_id = c.id
        LEFT JOIN sections s ON sd.section_id = s.id
        LEFT JOIN fee_categories fc ON sd.fee_category = fc.name
        WHERE fsc.component_name LIKE '%Scholarship)%'
        ORDER BY fs.month_year DESC, u.full_name ASC
    ");
    $stmt->execute();
    $scholarship_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Records | MSST Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="style.css">
    
    <style>
        .program-badge { font-size: 0.75rem; padding: 4px 8px; background-color: #906833; color: white; border-radius: 6px; display: inline-block; font-weight: 500;}
        .scholarship-badge { background-color: #ffc107; color: #000; font-weight: 900; letter-spacing: 0.5px; padding: 5px 10px; border-radius: 6px; }
        .savings-text { color: #198754; font-weight: bold; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Scholarship Awardees</h1>
                <a href="fee-management.php" class="btn btn-primary shadow-sm">
                    <i class="fas fa-arrow-left fa-sm text-white-50 me-1"></i> Back to Fees
                </a>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Scholarships Granted</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($scholarship_records) ?> Slips</div>
                                </div>
                                <div class="col-auto"><i class="fas fa-award fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Detailed Scholarship Ledger</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="scholarshipTable" width="100%" cellspacing="0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fee Month</th>
                                    <th>Slip #</th>
                                    <th>Student Details</th>
                                    <th>Class & Program</th>
                                    <th class="text-center">Discount %</th>
                                    <th class="text-end">Base Tuition</th>
                                    <th class="text-end">Final Tuition</th>
                                    <th class="text-end">Amount Saved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scholarship_records as $record): 
                                    // Extract the percentage from the string (e.g., "Base Monthly Tuition (50% Scholarship)")
                                    $percent = 0;
                                    if (preg_match('/\((\d+(?:\.\d+)?)% Scholarship\)/', $record['component_name'], $matches)) {
                                        $percent = floatval($matches[1]);
                                    }
                                    
                                    $original = floatval($record['original_tuition']);
                                    $discounted = floatval($record['discounted_tuition']);
                                    
                                    // If installments were used, the actual original per-slip base fee might be lower.
                                    // We reverse-engineer the original slip portion based on the percentage to be accurate.
                                    if ($percent > 0) {
                                        $original_slip_portion = $discounted / ((100 - $percent) / 100);
                                        $saved_amount = $original_slip_portion - $discounted;
                                    } else {
                                        $original_slip_portion = $original;
                                        $saved_amount = 0;
                                    }
                                ?>
                                    <tr>
                                        <td class="fw-bold"><?= date('M Y', strtotime($record['month_year'])) ?></td>
                                        <td><a href="fee-management.php" class="text-decoration-none"><?= htmlspecialchars($record['slip_no']) ?></a></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($record['full_name']) ?></div>
                                            <div class="small text-muted">S/D of: <?= htmlspecialchars($record['father_name'] ?? 'N/A') ?></div>
                                            <div class="small text-secondary"><?= htmlspecialchars($record['user_id_string']) ?></div>
                                        </td>
                                        <td>
                                            <div>Class <?= htmlspecialchars($record['class_name'] ?? '') ?>-<?= htmlspecialchars($record['section_name'] ?? '') ?></div>
                                            <span class="program-badge mt-1"><?= htmlspecialchars($record['fee_category'] ?? 'N/A') ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="scholarship-badge"><?= $percent ?>% OFF</span>
                                        </td>
                                        <td class="text-end text-muted text-decoration-line-through">PKR <?= number_format($original_slip_portion, 2) ?></td>
                                        <td class="text-end fw-bold text-dark">PKR <?= number_format($discounted, 2) ?></td>
                                        <td class="text-end savings-text">+ PKR <?= number_format($saved_amount, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> Muhaddisa School of Science and Technology.</p></div></footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#scholarshipTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc'], [2, 'asc']] // Sort by Date descending, then Student Name
        });
    });

    // Sidebar Toggle Script
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