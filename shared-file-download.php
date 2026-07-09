<?php
session_start();
require_once __DIR__ . '/config/db_config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit('Access denied.');
}

$current_type = 'admin';
$current_ref_id = (int)($_SESSION['admin_id'] ?? 0);

$file_id = (int)($_GET['file_id'] ?? 0);
$stmt = $pdo->prepare("SELECT file_name, file_path, sender_type, sender_ref_id FROM shared_files WHERE id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

$is_sender = ($file['sender_type'] === $current_type && (int)$file['sender_ref_id'] === $current_ref_id);
$is_recipient = false;
if (!$is_sender) {
    $stmt_r = $pdo->prepare("SELECT COUNT(*) FROM shared_file_recipients WHERE shared_file_id = ? AND recipient_type = ? AND recipient_ref_id = ?");
    $stmt_r->execute([$file_id, $current_type, $current_ref_id]);
    $is_recipient = (bool)$stmt_r->fetchColumn();
}

if (!$is_sender && !$is_recipient) {
    http_response_code(403);
    exit('Access denied.');
}

$full_path = __DIR__ . '/' . $file['file_path'];
if (!file_exists($full_path)) {
    http_response_code(404);
    exit('File not found on disk.');
}

// Disable output compression/buffering - a host-level gzip filter changing
// the byte count after Content-Length is already sent corrupts the download
// (browsers/Office/Adobe reject the mismatched file as "unreadable").
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
@ini_set('zlib.output_compression', 'Off');
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($full_path));
header('X-Content-Type-Options: nosniff');
readfile($full_path);
exit;
