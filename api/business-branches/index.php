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
    $now = date('Y-m-d H:i:s');
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES
            (:user_id, :device_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, :created_at)",
        [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'action' => $action,
            'module_name' => $moduleName,
            'reference_code' => $referenceCode,
            'ip_address' => $ipAddress,
            'old_value' => $oldValue ? json_encode($oldValue) : null,
            'new_value' => $newValue ? json_encode($newValue) : null,
            'created_at' => $now
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
    
    // Operating hours
    $mondayOpen = $input['monday_open'] ?? '08:00:00';
    $mondayClose = $input['monday_close'] ?? '18:00:00';
    $mondayClosed = $input['monday_closed'] ?? 0;
    $mondayBreakStart = $input['monday_break_start'] ?? null;
    $mondayBreakEnd = $input['monday_break_end'] ?? null;
    $tuesdayOpen = $input['tuesday_open'] ?? '08:00:00';
    $tuesdayClose = $input['tuesday_close'] ?? '18:00:00';
    $tuesdayClosed = $input['tuesday_closed'] ?? 0;
    $tuesdayBreakStart = $input['tuesday_break_start'] ?? null;
    $tuesdayBreakEnd = $input['tuesday_break_end'] ?? null;
    $wednesdayOpen = $input['wednesday_open'] ?? '08:00:00';
    $wednesdayClose = $input['wednesday_close'] ?? '18:00:00';
    $wednesdayClosed = $input['wednesday_closed'] ?? 0;
    $wednesdayBreakStart = $input['wednesday_break_start'] ?? null;
    $wednesdayBreakEnd = $input['wednesday_break_end'] ?? null;
    $thursdayOpen = $input['thursday_open'] ?? '08:00:00';
    $thursdayClose = $input['thursday_close'] ?? '18:00:00';
    $thursdayClosed = $input['thursday_closed'] ?? 0;
    $thursdayBreakStart = $input['thursday_break_start'] ?? null;
    $thursdayBreakEnd = $input['thursday_break_end'] ?? null;
    $fridayOpen = $input['friday_open'] ?? '08:00:00';
    $fridayClose = $input['friday_close'] ?? '18:00:00';
    $fridayClosed = $input['friday_closed'] ?? 0;
    $fridayBreakStart = $input['friday_break_start'] ?? null;
    $fridayBreakEnd = $input['friday_break_end'] ?? null;
    $saturdayOpen = $input['saturday_open'] ?? '08:00:00';
    $saturdayClose = $input['saturday_close'] ?? '18:00:00';
    $saturdayClosed = $input['saturday_closed'] ?? 0;
    $saturdayBreakStart = $input['saturday_break_start'] ?? null;
    $saturdayBreakEnd = $input['saturday_break_end'] ?? null;
    $sundayOpen = $input['sunday_open'] ?? '08:00:00';
    $sundayClose = $input['sunday_close'] ?? '18:00:00';
    $sundayClosed = $input['sunday_closed'] ?? 0;
    $sundayBreakStart = $input['sunday_break_start'] ?? null;
    $sundayBreakEnd = $input['sunday_break_end'] ?? null;
    $is24Hours = $input['is_24_hours'] ?? 0;
    $maxCapacity = $input['max_capacity'] ?? 100;
    $managerName = $input['manager_name'] ?? null;
    $managerContact = $input['manager_contact'] ?? null;
    $notes = $input['notes'] ?? null;
    
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
    $sql = "INSERT INTO business_branches (branch_code, branch_name, region_code, province_code, city_municipality_code, barangay_code, street_address, landmark, zip_code, contact_number, email, status, monday_open, monday_close, monday_closed, monday_break_start, monday_break_end, tuesday_open, tuesday_close, tuesday_closed, tuesday_break_start, tuesday_break_end, wednesday_open, wednesday_close, wednesday_closed, wednesday_break_start, wednesday_break_end, thursday_open, thursday_close, thursday_closed, thursday_break_start, thursday_break_end, friday_open, friday_close, friday_closed, friday_break_start, friday_break_end, saturday_open, saturday_close, saturday_closed, saturday_break_start, saturday_break_end, sunday_open, sunday_close, sunday_closed, sunday_break_start, sunday_break_end, is_24_hours, max_capacity, manager_name, manager_contact, notes, created_at)
            VALUES (:branch_code, :branch_name, :region_code, :province_code, :city_municipality_code, :barangay_code, :street_address, :landmark, :zip_code, :contact_number, :email, :status, :monday_open, :monday_close, :monday_closed, :monday_break_start, :monday_break_end, :tuesday_open, :tuesday_close, :tuesday_closed, :tuesday_break_start, :tuesday_break_end, :wednesday_open, :wednesday_close, :wednesday_closed, :wednesday_break_start, :wednesday_break_end, :thursday_open, :thursday_close, :thursday_closed, :thursday_break_start, :thursday_break_end, :friday_open, :friday_close, :friday_closed, :friday_break_start, :friday_break_end, :saturday_open, :saturday_close, :saturday_closed, :saturday_break_start, :saturday_break_end, :sunday_open, :sunday_close, :sunday_closed, :sunday_break_start, :sunday_break_end, :is_24_hours, :max_capacity, :manager_name, :manager_contact, :notes, NOW())";
    
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
        'status' => $status,
        'monday_open' => $mondayOpen,
        'monday_close' => $mondayClose,
        'monday_closed' => $mondayClosed,
        'monday_break_start' => $mondayBreakStart,
        'monday_break_end' => $mondayBreakEnd,
        'tuesday_open' => $tuesdayOpen,
        'tuesday_close' => $tuesdayClose,
        'tuesday_closed' => $tuesdayClosed,
        'tuesday_break_start' => $tuesdayBreakStart,
        'tuesday_break_end' => $tuesdayBreakEnd,
        'wednesday_open' => $wednesdayOpen,
        'wednesday_close' => $wednesdayClose,
        'wednesday_closed' => $wednesdayClosed,
        'wednesday_break_start' => $wednesdayBreakStart,
        'wednesday_break_end' => $wednesdayBreakEnd,
        'thursday_open' => $thursdayOpen,
        'thursday_close' => $thursdayClose,
        'thursday_closed' => $thursdayClosed,
        'thursday_break_start' => $thursdayBreakStart,
        'thursday_break_end' => $thursdayBreakEnd,
        'friday_open' => $fridayOpen,
        'friday_close' => $fridayClose,
        'friday_closed' => $fridayClosed,
        'friday_break_start' => $fridayBreakStart,
        'friday_break_end' => $fridayBreakEnd,
        'saturday_open' => $saturdayOpen,
        'saturday_close' => $saturdayClose,
        'saturday_closed' => $saturdayClosed,
        'saturday_break_start' => $saturdayBreakStart,
        'saturday_break_end' => $saturdayBreakEnd,
        'sunday_open' => $sundayOpen,
        'sunday_close' => $sundayClose,
        'sunday_closed' => $sundayClosed,
        'sunday_break_start' => $sundayBreakStart,
        'sunday_break_end' => $sundayBreakEnd,
        'is_24_hours' => $is24Hours,
        'max_capacity' => $maxCapacity,
        'manager_name' => $managerName,
        'manager_contact' => $managerContact,
        'notes' => $notes
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
    
    // Operating hours
    if (isset($input['monday_open'])) {
        $updateFields[] = "monday_open = :monday_open";
        $params['monday_open'] = $input['monday_open'];
    }
    if (isset($input['monday_close'])) {
        $updateFields[] = "monday_close = :monday_close";
        $params['monday_close'] = $input['monday_close'];
    }
    if (isset($input['monday_closed'])) {
        $updateFields[] = "monday_closed = :monday_closed";
        $params['monday_closed'] = $input['monday_closed'];
    }
    if (isset($input['monday_break_start'])) {
        $updateFields[] = "monday_break_start = :monday_break_start";
        $params['monday_break_start'] = $input['monday_break_start'];
    }
    if (isset($input['monday_break_end'])) {
        $updateFields[] = "monday_break_end = :monday_break_end";
        $params['monday_break_end'] = $input['monday_break_end'];
    }
    if (isset($input['tuesday_open'])) {
        $updateFields[] = "tuesday_open = :tuesday_open";
        $params['tuesday_open'] = $input['tuesday_open'];
    }
    if (isset($input['tuesday_close'])) {
        $updateFields[] = "tuesday_close = :tuesday_close";
        $params['tuesday_close'] = $input['tuesday_close'];
    }
    if (isset($input['tuesday_closed'])) {
        $updateFields[] = "tuesday_closed = :tuesday_closed";
        $params['tuesday_closed'] = $input['tuesday_closed'];
    }
    if (isset($input['tuesday_break_start'])) {
        $updateFields[] = "tuesday_break_start = :tuesday_break_start";
        $params['tuesday_break_start'] = $input['tuesday_break_start'];
    }
    if (isset($input['tuesday_break_end'])) {
        $updateFields[] = "tuesday_break_end = :tuesday_break_end";
        $params['tuesday_break_end'] = $input['tuesday_break_end'];
    }
    if (isset($input['wednesday_open'])) {
        $updateFields[] = "wednesday_open = :wednesday_open";
        $params['wednesday_open'] = $input['wednesday_open'];
    }
    if (isset($input['wednesday_close'])) {
        $updateFields[] = "wednesday_close = :wednesday_close";
        $params['wednesday_close'] = $input['wednesday_close'];
    }
    if (isset($input['wednesday_closed'])) {
        $updateFields[] = "wednesday_closed = :wednesday_closed";
        $params['wednesday_closed'] = $input['wednesday_closed'];
    }
    if (isset($input['wednesday_break_start'])) {
        $updateFields[] = "wednesday_break_start = :wednesday_break_start";
        $params['wednesday_break_start'] = $input['wednesday_break_start'];
    }
    if (isset($input['wednesday_break_end'])) {
        $updateFields[] = "wednesday_break_end = :wednesday_break_end";
        $params['wednesday_break_end'] = $input['wednesday_break_end'];
    }
    if (isset($input['thursday_open'])) {
        $updateFields[] = "thursday_open = :thursday_open";
        $params['thursday_open'] = $input['thursday_open'];
    }
    if (isset($input['thursday_close'])) {
        $updateFields[] = "thursday_close = :thursday_close";
        $params['thursday_close'] = $input['thursday_close'];
    }
    if (isset($input['thursday_closed'])) {
        $updateFields[] = "thursday_closed = :thursday_closed";
        $params['thursday_closed'] = $input['thursday_closed'];
    }
    if (isset($input['thursday_break_start'])) {
        $updateFields[] = "thursday_break_start = :thursday_break_start";
        $params['thursday_break_start'] = $input['thursday_break_start'];
    }
    if (isset($input['thursday_break_end'])) {
        $updateFields[] = "thursday_break_end = :thursday_break_end";
        $params['thursday_break_end'] = $input['thursday_break_end'];
    }
    if (isset($input['friday_open'])) {
        $updateFields[] = "friday_open = :friday_open";
        $params['friday_open'] = $input['friday_open'];
    }
    if (isset($input['friday_close'])) {
        $updateFields[] = "friday_close = :friday_close";
        $params['friday_close'] = $input['friday_close'];
    }
    if (isset($input['friday_closed'])) {
        $updateFields[] = "friday_closed = :friday_closed";
        $params['friday_closed'] = $input['friday_closed'];
    }
    if (isset($input['friday_break_start'])) {
        $updateFields[] = "friday_break_start = :friday_break_start";
        $params['friday_break_start'] = $input['friday_break_start'];
    }
    if (isset($input['friday_break_end'])) {
        $updateFields[] = "friday_break_end = :friday_break_end";
        $params['friday_break_end'] = $input['friday_break_end'];
    }
    if (isset($input['saturday_open'])) {
        $updateFields[] = "saturday_open = :saturday_open";
        $params['saturday_open'] = $input['saturday_open'];
    }
    if (isset($input['saturday_close'])) {
        $updateFields[] = "saturday_close = :saturday_close";
        $params['saturday_close'] = $input['saturday_close'];
    }
    if (isset($input['saturday_closed'])) {
        $updateFields[] = "saturday_closed = :saturday_closed";
        $params['saturday_closed'] = $input['saturday_closed'];
    }
    if (isset($input['saturday_break_start'])) {
        $updateFields[] = "saturday_break_start = :saturday_break_start";
        $params['saturday_break_start'] = $input['saturday_break_start'];
    }
    if (isset($input['saturday_break_end'])) {
        $updateFields[] = "saturday_break_end = :saturday_break_end";
        $params['saturday_break_end'] = $input['saturday_break_end'];
    }
    if (isset($input['sunday_open'])) {
        $updateFields[] = "sunday_open = :sunday_open";
        $params['sunday_open'] = $input['sunday_open'];
    }
    if (isset($input['sunday_close'])) {
        $updateFields[] = "sunday_close = :sunday_close";
        $params['sunday_close'] = $input['sunday_close'];
    }
    if (isset($input['sunday_closed'])) {
        $updateFields[] = "sunday_closed = :sunday_closed";
        $params['sunday_closed'] = $input['sunday_closed'];
    }
    if (isset($input['sunday_break_start'])) {
        $updateFields[] = "sunday_break_start = :sunday_break_start";
        $params['sunday_break_start'] = $input['sunday_break_start'];
    }
    if (isset($input['sunday_break_end'])) {
        $updateFields[] = "sunday_break_end = :sunday_break_end";
        $params['sunday_break_end'] = $input['sunday_break_end'];
    }
    if (isset($input['is_24_hours'])) {
        $updateFields[] = "is_24_hours = :is_24_hours";
        $params['is_24_hours'] = $input['is_24_hours'];
    }
    if (isset($input['max_capacity'])) {
        $updateFields[] = "max_capacity = :max_capacity";
        $params['max_capacity'] = $input['max_capacity'];
    }
    if (isset($input['manager_name'])) {
        $updateFields[] = "manager_name = :manager_name";
        $params['manager_name'] = $input['manager_name'];
    }
    if (isset($input['manager_contact'])) {
        $updateFields[] = "manager_contact = :manager_contact";
        $params['manager_contact'] = $input['manager_contact'];
    }
    if (isset($input['notes'])) {
        $updateFields[] = "notes = :notes";
        $params['notes'] = $input['notes'];
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    // Update branch
    $updateFields[] = "updated_at = :updated_at";
    $params['updated_at'] = date('Y-m-d H:i:s');
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
        "UPDATE business_branches SET deleted_at = :deleted_at WHERE branch_id = :branch_id",
        ['branch_id' => (int)$branchId, 'deleted_at' => date('Y-m-d H:i:s')]
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
