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

// CSRF protection
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? null;
if (!SecurityHelper::validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Rate limiting
$rateKey = 'api_permissions_assign_' . ($_SESSION['user']['user_id'] ?? 'guest');
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

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

try {
    Database::execute(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id) 
         VALUES (:role_id, :permission_id)",
        [
            'role_id' => (int)$data['role_id'],
            'permission_id' => (int)$data['permission_id']
        ]
    );
    
    echo json_encode(['success' => true, 'message' => 'Permission assigned']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
