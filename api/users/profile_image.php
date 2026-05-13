<?php
// Serve profile images stored under /api/images/users via a safe proxy
$filename = basename($_GET['file'] ?? '');

if (!$filename) {
    http_response_code(400);
    exit;
}

$allowedDir = __DIR__ . '/../images/users/';
$absPath = realpath($allowedDir . $filename);
$allowedReal = realpath($allowedDir);

// Security: ensure file is within the allowed directory
if (!$absPath || !$allowedReal || strpos($absPath, $allowedReal) !== 0 || !is_file($absPath)) {
    http_response_code(404);
    exit;
}

// Determine mime type from extension
$ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
$mimeMap = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($absPath));
readfile($absPath);
exit;
