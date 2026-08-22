<?php
/**
 * Cashier Transportation Type Assignments API
 * Manages cashier to transportation type assignments
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/CashierTransportAccess.php';

Auth::requireLogin();
$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

// Only SUPER_ADMIN and MANAGER can manage cashier transport assignments
if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $userId = $_GET['user_id'] ?? null;

    // Decode user_id if provided
    if ($userId) {
        $decodedUserId = IdEncoder::decode($userId);
        if ($decodedUserId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            return;
        }
        $userId = $decodedUserId;
    }

    if ($userId) {
        // Get assignments for a specific user
        $assignments = Database::fetchAll(
            "SELECT cta.*, tp.provider_name, tp.provider_code, tp.provider_type
             FROM cashier_transport_assignments cta
             LEFT JOIN ticket_providers tp ON cta.provider_id = tp.provider_id
             WHERE cta.user_id = :user_id
             ORDER BY cta.assignment_id",
            ['user_id' => $userId]
        );
        echo json_encode(['success' => true, 'data' => $assignments]);
        return;
    }
    
    // Get all assignments
    $assignments = Database::fetchAll(
        "SELECT cta.*, ua.username, ua.user_code, tp.provider_name, tp.provider_code, tp.provider_type
         FROM cashier_transport_assignments cta
         LEFT JOIN user_accounts ua ON cta.user_id = ua.user_id
         LEFT JOIN ticket_providers tp ON cta.provider_id = tp.provider_id
         ORDER BY cta.user_id, cta.assignment_id"
    );
    echo json_encode(['success' => true, 'data' => $assignments]);
    return;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    $providerId = $input['provider_id'] ?? null;
    $transportType = $input['transport_type'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }
    
    // Validate that at least one of provider_id or transport_type is specified
    if (!$providerId && !$transportType) {
        echo json_encode(['success' => false, 'error' => 'Either provider_id or transport_type is required']);
        return;
    }
    
    // Check if user exists and is a cashier
    $user = Database::fetch("SELECT * FROM user_accounts WHERE user_id = :id", ['id' => $userId]);
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }
    
    // Check if user has CASHIER role
    $role = Database::fetch("SELECT * FROM user_roles WHERE role_id = :id", ['id' => $user['role_id']]);
    if (!$role || $role['role_code'] !== 'CASHIER') {
        echo json_encode(['success' => false, 'error' => 'User must be a cashier to have transport assignments']);
        return;
    }

    if ($providerId) {
        $provider = Database::fetch(
            "SELECT provider_id FROM ticket_providers
             WHERE provider_id = :provider_id
               AND status = 'active'
               AND provider_type IN ('airline', 'shipping')",
            ['provider_id' => (int) $providerId]
        );
        if (!$provider) {
            echo json_encode(['success' => false, 'error' => 'Provider not found or inactive']);
            return;
        }
    }
    if ($transportType && !in_array($transportType, CashierTransportAccess::SUPPORTED_TRANSPORT_TYPES, true)) {
        echo json_encode(['success' => false, 'error' => 'Bus Lines and Other transportation access are inactive']);
        return;
    }

    try {
        Database::connection()->beginTransaction();
        
        // Delete existing assignments for this user (if replacing)
        if ($input['replace'] ?? false) {
            Database::execute("DELETE FROM cashier_transport_assignments WHERE user_id = :user_id", ['user_id' => $userId]);
        }
        
        // Insert new assignment
        Database::execute(
            "INSERT INTO cashier_transport_assignments (user_id, provider_id, transport_type, created_by, created_at)
             VALUES (:user_id, :provider_id, :transport_type, :created_by, :created_at)",
            [
                'user_id' => $userId,
                'provider_id' => $providerId,
                'transport_type' => $transportType,
                'created_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s')
            ]
        );
        
        // Update user_accounts to mark as having restricted transport types
        Database::execute(
            "UPDATE user_accounts SET has_restricted_transport = 1 WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        
        Database::connection()->commit();
        
        echo json_encode(['success' => true, 'message' => 'Transport assignment created successfully']);
        return;
    } catch (Exception $e) {
        Database::connection()->rollBack();
        echo json_encode(['success' => false, 'error' => 'Failed to create assignment: ' . $e->getMessage()]);
        return;
    }
}

if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $assignmentId = $input['assignment_id'] ?? null;
    $userId = $input['user_id'] ?? null;
    
    if (!$assignmentId && !$userId) {
        echo json_encode(['success' => false, 'error' => 'Assignment ID or User ID is required']);
        return;
    }
    
    try {
        Database::connection()->beginTransaction();
        
        if ($assignmentId) {
            $assignment = Database::fetch(
                "SELECT user_id FROM cashier_transport_assignments WHERE assignment_id = :id FOR UPDATE",
                ['id' => $assignmentId]
            );
            if (!$assignment) {
                throw new RuntimeException('Assignment not found.');
            }
            $userId = (int) $assignment['user_id'];
            Database::execute("DELETE FROM cashier_transport_assignments WHERE assignment_id = :id", ['id' => $assignmentId]);
        } else {
            Database::execute("DELETE FROM cashier_transport_assignments WHERE user_id = :user_id", ['user_id' => $userId]);
        }

        $remaining = Database::fetch(
            "SELECT COUNT(*) AS assignment_count FROM cashier_transport_assignments WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        Database::execute(
            "UPDATE user_accounts SET has_restricted_transport = :restricted WHERE user_id = :user_id",
            ['restricted' => ((int) ($remaining['assignment_count'] ?? 0) > 0) ? 1 : 0, 'user_id' => $userId]
        );
        CashierTransportAccess::invalidate((int) $userId);
        
        Database::connection()->commit();
        
        echo json_encode(['success' => true, 'message' => 'Transport assignment deleted successfully']);
        return;
    } catch (Exception $e) {
        Database::connection()->rollBack();
        echo json_encode(['success' => false, 'error' => 'Failed to delete assignment: ' . $e->getMessage()]);
        return;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
