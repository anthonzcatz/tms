<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check SUPER_ADMIN role
if ($_SESSION['user']['role_code'] !== 'SUPER_ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - SUPER_ADMIN only']);
    exit;
}

// CSRF protection for DELETE
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!SecurityHelper::validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Rate limiting
$rateKey = 'api_permissions_delete_' . ($_SESSION['user']['user_id'] ?? 'guest');
if (!isset($_SESSION['rate_limit'][$rateKey])) {
    $_SESSION['rate_limit'][$rateKey] = ['count' => 0, 'time' => time()];
}

$timeWindow = 60;
if (time() - $_SESSION['rate_limit'][$rateKey]['time'] > $timeWindow) {
    $_SESSION['rate_limit'][$rateKey] = ['count' => 0, 'time' => time()];
}

$_SESSION['rate_limit'][$rateKey]['count']++;

if ($_SESSION['rate_limit'][$rateKey]['count'] > 100) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}

// Only DELETE allowed
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get permission ID from URL path or query parameter
$permissionId = $_GET['id'] ?? null;

if (!$permissionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Permission ID required']);
    exit;
}

try {
    Database::execute(
        "DELETE FROM role_permissions WHERE permission_id = :permission_id",
        ['permission_id' => (int)$permissionId]
    );
    
    Database::execute(
        "DELETE FROM permissions WHERE permission_id = :permission_id",
        ['permission_id' => (int)$permissionId]
    );
    
    echo json_encode(['success' => true, 'message' => 'Permission deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
