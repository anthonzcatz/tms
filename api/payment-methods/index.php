<?php
/**
 * Payment Methods API Endpoint
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Helper function for logging activity
function logActivity($userId, $action, $moduleName, $referenceCode = null, $oldValue = null, $newValue = null) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceId = null;
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

Auth::requireLogin();
$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':    handleGet();    break;
    case 'POST':   handlePost();   break;
    case 'PUT':    handlePut();    break;
    case 'DELETE': handleDelete(); break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

/**
 * GET - Fetch one or all payment methods
 */
function handleGet() {
    $id = $_GET['id'] ?? null;

    if ($id) {
        $method = Database::fetch(
            "SELECT * FROM payment_methods WHERE method_id = :id",
            ['id' => $id]
        );
        if (!$method) {
            echo json_encode(['success' => false, 'error' => 'Payment method not found']);
            return;
        }
        echo json_encode(['success' => true, 'data' => $method]);
        return;
    }

    $activeOnly = $_GET['active_only'] ?? null;
    $sql = "SELECT * FROM payment_methods";
    if ($activeOnly) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY sort_order ASC, method_name ASC";

    $methods = Database::fetchAll($sql);
    echo json_encode(['success' => true, 'data' => $methods]);
}

/**
 * POST - Create a new payment method
 */
function handlePost() {
    global $user, $userRoleCode;

    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $methodCode = strtoupper(trim($input['method_code'] ?? ''));
    $methodName = trim($input['method_name'] ?? '');
    $methodType = $input['method_type'] ?? '';

    if (!$methodCode || !$methodName || !$methodType) {
        echo json_encode(['success' => false, 'error' => 'Method code, name, and type are required.']);
        return;
    }

    // Check unique code
    $existing = Database::fetch(
        "SELECT method_id FROM payment_methods WHERE method_code = :code",
        ['code' => $methodCode]
    );
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Method code already exists.']);
        return;
    }

    Database::execute(
        "INSERT INTO payment_methods
            (method_code, method_name, method_type, description, icon, requires_confirmation,
             requires_customer, requires_reference, include_in_expected_cash, is_active, sort_order, created_at)
         VALUES
            (:method_code, :method_name, :method_type, :description, :icon, :requires_confirmation,
             :requires_customer, :requires_reference, :include_in_expected_cash, :is_active, :sort_order, NOW())",
        [
            'method_code'               => $methodCode,
            'method_name'               => $methodName,
            'method_type'               => $methodType,
            'description'               => $input['description'] ?? null,
            'icon'                      => $input['icon'] ?? null,
            'requires_confirmation'     => $input['requires_confirmation'] ?? 0,
            'requires_customer'         => $input['requires_customer'] ?? 0,
            'requires_reference'        => $input['requires_reference'] ?? 0,
            'include_in_expected_cash'  => $input['include_in_expected_cash'] ?? ($methodType === 'CASH' ? 1 : 0),
            'is_active'                 => $input['is_active'] ?? 1,
            'sort_order'                => $input['sort_order'] ?? 0,
        ]
    );

    $newId = Database::connection()->lastInsertId();

    logActivity(
        $user['user_id'],
        'CREATE_PAYMENT_METHOD',
        'PAYMENT_METHODS',
        "PM-{$newId}",
        null,
        ['method_code' => $methodCode, 'method_name' => $methodName, 'method_type' => $methodType]
    );

    echo json_encode(['success' => true, 'message' => 'Payment method created successfully.', 'method_id' => $newId]);
}

/**
 * PUT - Update a payment method
 */
