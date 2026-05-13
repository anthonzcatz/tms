<?php
/**
 * Business Branches API Endpoint
 * Handles branch management operations
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

// Check permission - SUPER_ADMIN or users with VIEW_BRANCHES permission
$user = Auth::user();
$canView = ($user['role_code'] === 'SUPER_ADMIN');

if (!$canView) {
    $canView = Auth::can('VIEW_BRANCHES');
}

if (!$canView) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. You need VIEW_BRANCHES permission to access this resource.']);
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
    error_log("Business Branches API Error: " . $e->getMessage());
    error_log("Business Branches API Trace: " . $e->getTraceAsString());
    error_log("Business Branches API File: " . $e->getFile() . " Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error', 'debug' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}

/**
 * Handle GET requests - list branches
 */
function handleGet() {
    $branchId = $_GET['id'] ?? null;

    // Get single branch
    if ($branchId) {
        $sql = "SELECT bb.*,
                       r.region_name,
                       p.province_name,
                       c.city_municipality_name,
                       b.barangay_name
                FROM business_branches bb
                LEFT JOIN psgc_regions r ON bb.region_code = r.region_code
                LEFT JOIN psgc_provinces p ON bb.province_code = p.province_code
                LEFT JOIN psgc_cities_municipalities c ON bb.city_municipality_code = c.city_municipality_code
                LEFT JOIN psgc_barangays b ON bb.barangay_code = b.barangay_code
                WHERE bb.branch_id = :branch_id";
        
        $branch = Database::fetch($sql, ['branch_id' => (int)$branchId]);
        
        if ($branch) {
            echo json_encode(['success' => true, 'data' => $branch]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Branch not found']);
        }
        return;
    }

    // List all branches
    $branchFilter = "";
    $params = [];

    // SUPER_ADMIN can see all branches, others are restricted to their branch
    global $userRoleCode, $userBranchId;
    if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        $branchFilter = "WHERE bb.branch_id = :user_branch_id";
        $params['user_branch_id'] = $userBranchId;
    }

    $sql = "SELECT bb.*,
                   r.region_name,
                   p.province_name,
                   c.city_municipality_name,
                   b.barangay_name
            FROM business_branches bb
            LEFT JOIN psgc_regions r ON bb.region_code = r.region_code
            LEFT JOIN psgc_provinces p ON bb.province_code = p.province_code
            LEFT JOIN psgc_cities_municipalities c ON bb.city_municipality_code = c.city_municipality_code
            LEFT JOIN psgc_barangays b ON bb.barangay_code = b.barangay_code
            $branchFilter
            ORDER BY bb.branch_name";

    $branches = Database::fetchAll($sql, $params);

    echo json_encode([
        'success' => true,
        'data' => [
            'branches' => $branches
        ]
    ]);
}

/**
 * Handle POST requests - create branch
 */
function handlePost() {
    global $user, $userRoleCode;
    
    // Check permission
    $canCreate = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canCreate) {
        $canCreate = Auth::can('CREATE_BRANCH');
    }
    
    if (!$canCreate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need CREATE_BRANCH permission.']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $branchCode = $input['branch_code'] ?? null;
    $branchName = $input['branch_name'] ?? null;
    $regionCode = $input['region_code'] ?? null;
    $provinceCode = $input['province_code'] ?? null;
    $cityCode = $input['city_municipality_code'] ?? null;
    $barangayCode = $input['barangay_code'] ?? null;
    $streetAddress = $input['street_address'] ?? null;
    $landmark = $input['landmark'] ?? null;
    $zipCode = $input['zip_code'] ?? null;
    $contactNumber = $input['contact_number'] ?? null;
    $email = $input['email'] ?? null;
    $status = $input['status'] ?? 'active';
    
    // Validate required fields
    if (!$branchCode || !$branchName) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Check if branch code already exists
    $existing = Database::fetch(
        "SELECT branch_id FROM business_branches WHERE branch_code = :branch_code",
        ['branch_code' => $branchCode]
    );
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Branch code already exists']);
        return;
    }
    
    // Insert new branch
    $sql = "INSERT INTO business_branches (branch_code, branch_name, region_code, province_code, city_municipality_code, barangay_code, street_address, landmark, zip_code, contact_number, email, status, created_at)
            VALUES (:branch_code, :branch_name, :region_code, :province_code, :city_municipality_code, :barangay_code, :street_address, :landmark, :zip_code, :contact_number, :email, :status, NOW())";
    
    Database::execute($sql, [
        'branch_code' => $branchCode,
        'branch_name' => $branchName,
        'region_code' => $regionCode ?: null,
        'province_code' => $provinceCode ?: null,
        'city_municipality_code' => $cityCode ?: null,
        'barangay_code' => $barangayCode ?: null,
        'street_address' => $streetAddress ?: null,
        'landmark' => $landmark ?: null,
        'zip_code' => $zipCode ?: null,
        'contact_number' => $contactNumber ?: null,
        'email' => $email ?: null,
        'status' => $status
    ]);
    
    $branchId = Database::connection()->lastInsertId();
    
    // Log activity
    logActivity(
        $user['user_id'],
        'CREATE_BRANCH',
        'BRANCH_MANAGEMENT',
        "BRANCH-{$branchId}",
        null,
        [
            'branch_id' => $branchId,
            'branch_code' => $branchCode,
            'branch_name' => $branchName,
            'status' => $status
        ]
    );
    
    echo json_encode(['success' => true, 'message' => 'Branch created successfully', 'branch_id' => $branchId]);
}

/**
 * Handle PUT requests - update branch
 */
