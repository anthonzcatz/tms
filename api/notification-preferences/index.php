<?php
/**
 * Notification Preferences API Endpoint
 * Handles CRUD operations for user notification preferences
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
$userId = $user['user_id'];

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
            handleGet($userId);
            break;
        case 'POST':
            handlePost($userId);
            break;
        case 'PUT':
            handlePut($userId);
            break;
        case 'DELETE':
            handleDelete($userId);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Notification Preferences API Error: " . $e->getMessage());
    error_log("Notification Preferences API Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Handle GET requests - fetch user preferences
 */
function handleGet($userId) {
    $type = $_GET['type'] ?? null;
    
    if ($type) {
        // Get specific preference
        $preference = Database::fetch(
            "SELECT * FROM notification_preferences WHERE user_id = :user_id AND type = :type",
            ['user_id' => (int)$userId, 'type' => $type]
        );
        
        if ($preference) {
            $preference['channels'] = json_decode($preference['channels'], true) ?? [];
            $preference['settings'] = json_decode($preference['settings'], true) ?? [];
            echo json_encode(['success' => true, 'data' => $preference]);
        } else {
            // Return default preference if not found
            echo json_encode([
                'success' => true,
                'data' => [
                    'type' => $type,
                    'channels' => ['in-app'],
                    'enabled' => true,
                    'settings' => []
                ]
            ]);
        }
    } else {
        // Get all preferences for user
        $preferences = Database::fetchAll(
            "SELECT * FROM notification_preferences WHERE user_id = :user_id",
            ['user_id' => (int)$userId]
        );
        
        foreach ($preferences as &$pref) {
            $pref['channels'] = json_decode($pref['channels'], true) ?? [];
            $pref['settings'] = json_decode($pref['settings'], true) ?? [];
        }
        
        echo json_encode(['success' => true, 'data' => $preferences]);
    }
}

/**
 * Handle POST requests - create or update preference
 */
function handlePost($userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $type = $input['type'] ?? null;
    $channels = $input['channels'] ?? ['in-app'];
    $enabled = $input['enabled'] ?? true;
    $settings = $input['settings'] ?? [];
    
    if (!$type) {
        echo json_encode(['success' => false, 'error' => 'Missing required field: type']);
        return;
    }
    
    // Validate channels
    $validChannels = ['email', 'sms', 'push', 'in-app'];
    foreach ($channels as $channel) {
        if (!in_array($channel, $validChannels)) {
            echo json_encode(['success' => false, 'error' => 'Invalid channel: ' . $channel]);
            return;
        }
    }
    
    // Check if preference exists
    $existing = Database::fetch(
        "SELECT preference_id FROM notification_preferences WHERE user_id = :user_id AND type = :type",
        ['user_id' => (int)$userId, 'type' => $type]
    );
    
    if ($existing) {
        // Update existing
        Database::execute(
            "UPDATE notification_preferences 
             SET channels = :channels, enabled = :enabled, settings = :settings, updated_at = NOW()
             WHERE user_id = :user_id AND type = :type",
            [
                'user_id' => (int)$userId,
                'type' => $type,
                'channels' => json_encode($channels),
                'enabled' => (bool)$enabled,
                'settings' => json_encode($settings)
            ]
        );
    } else {
        // Insert new
        Database::execute(
            "INSERT INTO notification_preferences (user_id, type, channels, enabled, settings, created_at, updated_at)
             VALUES (:user_id, :type, :channels, :enabled, :settings, NOW(), NOW())",
            [
                'user_id' => (int)$userId,
                'type' => $type,
                'channels' => json_encode($channels),
                'enabled' => (bool)$enabled,
                'settings' => json_encode($settings)
            ]
        );
    }
    
    echo json_encode(['success' => true, 'message' => 'Preference saved successfully']);
}

/**
 * Handle PUT requests - update preference
 */
function handlePut($userId) {
    handlePost($userId); // Same logic as POST
}

/**
 * Handle DELETE requests - delete preference
 */
function handleDelete($userId) {
    $type = $_GET['type'] ?? null;
    
    if (!$type) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameter: type']);
        return;
    }
    
    Database::execute(
        "DELETE FROM notification_preferences WHERE user_id = :user_id AND type = :type",
        ['user_id' => (int)$userId, 'type' => $type]
    );
    
    echo json_encode(['success' => true, 'message' => 'Preference deleted successfully']);
}