function handlePut() {
    global $user, $userRoleCode;

    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $methodId = $input['method_id'] ?? null;

    if (!$methodId) {
        echo json_encode(['success' => false, 'error' => 'Missing method ID.']);
        return;
    }

    $existing = Database::fetch(
        "SELECT * FROM payment_methods WHERE method_id = :id",
        ['id' => $methodId]
    );
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'Payment method not found.']);
        return;
    }

    // Status-only toggle
    if (count($input) === 2 && isset($input['is_active'])) {
        Database::execute(
            "UPDATE payment_methods SET is_active = :is_active, updated_at = NOW() WHERE method_id = :id",
            ['is_active' => $input['is_active'], 'id' => $methodId]
        );
        logActivity($user['user_id'], 'TOGGLE_PAYMENT_METHOD', 'PAYMENT_METHODS', "PM-{$methodId}",
            ['is_active' => $existing['is_active']], ['is_active' => $input['is_active']]);
        echo json_encode(['success' => true, 'message' => 'Status updated.']);
        return;
    }

    $methodCode = strtoupper(trim($input['method_code'] ?? $existing['method_code']));
    $methodName = trim($input['method_name'] ?? $existing['method_name']);
    $methodType = $input['method_type'] ?? $existing['method_type'];

    if (!$methodCode || !$methodName || !$methodType) {
        echo json_encode(['success' => false, 'error' => 'Method code, name, and type are required.']);
        return;
    }

    // Check unique code (exclude self)
    $dup = Database::fetch(
        "SELECT method_id FROM payment_methods WHERE method_code = :code AND method_id != :id",
        ['code' => $methodCode, 'id' => $methodId]
    );
    if ($dup) {
        echo json_encode(['success' => false, 'error' => 'Method code already in use by another method.']);
        return;
    }

    Database::execute(
        "UPDATE payment_methods SET
            method_code = :method_code,
            method_name = :method_name,
            method_type = :method_type,
            description = :description,
            icon = :icon,
            requires_confirmation = :requires_confirmation,
            requires_customer = :requires_customer,
            requires_reference = :requires_reference,
            include_in_expected_cash = :include_in_expected_cash,
            is_active = :is_active,
            sort_order = :sort_order,
            updated_at = NOW()
         WHERE method_id = :id",
        [
            'method_code'              => $methodCode,
            'method_name'              => $methodName,
            'method_type'              => $methodType,
            'description'              => $input['description'] ?? null,
            'icon'                     => $input['icon'] ?? null,
            'requires_confirmation'    => $input['requires_confirmation'] ?? 0,
            'requires_customer'        => $input['requires_customer'] ?? 0,
            'requires_reference'       => $input['requires_reference'] ?? 0,
            'include_in_expected_cash' => $input['include_in_expected_cash'] ?? 0,
            'is_active'                => $input['is_active'] ?? 1,
            'sort_order'               => $input['sort_order'] ?? 0,
            'id'                       => $methodId
        ]
    );

    logActivity($user['user_id'], 'UPDATE_PAYMENT_METHOD', 'PAYMENT_METHODS', "PM-{$methodId}",
        $existing, ['method_code' => $methodCode, 'method_name' => $methodName, 'method_type' => $methodType]);

    echo json_encode(['success' => true, 'message' => 'Payment method updated successfully.']);
}

/**
 * DELETE - Delete a payment method
 */
function handleDelete() {
    global $user, $userRoleCode;

    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $methodId = $input['method_id'] ?? null;

    if (!$methodId) {
        echo json_encode(['success' => false, 'error' => 'Missing method ID.']);
        return;
    }

    $existing = Database::fetch(
        "SELECT * FROM payment_methods WHERE method_id = :id",
        ['id' => $methodId]
    );
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'Payment method not found.']);
        return;
    }

    // Check if used in transactions
    $inUse = Database::fetch(
        "SELECT payment_id FROM transaction_payments WHERE payment_method_id = :id LIMIT 1",
        ['id' => $methodId]
    );
    if ($inUse) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete — this method is already used in transactions. Deactivate it instead.']);
        return;
    }

    Database::execute("DELETE FROM payment_methods WHERE method_id = :id", ['id' => $methodId]);

    logActivity($user['user_id'], 'DELETE_PAYMENT_METHOD', 'PAYMENT_METHODS', "PM-{$methodId}", $existing, null);

    echo json_encode(['success' => true, 'message' => 'Payment method deleted successfully.']);
}
