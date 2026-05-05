<?php
session_start();
require_once __DIR__ . '/config/db_config.php'; 

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Initialize arrays to prevent "Undefined variable" warnings if the DB crashes
$generated_cards = [];
$all_students = [];
$db_error = null;

try {
    // 1. Create the base table if it doesn't exist at all
    $pdo->exec("CREATE TABLE IF NOT EXISTS generated_id_cards (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        card_name VARCHAR(100),
        card_fname VARCHAR(100),
        card_class VARCHAR(50),
        card_session VARCHAR(50),
        issue_date VARCHAR(50),
        expiry_date VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id)
    )");

    // 2. AUTO-PATCHER: Add 'card_photo' column if it is missing from an older version
    try {
        $pdo->query("SELECT card_photo FROM generated_id_cards LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE generated_id_cards ADD COLUMN card_photo LONGTEXT AFTER expiry_date");
    }

    // 3. Handle AJAX Requests (Delete & Confirm)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        
        // ACTION: DELETE CARD
        if ($_POST['action'] === 'delete_card') {
            header('Content-Type: application/json');
            try {
                $stmt = $pdo->prepare("DELETE FROM generated_id_cards WHERE id = ?");
                $stmt->execute([$_POST['card_id']]);
                echo json_encode(['success' => true, 'message' => 'Card deleted successfully.']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete card.']);
            }
            exit;
        }

        // ACTION: CONFIRM & SAVE CARD
        if ($_POST['action'] === 'confirm_card') {
            $user_id = $_POST['user_id'];
            $name = $_POST['card_name'];
            $fname = $_POST['card_fname'];
            $cls = $_POST['card_class'];
            $session_yr = $_POST['card_session'];
            $issue = $_POST['issue_date'];
            $expiry = $_POST['expiry_date'];
            $photo = $_POST['custom_photo'] ?? null; 
            
            $stmt = $pdo->prepare("INSERT INTO generated_id_cards 
                (user_id, card_name, card_fname, card_class, card_session, issue_date, expiry_date, card_photo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                card_name=?, card_fname=?, card_class=?, card_session=?, issue_date=?, expiry_date=?, card_photo=?");
            
            $stmt->execute([$user_id, $name, $fname, $cls, $session_yr, $issue, $expiry, $photo, 
                            $name, $fname, $cls, $session_yr, $issue, $expiry, $photo]);
            
            $_SESSION['action_msg'] = '<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-check-circle me-2"></i> Card successfully confirmed and saved!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            header("Location: id-card-generator.php");
            exit;
        }
    }

    // 4. Fetch Confirmed Cards
    $stmt_cards = $pdo->query("
        SELECT gc.*, u.user_id_string, u.profile_picture as db_profile_picture 
        FROM generated_id_cards gc
        JOIN users u ON gc.user_id = u.id
        ORDER BY gc.created_at DESC
    ");
    $generated_cards = $stmt_cards->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch All Active Students
    $stmt_students = $pdo->query("
        SELECT u.id, u.user_id_string, u.full_name, u.profile_picture, sd.father_name, c.class_name, u.created_at as admission_date
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN student_details sd ON u.id = sd.user_id
        LEFT JOIN classes c ON sd.class_id = c.id
        WHERE r.role_name = 'Student' AND u.status = 'Active'
        ORDER BY u.full_name ASC
    ");
    $all_students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

$action_msg = '';
if (isset($_SESSION['action_msg'])) {
    $action_msg = $_SESSION['action_msg'];
    unset($_SESSION['action_msg']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card Generator | MSST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* --- PREMIUM ID CARD STYLES (CR80: 54mm x 86mm STRICT) --- */
        :root {
            --brand-primary: #906833;
            --brand-accent: #cc8636;
            --text-dark: #2c3e50;
        }

        .workspace-area { background: #e9ecef; padding: 30px; border-radius: 12px; display: flex; justify-content: center; }
        .print-layout { display: flex; gap: 15px; justify-content: center; align-items: flex-start; }
        .id-card { width: 54mm; height: 86mm; background-color: #ffffff; position: relative; box-sizing: border-box; font-family: 'Arial', sans-serif; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.15); border: 1px solid #ddd; z-index: 1; }
        .id-card::before { content: ''; position: absolute; top: 50%; left: 50%; width: 45mm; height: 45mm; transform: translate(-50%, -50%); background-image: url('assets/images/msst-logo.png'); background-size: contain; background-repeat: no-repeat; background-position: center; opacity: 0.05; z-index: -1; }

        /* --- FRONT DESIGN --- */
        .card-front .header { background: var(--brand-primary); color: #ffffff; text-align: center; padding: 3mm 1mm; border-bottom: 2mm solid var(--brand-accent); }
        .card-front .header-content { display: flex; align-items: center; justify-content: center; gap: 2mm; margin-bottom: .5mm;margin-top: 5mm; }
        .card-front .logo { width: 10mm; height: 10mm; background: white; border-radius: 50%; padding: 0.5mm; }
        .card-front .school-name { font-size: 6.5pt; font-weight: 900; line-height: 1.1; text-align: left; text-transform: uppercase; }
        .card-front .motto { font-size: 4.5pt; font-weight: 600; letter-spacing: 0.5px; color: #f8f9fa; text-align: center; padding-top: 1mm;}
        .card-front .badge-title { background-color: #f1f3f5; color: var(--brand-primary); text-align: center; font-size: 6.5pt; font-weight: 900; padding: 1.5mm 0; letter-spacing: 1px; text-transform: uppercase; border-bottom: 1px solid #ddd; }
        .card-front .photo-wrap { text-align: center; margin-top: 2.5mm; margin-bottom: 2.5mm; }
        .card-front .student-photo { width: 14mm; height: 15mm; object-fit: cover; border: 1.5px solid var(--brand-primary); border-radius: 2mm; padding: 1px; background: #fff; }
        .card-front .info-grid { display: grid; grid-template-columns: 18mm 1fr; gap: 1.2mm; padding: 0 4mm; font-size: 6pt; line-height: 1; }
        .card-front .info-lbl { font-weight: 800; color: var(--brand-primary); }
        .card-front .info-val { font-weight: 700; color: var(--text-dark); white-space: nowrap;  }
        .card-front .dates-container { display: flex; justify-content: space-between; padding: 0 4mm; margin-top: 3.5mm; font-size: 5pt; font-weight: bold; color: #555; }
        .card-front .director-sig { position: absolute; bottom: 8mm; right: 4mm; text-align: center; font-size: 5pt; font-weight: bold; color: var(--text-dark); display: flex; flex-direction: column; align-items: center; }
        .card-front .signature-img { width: 16mm; height: 6mm; object-fit: contain; margin-bottom: 0.5mm; }
        .card-front .validity-bar { position: absolute; bottom: 0; width: 100%; background: var(--brand-primary); color: white; text-align: center; font-size: 5.5pt; font-weight: bold; padding: 1.5mm 0; letter-spacing: 0.5px; }

        /* --- BACK DESIGN --- */
        .card-back { background-color: #fdfdfd; }
        .card-back .header { background: var(--brand-primary); color: white; text-align: center; padding-top: 10mm;padding-bottom: 4mm; border-bottom: 2mm solid var(--brand-accent); }
        .card-back .campus-title { font-size: 7.5pt; font-weight: 900; letter-spacing: 1px; }
        .card-back .address { font-size: 6pt; margin-top: 1.5mm; line-height: 1.3; font-weight: 600; }
        .card-back .contact-grid { padding: 3mm 4mm; font-size: 6pt; border-bottom: 1px solid #eee; }
        .card-back .contact-item { margin-bottom: 1.5mm; display: flex; gap: 2mm; }
        .card-back .contact-icon { color: var(--brand-primary); width: 3mm; text-align: center; }
        .card-back .contact-text { font-weight: 700; color: var(--text-dark); }
        .card-back .instructions { padding: 2mm 4mm; }
        .card-back .instructions h4 { font-size: 6.5pt; font-weight: 900; color: var(--brand-primary); margin-bottom: 1.5mm; text-decoration: underline; text-decoration-color: var(--brand-accent); text-underline-offset: 2px; }
        .card-back .instructions ul { padding-left: 3mm; margin: 0; font-size: 5.5pt; color: var(--text-dark); line-height: 1; font-weight: 600; }
        .card-back .instructions ul li { margin-bottom: 1mm; }

        /* --- PRINT CSS --- */
        @media print {
            @page { size: A4; margin: 0; }
            body { margin: 0; padding: 10mm; background: white; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
            #sidebar, #main-content > header, .footer, .no-print, .topbar, .modal { display: none !important; }
            #main-content { margin: 0 !important; padding: 0 !important; }
            .content-wrapper { padding: 0 !important; }
            .workspace-area { background: none !important; padding: 0 !important; border: none !important; display: block !important; }
            .print-layout { display: flex !important; flex-direction: row !important; gap: 5mm !important; justify-content: flex-start !important; align-items: flex-start !important; page-break-inside: avoid !important; }
            .id-card { box-shadow: none !important; border: 0.1mm solid #ccc !important; margin: 0 !important; break-inside: avoid !important; }
        }
        
        .student-thumbnail { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; border: 2px solid #e9ecef; }
        /* Select2 Modal Fix */
        .select2-container { width: 100% !important; z-index: 9999; }
        .select2-selection { height: 38px !important; border: 1px solid #0d6efd !important; padding-top: 4px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        
        <div id="alert-container"><?= $action_msg ?></div>

        <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
            <h1 class="h3 mb-0 text-gray-800">ID Card Manager</h1>
            <button class="btn btn-primary shadow-sm fw-bold px-4" onclick="openCreateModal()">
                <i class="fas fa-plus-circle me-2"></i> Create New ID Card
            </button>
        </div>

        <ul class="nav nav-pills mb-4 no-print" id="cardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4 fw-bold shadow-sm" id="records-tab" data-bs-toggle="pill" data-bs-target="#records" type="button" role="tab"><i class="fas fa-check-double me-2"></i> Confirmed Records</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 fw-bold ms-2 shadow-sm" id="studio-tab" data-bs-toggle="pill" data-bs-target="#studio" type="button" role="tab" disabled><i class="fas fa-id-badge me-2"></i> Print Studio</button>
            </li>
        </ul>

        <div class="tab-content" id="cardTabsContent">
            
            <!-- TAB 1: CONFIRMED CARD RECORDS -->
            <div class="tab-pane fade show active no-print" id="records" role="tabpanel">
                <div class="card shadow border-0">
                    <div class="card-header bg-white py-3 border-bottom-primary">
                        <h6 class="m-0 font-weight-bold text-primary">Saved & Confirmed Cards Ready for Printing</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($db_error): ?>
                            <div class="alert alert-danger">Database Connection Error: <?= htmlspecialchars($db_error) ?></div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="confirmedTable" width="100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Photo</th>
                                        <th>Student ID</th>
                                        <th>Card Name</th>
                                        <th>Card Class</th>
                                        <th>Issue Date</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($generated_cards as $card): 
                                        $rawPic = !empty($card['card_photo']) ? $card['card_photo'] : (!empty($card['db_profile_picture']) ? $card['db_profile_picture'] : null);
                                        $fallbackPic = 'https://ui-avatars.com/api/?background=0D8ABC&color=fff&name=' . urlencode($card['card_name']);
                                        $picUrl = $rawPic ?: $fallbackPic;
                                        
                                        $studioData = json_encode([
                                            'id' => $card['user_id_string'],
                                            'name' => $card['card_name'],
                                            'fname' => $card['card_fname'],
                                            'class' => $card['card_class'],
                                            'session' => $card['card_session'],
                                            'issue' => $card['issue_date'],
                                            'expiry' => $card['expiry_date'],
                                            'photo' => $picUrl
                                        ]);
                                    ?>
                                        <tr id="row-<?= $card['id'] ?>">
                                            <td class="text-center"><img src="<?= $picUrl ?>" class="student-thumbnail" alt="Pic"></td>
                                            <td class="fw-bold"><?= htmlspecialchars($card['user_id_string']) ?></td>
                                            <td><?= htmlspecialchars($card['card_name']) ?></td>
                                            <td><?= htmlspecialchars($card['card_class']) ?></td>
                                            <td><?= htmlspecialchars($card['issue_date']) ?></td>
                                            <td class="text-center">
                                                <div class="btn-group shadow-sm" role="group">
                                                    <button class="btn btn-info text-white btn-sm fw-bold px-3" onclick='openInStudio(<?= $studioData ?>)'>
                                                        <i class="fas fa-print me-1"></i> Print
                                                    </button>
                                                    <button class="btn btn-danger btn-sm px-3" onclick="deleteCard(<?= $card['id'] ?>, '<?= htmlspecialchars(addslashes($card['card_name'])) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: CARD PRINT STUDIO -->
            <div class="tab-pane fade" id="studio" role="tabpanel">
                
                <div class="d-flex flex-wrap gap-2 mb-3 no-print justify-content-between align-items-center">
                    <button class="btn btn-outline-secondary shadow-sm fw-bold" onclick="backToRecords()">
                        <i class="fas fa-arrow-left me-2"></i> Back to Records
                    </button>

                    <div id="studioActionControls">
                        <!-- PREVIEW MODE -->
                        <div id="previewModeControls" style="display: none;">
                            <form method="POST" id="confirmSaveForm" class="d-inline">
                                <input type="hidden" name="action" value="confirm_card">
                                <input type="hidden" name="user_id" id="save_user_id">
                                <input type="hidden" name="card_name" id="save_card_name">
                                <input type="hidden" name="card_fname" id="save_card_fname">
                                <input type="hidden" name="card_class" id="save_card_class">
                                <input type="hidden" name="card_session" id="save_card_session">
                                <input type="hidden" name="issue_date" id="save_issue_date">
                                <input type="hidden" name="expiry_date" id="save_expiry_date">
                                <input type="hidden" name="custom_photo" id="save_custom_photo">
                                <button type="submit" class="btn btn-success shadow-lg fw-bold px-4">
                                    <i class="fas fa-check-circle me-2"></i> Confirm & Save Card
                                </button>
                            </form>
                        </div>

                        <!-- CONFIRMED MODE -->
                        <div id="confirmedModeControls" style="display: none;">
                            <button class="btn btn-warning shadow-sm fw-bold px-3 text-dark me-2" data-bs-toggle="modal" data-bs-target="#advancedEditModal">
                                <i class="fas fa-tools me-2"></i> Studio Edit
                            </button>

                            <div class="dropdown d-inline-block">
                                <button class="btn btn-success shadow-sm fw-bold px-3 dropdown-toggle" type="button" id="downloadBtnMenu" data-bs-toggle="dropdown">
                                    <i class="fas fa-download me-2"></i> Download High-Res Image
                                </button>
                                <ul class="dropdown-menu shadow">
                                    <li><a class="dropdown-item fw-bold text-success" href="#" onclick="downloadCard('front', event)"><i class="fas fa-id-card me-2"></i>Download Front Only</a></li>
                                    <li><a class="dropdown-item fw-bold text-primary" href="#" onclick="downloadCard('back', event)"><i class="fas fa-barcode me-2"></i>Download Back Only</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item fw-bold text-dark" href="#" onclick="downloadCard('both', event)"><i class="fas fa-object-group me-2"></i>Download Both</a></li>
                                </ul>
                            </div>

                            <button class="btn btn-primary shadow-sm fw-bold px-3 ms-2" onclick="window.print()">
                                <i class="fas fa-print me-2"></i> Print PVC
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Workspace Area -->
                <div class="workspace-area">
                    <div class="print-layout" id="cardCaptureArea">
                        
                        <!-- FRONT OF CARD -->
                        <div class="id-card card-front" id="cardFront">
                            <div class="header">
                                <div class="header-content">
                                    <img src="assets/images/msst-logo.png" alt="Logo" class="logo" crossorigin="anonymous">
                                    <div class="school-name">Muhaddisa School of<br>Science & Technology</div>
                                </div>
                                <div class="motto">KNOWLEDGE • DISCIPLINE • EXCELLENCE</div>
                            </div>
                            
                            <div class="badge-title">STUDENT IDENTITY CARD</div>
                            
                            <div class="photo-wrap">
                                <img src="" id="cardPhoto" class="student-photo" alt="Student Photo" crossorigin="anonymous">
                            </div>
                            
                            <div class="info-grid">
                                <div class="info-lbl">Name:</div><div class="info-val" id="cardName"></div>
                                <div class="info-lbl">F. Name:</div><div class="info-val" id="cardFName"></div>
                                <div class="info-lbl">Student ID:</div><div class="info-val" id="cardId"></div>
                                <div class="info-lbl">Class:</div><div class="info-val" id="cardClass"></div>
                                <div class="info-lbl">Session:</div><div class="info-val" id="cardSession"></div>
                            </div>
                            
                            <div class="dates-container">
                                <div>Issue Date: <span id="cardIssue"></span><br>Expiry Date: <span id="cardExpiry"></span></div>
                            </div>
                            
                            <div class="director-sig">
                                <img src="assets/images/sign.png" id="cardSignature" class="signature-img" alt="Director Signature" crossorigin="anonymous">
                                Director
                            </div>

                            <div class="validity-bar">
                                VALID UNTIL <span id="cardValidity"></span>
                            </div>
                        </div>

                        <!-- BACK OF CARD -->
                        <div class="id-card card-back" id="cardBack">
                            <div class="header">
                                <div class="campus-title" id="backCampusTitle">CAMPUS ADDRESS</div>
                                <div class="address" id="backCampusAddress">
                                    Kushmara Toq, Near Fatima Jinnah<br>Girls HSS, Quaidabad Skardu
                                </div>
                            </div>
                            
                            <div class="contact-grid">
                                <div class="contact-item"><div class="contact-icon"><i class="fas fa-phone-alt"></i></div><div class="contact-text" id="backPhone">0317 9174495 | 0355 5851351</div></div>
                                <div class="contact-item"><div class="contact-icon"><i class="fab fa-whatsapp"></i></div><div class="contact-text" id="backWhatsapp">0355 4201394</div></div>
                                <div class="contact-item"><div class="contact-icon"><i class="fas fa-globe"></i></div><div class="contact-text" id="backWebsite">www.msstskardu.com</div></div>
                                <div class="contact-item"><div class="contact-icon"><i class="fas fa-envelope"></i></div><div class="contact-text" id="backEmail">msst.skd@gmail.com</div></div>
                            </div>
                            
                            <div class="instructions">
                                <h4>IMPORTANT INSTRUCTIONS</h4>
                                <ul id="backInstructions">
                                    <li>This card is the property of the institute.</li>
                                    <li>It must be carried daily by the student.</li>
                                    <li>This card is non-transferable.</li>
                                    <li>Report immediately if lost.</li>
                                </ul>
                            </div>
                            
                            <div style="position: absolute; bottom: 4mm; width: 100%; text-align: center;">
                                <img src="" id="cardQR" style="width: 10mm; height: 10mm;" alt="QR Code" crossorigin="anonymous">
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- 1. CREATE CARD MODAL (PREVIEW WORKFLOW WITH IMAGE UPLOAD) -->
<div class="modal fade no-print" id="createCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-id-badge me-2"></i> Create New ID Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light p-4">
                <div class="row g-3">
                    <div class="col-12 mb-2">
                        <label class="form-label fw-bold text-primary">1. Search & Select Student</label>
                        <select class="form-select border-primary shadow-sm" id="studentSelect" onchange="autoFillStudentData()" style="width: 100%;">
                            <option value="" disabled selected>Start typing to search...</option>
                            <?php foreach($all_students as $std): 
                                $issueYear = !empty($std['admission_date']) ? date('Y', strtotime($std['admission_date'])) : date('Y');
                                $expiryYear = $issueYear + 2; 
                                $fallbackPic = 'https://ui-avatars.com/api/?background=0D8ABC&color=fff&name=' . urlencode($std['full_name']);
                                $picUrl = !empty($std['profile_picture']) ? $std['profile_picture'] : $fallbackPic;
                            ?>
                                <option value="<?= $std['id'] ?>" 
                                        data-id_str="<?= htmlspecialchars($std['user_id_string'] ?? 'N/A') ?>"
                                        data-name="<?= htmlspecialchars($std['full_name']) ?>"
                                        data-fname="<?= htmlspecialchars($std['father_name'] ?? 'N/A') ?>"
                                        data-class="<?= htmlspecialchars($std['class_name'] ?? 'N/A') ?>"
                                        data-session="<?= $issueYear . ' - ' . $expiryYear ?>"
                                        data-issue="Aug <?= $issueYear ?>"
                                        data-expiry="Aug <?= $expiryYear ?>"
                                        data-photo="<?= $picUrl ?>">
                                    <?= htmlspecialchars($std['user_id_string']) ?> - <?= htmlspecialchars($std['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12"><hr class="my-2"></div>
                    <div class="col-12 mb-2"><h6 class="fw-bold text-muted">2. Verify Data & Upload New Photo (Optional)</h6></div>

                    <input type="hidden" id="createUserId">
                    <input type="hidden" id="createIdStr">
                    <input type="hidden" id="createOriginalPhoto">
                    <input type="hidden" id="createCustomPhoto">

                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold text-success"><i class="fas fa-camera me-1"></i> Custom Student Photo</label>
                        <input class="form-control border-success bg-white shadow-sm" type="file" id="createPhotoUpload" accept="image/png, image/jpeg, image/jpg">
                        <small class="text-muted">Leave blank to use the student's default profile picture.</small>
                    </div>

                    <div class="col-md-6"><label class="form-label fw-bold">Card Name</label><input type="text" class="form-control" id="createInputName"></div>
                    <div class="col-md-6"><label class="form-label fw-bold">Father's Name</label><input type="text" class="form-control" id="createInputFName"></div>
                    <div class="col-md-6"><label class="form-label fw-bold">Class / Designation</label><input type="text" class="form-control" id="createInputClass"></div>
                    <div class="col-md-6"><label class="form-label fw-bold">Session</label><input type="text" class="form-control" id="createInputSession"></div>
                    <div class="col-md-6 mt-3"><label class="form-label fw-bold">Issue Date</label><input type="text" class="form-control" id="createInputIssue"></div>
                    <div class="col-md-6 mt-3"><label class="form-label fw-bold">Expiry Date</label><input type="text" class="form-control" id="createInputExpiry"></div>
                </div>
            </div>
            <div class="modal-footer border-top bg-white">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" onclick="previewNewCard()" data-bs-dismiss="modal"><i class="fas fa-eye me-2"></i> Preview Card</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. ADVANCED STUDIO EDIT MODAL -->
<div class="modal fade no-print" id="advancedEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-tools me-2"></i> Studio Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <ul class="nav nav-tabs mb-3" id="editTabs" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#editFront" type="button" role="tab">Front Card</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#editBack" type="button" role="tab">Back Card</button></li>
                </ul>
                <div class="tab-content" id="editTabsContent">
                    <div class="tab-pane fade show active" id="editFront" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-primary"><i class="fas fa-camera me-1"></i> Update Student Photo (Live Preview)</label>
                                <input class="form-control border-primary" type="file" id="inputPhoto" accept="image/png, image/jpeg, image/jpg">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-success"><i class="fas fa-signature me-1"></i> Update Director Signature</label>
                                <input class="form-control border-success" type="file" id="inputSignature" accept="image/png, image/jpeg">
                            </div>
                            <div class="col-12"><hr class="mt-1 mb-2"></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Student Name</label><input type="text" class="form-control" id="editName"></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Father's Name</label><input type="text" class="form-control" id="editFName"></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Student ID</label><input type="text" class="form-control" id="editId"></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Class / Designation</label><input type="text" class="form-control" id="editClass"></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Session</label><input type="text" class="form-control" id="editSession"></div>
                            <div class="col-md-6 mt-3"><label class="form-label fw-bold">Issue Date</label><input type="text" class="form-control" id="editIssue"></div>
                            <div class="col-md-6 mt-3"><label class="form-label fw-bold">Expiry Date</label><input type="text" class="form-control" id="editExpiry"></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="editBack" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label fw-bold">Campus Title</label><input type="text" class="form-control" id="inputCampusTitle" value="CAMPUS ADDRESS"></div>
                            <div class="col-md-8"><label class="form-label fw-bold">Campus Address</label><textarea class="form-control" id="inputCampusAddress" rows="2">Kushmara Toq, Near Fatima Jinnah
Girls HSS, Quaidabad Skardu</textarea></div>
                            <div class="col-md-3"><label class="form-label fw-bold">Phone</label><input type="text" class="form-control" id="inputPhone" value="0317 9174495 | 0355 5851351"></div>
                            <div class="col-md-3"><label class="form-label fw-bold">WhatsApp</label><input type="text" class="form-control" id="inputWhatsapp" value="0355 4201394"></div>
                            <div class="col-md-3"><label class="form-label fw-bold">Website</label><input type="text" class="form-control" id="inputWebsite" value="www.msstskardu.com"></div>
                            <div class="col-md-3"><label class="form-label fw-bold">Email</label><input type="text" class="form-control" id="inputEmail" value="msst.skd@gmail.com"></div>
                            <div class="col-12 mt-3"><label class="form-label fw-bold text-danger">Important Instructions (One per line)</label><textarea class="form-control border-danger" id="inputInstructions" rows="5">This card is the property of the institute.
It must be carried daily by the student.
This card is non-transferable.
Report immediately if lost.</textarea></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-white">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning px-4 fw-bold shadow-sm" onclick="applyAdvancedUpdates()" data-bs-dismiss="modal"><i class="fas fa-sync me-2"></i> Update Print Studio</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    let dtTable;
    $(document).ready(function() {
        dtTable = $('#confirmedTable').DataTable({ pageLength: 10, responsive: true });
        
        $('#createCardModal').on('shown.bs.modal', function () {
            $('#studentSelect').select2({
                dropdownParent: $('#createCardModal'),
                width: '100%',
                placeholder: "Start typing a name or ID..."
            });
        });
    });

    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            if (window.innerWidth <= 768) { sidebar.classList.toggle('show'); } 
            else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('collapsed'); }
        });
    }

    // --- WORKFLOW 1: PREVIEW NEW CARD ---
    function openCreateModal() {
        document.getElementById('createUserId').value = '';
        document.getElementById('createCustomPhoto').value = '';
        document.getElementById('createPhotoUpload').value = '';
        $('#studentSelect').val(null).trigger('change');
        new bootstrap.Modal(document.getElementById('createCardModal')).show();
    }

    document.getElementById('createPhotoUpload').addEventListener('change', function(e) {
        if (e.target.files[0]) {
            const r = new FileReader();
            r.onload = function(evt) { document.getElementById('createCustomPhoto').value = evt.target.result; }
            r.readAsDataURL(e.target.files[0]);
        } else {
            document.getElementById('createCustomPhoto').value = ''; 
        }
    });

    function autoFillStudentData() {
        const select = document.getElementById('studentSelect');
        const option = select.options[select.selectedIndex];
        if(!option || !option.value) return;
        
        document.getElementById('createUserId').value = option.value;
        document.getElementById('createIdStr').value = option.getAttribute('data-id_str');
        document.getElementById('createOriginalPhoto').value = option.getAttribute('data-photo');
        
        document.getElementById('createInputName').value = option.getAttribute('data-name');
        document.getElementById('createInputFName').value = option.getAttribute('data-fname');
        document.getElementById('createInputClass').value = option.getAttribute('data-class');
        document.getElementById('createInputSession').value = option.getAttribute('data-session');
        document.getElementById('createInputIssue').value = option.getAttribute('data-issue');
        document.getElementById('createInputExpiry').value = option.getAttribute('data-expiry');
    }

    function previewNewCard() {
        if (!document.getElementById('createUserId').value) {
            alert('Please select a student first.');
            return false;
        }

        const idStr = document.getElementById('createIdStr').value;
        const expiry = document.getElementById('createInputExpiry').value;
        
        const customPhoto = document.getElementById('createCustomPhoto').value;
        const dbPhoto = document.getElementById('createOriginalPhoto').value;
        const finalPhoto = customPhoto ? customPhoto : dbPhoto;

        document.getElementById('cardName').textContent = document.getElementById('createInputName').value;
        document.getElementById('cardFName').textContent = document.getElementById('createInputFName').value;
        document.getElementById('cardId').textContent = idStr;
        document.getElementById('cardClass').textContent = document.getElementById('createInputClass').value;
        document.getElementById('cardSession').textContent = document.getElementById('createInputSession').value;
        document.getElementById('cardIssue').textContent = document.getElementById('createInputIssue').value;
        document.getElementById('cardExpiry').textContent = expiry;
        document.getElementById('cardValidity').textContent = expiry.toUpperCase();
        document.getElementById('cardPhoto').src = finalPhoto;
        document.getElementById('cardQR').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(idStr)}`;

        document.getElementById('save_user_id').value = document.getElementById('createUserId').value;
        document.getElementById('save_card_name').value = document.getElementById('createInputName').value;
        document.getElementById('save_card_fname').value = document.getElementById('createInputFName').value;
        document.getElementById('save_card_class').value = document.getElementById('createInputClass').value;
        document.getElementById('save_card_session').value = document.getElementById('createInputSession').value;
        document.getElementById('save_issue_date').value = document.getElementById('createInputIssue').value;
        document.getElementById('save_expiry_date').value = document.getElementById('createInputExpiry').value;
        document.getElementById('save_custom_photo').value = customPhoto; 

        document.getElementById('previewModeControls').style.display = 'block';
        document.getElementById('confirmedModeControls').style.display = 'none';
        document.getElementById('studio-tab').removeAttribute('disabled');
        const studioTab = new bootstrap.Tab(document.querySelector('#studio-tab'));
        studioTab.show();
    }

    // --- WORKFLOW 2: LOAD CONFIRMED RECORD ---
    function openInStudio(data) {
        document.getElementById('cardName').textContent = data.name;
        document.getElementById('cardFName').textContent = data.fname;
        document.getElementById('cardId').textContent = data.id;
        document.getElementById('cardClass').textContent = data.class;
        document.getElementById('cardSession').textContent = data.session;
        document.getElementById('cardIssue').textContent = data.issue;
        document.getElementById('cardExpiry').textContent = data.expiry;
        document.getElementById('cardValidity').textContent = data.expiry.toUpperCase();
        document.getElementById('cardPhoto').src = data.photo;
        document.getElementById('cardQR').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(data.id)}`;
        
        document.getElementById('editName').value = data.name;
        document.getElementById('editFName').value = data.fname;
        document.getElementById('editId').value = data.id;
        document.getElementById('editClass').value = data.class;
        document.getElementById('editSession').value = data.session;
        document.getElementById('editIssue').value = data.issue;
        document.getElementById('editExpiry').value = data.expiry;
        
        document.getElementById('previewModeControls').style.display = 'none';
        document.getElementById('confirmedModeControls').style.display = 'block';
        document.getElementById('studio-tab').removeAttribute('disabled');
        const studioTab = new bootstrap.Tab(document.querySelector('#studio-tab'));
        studioTab.show();
    }

    function backToRecords() {
        const recordsTab = new bootstrap.Tab(document.querySelector('#records-tab'));
        recordsTab.show();
        document.getElementById('studio-tab').setAttribute('disabled', 'true');
    }

    // --- WORKFLOW 3: DELETE CONFIRMED CARD (AJAX) ---
    function deleteCard(id, name) {
        if(confirm(`Are you sure you want to delete the ID Card record for ${name}?`)) {
            $.ajax({
                url: 'id-card-generator.php',
                type: 'POST',
                data: { action: 'delete_card', card_id: id },
                dataType: 'json',
                success: function(res) {
                    if(res.success) {
                        dtTable.row($('#row-' + id)).remove().draw();
                        $('#alert-container').html(`<div class="alert alert-success alert-dismissible shadow-sm"><i class="fas fa-trash-alt me-2"></i> ${res.message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
                    } else {
                        alert(res.message);
                    }
                },
                error: function() {
                    alert('An error occurred while deleting the card.');
                }
            });
        }
    }

    // --- ADVANCED EDIT MODAL LOGIC ---
    document.getElementById('inputPhoto').addEventListener('change', function(e) {
        if (e.target.files[0]) {
            const r = new FileReader();
            r.onload = function(evt) { document.getElementById('cardPhoto').src = evt.target.result; }
            r.readAsDataURL(e.target.files[0]);
        }
    });

    document.getElementById('inputSignature').addEventListener('change', function(e) {
        if (e.target.files[0]) {
            const r = new FileReader();
            r.onload = function(evt) { document.getElementById('cardSignature').src = evt.target.result; }
            r.readAsDataURL(e.target.files[0]);
        }
    });

    function applyAdvancedUpdates() {
        const eId = document.getElementById('editId').value;
        const eExpiry = document.getElementById('editExpiry').value;
        
        document.getElementById('cardName').textContent = document.getElementById('editName').value;
        document.getElementById('cardFName').textContent = document.getElementById('editFName').value;
        document.getElementById('cardId').textContent = eId;
        document.getElementById('cardClass').textContent = document.getElementById('editClass').value;
        document.getElementById('cardSession').textContent = document.getElementById('editSession').value;
        document.getElementById('cardIssue').textContent = document.getElementById('editIssue').value;
        document.getElementById('cardExpiry').textContent = eExpiry;
        document.getElementById('cardValidity').textContent = eExpiry.toUpperCase();
        document.getElementById('cardQR').src = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(eId)}`;

        document.getElementById('backCampusTitle').textContent = document.getElementById('inputCampusTitle').value;
        document.getElementById('backCampusAddress').innerHTML = document.getElementById('inputCampusAddress').value.replace(/\n/g, '<br>');
        document.getElementById('backPhone').textContent = document.getElementById('inputPhone').value;
        document.getElementById('backWhatsapp').textContent = document.getElementById('inputWhatsapp').value;
        document.getElementById('backWebsite').textContent = document.getElementById('inputWebsite').value;
        document.getElementById('backEmail').textContent = document.getElementById('inputEmail').value;

        const instList = document.getElementById('inputInstructions').value.split('\n').filter(i => i.trim() !== '');
        document.getElementById('backInstructions').innerHTML = instList.map(i => `<li>${i}</li>`).join('');
    }

    // --- DOWNLOAD LOGIC (CHANGED SCALE TO 4 FOR HIGH RESOLUTION) ---
    function downloadCard(type, event) {
        event.preventDefault(); 
        const studentId = document.getElementById('cardId').textContent.trim();
        const mainBtn = document.getElementById('downloadBtnMenu');
        const originalText = mainBtn.innerHTML;
        
        let targetElement, fileNameSuffix;
        if (type === 'front') { targetElement = document.getElementById('cardFront'); fileNameSuffix = 'Front'; } 
        else if (type === 'back') { targetElement = document.getElementById('cardBack'); fileNameSuffix = 'Back'; } 
        else { targetElement = document.getElementById('cardCaptureArea'); fileNameSuffix = 'Both'; }
        
        mainBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating...';
        mainBtn.disabled = true;

        // CRITICAL FIX: SCALE 4 APPLIED FOR HIGH DPI DOWNLOADING
        html2canvas(targetElement, {
            scale: 4, 
            useCORS: true, 
            allowTaint: false,
            backgroundColor: (type === 'both') ? null : '#ffffff' 
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = `MSST_ID_${studentId}_${fileNameSuffix}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
            mainBtn.innerHTML = originalText;
            mainBtn.disabled = false;
        }).catch(err => {
            console.error("Error: ", err);
            alert("Failed to generate image.");
            mainBtn.innerHTML = originalText;
            mainBtn.disabled = false;
        });
    }
</script>
</body>
</html>