function handlePut() {
    global $user, $userRoleCode;
    
    // Check permission
    $canUpdate = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canUpdate) {
        $canUpdate = Auth::can('UPDATE_BRANCH');
    }
    
    if (!$canUpdate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need UPDATE_BRANCH permission.']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $branchId = $input['branch_id'] ?? null;
    
    if (!$branchId) {
        echo json_encode(['success' => false, 'error' => 'Missing branch ID']);
        return;
    }
    
    // Get current branch data
    $currentBranch = Database::fetch(
        "SELECT * FROM business_branches WHERE branch_id = :branch_id",
        ['branch_id' => (int)$branchId]
    );
    
    if (!$currentBranch) {
        echo json_encode(['success' => false, 'error' => 'Branch not found']);
        return;
    }
    
    // Build update query
    $updateFields = [];
    $params = ['branch_id' => (int)$branchId];
    
    if (isset($input['branch_code'])) {
        $updateFields[] = "branch_code = :branch_code";
        $params['branch_code'] = $input['branch_code'];
    }
    if (isset($input['branch_name'])) {
        $updateFields[] = "branch_name = :branch_name";
        $params['branch_name'] = $input['branch_name'];
    }
    if (isset($input['region_code'])) {
        $updateFields[] = "region_code = :region_code";
        $params['region_code'] = $input['region_code'] ?: null;
    }
    if (isset($input['province_code'])) {
        $updateFields[] = "province_code = :province_code";
        $params['province_code'] = $input['province_code'] ?: null;
    }
    if (isset($input['city_municipality_code'])) {
        $updateFields[] = "city_municipality_code = :city_municipality_code";
        $params['city_municipality_code'] = $input['city_municipality_code'] ?: null;
    }
    if (isset($input['barangay_code'])) {
        $updateFields[] = "barangay_code = :barangay_code";
        $params['barangay_code'] = $input['barangay_code'] ?: null;
    }
    if (isset($input['street_address'])) {
        $updateFields[] = "street_address = :street_address";
        $params['street_address'] = $input['street_address'] ?: null;
    }
    if (isset($input['landmark'])) {
        $updateFields[] = "landmark = :landmark";
        $params['landmark'] = $input['landmark'] ?: null;
    }
    if (isset($input['zip_code'])) {
        $updateFields[] = "zip_code = :zip_code";
        $params['zip_code'] = $input['zip_code'] ?: null;
    }
    if (isset($input['contact_number'])) {
        $updateFields[] = "contact_number = :contact_number";
        $params['contact_number'] = $input['contact_number'] ?: null;
    }
    if (isset($input['email'])) {
        $updateFields[] = "email = :email";
        $params['email'] = $input['email'] ?: null;
    }
    if (isset($input['status'])) {
        $updateFields[] = "status = :status";
        $params['status'] = $input['status'];
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    // Update branch
    $updateFields[] = "updated_at = NOW()";
    $sql = "UPDATE business_branches SET " . implode(', ', $updateFields) . " WHERE branch_id = :branch_id";
    Database::execute($sql, $params);
    
    // Log activity
    logActivity(
        $user['user_id'],
        'UPDATE_BRANCH',
        'BRANCH_MANAGEMENT',
        "BRANCH-{$branchId}",
        ['branch_name' => $currentBranch['branch_name']],
        ['branch_name' => $input['branch_name'] ?? $currentBranch['branch_name']]
    );
    
    echo json_encode(['success' => true, 'message' => 'Branch updated successfully']);
}

/**
 * Handle DELETE requests - delete branch
 */
function handleDelete() {
    global $user, $userRoleCode;
    
    // Check permission
    $canDelete = ($userRoleCode === 'SUPER_ADMIN');
    if (!$canDelete) {
        $canDelete = Auth::can('DELETE_BRANCH');
    }
    
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied. You need DELETE_BRANCH permission.']);
        exit;
    }
    
    $branchId = $_GET['id'] ?? null;
    
    if (!$branchId) {
        echo json_encode(['success' => false, 'error' => 'Missing branch ID']);
        return;
    }
    
    // Get current branch data
    $currentBranch = Database::fetch(
        "SELECT * FROM business_branches WHERE branch_id = :branch_id",
        ['branch_id' => (int)$branchId]
    );
    
    if (!$currentBranch) {
        echo json_encode(['success' => false, 'error' => 'Branch not found']);
        return;
    }
    
    // Check if branch has employees
    $hasEmployees = Database::fetch(
        "SELECT COUNT(*) as count FROM employees WHERE branch_id = :branch_id",
        ['branch_id' => (int)$branchId]
    );
    
    if ($hasEmployees && $hasEmployees['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete branch with existing employees']);
        return;
    }
    
    // Check if branch has wallets
    $hasWallets = Database::fetch(
        "SELECT COUNT(*) as count FROM provider_wallets WHERE branch_id = :branch_id",
        ['branch_id' => (int)$branchId]
    );
    
    if ($hasWallets && $hasWallets['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete branch with existing wallets']);
        return;
    }
    
    // Delete branch (soft delete)
    Database::execute(
        "UPDATE business_branches SET deleted_at = NOW() WHERE branch_id = :branch_id",
        ['branch_id' => (int)$branchId]
    );
    
    // Log activity
    logActivity(
        $user['user_id'],
        'DELETE_BRANCH',
        'BRANCH_MANAGEMENT',
        "BRANCH-{$branchId}",
        $currentBranch,
        null
    );
    
    echo json_encode(['success' => true, 'message' => 'Branch deleted successfully']);
}
