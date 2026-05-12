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

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? null;
    if (!SecurityHelper::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

// Rate limiting
$rateKey = 'api_role_dashboards_' . ($_SESSION['user']['user_id'] ?? 'guest');
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

// Validate JSON input for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }
    
    if ($data && is_array($data)) {
        foreach ($data as $key => $value) {
            if (!is_string($value) && !is_int($value)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid input format']);
                exit;
            }
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get all roles with their default dashboards
            $roles = Database::fetchAll(
                "SELECT * FROM user_roles ORDER BY role_code"
            );
            
            echo json_encode($roles);
            break;
            
        case 'POST':
            // Validate input
            if (!isset($data['role_id']) || !isset($data['default_dashboard'])) {
                throw new Exception('Missing required fields: role_id and default_dashboard');
            }
            
            // Update default dashboard for a role
            $result = Database::execute(
                "UPDATE user_roles SET default_dashboard = :default_dashboard 
                 WHERE role_id = :role_id",
                [
                    'default_dashboard' => $data['default_dashboard'],
                    'role_id' => (int)$data['role_id']
                ]
            );
            
            if ($result > 0) {
                echo json_encode(['success' => true, 'message' => 'Dashboard updated successfully']);
            } else {
                // Check if role exists
                $role = Database::fetch(
                    "SELECT role_id, default_dashboard FROM user_roles WHERE role_id = :role_id",
                    ['role_id' => (int)$data['role_id']]
                );
                if ($role) {
                    if ($role['default_dashboard'] === $data['default_dashboard']) {
                        throw new Exception('Dashboard is already set to this value');
                    } else {
                        throw new Exception('Failed to update dashboard');
                    }
                } else {
                    throw new Exception('Role ID does not exist');
                }
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Role Dashboards API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
