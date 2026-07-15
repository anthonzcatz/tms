<?php
/**
 * Notifications API
 * RESTful API for notification management
 */

// Disable error display to client
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Catch any errors and return as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Ignore deprecation warnings
    if ($errno === E_DEPRECATED || $errno === E_STRICT) {
        return false;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $errstr]);
    exit;
});

set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $exception->getMessage()]);
    exit;
});

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/NotificationService.php';

header('Content-Type: application/json');

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];

// Parse path from REQUEST_URI
// REQUEST_URI will be like /TMS/api/notifications/123
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = '';

// Extract path after /api/notifications/
if (preg_match('#/api/notifications/([^?]+)#', $requestUri, $matches)) {
    $path = $matches[1];
    $path = trim($path, '/');
}

// Authenticate user
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
$userId = $user['user_id'];

// Permission-based access control - check system_settings for allowed roles
$systemSettings = Database::fetch("SELECT notification_roles FROM system_settings WHERE setting_id = 1");
$notificationRoles = $systemSettings['notification_roles'] ?? null;
$hasAccess = false;

// SUPER_ADMIN always has access
if ($user['role_code'] === 'SUPER_ADMIN') {
    $hasAccess = true;
}
// Check if notification_roles is set and user's role is in the list
elseif ($notificationRoles) {
    $allowedRoles = array_map('trim', explode(',', $notificationRoles));
    $hasAccess = in_array($user['role_code'], $allowedRoles);
}
// Fallback to VIEW_NOTIFICATIONS permission if no system setting
elseif (Auth::can('VIEW_NOTIFICATIONS')) {
    $hasAccess = true;
}

if (!$hasAccess) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - You do not have permission to access notifications']);
    exit;
}

// Route based on method and path
switch ($method) {
    case 'GET':
        handleGet($userId, $path);
        break;
    case 'POST':
        handlePost($userId, $path);
        break;
    case 'PUT':
        handlePut($userId, $path);
        break;
    case 'DELETE':
        handleDelete($userId, $path);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

/**
 * Handle GET requests
 */
function handleGet($userId, $path) {
    // GET /api/notifications/ - Get user notifications
    if (empty($path) || $path === '') {
        $filters = [
            'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 50,
            'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0,
        ];
        
        if (isset($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }
        
        if (isset($_GET['is_read'])) {
            $filters['is_read'] = $_GET['is_read'] === 'true';
        }
        
        if (isset($_GET['priority'])) {
            $filters['priority'] = $_GET['priority'];
        }
        
        if (isset($_GET['real_time'])) {
            $filters['real_time'] = $_GET['real_time'] === 'true';
        }
        
        $notifications = NotificationService::getNotifications($userId, $filters);
        $unreadCount = NotificationService::getUnreadCount($userId);
        
        // Debug: Log user ID and notification count
        error_log("Notifications API - User ID: $userId, Unread Count: $unreadCount, Total: " . count($notifications));
        
        echo json_encode([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount,
            'total' => count($notifications),
            'debug_user_id' => $userId
        ]);
        return;
    }
    
    // GET /api/notifications/count - Get unread count
    if ($path === 'count') {
        $count = NotificationService::getUnreadCount($userId);
        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
        return;
    }
    
    // GET /api/notifications/{id} - Get specific notification
    if (is_numeric($path)) {
        $notifications = NotificationService::getNotifications($userId, ['limit' => 1]);
        $notification = null;
        
        foreach ($notifications as $notif) {
            if ($notif['notification_id'] == $path) {
                $notification = $notif;
                break;
            }
        }
        
        if (!$notification) {
            http_response_code(404);
            echo json_encode(['error' => 'Notification not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $notification
        ]);
        return;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}

/**
 * Handle POST requests
 */
function handlePost($userId, $path) {
    // POST /api/notifications/ - Create notification
    if (empty($path) || $path === '') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }
        
        // Validate required fields
        if (empty($input['title']) || empty($input['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Title and message are required']);
            return;
        }
        
        $notificationId = NotificationService::create([
            'user_id' => $userId,
            'type' => $input['type'] ?? 'system',
            'title' => $input['title'],
            'message' => $input['message'],
            'data' => $input['data'] ?? null,
            'icon' => $input['icon'] ?? null,
            'color' => $input['color'] ?? 'info',
            'link' => $input['link'] ?? null,
            'priority' => $input['priority'] ?? 'medium',
            'real_time' => $input['real_time'] ?? false,
            'channels' => $input['channels'] ?? ['in-app']
        ]);
        
        if ($notificationId) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'notification_id' => $notificationId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create notification']);
        }
        return;
    }
    
    // POST /api/notifications/from-template - Create from template
    if ($path === 'from-template') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }
        
        if (empty($input['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification type is required']);
            return;
        }
        
        $notificationId = NotificationService::createFromTemplate(
            $input['type'],
            $userId,
            $input['data'] ?? []
        );
        
        if ($notificationId) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'notification_id' => $notificationId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create notification']);
        }
        return;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}

/**
 * Handle PUT requests
 */
function handlePut($userId, $path) {
    // PUT /api/notifications/{id} - Update notification
    if (is_numeric($path)) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }
        
        // Currently only supports marking as read
        if (isset($input['is_read']) && $input['is_read'] === true) {
            $success = NotificationService::markAsRead($path, $userId);
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification marked as read'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update notification']);
            }
            return;
        }
        
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }
    
    // PUT /api/notifications/mark-all - Mark all as read
    if ($path === 'mark-all') {
        $success = NotificationService::markAllAsRead($userId);
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to mark all as read']);
        }
        return;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}

/**
 * Handle DELETE requests
 */
function handleDelete($userId, $path) {
    // DELETE /api/notifications/{id} - Delete notification
    if (is_numeric($path)) {
        $success = NotificationService::delete($path, $userId);
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification deleted'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete notification']);
        }
        return;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
