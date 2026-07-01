<?php
/**
 * Support API
 * Handles support ticket submissions and management
 */
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/NotificationService.php';

header('Content-Type: application/json');

// Require login
Auth::requireLogin();
$user = Auth::user();

$method = $_SERVER['REQUEST_METHOD'];

// Parse path from REQUEST_URI since PATH_INFO may not be set by router
// REQUEST_URI will be like /TMS/api/support/123
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = '';
$requestId = null;

// Extract path after /api/support/
if (preg_match('#/api/support/([^?]+)#', $requestUri, $matches)) {
    $path = $matches[1];
    $path = trim($path, '/');
    if (preg_match('#^(\d+)$#', $path, $idMatches)) {
        $requestId = $idMatches[1];
    }
}

// POST - Create new support request
if ($method === 'POST' && empty($path)) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    // Validate required fields
    if (empty($data['subject']) || empty($data['message'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Subject and message are required']);
        exit;
    }

    try {
        Database::execute(
            "INSERT INTO support_requests (
                user_id, username, email, subject, message, status, priority, created_at
            ) VALUES (:user_id, :username, :email, :subject, :message, 'pending', 'normal', NOW())",
            [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'subject' => $data['subject'],
                'message' => $data['message']
            ]
        );

        // Create notification for SUPER_ADMIN and ADMIN users
        $adminUsers = Database::fetchAll(
            "SELECT ua.user_id FROM user_accounts ua
             JOIN user_roles r ON ua.role_id = r.role_id
             WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN') AND ua.status = 'active'"
        );

        foreach ($adminUsers as $admin) {
            NotificationService::createFromTemplate('support', $admin['user_id'], [
                'user' => $user['username'],
                'subject' => $data['subject']
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Your support request has been submitted. We will get back to you within 24 hours.'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit support request: ' . $e->getMessage()]);
    }
    exit;
}

// GET - View single support request
if ($method === 'GET' && preg_match('#^(\d+)$#', $path, $matches)) {
    $requestId = $matches[1];

    // Only SUPER_ADMIN and ADMIN can view requests
    if ($user['role_code'] !== 'SUPER_ADMIN' && $user['role_code'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $request = Database::fetch(
        "SELECT sr.*, ua_assigned.username AS assigned_to_name
         FROM support_requests sr
         LEFT JOIN user_accounts ua_assigned ON sr.assigned_to = ua_assigned.user_id
         WHERE sr.request_id = :request_id",
        ['request_id' => $requestId]
    );

    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
        exit;
    }

    echo json_encode(['success' => true, 'request' => $request]);
    exit;
}

// PUT - Update support request
if ($method === 'PUT' && preg_match('#^(\d+)$#', $path, $matches)) {
    $requestId = $matches[1];

    // Only SUPER_ADMIN and ADMIN can update requests
    if ($user['role_code'] !== 'SUPER_ADMIN' && $user['role_code'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $updateFields = [];
        $params = ['request_id' => $requestId];

        if (isset($data['status'])) {
            $updateFields[] = 'status = :status';
            $params['status'] = $data['status'];
            
            // Set resolved_at when status changes to resolved
            if ($data['status'] === 'resolved') {
                $updateFields[] = 'resolved_at = NOW()';
            }
        }

        if (isset($data['priority'])) {
            $updateFields[] = 'priority = :priority';
            $params['priority'] = $data['priority'];
        }

        if (isset($data['assigned_to'])) {
            $updateFields[] = 'assigned_to = :assigned_to';
            $params['assigned_to'] = $data['assigned_to'] ?: null;
        }

        if (isset($data['response'])) {
            $updateFields[] = 'response = :response';
            $params['response'] = $data['response'];
        }

        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }

        $sql = "UPDATE support_requests SET " . implode(', ', $updateFields) . " WHERE request_id = :request_id";
        Database::execute($sql, $params);

        echo json_encode(['success' => true, 'message' => 'Support request updated successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update support request: ' . $e->getMessage()]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
