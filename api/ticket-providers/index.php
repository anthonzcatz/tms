<?php
/**
 * Ticket Providers API Endpoint
 * Handles provider management operations
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Helper function for logging activity
function logActivity($userId, $action, $moduleName, $referenceCode = null, $oldValue = null, $newValue = null) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceId = null; // Can be enhanced to track device ID if needed
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES
            (:user_id, :device_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, NOW())",
        [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'action' => $action,
            'module_name' => $moduleName,
            'reference_code' => $referenceCode,
            'ip_address' => $ipAddress,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null
        ]
    );
}

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission - SUPER_ADMIN or users with VIEW_WALLET_MANAGEMENT permission
$user = Auth::user();
$canView = ($user['role_code'] === 'SUPER_ADMIN');

if (!$canView) {
    $canView = Auth::can('VIEW_WALLET_MANAGEMENT');
}

if (!$canView) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. You need VIEW_WALLET_MANAGEMENT permission to access this resource.']);
    exit;
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
    error_log("Ticket Providers API Error: " . $e->getMessage());
    error_log("Ticket Providers API Trace: " . $e->getTraceAsString());
    error_log("Ticket Providers API File: " . $e->getFile() . " Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

/**
 * Handle GET requests - list providers or get stats
 */
function handleGet() {
    $providerId = $_GET['id'] ?? null;
    $action = $_GET['action'] ?? null;

    // Get provider stats
    if ($action === 'stats') {
        $sql = "SELECT 
                    COUNT(*) as total_providers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_providers,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_providers
                FROM ticket_providers";

        $stats = Database::fetch($sql);

        echo json_encode([
            'success' => true,
            'data' => [
                'total_providers' => (int)$stats['total_providers'],
                'active_providers' => (int)$stats['active_providers'],
                'inactive_providers' => (int)$stats['inactive_providers']
            ]
        ]);
        return;
    }

    // Get single provider
    if ($providerId) {
        $sql = "SELECT * FROM ticket_providers WHERE provider_id = :provider_id";
        $provider = Database::fetch($sql, ['provider_id' => (int)$providerId]);
        
        if ($provider) {
            echo json_encode(['success' => true, 'data' => $provider]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Provider not found']);
        }
        return;
    }

    // List all providers
    $sql = "SELECT * FROM ticket_providers ORDER BY provider_name";
    $providers = Database::fetchAll($sql);

    echo json_encode([
        'success' => true,
        'data' => [
            'providers' => $providers
        ]
    ]);
}

/**
 * Handle POST requests - create provider
 */
function handlePost() {
    global $user, $userRoleCode;
    
    // Check permission
    $canCreate = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canCreate) {
        $canCreate = Auth::can('CREATE_WALLET');
    }
    
    if (!$canCreate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need CREATE_WALLET permission.']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $providerCode = $input['provider_code'] ?? null;
    $providerName = $input['provider_name'] ?? null;
    $providerType = $input['provider_type'] ?? null;
    $status = $input['status'] ?? 'active';
    
    // Validate required fields
    if (!$providerCode || !$providerName || !$providerType) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Check if provider code already exists
    $existing = Database::fetch(
        "SELECT provider_id FROM ticket_providers WHERE provider_code = :provider_code",
        ['provider_code' => $providerCode]
    );
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Provider code already exists']);
        return;
    }
    
    // Insert new provider
    $sql = "INSERT INTO ticket_providers (provider_code, provider_name, provider_type, status, created_at)
            VALUES (:provider_code, :provider_name, :provider_type, :status, NOW())";
    
    Database::execute($sql, [
        'provider_code' => $providerCode,
        'provider_name' => $providerName,
        'provider_type' => $providerType,
        'status' => $status
    ]);
    
    $providerId = Database::connection()->lastInsertId();
    
    // Log activity
    logActivity(
        $user['user_id'],
        'CREATE_PROVIDER',
        'PROVIDER_MANAGEMENT',
        "PROVIDER-{$providerId}",
        null,
        [
            'provider_id' => $providerId,
            'provider_code' => $providerCode,
            'provider_name' => $providerName,
            'provider_type' => $providerType,
            'status' => $status
        ]
    );
    
    echo json_encode(['success' => true, 'message' => 'Provider created successfully', 'provider_id' => $providerId]);
}

