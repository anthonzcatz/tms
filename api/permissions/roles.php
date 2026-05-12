<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Rate limiting
$rateKey = 'api_permissions_roles_' . ($_SESSION['user']['user_id'] ?? 'guest');
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

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get all roles
    $roles = Database::fetchAll("SELECT * FROM user_roles ORDER BY role_code");
    
    // Get role permissions for each role
    foreach ($roles as &$role) {
        $rolePermissions = Database::fetchAll(
            "SELECT p.* FROM permissions p
             JOIN role_permissions rp ON p.permission_id = rp.permission_id
             WHERE rp.role_id = :role_id",
            ['role_id' => $role['role_id']]
        );
        $role['permissions'] = $rolePermissions;
    }
    
    echo json_encode($roles);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
