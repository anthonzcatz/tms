<?php
/**
 * Discount Types API
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

function dtLogActivity($userId, $action, $ref, $old = null, $new = null) {
    Database::execute(
        "INSERT INTO activity_logs (user_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES (:uid, :action, 'DISCOUNT_TYPES', :ref, :ip, :old, :new, :now)",
        [
            'uid'    => $userId,
            'action' => $action,
            'ref'    => $ref,
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            'old'    => $old  ? json_encode($old)  : null,
            'new'    => $new  ? json_encode($new)   : null,
            'now'    => date('Y-m-d H:i:s'),
        ]
    );
}

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
    if ($id && !is_numeric($id)) {
        $decoded = IdEncoder::decode($id);
        if ($decoded === false) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); return; }
        $id = $decoded;
    }
    if ($id) {
        $row = Database::fetch("SELECT * FROM discount_types WHERE discount_id = :id", ['id' => $id]);
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Not found']); return; }
        echo json_encode(['success' => true, 'data' => $row]);
        return;
    }
    $rows = Database::fetchAll(
        "SELECT * FROM discount_types ORDER BY is_default DESC, name ASC"
    );
    echo json_encode(['success' => true, 'data' => $rows]);
}

function handlePost() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    if (!$code || !$name) { echo json_encode(['success' => false, 'error' => 'Code and name are required.']); return; }

    $dup = Database::fetch("SELECT discount_id FROM discount_types WHERE code = :code", ['code' => $code]);
    if ($dup) { echo json_encode(['success' => false, 'error' => 'Discount code already exists.']); return; }

    $isDefault = !empty($input['is_default']) ? 1 : 0;
    if ($isDefault) {
        Database::execute("UPDATE discount_types SET is_default = 0", []);
    }

    Database::execute(
        "INSERT INTO discount_types (code, name, description, discount_percentage, is_default, created_at)
         VALUES (:code, :name, :desc, :pct, :def, :now)",
        [
            'code' => $code,
            'name' => $name,
            'desc' => $input['description'] ?? null,
            'pct'  => floatval($input['discount_percentage'] ?? 0),
            'def'  => $isDefault,
            'now'  => date('Y-m-d H:i:s'),
        ]
    );
    $newId = Database::connection()->lastInsertId();
    dtLogActivity($user['user_id'], 'CREATE_DISCOUNT_TYPE', "DT-{$newId}", null,
        ['code' => $code, 'name' => $name]);
    echo json_encode(['success' => true, 'message' => 'Discount type created.', 'discount_id' => $newId]);
}

function handlePut() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['discount_id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing ID.']); return; }

    $existing = Database::fetch("SELECT * FROM discount_types WHERE discount_id = :id", ['id' => $id]);
    if (!$existing) { echo json_encode(['success' => false, 'error' => 'Not found.']); return; }

    $code = strtoupper(trim($input['code'] ?? $existing['code']));
    $name = trim($input['name'] ?? $existing['name']);
    if (!$code || !$name) { echo json_encode(['success' => false, 'error' => 'Code and name are required.']); return; }

    $dup = Database::fetch("SELECT discount_id FROM discount_types WHERE code = :code AND discount_id != :id",
        ['code' => $code, 'id' => $id]);
    if ($dup) { echo json_encode(['success' => false, 'error' => 'Code already in use.']); return; }

    $isDefault = !empty($input['is_default']) ? 1 : 0;
    if ($isDefault) {
        Database::execute("UPDATE discount_types SET is_default = 0 WHERE discount_id != :id", ['id' => $id]);
    }

    Database::execute(
        "UPDATE discount_types SET code = :code, name = :name, description = :desc,
         discount_percentage = :pct, is_default = :def WHERE discount_id = :id",
        [
            'code' => $code,
            'name' => $name,
            'desc' => $input['description'] ?? null,
            'pct'  => floatval($input['discount_percentage'] ?? $existing['discount_percentage']),
            'def'  => $isDefault,
            'id'   => $id,
        ]
    );
    dtLogActivity($user['user_id'], 'UPDATE_DISCOUNT_TYPE', "DT-{$id}", $existing,
        ['code' => $code, 'name' => $name]);
    echo json_encode(['success' => true, 'message' => 'Discount type updated.']);
}

function handleDelete() {
    global $user, $userRoleCode;
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_SETTINGS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['discount_id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing ID.']); return; }

    $existing = Database::fetch("SELECT * FROM discount_types WHERE discount_id = :id", ['id' => $id]);
    if (!$existing) { echo json_encode(['success' => false, 'error' => 'Not found.']); return; }

    $inUse = Database::fetch(
        "SELECT COUNT(*) as cnt FROM ticket_transactions WHERE discount_id = :id",
        ['id' => $id]
    );
    if (($inUse['cnt'] ?? 0) > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete — already used in transactions. Set the percentage to 0 instead.']);
        return;
    }

    Database::execute("DELETE FROM discount_types WHERE discount_id = :id", ['id' => $id]);
    dtLogActivity($user['user_id'], 'DELETE_DISCOUNT_TYPE', "DT-{$id}", $existing, null);
    echo json_encode(['success' => true, 'message' => 'Discount type deleted.']);
}