/**
 * Handle PUT requests - update provider
 */
function handlePut() {
    global $user, $userRoleCode;
    
    // Check permission
    $canUpdate = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canUpdate) {
        $canUpdate = Auth::can('UPDATE_WALLET');
    }
    
    if (!$canUpdate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need UPDATE_WALLET permission.']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $providerId = $input['provider_id'] ?? null;
    $providerCode = $input['provider_code'] ?? null;
    $providerName = $input['provider_name'] ?? null;
    $providerType = $input['provider_type'] ?? null;
    $status = $input['status'] ?? null;
    
    if (!$providerId) {
        echo json_encode(['success' => false, 'error' => 'Missing provider ID']);
        return;
    }
    
    // Get current provider data
    $currentProvider = Database::fetch(
        "SELECT * FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    if (!$currentProvider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        return;
    }
    
    // Build update query
    $updateFields = [];
    $params = ['provider_id' => (int)$providerId];
    
    if ($providerCode !== null) {
        $updateFields[] = "provider_code = :provider_code";
        $params['provider_code'] = $providerCode;
    }
    if ($providerName !== null) {
        $updateFields[] = "provider_name = :provider_name";
        $params['provider_name'] = $providerName;
    }
    if ($providerType !== null) {
        $updateFields[] = "provider_type = :provider_type";
        $params['provider_type'] = $providerType;
    }
    if ($status !== null) {
        $updateFields[] = "status = :status";
        $params['status'] = $status;
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    // Update provider
    $sql = "UPDATE ticket_providers SET " . implode(', ', $updateFields) . " WHERE provider_id = :provider_id";
    Database::execute($sql, $params);
    
    // Log activity
    $oldValues = [];
    $newValues = [];
    
    if ($providerCode !== null) {
        $oldValues['provider_code'] = $currentProvider['provider_code'];
        $newValues['provider_code'] = $providerCode;
    }
    if ($providerName !== null) {
        $oldValues['provider_name'] = $currentProvider['provider_name'];
        $newValues['provider_name'] = $providerName;
    }
    if ($providerType !== null) {
        $oldValues['provider_type'] = $currentProvider['provider_type'];
        $newValues['provider_type'] = $providerType;
    }
    if ($status !== null) {
        $oldValues['status'] = $currentProvider['status'];
        $newValues['status'] = $status;
    }
    
    logActivity(
        $user['user_id'],
        'UPDATE_PROVIDER',
        'PROVIDER_MANAGEMENT',
        "PROVIDER-{$providerId}",
        !empty($oldValues) ? $oldValues : null,
        !empty($newValues) ? $newValues : null
    );
    
    echo json_encode(['success' => true, 'message' => 'Provider updated successfully']);
}

/**
 * Handle DELETE requests - delete provider
 */
function handleDelete() {
    global $user, $userRoleCode;
    
    // Check permission
    $canDelete = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canDelete) {
        $canDelete = Auth::can('DELETE_WALLET');
    }
    
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need DELETE_WALLET permission.']);
        exit;
    }
    
    $providerId = $_GET['id'] ?? null;
    
    if (!$providerId) {
        echo json_encode(['success' => false, 'error' => 'Missing provider ID']);
        return;
    }
    
    // Get current provider data
    $currentProvider = Database::fetch(
        "SELECT * FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    if (!$currentProvider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        return;
    }
    
    // Check if provider has wallets
    $hasWallets = Database::fetch(
        "SELECT COUNT(*) as count FROM provider_wallets WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    if ($hasWallets && $hasWallets['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete provider with existing wallets']);
        return;
    }
    
    // Check if provider has service fees
    $hasServiceFees = Database::fetch(
        "SELECT COUNT(*) as count FROM provider_service_fees WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    if ($hasServiceFees && $hasServiceFees['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete provider with existing service fees']);
        return;
    }
    
    // Delete provider
    Database::execute(
        "DELETE FROM ticket_providers WHERE provider_id = :provider_id",
        ['provider_id' => (int)$providerId]
    );
    
    // Log activity
    logActivity(
        $user['user_id'],
        'DELETE_PROVIDER',
        'PROVIDER_MANAGEMENT',
        "PROVIDER-{$providerId}",
        $currentProvider,
        null
    );
    
    echo json_encode(['success' => true, 'message' => 'Provider deleted successfully']);
}
