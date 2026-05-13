<?php
/**
 * Provider Service Fees API Endpoint
 * Handles service fee management operations
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

// Get user branch for filtering
$userBranchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

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
    error_log("Provider Service Fees API Error: " . $e->getMessage());
    error_log("Provider Service Fees API Trace: " . $e->getTraceAsString());
    error_log("Provider Service Fees API File: " . $e->getFile() . " Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

/**
 * Handle GET requests - list service fees
 */
function handleGet() {
    $feeId = $_GET['id'] ?? null;

    // Get single fee
    if ($feeId) {
        $sql = "SELECT psf.*,
                       tp.provider_name,
                       bb.branch_name,
                       CONCAT(tp.provider_name, ' - ', bb.branch_name) as wallet_name
                FROM provider_service_fees psf
                LEFT JOIN ticket_providers tp ON psf.provider_id = tp.provider_id
                LEFT JOIN business_branches bb ON psf.branch_id = bb.branch_id
                WHERE psf.fee_id = :fee_id";
        
        $fee = Database::fetch($sql, ['fee_id' => (int)$feeId]);
        
        if ($fee) {
            echo json_encode(['success' => true, 'data' => $fee]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Service fee not found']);
        }
        return;
    }

    // List all fees
    $branchFilter = "";
    $params = [];

    // SUPER_ADMIN can see all fees, others are restricted to their branch
    global $userRoleCode, $userBranchId;
    if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        $branchFilter = "WHERE psf.branch_id = :user_branch_id";
        $params['user_branch_id'] = $userBranchId;
    }

    // Filter by provider_id if provided
    $providerId = $_GET['provider_id'] ?? null;
    if ($providerId) {
        $branchFilter = ($branchFilter ? $branchFilter . " AND " : "WHERE ") . "psf.provider_id = :provider_id";
        $params['provider_id'] = (int)$providerId;
    }

    // Filter by branch_id if provided
    $branchId = $_GET['branch_id'] ?? null;
    if ($branchId) {
        $branchFilter = ($branchFilter ? $branchFilter . " AND " : "WHERE ") . "psf.branch_id = :branch_id";
        $params['branch_id'] = (int)$branchId;
    }

    // Filter by active fees only
    $branchFilter = ($branchFilter ? $branchFilter . " AND " : "WHERE ") . "psf.is_active = 1";
    
    // Filter by effective date range (only if dates are set)
    $branchFilter .= " AND (psf.effective_start_date IS NULL OR psf.effective_start_date <= CURDATE())";
    $branchFilter .= " AND (psf.effective_end_date IS NULL OR psf.effective_end_date >= CURDATE())";

    $sql = "SELECT psf.*,
                   tp.provider_name,
                   bb.branch_name,
                   CONCAT(tp.provider_name, ' - ', bb.branch_name) as wallet_name
            FROM provider_service_fees psf
            LEFT JOIN ticket_providers tp ON psf.provider_id = tp.provider_id
            LEFT JOIN business_branches bb ON psf.branch_id = bb.branch_id
            $branchFilter
            ORDER BY tp.provider_name, bb.branch_name, psf.fee_type";

    $fees = Database::fetchAll($sql, $params);
    
    // Debug logging
    error_log("Service Fees Query: " . $sql);
    error_log("Service Fees Params: " . json_encode($params));
    error_log("Service Fees Result: " . json_encode($fees));

    echo json_encode([
        'success' => true,
        'data' => [
            'fees' => $fees
        ]
    ]);
}

