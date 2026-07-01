<?php
/**
 * System Notification Settings API Endpoint
 * Handles CRUD operations for global notification threshold settings
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

// Check permission - only SUPER_ADMIN can modify system settings
if (!Auth::can('SUPER_ADMIN')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. Only SUPER_ADMIN can modify system settings.']);
    exit;
}

// Get current user
$user = Auth::user();

// CSRF protection for POST/PUT/DELETE requests
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? $_GET['_token'] ?? null;
    if (!SecurityHelper::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("System Notification Settings API Error: " . $e->getMessage());
    error_log("System Notification Settings API Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Handle GET requests - fetch all system notification settings
 */
function handleGet() {
    $settings = Database::fetchAll(
        "SELECT * FROM system_notification_settings ORDER BY setting_key"
    );
    
    echo json_encode(['success' => true, 'data' => $settings]);
}

/**
 * Handle POST requests - create or update setting
 */
function handlePost() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $settingKey = $input['setting_key'] ?? null;
    $settingValue = $input['setting_value'] ?? null;
    $description = $input['description'] ?? null;
    
    if (!$settingKey || $settingValue === null) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields: setting_key, setting_value']);
        return;
    }
    
    // Check if setting exists
    $existing = Database::fetch(
        "SELECT setting_id FROM system_notification_settings WHERE setting_key = :setting_key",
        ['setting_key' => $settingKey]
    );
    
    if ($existing) {
        // Update existing
        $sql = "UPDATE system_notification_settings SET setting_value = :setting_value";
        $params = ['setting_key' => $settingKey, 'setting_value' => (float)$settingValue];
        
        if ($description !== null) {
            $sql .= ", description = :description";
            $params['description'] = $description;
        }
        
        $sql .= ", updated_at = NOW() WHERE setting_key = :setting_key";
        
        Database::execute($sql, $params);
    } else {
        // Insert new
        Database::execute(
            "INSERT INTO system_notification_settings (setting_key, setting_value, description, created_at, updated_at)
             VALUES (:setting_key, :setting_value, :description, NOW(), NOW())",
            [
                'setting_key' => $settingKey,
                'setting_value' => (float)$settingValue,
                'description' => $description
            ]
        );
    }
    
    echo json_encode(['success' => true, 'message' => 'Setting saved successfully']);
}

/**
 * Handle PUT requests - update setting
 */
function handlePut() {
    handlePost(); // Same logic as POST
}

/**
 * Handle DELETE requests - delete setting
 */
function handleDelete() {
    $settingKey = $_GET['setting_key'] ?? null;
    
    if (!$settingKey) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameter: setting_key']);
        return;
    }
    
    Database::execute(
        "DELETE FROM system_notification_settings WHERE setting_key = :setting_key",
        ['setting_key' => $settingKey]
    );
    
    echo json_encode(['success' => true, 'message' => 'Setting deleted successfully']);
}
