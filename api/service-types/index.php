<?php
/**
 * Service Types API Endpoint
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

function logActivity($userId, $action, $moduleName, $referenceCode = null, $oldValue = null, $newValue = null) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceId = null;
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES
            (:user_id, :device_id, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, NOW())",
        [
            'user_id' => $userId, 'device_id' => $deviceId, 'action' => $action,
            'module_name' => $moduleName, 'reference_code' => $referenceCode,
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

function handleGet() {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $st = Database::fetch("SELECT * FROM service_types WHERE service_type_id = :id", ['id' => $id]);
        if (!$st) { echo json_encode(['success' => false, 'error' => 'Not found']); return; }
        echo json_encode(['success' => true, 'data' => $st]);
        return;
    }

    $activeOnly = $_GET['active_only'] ?? null;
    $sql = "SELECT * FROM service_types";
    if ($activeOnly) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY name ASC";

    echo json_encode(['success' => true, 'data' => Database::fetchAll($sql)]);
}

function handlePost() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');

    if (!$code || !$name) {
        echo json_encode(['success' => false, 'error' => 'Code and name are required.']); return;
    }

    $existing = Database::fetch("SELECT service_type_id FROM service_types WHERE code = :code", ['code' => $code]);
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Service code already exists.']); return;
    }

    Database::execute(
        "INSERT INTO service_types (code, name, description, default_amount, allow_custom_amount, requires_wallet, is_active, created_at)
         VALUES (:code, :name, :description, :default_amount, :allow_custom_amount, :requires_wallet, :is_active, NOW())",
        [
            'code' => $code, 'name' => $name,
            'description' => $input['description'] ?? null,
            'default_amount' => $input['default_amount'] ?? 0,
            'allow_custom_amount' => $input['allow_custom_amount'] ?? 1,
            'requires_wallet' => $input['requires_wallet'] ?? 0,
            'is_active' => $input['is_active'] ?? 1,
        ]
    );
    $newId = Database::connection()->lastInsertId();

    logActivity($user['user_id'], 'CREATE_SERVICE_TYPE', 'SERVICE_TYPES', "ST-{$newId}", null,
        ['code' => $code, 'name' => $name]);

    echo json_encode(['success' => true, 'message' => 'Service type created.', 'service_type_id' => $newId]);
}

function handlePut() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['service_type_id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing ID.']); return; }

    $existing = Database::fetch("SELECT * FROM service_types WHERE service_type_id = :id", ['id' => $id]);
    if (!$existing) { echo json_encode(['success' => false, 'error' => 'Not found.']); return; }

    // Status-only toggle
    if (count($input) === 2 && isset($input['is_active'])) {
        Database::execute("UPDATE service_types SET is_active = :is_active, updated_at = NOW() WHERE service_type_id = :id",
            ['is_active' => $input['is_active'], 'id' => $id]);
        logActivity($user['user_id'], 'TOGGLE_SERVICE_TYPE', 'SERVICE_TYPES', "ST-{$id}",
            ['is_active' => $existing['is_active']], ['is_active' => $input['is_active']]);
        echo json_encode(['success' => true, 'message' => 'Status updated.']); return;
    }

    $code = strtoupper(trim($input['code'] ?? $existing['code']));
    $name = trim($input['name'] ?? $existing['name']);
    if (!$code || !$name) {
        echo json_encode(['success' => false, 'error' => 'Code and name are required.']); return;
    }

    $dup = Database::fetch("SELECT service_type_id FROM service_types WHERE code = :code AND service_type_id != :id",
        ['code' => $code, 'id' => $id]);
    if ($dup) { echo json_encode(['success' => false, 'error' => 'Code already in use.']); return; }

    Database::execute(
        "UPDATE service_types SET code = :code, name = :name, description = :description,
            default_amount = :default_amount, allow_custom_amount = :allow_custom_amount,
            requires_wallet = :requires_wallet, is_active = :is_active, updated_at = NOW()
         WHERE service_type_id = :id",
        [
            'code' => $code, 'name' => $name,
            'description' => $input['description'] ?? null,
            'default_amount' => $input['default_amount'] ?? 0,
            'allow_custom_amount' => $input['allow_custom_amount'] ?? 1,
            'requires_wallet' => $input['requires_wallet'] ?? 0,
            'is_active' => $input['is_active'] ?? 1,
            'id' => $id
        ]
    );

    logActivity($user['user_id'], 'UPDATE_SERVICE_TYPE', 'SERVICE_TYPES', "ST-{$id}", $existing,
        ['code' => $code, 'name' => $name]);

    echo json_encode(['success' => true, 'message' => 'Service type updated.']);
}

function handleDelete() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['service_type_id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing ID.']); return; }

    $existing = Database::fetch("SELECT * FROM service_types WHERE service_type_id = :id", ['id' => $id]);
    if (!$existing) { echo json_encode(['success' => false, 'error' => 'Not found.']); return; }

    // Check if used in transactions
    $inUse = Database::fetch("SELECT service_txn_id FROM service_transactions WHERE service_type_id = :id LIMIT 1", ['id' => $id]);
    if ($inUse) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete — already used in service transactions. Deactivate it instead.']); return;
    }

    Database::execute("DELETE FROM service_types WHERE service_type_id = :id", ['id' => $id]);
    logActivity($user['user_id'], 'DELETE_SERVICE_TYPE', 'SERVICE_TYPES', "ST-{$id}", $existing, null);
    echo json_encode(['success' => true, 'message' => 'Service type deleted.']);
}
