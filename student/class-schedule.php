<?php
session_start();
// require_once __DIR__ . '/../config/db_config.php'; // Uncomment if schedule data comes from DB

// Basic login check, adjust as needed for your application
if (!isset($_SESSION['student_logged_in'])) {
    // header('Location: login.php');
    // exit();
}

// --- Placeholder Class Schedule Data ---
// In a real application, you would fetch this from a database,
// possibly based on the student's class, section, or user role.
$days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
$time_slots = [
    "08:00 AM - 08:50 AM",
    "08:50 AM - 09:40 AM",
    "09:40 AM - 10:30 AM",
    "10:30 AM - 10:50 AM", // Break
    "10:50 AM - 11:40 AM",
    "11:40 AM - 12:30 PM",
    "12:30 PM - 01:10 PM", // Lunch Break
    "01:10 PM - 02:00 PM",
    "02:00 PM - 02:50 PM",
];

// Example: schedule_data[TIME_SLOT][DAY]
$schedule_data = [
    "08:00 AM - 08:50 AM" => [
        "Monday"    => ['subject' => "Mathematics", 'teacher' => "Mr. A. Khan", 'room' => "101"],
        "Tuesday"   => ['subject' => "Physics", 'teacher' => "Ms. B. Sharma", 'room' => "Lab A"],
        "Wednesday" => ['subject' => "Mathematics", 'teacher' => "Mr. A. Khan", 'room' => "101"],
        "Thursday"  => ['subject' => "Physics", 'teacher' => "Ms. B. Sharma", 'room' => "Lab A"],
        "Friday"    => ['subject' => "Mathematics", 'teacher' => "Mr. A. Khan", 'room' => "101"],
        "Saturday"  => ['subject' => "Sports", 'teacher' => "Mr. C. Lee", 'room' => "Field"],
    ],
    "08:50 AM - 09:40 AM" => [
        "Monday"    => ['subject' => "English", 'teacher' => "Mrs. D. Singh", 'room' => "102"],
        "Tuesday"   => ['subject' => "Chemistry", 'teacher' => "Mr. E. David", 'room' => "Lab B"],
        "Wednesday" => ['subject' => "English", 'teacher' => "Mrs. D. Singh", 'room' => "102"],
        "Thursday"  => ['subject' => "Chemistry", 'teacher' => "Mr. E. David", 'room' => "Lab B"],
        "Friday"    => ['subject' => "English", 'teacher' => "Mrs. D. Singh", 'room' => "102"],
        "Saturday"  => ['subject' => "Activities", 'teacher' => "Ms. F. Roy", 'room' => "Hall"],
    ],
    "09:40 AM - 10:30 AM" => [
        "Monday"    => ['subject' => "Biology", 'teacher' => "Ms. G. Fatima", 'room' => "Lab C"],
        "Tuesday"   => ['subject' => "Computer Sc.", 'teacher' => "Mr. H. Ali", 'room' => "Comp Lab"],
        "Wednesday" => ['subject' => "Biology", 'teacher' => "Ms. G. Fatima", 'room' => "Lab C"],
        "Thursday"  => ['subject' => "Computer Sc.", 'teacher' => "Mr. H. Ali", 'room' => "Comp Lab"],
        "Friday"    => ['subject' => "Biology", 'teacher' => "Ms. G. Fatima", 'room' => "Lab C"],
        "Saturday"  => ['subject' => ""], // Empty slot
    ],
    "10:30 AM - 10:50 AM" => [ // Break
        "Monday"    => ['subject' => "B R E A K", 'break' => true],
        "Tuesday"   => ['subject' => "B R E A K", 'break' => true],
        "Wednesday" => ['subject' => "B R E A K", 'break' => true],
        "Thursday"  => ['subject' => "B R E A K", 'break' => true],
        "Friday"    => ['subject' => "B R E A K", 'break' => true],
        "Saturday"  => ['subject' => "B R E A K", 'break' => true],
    ],
    "10:50 AM - 11:40 AM" => [
        "Monday"    => ['subject' => "Social Studies", 'teacher' => "Mr. I. Ahmed", 'room' => "103"],
        "Tuesday"   => ['subject' => "Urdu/Hindi", 'teacher' => "Ms. J. Kaur", 'room' => "104"],
        "Wednesday" => ['subject' => "Social Studies", 'teacher' => "Mr. I. Ahmed", 'room' => "103"],
        "Thursday"  => ['subject' => "Urdu/Hindi", 'teacher' => "Ms. J. Kaur", 'room' => "104"],
        "Friday"    => ['subject' => "Social Studies", 'teacher' => "Mr. I. Ahmed", 'room' => "103"],
        "Saturday"  => ['subject' => ""],
    ],
     "11:40 AM - 12:30 PM" => [
        "Monday"    => ['subject' => "Art/Music", 'teacher' => "Ms. K. Lata", 'room' => "Art Room"],
        "Tuesday"   => ['subject' => "Physics Practical", 'teacher' => "Ms. B. Sharma", 'room' => "Lab A"],
        "Wednesday" => ['subject' => "Art/Music", 'teacher' => "Ms. K. Lata", 'room' => "Art Room"],
        "Thursday"  => ['subject' => "Chemistry Practical", 'teacher' => "Mr. E. David", 'room' => "Lab B"],
        "Friday"    => ['subject' => "Library", 'teacher' => "Librarian", 'room' => "Library"],
        "Saturday"  => ['subject' => ""],
    ],
    "12:30 PM - 01:10 PM" => [ // Lunch Break
        "Monday"    => ['subject' => "L U N C H", 'break' => true],
        "Tuesday"   => ['subject' => "L U N C H", 'break' => true],
        "Wednesday" => ['subject' => "L U N C H", 'break' => true],
        "Thursday"  => ['subject' => "L U N C H", 'break' => true],
        "Friday"    => ['subject' => "L U N C H", 'break' => true],
        "Saturday"  => ['subject' => "L U N C H", 'break' => true],
    ],
    "01:10 PM - 02:00 PM" => [
        "Monday"    => ['subject' => "P.E.", 'teacher' => "Mr. C. Lee", 'room' => "Field"],
        "Tuesday"   => ['subject' => "Mathematics", 'teacher' => "Mr. A. Khan", 'room' => "101"],
        "Wednesday" => ['subject' => "P.E.", 'teacher' => "Mr. C. Lee", 'room' => "Field"],
        "Thursday"  => ['subject' => "English", 'teacher' => "Mrs. D. Singh", 'room' => "102"],
        "Friday"    => ['subject' => "Value Education", 'teacher' => "Ms. M. Jose", 'room' => "105"],
        "Saturday"  => ['subject' => ""],
    ],
    "02:00 PM - 02:50 PM" => [
        "Monday"    => ['subject' => "Physics", 'teacher' => "Ms. B. Sharma", 'room' => "Lab A"],
        "Tuesday"   => ['subject' => "Biology", 'teacher' => "Ms. G. Fatima", 'room' => "Lab C"],
        "Wednesday" => ['subject' => "Chemistry", 'teacher' => "Mr. E. David", 'room' => "Lab B"],
        "Thursday"  => ['subject' => "Social Studies", 'teacher' => "Mr. I. Ahmed", 'room' => "103"],
        "Friday"    => ['subject' => "Club Activity", 'teacher' => "Coordinator", 'room' => "Hall"],
        "Saturday"  => ['subject' => ""],
    ],
];

