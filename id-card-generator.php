<?php
session_start();
// require_once __DIR__ . '/config/db_config.php'; // For Phase 3: DB Integration

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card Generator | MSST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* --- PREMIUM ID CARD STYLES (CR80: 54mm x 86mm STRICT) --- */
        :root {
            --brand-primary: #906833;
            --brand-accent: #cc8636;
            --text-dark: #2c3e50;
        }

        .workspace-area {
            background: #e9ecef;
            padding: 30px;
            border-radius: 12px;
            display: flex;
            justify-content: center;
        }

        .print-layout {
            display: flex;
            gap: 15px; 
            justify-content: center;
            align-items: flex-start;
        }

        .id-card {
            width: 54mm;
            height: 86mm;
            background-color: #ffffff;
            position: relative;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border: 1px solid #ddd;
            z-index: 1;
        }

        /* Subtle Watermark Background */
        .id-card::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 45mm;
            height: 45mm;
            transform: translate(-50%, -50%);
            background-image: url('/assets/images/msst-logo.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 0.05;
            z-index: -1;
        }

        /* --- FRONT DESIGN --- */
        .card-front .header {
            background: var(--brand-primary);
            color: #ffffff;
            text-align: center;
            padding: 3mm 1mm;
            border-bottom: 2mm solid var(--brand-accent);
        }
        
        .card-front .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2mm;
            margin-bottom: 1.5mm;
        }

        .card-front .logo {
            width: 10mm;
            height: 10mm;
            background: white;
            border-radius: 50%;
            padding: 0.5mm;
        }

        .card-front .school-name {
            font-size: 6.5pt;
            font-weight: 900;
            line-height: 1.1;
            text-align: left;
            text-transform: uppercase;
        }

        .card-front .motto {
            font-size: 4.5pt;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #f8f9fa;
            text-align: center;
        }

        .card-front .badge-title {
            background-color: #f1f3f5;
            color: var(--brand-primary);
            text-align: center;
            font-size: 6.5pt;
            font-weight: 900;
            padding: 1.5mm 0;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-bottom: 1px solid #ddd;
        }

        .card-front .photo-wrap {
            text-align: center;
            margin-top: 2.5mm;
            margin-bottom: 2.5mm;
        }

        .card-front .student-photo {
            width: 14mm;  
            height: 18mm; 
            object-fit: cover;
            border: 1.5px solid var(--brand-primary);
            border-radius: 2mm;
            padding: 1px;
            background: #fff;
        }

        .card-front .info-grid {
            display: grid;
            grid-template-columns: 18mm 1fr;
            gap: 1.2mm;
            padding: 0 4mm;
            font-size: 6pt;
            line-height: 1.2;
        }

        .card-front .info-lbl {
            font-weight: 800;
            color: var(--brand-primary);
        }

        .card-front .info-val {
            font-weight: 700;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-front .dates-container {
            display: flex;
            justify-content: space-between;
            padding: 0 4mm;
            margin-top: 3.5mm;
            font-size: 5pt;
            font-weight: bold;
            color: #555;
        }

        .card-front .principal-sig {
            position: absolute;
            bottom: 8mm; 
            right: 4mm;
            text-align: center;
            font-size: 5pt;
            font-weight: bold;
            color: var(--text-dark);
        }

        .card-front .sig-line {
            width: 15mm;
            border-bottom: 1px solid var(--text-dark);
            margin-bottom: 1mm;
        }

        .card-front .validity-bar {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: var(--brand-primary);
            color: white;
            text-align: center;
            font-size: 5.5pt;
            font-weight: bold;
            padding: 1.5mm 0;
            letter-spacing: 0.5px;
        }

        /* --- BACK DESIGN --- */
        .card-back {
            background-color: #fdfdfd;
        }

        .card-back .header {
            background: var(--brand-primary);
            color: white;
            text-align: center;
            padding: 2.5mm 2mm;
            border-bottom: 2mm solid var(--brand-accent);
        }

        .card-back .campus-title {
            font-size: 7.5pt;
            font-weight: 900;
            letter-spacing: 1px;
        }

        .card-back .address {
            font-size: 6pt;
            margin-top: 1.5mm;
            line-height: 1.3;
            font-weight: 600;
        }

        .card-back .contact-grid {
            padding: 3mm 4mm;
            font-size: 6pt;
            border-bottom: 1px solid #eee;
        }

        .card-back .contact-item {
            margin-bottom: 1.5mm;
            display: flex;
            gap: 2mm;
        }

        .card-back .contact-icon {
            color: var(--brand-primary);
            width: 3mm;
            text-align: center;
        }

        .card-back .contact-text {
            font-weight: 700;
            color: var(--text-dark);
        }

        .card-back .instructions {
            padding: 2mm 4mm;
        }

        .card-back .instructions h4 {
            font-size: 6.5pt;
            font-weight: 900;
            color: var(--brand-primary);
            margin-bottom: 1.5mm;
            text-decoration: underline;
            text-decoration-color: var(--brand-accent);
            text-underline-offset: 2px;
        }

        .card-back .instructions ul {
            padding-left: 3mm;
            margin: 0;
            font-size: 5.5pt;
            color: var(--text-dark);
            line-height: 1.4;
            font-weight: 600;
        }
        
        .card-back .instructions ul li {
            margin-bottom: 1mm;
        }

        /* --- EXACT EPSON PRINT OVERRIDES --- */
        @media print {
            @page {
                size: A4; 
                margin: 0; 
            }

            body {
                margin: 0;
                padding: 10mm; 
                background: white;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            #sidebar, #main-content > header, .footer, .no-print, .topbar, .modal {
                display: none !important;
            }

            #main-content { margin: 0 !important; padding: 0 !important; }
            .content-wrapper { padding: 0 !important; }
            
            .workspace-area {
                background: none !important;
                padding: 0 !important;
                border: none !important;
                display: block !important;
            }

            .print-layout {
                display: flex !important;
                flex-direction: row !important;
                gap: 5mm !important; 
                justify-content: flex-start !important;
                align-items: flex-start !important;
                page-break-inside: avoid !important;
            }

            .id-card {
                box-shadow: none !important;
                border: 0.1mm solid #ccc !important;
                margin: 0 !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>

    <div class="content-wrapper p-4">
        <!-- Controls (Hidden on Print) -->
        <div class="container-fluid no-print">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">ID Card Generator</h1>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-secondary shadow-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#editCardModal">
                        <i class="fas fa-edit me-2"></i> Edit Details
                    </button>

                    <!-- SEPARATED DOWNLOAD DROPDOWN MENU -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-success shadow-sm fw-bold px-3 dropdown-toggle" type="button" id="downloadBtnMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download me-2"></i> Download Image
                        </button>
                        <ul class="dropdown-menu shadow" aria-labelledby="downloadBtnMenu">
                            <li><a class="dropdown-item fw-bold text-success" href="#" onclick="downloadCard('front', event)"><i class="fas fa-id-card me-2"></i>Download Front Only</a></li>
                            <li><a class="dropdown-item fw-bold text-primary" href="#" onclick="downloadCard('back', event)"><i class="fas fa-barcode me-2"></i>Download Back Only</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item fw-bold text-dark" href="#" onclick="downloadCard('both', event)"><i class="fas fa-object-group me-2"></i>Download Both (Side-by-Side)</a></li>
                        </ul>
                    </div>

                    <button class="btn btn-primary shadow-sm fw-bold px-3" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Print PVC
                    </button>
                </div>
            </div>
        </div>

        <!-- Workspace Area -->
        <div class="workspace-area">
            
            <!-- PRINT LAYOUT CONTAINER -->
            <div class="print-layout" id="cardCaptureArea">
                
                <!-- FRONT OF CARD (Added specific ID) -->
                <div class="id-card card-front" id="cardFront">
                    <div class="header">
                        <div class="header-content">
                            <!-- crossorigin="anonymous" required for image capture -->
                            <img src="http://localhost/msst/lms/assets/images/msst-logo.png" alt="Logo" class="logo" crossorigin="anonymous">
                            <div class="school-name">Muhaddisa School of<br>Science & Technology</div>
                        </div>
                        <div class="motto">KNOWLEDGE • DISCIPLINE • EXCELLENCE</div>
                    </div>
                    
                    <div class="badge-title">STUDENT IDENTITY CARD</div>
                    
                    <div class="photo-wrap">
                        <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=200&h=250" id="cardPhoto" class="student-photo" alt="Student Photo" crossorigin="anonymous">
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-lbl">Name:</div>
                        <div class="info-val" id="cardName">Aliya Batool</div>
                        
                        <div class="info-lbl">F. Name:</div>
                        <div class="info-val" id="cardFName">Ghulam Ali</div>
                        
                        <div class="info-lbl">Student ID:</div>
                        <div class="info-val" id="cardId">MST-26-0128</div>
                        
                        <div class="info-lbl">Class:</div>
                        <div class="info-val" id="cardClass">FSC Pre-Medical</div>
                        
                        <div class="info-lbl">Session:</div>
                        <div class="info-val" id="cardSession">2026 – 2027</div>
                    </div>
                    
                    <div class="dates-container">
                        <div>Issue Date: <span id="cardIssue">May 2025</span><br>Expiry Date: <span id="cardExpiry">May 2028</span></div>
                    </div>
                    
                    <div class="principal-sig">
                        <div class="sig-line"></div>
                        Principal
                    </div>

                    <div class="validity-bar">
                        VALID UNTIL <span id="cardValidity">MAY 2028</span>
                    </div>
                </div>

                <!-- BACK OF CARD (Added specific ID) -->
                <div class="id-card card-back" id="cardBack">
                    <div class="header">
                        <div class="campus-title">CAMPUS ADDRESS</div>
                        <div class="address">
                            Kushmara Toq, Near Fatima Jinnah<br>
                            Girls HSS, Quaidabad Skardu
                        </div>
                    </div>
                    
                    <div class="contact-grid">
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                            <div class="contact-text">0317 9174495 | 0355 5851351</div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fab fa-whatsapp"></i></div>
                            <div class="contact-text">0355 4201394</div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fas fa-globe"></i></div>
                            <div class="contact-text">www.msstskardu.com</div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                            <div class="contact-text">msst.skd@gmail.com</div>
                        </div>
                    </div>
                    
                    <div class="instructions">
                        <h4>IMPORTANT INSTRUCTIONS</h4>
                        <ul>
                            <li>This card is the property of the institute.</li>
                            <li>It must be carried daily by the student.</li>
                            <li>This card is non-transferable.</li>
                            <li>Report immediately if lost.</li>
                        </ul>
                    </div>
                    
                    <!-- Dynamic QR Code -->
                    <div style="position: absolute; bottom: 4mm; width: 100%; text-align: center;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=MST-26-0128" id="cardQR" style="width: 14mm; height: 14mm;" alt="QR Code" crossorigin="anonymous">
                    </div>
                </div>

            </div>
            <!-- END PRINT LAYOUT CONTAINER -->
            
        </div>
    </div>
</div>

<!-- EDIT CARD MODAL -->
<div class="modal fade no-print" id="editCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-id-badge me-2"></i> Edit Card Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form id="cardDataForm">
                    <div class="row g-3">
                        
                        <!-- Photo Upload -->
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold text-primary">Student Photo</label>
                            <input class="form-control" type="file" id="inputPhoto" accept="image/png, image/jpeg, image/jpg">
                            <small class="text-muted">Select an image to preview it instantly on the card.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Student Name</label>
                            <input type="text" class="form-control" id="inputName" value="Aliya Batool">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Father's Name</label>
                            <input type="text" class="form-control" id="inputFName" value="Ghulam Ali">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Student ID</label>
                            <input type="text" class="form-control" id="inputId" value="MST-26-0128">
                            <small class="text-muted" style="font-size: 0.7rem;">QR Code will update automatically.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Class</label>
                            <input type="text" class="form-control" id="inputClass" value="FSC Pre-Medical">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Session</label>
                            <input type="text" class="form-control" id="inputSession" value="2026 – 2027">
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold">Issue Date (e.g., May 2025)</label>
                            <input type="text" class="form-control" id="inputIssue" value="May 2025">
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold">Expiry Date (e.g., May 2028)</label>
                            <input type="text" class="form-control" id="inputExpiry" value="May 2028">
                        </div>

                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-white">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success px-4 fw-bold shadow-sm" onclick="applyCardUpdates()" data-bs-dismiss="modal"><i class="fas fa-check me-2"></i> Apply to Card</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Include html2canvas library for capturing the HTML as an image -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    // Sidebar toggle logic
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

    // --- INSTANT CARD UPDATE LOGIC ---
    document.getElementById('inputPhoto').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('cardPhoto').src = event.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    function applyCardUpdates() {
        const name = document.getElementById('inputName').value;
        const fName = document.getElementById('inputFName').value;
        const id = document.getElementById('inputId').value;
        const cls = document.getElementById('inputClass').value;
        const session = document.getElementById('inputSession').value;
        const issue = document.getElementById('inputIssue').value;
        const expiry = document.getElementById('inputExpiry').value;

        document.getElementById('cardName').textContent = name;
        document.getElementById('cardFName').textContent = fName;
        document.getElementById('cardId').textContent = id;
        document.getElementById('cardClass').textContent = cls;
        document.getElementById('cardSession').textContent = session;
        document.getElementById('cardIssue').textContent = issue;
        document.getElementById('cardExpiry').textContent = expiry;
        
        document.getElementById('cardValidity').textContent = expiry.toUpperCase();

        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(id)}`;
        document.getElementById('cardQR').src = qrUrl;
    }

    // --- ENHANCED TARGETED IMAGE DOWNLOAD LOGIC ---
    function downloadCard(type, event) {
        event.preventDefault(); // Prevent dropdown from jumping to top
        const studentId = document.getElementById('cardId').textContent.trim();
        const mainBtn = document.getElementById('downloadBtnMenu');
        const originalText = mainBtn.innerHTML;
        
        let targetElement;
        let fileNameSuffix;

        // Determine what element to take a screenshot of
        if (type === 'front') {
            targetElement = document.getElementById('cardFront');
            fileNameSuffix = 'Front';
        } else if (type === 'back') {
            targetElement = document.getElementById('cardBack');
            fileNameSuffix = 'Back';
        } else {
            targetElement = document.getElementById('cardCaptureArea');
            fileNameSuffix = 'Both';
        }
        
        mainBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating...';
        mainBtn.disabled = true;

        // Generate the image
        html2canvas(targetElement, {
            scale: 1, // 4x scale for high resolution printing
            useCORS: true, 
            allowTaint: false,
            // If downloading 'both', keep background transparent so they don't get stitched with white.
            // If individual, ensure a clean white background.
            backgroundColor: (type === 'both') ? null : '#ffffff' 
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = `MSST_ID_${studentId}_${fileNameSuffix}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
            
            mainBtn.innerHTML = originalText;
            mainBtn.disabled = false;
        }).catch(err => {
            console.error("Error generating image: ", err);
            alert("Failed to generate image. Please ensure your logo and photo are accessible via HTTPs.");
            mainBtn.innerHTML = originalText;
            mainBtn.disabled = false;
        });
    }
</script>
</body>
</html>