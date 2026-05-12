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
$rateKey = 'api_permissions_' . ($_SESSION['user']['user_id'] ?? 'guest');
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
            if (!is_string($value) && !is_int($value) && !is_null($value)) {
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
            // Get all permissions
            $permissions = Database::fetchAll(
                "SELECT * FROM permissions ORDER BY module_name, permission_code"
            );
            
            // Group by module
            $grouped = [];
            foreach ($permissions as $perm) {
                $grouped[$perm['module_name']][] = $perm;
            }
            
            echo json_encode($grouped);
            break;
            
        case 'POST':
            // Create new permission
            $permissionId = $data['permission_id'] ?? null;
            
            if ($permissionId) {
                // Update existing permission
                
                // Check for duplicate permission_code (excluding current permission)
                $existingCode = Database::fetch(
                    "SELECT permission_id FROM permissions WHERE permission_code = :permission_code AND permission_id != :permission_id",
                    ['permission_code' => strtoupper($data['permission_code']), 'permission_id' => (int)$permissionId]
                );
                
                if ($existingCode) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Permission code already exists']);
                    exit;
                }
                
                // Check for duplicate permission_name (excluding current permission)
                $existingName = Database::fetch(
                    "SELECT permission_id FROM permissions WHERE permission_name = :permission_name AND permission_id != :permission_id",
                    ['permission_name' => strtoupper($data['permission_name']), 'permission_id' => (int)$permissionId]
                );
                
                if ($existingName) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Permission name already exists']);
                    exit;
                }
                
                $sql = "UPDATE permissions SET 
                        permission_code = :permission_code,
                        permission_name = :permission_name,
                        module_name = :module_name,
                        parent_permission_id = :parent_permission_id,
                        menu_order = :menu_order,
                        menu_icon = :menu_icon,
                        menu_url = :menu_url,
                        menu_level = :menu_level,
                        is_menu_item = :is_menu_item
                        WHERE permission_id = :permission_id";
                
                Database::execute($sql, [
                    'permission_id' => (int)$permissionId,
                    'permission_code' => strtoupper($data['permission_code']),
                    'permission_name' => strtoupper($data['permission_name']),
                    'module_name' => strtoupper($data['module_name']),
                    'parent_permission_id' => !empty($data['parent_permission_id']) ? (int)$data['parent_permission_id'] : null,
                    'menu_order' => (int)($data['menu_order'] ?? 0),
                    'menu_icon' => $data['menu_icon'] ?? null,
                    'menu_url' => $data['menu_url'] ?? null,
                    'menu_level' => (int)($data['menu_level'] ?? 1),
                    'is_menu_item' => (int)($data['is_menu_item'] ?? 0)
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Permission updated']);
            } else {
                // Create new permission
                
                // Check for duplicate permission_code
                $existingCode = Database::fetch(
                    "SELECT permission_id FROM permissions WHERE permission_code = :permission_code",
                    ['permission_code' => strtoupper($data['permission_code'])]
                );
                
                if ($existingCode) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Permission code already exists']);
                    exit;
                }
                
                // Check for duplicate permission_name
                $existingName = Database::fetch(
                    "SELECT permission_id FROM permissions WHERE permission_name = :permission_name",
                    ['permission_name' => strtoupper($data['permission_name'])]
                );
                
                if ($existingName) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Permission name already exists']);
                    exit;
                }
                
                Database::execute(
                    "INSERT INTO permissions (permission_code, permission_name, module_name, parent_permission_id, menu_order, menu_icon, menu_url, menu_level, is_menu_item) 
                     VALUES (:permission_code, :permission_name, :module_name, :parent_permission_id, :menu_order, :menu_icon, :menu_url, :menu_level, :is_menu_item)",
                    [
                        'permission_code' => strtoupper($data['permission_code']),
                        'permission_name' => strtoupper($data['permission_name']),
                        'module_name' => strtoupper($data['module_name']),
                        'parent_permission_id' => !empty($data['parent_permission_id']) ? (int)$data['parent_permission_id'] : null,
                        'menu_order' => (int)($data['menu_order'] ?? 0),
                        'menu_icon' => $data['menu_icon'] ?? null,
                        'menu_url' => $data['menu_url'] ?? null,
                        'menu_level' => (int)($data['menu_level'] ?? 1),
                        'is_menu_item' => (int)($data['is_menu_item'] ?? 0)
                    ]
                );
                
                echo json_encode(['success' => true, 'message' => 'Permission created']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
