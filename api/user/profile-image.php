<?php
/**
 * API Endpoint: Upload User Profile Image
 * Handles image upload for user profile with server-side processing
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

// Get current user
$user = Auth::user();
if (!$user || !isset($user['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$userId = $user['user_id'];

// Check if file was uploaded
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['profile_image'];

// Validate file type
$allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedType = finfo_file($finfo, $file['tmp_name']);

if (!in_array($detectedType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PNG, JPG, and WebP are allowed.']);
    exit;
}

// Validate file size (max 5MB for profile images)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit']);
    exit;
}

// Use api/images/profile directory
$uploadDir = __DIR__ . '/../images/profile/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Get current profile image from database to delete old file
$currentProfile = Database::fetch("SELECT profile_image FROM user_accounts WHERE user_id = :user_id", ['user_id' => $userId]);
$currentImage = $currentProfile['profile_image'] ?? null;

// Delete old profile image file if it exists
if ($currentImage && strpos($currentImage, '/api/images/profile/') === 0) {
    $oldFile = __DIR__ . '/../images/profile/' . basename($currentImage);
    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

// Check if GD extension is available for image processing
if (!extension_loaded('gd')) {
    // GD not available, just move the file without processing
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        exit;
    }
    
    $profileImageUrl = '/api/images/profile/' . $filename;
    
    // Update user_accounts database
    Database::execute(
        "UPDATE user_accounts SET profile_image = :profile_image WHERE user_id = :user_id",
        ['profile_image' => $profileImageUrl, 'user_id' => $userId]
    );
    
    echo json_encode([
        'success' => true,
        'profile_image_url' => $profileImageUrl,
        'filename' => $filename,
        'note' => 'Image saved without processing (GD extension not available)'
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

// Target dimensions - 1:1 ratio for profile images, max 1000x1000
$maxSize = 1000;

// Calculate new dimensions maintaining aspect ratio (square crop)
$minDim = min($origWidth, $origHeight);
$newWidth = $minDim;
$newHeight = $minDim;

// Calculate crop coordinates to center the image
$cropX = ($origWidth - $minDim) / 2;
$cropY = ($origHeight - $minDim) / 2;

// Create new image (square)
$dest = imagecreatetruecolor($newWidth, $newHeight);

// Handle transparency for PNG
if ($imageInfo[2] == IMAGETYPE_PNG) {
    imagealphablending($dest, false);
    imagesavealpha($dest, true);
    $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
    imagefilledrectangle($dest, 0, 0, $newWidth, $newHeight, $transparent);
}

// Crop and resize image
imagecopyresampled($dest, $source, 0, 0, $cropX, $cropY, $newWidth, $newHeight, $minDim, $minDim);

// Generate unique filename
$filename = 'profile_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
$filepath = $uploadDir . $filename;

// Save as PNG with medium compression for quality/size balance (level 6)
if (!imagepng($dest, $filepath, 6)) {
    imagedestroy($source);
    imagedestroy($dest);
    echo json_encode(['success' => false, 'error' => 'Failed to save image']);
    exit;
}

// Free memory
imagedestroy($source);
imagedestroy($dest);

// Generate URL for the uploaded file (relative path only, no BASE_URL)
$profileImageUrl = '/api/images/profile/' . $filename;

// Update profile_image in database immediately
Database::execute(
    "UPDATE user_accounts SET profile_image = :profile_image WHERE user_id = :user_id",
    ['profile_image' => $profileImageUrl, 'user_id' => $userId]
);

echo json_encode([
    'success' => true,
    'profile_image_url' => $profileImageUrl,
    'filename' => $filename
]);
