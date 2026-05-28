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

// Validate required fields
if (empty($data['role_id']) || empty($data['permission_ids']) || !is_array($data['permission_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'role_id and permission_ids array are required']);
    exit;
}

try {
    $roleId = (int)$data['role_id'];
    $permissionIds = array_map('intval', $data['permission_ids']);
    
    // Build placeholders for bulk insert
    $placeholders = [];
    $params = [];
    foreach ($permissionIds as $pid) {
        $placeholders[] = "(:role_id_{$pid}, :permission_id_{$pid})";
        $params["role_id_{$pid}"] = $roleId;
        $params["permission_id_{$pid}"] = $pid;
    }
    
    $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES " . implode(', ', $placeholders);
    
    Database::execute($sql, $params);
    
    // Return new CSRF token for subsequent requests
    $newCsrfToken = SecurityHelper::generateCSRFToken();
    echo json_encode(['success' => true, 'message' => 'Permissions assigned', 'csrf_token' => $newCsrfToken]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
