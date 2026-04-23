<?php
session_start();

// Check if the admin is logged in.
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Set admin details from the session.
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
date_default_timezone_set('Asia/Karachi');

// --- Dummy Notice Data ---
$notices = [
    [
        'id' => 'NTC-001',
        'title' => 'School Holiday Announcement',
        'message' => 'The school will remain closed on Tuesday, June 17, 2025 on account of a local holiday. Classes will resume as normal on Wednesday, June 18, 2025.',
        'status' => 'Sent',
        'sent_to' => ['All Students', 'All Teachers', 'Staff'],
        'sent_on' => '2025-06-16 14:30:00',
        'scheduled_for' => '2025-06-16 14:30:00',
    ],
    [
        'id' => 'NTC-002',
        'title' => 'Parent-Teacher Meeting',
        'message' => 'A parent-teacher meeting for classes 9 and 10 is scheduled for Saturday, June 21, 2025, from 09:00 AM to 12:00 PM.',
        'status' => 'Scheduled',
        'sent_to' => ['Class 9-A', 'Class 9-B', 'Class 10-A', 'Class 10-B'],
        'sent_on' => null,
        'scheduled_for' => '2025-06-20 09:00:00',
    ],
     [
        'id' => 'NTC-003',
        'title' => 'Mid-Term Exam Schedule',
        'message' => 'The mid-term examination schedule for all classes has been uploaded to the content portal. Please review it carefully.',
        'status' => 'Sent',
        'sent_to' => ['All Students'],
        'sent_on' => '2025-06-15 11:00:00',
        'scheduled_for' => '2025-06-15 11:00:00',
    ],
];

// Data for forms
$available_target_groups = ['All Students', 'All Teachers', 'Staff', 'Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice Board | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .access-tag {
            display: inline-block; padding: 0.25em 0.6em; font-size: 75%; font-weight: 700;
            line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline;
            border-radius: 0.25rem; margin: 2px;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div id="main-content">
    <?php include 'header.php'; ?>
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Page Heading & Actions -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Notice Board</h1>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#noticeModal">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Create New Notice
                </button>
            </div>

            <!-- Delivery Logs Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Delivery Logs</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead><tr><th>Title</th><th>Status</th><th>Target Audience</th><th>Date & Time</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($notices as $notice): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($notice['title']) ?></td>
                                        <td>
                                            <span class="badge <?= $notice['status'] == 'Sent' ? 'bg-success' : 'bg-warning' ?>">
                                                <i class="fas <?= $notice['status'] == 'Sent' ? 'fa-check-circle' : 'fa-clock' ?> me-1"></i>
                                                <?= htmlspecialchars($notice['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php foreach($notice['sent_to'] as $group): ?>
                                                <span class="access-tag bg-light text-dark border"><?= htmlspecialchars($group) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <?= $notice['status'] == 'Sent' ? date('M d, Y h:i A', strtotime($notice['sent_on'])) : 'Scheduled for ' . date('M d, Y h:i A', strtotime($notice['scheduled_for'])) ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-secondary btn-sm" title="View Notice" data-bs-toggle="modal" data-bs-target="#viewNoticeModal" onclick='viewNotice(<?= json_encode($notice) ?>)'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer"><div class="container-fluid"><p class="text-center mb-0">© <?= date('Y') ?> School Management System. All rights reserved.</p></div></footer>
</div>

<!-- Create Notice Modal -->
<div class="modal fade" id="noticeModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Create New Notice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form id="noticeForm">
            <div class="mb-3"><label class="form-label">Notice Title</label><input type="text" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" rows="5" required></textarea></div>
            <div class="mb-3"><label class="form-label">Send To</label><select class="form-select" multiple required size="4"><?php foreach($available_target_groups as $group): echo "<option>$group</option>"; endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label">Schedule For</label><input type="datetime-local" class="form-control" id="scheduleTime" required></div>
        </form>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="noticeForm" class="btn btn-primary">Schedule/Send Notice</button></div>
</div></div></div>

<!-- View Notice Modal -->
<div class="modal fade" id="viewNoticeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="viewNoticeTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p><strong class="me-2">Status:</strong><span id="viewNoticeStatus"></span></p>
        <p><strong class="me-2">Audience:</strong><span id="viewNoticeAudience"></span></p>
        <p><strong class="me-2">Time:</strong><span id="viewNoticeTime"></span></p>
        <hr>
        <p id="viewNoticeMessage" style="white-space: pre-wrap;"></p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Sidebar Script ---
    const sidebar = document.getElementById('sidebar'), mainContent = document.getElementById('main-content'), toggleBtn = document.getElementById('sidebarToggle');
    function saveSidebarState(c){localStorage.setItem('sidebarCollapsed',c);}
    function loadSidebarState(){return localStorage.getItem('sidebarCollapsed')==='true';}
    if(loadSidebarState()){sidebar.classList.add('collapsed');mainContent.classList.add('collapsed');}
    toggleBtn.addEventListener('click',()=>{sidebar.classList.toggle('collapsed');mainContent.classList.toggle('collapsed');saveSidebarState(sidebar.classList.contains('collapsed'));if(window.innerWidth<768){if(!sidebar.classList.contains('collapsed')){sidebar.classList.add('show');mainContent.classList.add('show-sidebar');}else{sidebar.classList.remove('show');mainContent.classList.remove('show-sidebar');}}});
    document.addEventListener('click',(e)=>{if(window.innerWidth<768&&sidebar.classList.contains('show')&&!sidebar.contains(e.target)&&!toggleBtn.contains(e.target)){sidebar.classList.remove('show');sidebar.classList.add('collapsed');mainContent.classList.remove('show-sidebar');mainContent.classList.add('collapsed');saveSidebarState(true);}});

    // --- Page-specific Scripts ---
    document.getElementById('noticeForm').addEventListener('submit', (e) => { e.preventDefault(); alert('Notice has been scheduled/sent successfully. (Simulation)'); bootstrap.Modal.getInstance(document.getElementById('noticeModal')).hide(); });
    
    // Set default schedule time to now
    function setDefaultTime() {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('scheduleTime').value = now.toISOString().slice(0,16);
    }
    setDefaultTime();

    function viewNotice(notice) {
        document.getElementById('viewNoticeTitle').textContent = notice.title;
        document.getElementById('viewNoticeMessage').textContent = notice.message;

        const statusBadge = `<span class="badge ${notice.status == 'Sent' ? 'bg-success' : 'bg-warning'}">${notice.status}</span>`;
        document.getElementById('viewNoticeStatus').innerHTML = statusBadge;
        
        let audienceHtml = '';
        notice.sent_to.forEach(group => {
            audienceHtml += `<span class="access-tag bg-light text-dark border">${group}</span>`;
        });
        document.getElementById('viewNoticeAudience').innerHTML = audienceHtml;
        
        const timeText = notice.status == 'Sent' ? `Sent on ${new Date(notice.sent_on).toLocaleString()}` : `Scheduled for ${new Date(notice.scheduled_for).toLocaleString()}`;
        document.getElementById('viewNoticeTime').textContent = timeText;
    }
</script>
</body>
</html>
