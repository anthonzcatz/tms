<?php
// Serve profile images stored under /api/images/users via a safe proxy
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';

// Only allow authenticated users (optional, comment out if public)
if (!class_exists('Auth') && file_exists(dirname(dirname(__DIR__)) . '/app/helpers/Auth.php')) {
    require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
}
if (function_exists('session_status') && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Resolve and validate path
$relPath = $_GET['path'] ?? '';
error_log("Profile image proxy - Requested path: " . $relPath);

if (!$relPath || strpos($relPath, '/api/images/users/') !== 0) {
    http_response_code(400);
    echo 'Invalid path: ' . $relPath;
    error_log("Profile image proxy - Invalid path: " . $relPath);
    exit;
}

$baseDir = dirname(dirname(__DIR__)); // .../TMS
$absPath = realpath($baseDir . $relPath);
error_log("Profile image proxy - Base dir: " . $baseDir . ", Absolute path: " . ($absPath ?: 'null'));

// Ensure the resolved path is still within the allowed directory
$allowedDir = realpath($baseDir . '/api/images/users');
error_log("Profile image proxy - Allowed dir: " . ($allowedDir ?: 'null'));

if (!$allowedDir) {
    http_response_code(500);
    echo 'Images directory not found';
    error_log("Profile image proxy - Images directory not found");
    exit;
}

if (!$absPath || strpos($absPath, $allowedDir) !== 0 || !is_file($absPath)) {
    http_response_code(404);
    echo 'File not found: ' . $absPath;
    error_log("Profile image proxy - File not found or invalid: " . ($absPath ?: 'null'));
    exit;
}

// Determine mime type
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
$mime = $finfo ? finfo_file($finfo, $absPath) : null;
if ($finfo) finfo_close($finfo);
if (!$mime) {
    // Fallback based on extension
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
}

// Caching headers
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . filesize($absPath));

// Output file
readfile($absPath);
exit;