// You can add logic here to select a specific class schedule if needed, e.g., based on $_GET['class_id']
$current_class_name = "Class X - Section A"; // Example class name

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule | <?php echo $_SESSION['student_name'] ?? 'LMS'; ?></title>

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


        /* CLASS SCHEDULE SPECIFIC STYLES */
        .schedule-table-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.85rem; /* Adjust for readability */
        }
        .schedule-table th, .schedule-table td {
            border: 1px solid #dee2e6; /* Lighter borders */
            padding: 10px; /* More padding */
            text-align: center;
            vertical-align: middle;
        }
        .schedule-table th {
            background-color: var(--primary); /* Use theme primary color */
            color: white;
            font-weight: 600; /* Slightly bolder */
        }
        .schedule-table td .subject {
            font-weight: bold;
            display: block;
            margin-bottom: 3px;
            color: var(--dark-text);
        }
        .schedule-table td .teacher {
            font-size: 0.75rem;
            display: block;
            color: #777;
            margin-bottom: 2px;
        }
        .schedule-table td .room {
            font-size: 0.7rem;
            display: block;
            color: #999;
            font-style: italic;
        }
        .schedule-table td.break-slot {
            background-color: #f8f9fa; /* Light grey for breaks */
            font-weight: bold;
            color: var(--secondary); /* Use theme secondary color */
            text-transform: uppercase;
        }
        .schedule-table tr:nth-child(even) td:not(.break-slot) { /* Optional: Zebra striping for non-break rows */
           /* background-color: #f9f9f9; */
        }
        .schedule-table td:empty { /* Style empty cells if any */
            background-color: #fafafa;
        }
        .schedule-title-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .schedule-title-bar .print-schedule-btn {
             /* Style as needed */
        }

        /* PRINT STYLES FOR SCHEDULE */
        @media print {
            body {
                background-color: #fff !important; padding: 0 !important; margin: 0 !important;
                font-size: 8pt !important; /* Smaller font for print to fit more */
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }

            /* HIDE ALL DASHBOARD ELEMENTS - Confirm selectors match your HTML */
            #sidebar,
            #main-content > header, /* Or more specific selector for your header output */
            .dashboard-top-header, /* Example if header has this class */
            #main-content > footer.footer,
            .page-main-title, /* The H1 "Class Schedule" */
            .schedule-title-bar .print-schedule-btn /* Hide print button inside content */
            {
                display: none !important;
            }

            #main-content {
                margin-left: 0 !important; padding: 0 !important; width: 100% !important;
                border: none !important; box-shadow: none !important; float: none !important;
            }
            .content-wrapper { padding: 0 !important; margin: 0 !important; }
            .container-fluid.page-container-for-schedule { /* Class added for print */
                padding: 5mm !important; width: 100% !important; max-width: 100% !important;
                margin:0 !important;
            }
            .schedule-table-container {
                box-shadow: none !important; border: 1px solid #ccc;
                padding: 10px !important; margin-top: 0 !important;
            }
            .schedule-table th {
                background-color: #e9ecef !important; /* Lighter grey for print TH */
                color: #000 !important; /* Black text for print TH */
            }
            .schedule-table th, .schedule-table td {
                padding: 4px !important; /* Reduce padding for print */
                border: 1px solid #666 !important; /* Ensure borders are visible */
            }
            .schedule-table td .subject { font-size: 7.5pt; }
            .schedule-table td .teacher { font-size: 7pt; }
            .schedule-table td .room { font-size: 6.5pt; }
            .schedule-table td.break-slot {
                background-color: #f0f0f0 !important; color: #333 !important;
            }
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; // Ensure path is correct. Expected to create #sidebar. ?>

    <div id="main-content"> <?php include 'header.php'; // Ensure path is correct. Expected to create #sidebarToggle. ?>

        <div class="content-wrapper"> <div class="container-fluid page-container-for-schedule"> <div class="schedule-title-bar">
                    <h1 class="h3 mb-0 text-gray-800 page-main-title">Class Schedule <small class="text-muted fs-6">(<?= htmlspecialchars($current_class_name) ?>)</small></h1>
                    <button class="btn btn-sm btn-outline-primary print-schedule-btn" onclick="window.print()"><i class="fas fa-print"></i> Print Schedule</button>
                </div>

                <div class="schedule-table-container mt-4">
                    <div class="table-responsive"> <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <?php foreach ($days as $day) : ?>
                                        <th><?= htmlspecialchars($day) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($time_slots as $time) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($time) ?></td>
                                        <?php foreach ($days as $day) : ?>
                                            <?php $period_data = $schedule_data[$time][$day] ?? null; ?>
                                            <?php if ($period_data && isset($period_data['break'])) : ?>
                                                <td class="break-slot" colspan="<?= count($days) - array_search($day, $days)  ?>"><?= htmlspecialchars($period_data['subject']) ?></td>
                                                <?php break; // Break the inner loop for days as this colspan covers remaining ?>
                                            <?php elseif ($period_data && !empty($period_data['subject'])) : ?>
                                                <td>
                                                    <span class="subject"><?= htmlspecialchars($period_data['subject']) ?></span>
                                                    <?php if (isset($period_data['teacher']) && $period_data['teacher']) : ?>
                                                        <span class="teacher"><i class="fas fa-chalkboard-teacher fa-fw"></i> <?= htmlspecialchars($period_data['teacher']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if (isset($period_data['room']) && $period_data['room']) : ?>
                                                        <span class="room"><i class="fas fa-map-marker-alt fa-fw"></i> <?= htmlspecialchars($period_data['room']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php else : ?>
                                                <td></td> <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
        console.log('Class Schedule Page JS - Looking for elements:');
        console.log('Sidebar Element (#sidebar):', sidebar);
        console.log('Main Content Element (#main-content):', mainContent);
        console.log('Toggle Button Element (#sidebarToggle):', toggleBtn);

        if (sidebar && mainContent && toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
                // Your theme's CSS MUST define #sidebar.collapsed and #main-content.collapsed
            });

            toggleBtn.addEventListener('click', () => { // Second listener from your profile.php
                if (window.innerWidth < 768) {
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('show');
                    // Your theme's CSS MUST define #sidebar.show and #main-content.show for mobile
                }
            });
            console.log('Sidebar toggle event listeners have been attached.');
        } else {
            let missing = [];
            if (!sidebar) missing.push("#sidebar (from sidebar.php)");
            if (!mainContent) missing.push("#main-content (main page div)");
            if (!toggleBtn) missing.push("#sidebarToggle (from header.php)");
            console.error("LAYOUT/TOGGLE SCRIPT ERROR: Critical element(s) not found: " + missing.join(', ') + ". " +
                          "Verify IDs in included files and ensure your main layout CSS is linked.");
        }
    </script>
</body>
</html>