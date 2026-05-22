<?php
/**
 * API Endpoint: Upload System Logo
 * Handles image upload for system branding with server-side resizing
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['logo'];

// Validate file type
$allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedType = finfo_file($finfo, $file['tmp_name']);
// finfo_close is deprecated in PHP 8.5, resource is freed automatically

if (!in_array($detectedType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PNG, JPG, and WebP are allowed.']);
    exit;
}

// Validate file size (max 10MB)
$maxSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File size exceeds 10MB limit']);
    exit;
}

// Use existing api/images/logo directory
$uploadDir = __DIR__ . '/../images/logo/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Get current logo from database to delete old file
$currentSettings = Database::fetch("SELECT system_logo FROM system_settings WHERE setting_id = 1");
$currentLogo = $currentSettings['system_logo'] ?? null;

// Delete old logo file if it exists
if ($currentLogo && strpos($currentLogo, '/api/images/logo/') === 0) {
    $oldFile = __DIR__ . '/../images/logo/' . basename($currentLogo);
    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

// Check if GD extension is available for image resizing
if (!extension_loaded('gd')) {
    // GD not available, just move the file without resizing
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        exit;
    }
    
    $logoUrl = '/api/images/logo/' . $filename;
    
    // Update system_settings database
    Database::execute(
        "UPDATE system_settings SET system_logo = :logo WHERE setting_id = 1",
        ['logo' => $logoUrl]
    );
    
    echo json_encode([
        'success' => true,
        'logo_url' => $logoUrl,
        'filename' => $filename,
        'note' => 'Image saved without resizing (GD extension not available)'
    ]);
    exit;
}

// Load image
$imageInfo = getimagesize($file['tmp_name']);
if (!$imageInfo) {
    echo json_encode(['success' => false, 'error' => 'Invalid image file']);
    exit;
}

// Create image resource based on type
switch ($imageInfo[2]) {
    case IMAGETYPE_PNG:
        $source = imagecreatefrompng($file['tmp_name']);
        break;
    case IMAGETYPE_JPEG:
        $source = imagecreatefromjpeg($file['tmp_name']);
        break;
    case IMAGETYPE_WEBP:
        $source = imagecreatefromwebp($file['tmp_name']);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unsupported image type']);
        exit;
}

if (!$source) {
    echo json_encode(['success' => false, 'error' => 'Failed to load image']);
    exit;
}

// Get original dimensions
$origWidth = imagesx($source);
$origHeight = imagesy($source);

// Target dimensions (max 400x120 for logo)
$maxWidth = 400;
$maxHeight = 120;

// Calculate new dimensions maintaining aspect ratio
$ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
$newWidth = round($origWidth * $ratio);
$newHeight = round($origHeight * $ratio);

// Create new image
$dest = imagecreatetruecolor($newWidth, $newHeight);

// Handle transparency for PNG
if ($imageInfo[2] == IMAGETYPE_PNG) {
    imagealphablending($dest, false);
    imagesavealpha($dest, true);
    $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
    imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
}

// Resize image
imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

// Generate unique filename
$filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
$filepath = $uploadDir . $filename;

// Save as PNG
if (!imagepng($dest, $filepath, 9)) {
    imagedestroy($source);
    imagedestroy($dest);
    echo json_encode(['success' => false, 'error' => 'Failed to save image']);
    exit;
}

// Free memory
imagedestroy($source);
imagedestroy($dest);

// Generate URL for the uploaded file (relative path only, no BASE_URL)
$logoUrl = '/api/images/logo/' . $filename;

// Update system_logo in database immediately
Database::execute(
    "UPDATE system_settings SET system_logo = :logo WHERE setting_id = 1",
    ['logo' => $logoUrl]
);

echo json_encode([
    'success' => true,
    'logo_url' => $logoUrl,
    'filename' => $filename
]);