/**
 * Handle POST requests - create service fee
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
    
    $providerId = $input['provider_id'] ?? null;
    $branchId = $input['branch_id'] ?? null;
    $feeType = $input['fee_type'] ?? null;
    $feeAmount = $input['fee_amount'] ?? 0;
    $feePercentage = $input['fee_percentage'] ?? 0;
    $status = $input['status'] ?? 'active';
    
    // Validate required fields
    if (!$providerId || !$branchId || !$feeType) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Check if fee already exists for this provider-branch-fee_type combination
    $existing = Database::fetch(
        "SELECT fee_id FROM provider_service_fees WHERE provider_id = :provider_id AND branch_id = :branch_id AND fee_type = :fee_type",
        ['provider_id' => (int)$providerId, 'branch_id' => (int)$branchId, 'fee_type' => $feeType]
    );
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Service fee already exists for this provider, branch, and fee type']);
        return;
    }
    
    // Insert new service fee
    $sql = "INSERT INTO provider_service_fees (provider_id, branch_id, fee_type, fee_amount, fee_percentage, is_active, created_by, created_at)
            VALUES (:provider_id, :branch_id, :fee_type, :fee_amount, :fee_percentage, :is_active, :created_by, NOW())";
    
    Database::execute($sql, [
        'provider_id' => (int)$providerId,
        'branch_id' => (int)$branchId,
        'fee_type' => $feeType,
        'fee_amount' => (float)$feeAmount,
        'fee_percentage' => (float)$feePercentage,
        'is_active' => $status === 'active' ? 1 : 0,
        'created_by' => $user['user_id']
    ]);
    
    $feeId = Database::connection()->lastInsertId();
    
    // Log activity
    logActivity(
        $user['user_id'],
        'CREATE_SERVICE_FEE',
        'SERVICE_FEE_MANAGEMENT',
        "FEE-{$feeId}",
        null,
        [
            'fee_id' => $feeId,
            'provider_id' => $providerId,
            'branch_id' => $branchId,
            'fee_type' => $feeType,
            'fee_amount' => $feeAmount,
            'fee_percentage' => $feePercentage,
            'status' => $status
        ]
    );
    
    echo json_encode(['success' => true, 'message' => 'Service fee created successfully', 'fee_id' => $feeId]);
}

/**
 * Handle PUT requests - update service fee
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
    $feeId = $input['fee_id'] ?? null;
    $providerId = $input['provider_id'] ?? null;
    $branchId = $input['branch_id'] ?? null;
    $feeType = $input['fee_type'] ?? null;
    $feeAmount = $input['fee_amount'] ?? null;
    $feePercentage = $input['fee_percentage'] ?? null;
    $status = $input['status'] ?? null;
    
    if (!$feeId) {
        echo json_encode(['success' => false, 'error' => 'Missing fee ID']);
        return;
    }
    
    // Get current fee data
    $currentFee = Database::fetch(
        "SELECT * FROM provider_service_fees WHERE fee_id = :fee_id",
        ['fee_id' => (int)$feeId]
    );
    
    if (!$currentFee) {
        echo json_encode(['success' => false, 'error' => 'Service fee not found']);
        return;
    }
    
    // Build update query
    $updateFields = [];
    $params = ['fee_id' => (int)$feeId];
    
    if ($providerId !== null) {
        $updateFields[] = "provider_id = :provider_id";
        $params['provider_id'] = (int)$providerId;
    }
    if ($branchId !== null) {
        $updateFields[] = "branch_id = :branch_id";
        $params['branch_id'] = (int)$branchId;
    }
    if ($feeType !== null) {
        $updateFields[] = "fee_type = :fee_type";
        $params['fee_type'] = $feeType;
    }
    if ($feeAmount !== null) {
        $updateFields[] = "fee_amount = :fee_amount";
        $params['fee_amount'] = (float)$feeAmount;
    }
    if ($feePercentage !== null) {
        $updateFields[] = "fee_percentage = :fee_percentage";
        $params['fee_percentage'] = (float)$feePercentage;
    }
    if ($status !== null) {
        $updateFields[] = "is_active = :is_active";
        $params['is_active'] = $status === 'active' ? 1 : 0;
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    // Update service fee
    $sql = "UPDATE provider_service_fees SET " . implode(', ', $updateFields) . " WHERE fee_id = :fee_id";
    Database::execute($sql, $params);
    
    // Log activity
    $oldValues = [];
    $newValues = [];
    
    if ($providerId !== null) {
        $oldValues['provider_id'] = $currentFee['provider_id'];
        $newValues['provider_id'] = $providerId;
    }
    if ($branchId !== null) {
        $oldValues['branch_id'] = $currentFee['branch_id'];
        $newValues['branch_id'] = $branchId;
    }
    if ($feeType !== null) {
        $oldValues['fee_type'] = $currentFee['fee_type'];
        $newValues['fee_type'] = $feeType;
    }
    
    logActivity(
        $user['user_id'],
        'UPDATE_SERVICE_FEE',
        'SERVICE_FEE_MANAGEMENT',
        "FEE-{$feeId}",
        !empty($oldValues) ? $oldValues : null,
        !empty($newValues) ? $newValues : null
    );
    
    echo json_encode(['success' => true, 'message' => 'Service fee updated successfully']);
}

/**
 * Handle DELETE requests - delete service fee
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
    
    $feeId = $_GET['id'] ?? null;
    
    if (!$feeId) {
        echo json_encode(['success' => false, 'error' => 'Missing fee ID']);
        return;
    }
    
    // Get current fee data
    $currentFee = Database::fetch(
        "SELECT * FROM provider_service_fees WHERE fee_id = :fee_id",
        ['fee_id' => (int)$feeId]
    );
    
    if (!$currentFee) {
        echo json_encode(['success' => false, 'error' => 'Service fee not found']);
        return;
    }
    
    // Delete service fee
    Database::execute(
        "DELETE FROM provider_service_fees WHERE fee_id = :fee_id",
        ['fee_id' => (int)$feeId]
    );
    
    // Log activity
    logActivity(
        $user['user_id'],
        'DELETE_SERVICE_FEE',
        'SERVICE_FEE_MANAGEMENT',
        "FEE-{$feeId}",
        $currentFee,
        null
    );
    
    echo json_encode(['success' => true, 'message' => 'Service fee deleted successfully']);
}
