<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$user = Auth::user(); $userRoleCode = $user['role_code'] ?? '';
$canView = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('MANAGE_POSITIONS');
if (!$canView) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
try {
    switch ($method) {
        case 'GET': handleGet(); break;
        case 'POST': handlePost(); break;
        case 'PUT': handlePut(); break;
        case 'DELETE': handleDelete(); break;
        default: http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) { error_log("Positions API Error: " . $e->getMessage()); http_response_code(500); echo json_encode(['success' => false, 'error' => 'Internal server error']); }

function handleGet() {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $pos = Database::fetch("SELECT * FROM position WHERE pos_id = :id", ['id' => (int)$id]);
        echo json_encode($pos ? ['success' => true, 'data' => $pos] : ['success' => false, 'error' => 'Position not found']);
        return;
    }
    $positions = Database::fetchAll("SELECT * FROM position ORDER BY position_name");
    echo json_encode(['success' => true, 'data' => ['positions' => $positions]]);
}

function handlePost() {
    global $user, $userRoleCode;
    $canCreate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('CREATE_POSITION');
    if (!$canCreate) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['position_name'] ?? '');
    $code = trim($input['pos_code'] ?? '');
    if (!$name) { echo json_encode(['success' => false, 'error' => 'Position name is required']); return; }
    $existing = Database::fetch("SELECT pos_id FROM position WHERE position_name = :name", ['name' => $name]);
    if ($existing) { echo json_encode(['success' => false, 'error' => 'Position name already exists']); return; }
    Database::execute("INSERT INTO position (position_name, pos_code, pos_addedby, pos_dateadded) VALUES (:name, :code, :addedby, NOW())",
        ['name' => $name, 'code' => $code ?: null, 'addedby' => $user['user_id'] ?? 1]);
    echo json_encode(['success' => true, 'message' => 'Position created successfully', 'pos_id' => Database::connection()->lastInsertId()]);
}

function handlePut() {
    global $user, $userRoleCode;
    $canUpdate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('UPDATE_POSITION');
    if (!$canUpdate) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['pos_id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing position ID']); return; }
    $fields = []; $params = ['id' => (int)$id];
    if (isset($input['position_name'])) {
        $name = trim($input['position_name']);
        if (!$name) { echo json_encode(['success' => false, 'error' => 'Position name is required']); return; }
        $existing = Database::fetch("SELECT pos_id FROM position WHERE position_name = :name AND pos_id != :id", ['name' => $name, 'id' => (int)$id]);
        if ($existing) { echo json_encode(['success' => false, 'error' => 'Position name already exists']); return; }
        $fields[] = "position_name = :name"; $params['name'] = $name;
    }
    if (isset($input['pos_code'])) { $fields[] = "pos_code = :code"; $params['code'] = trim($input['pos_code']) ?: null; }
    if (empty($fields)) { echo json_encode(['success' => false, 'error' => 'No fields to update']); return; }
    Database::execute("UPDATE position SET " . implode(', ', $fields) . " WHERE pos_id = :id", $params);
    echo json_encode(['success' => true, 'message' => 'Position updated successfully']);
}

function handleDelete() {
    global $user, $userRoleCode;
    $canDelete = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('DELETE_POSITION');
    if (!$canDelete) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $id = $_GET['id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing position ID']); return; }
    $hasEmployees = Database::fetch("SELECT COUNT(*) as count FROM employees WHERE job_title = :id", ['id' => (int)$id]);
    if ($hasEmployees && $hasEmployees['count'] > 0) { echo json_encode(['success' => false, 'error' => 'Cannot delete position with existing employees']); return; }
    Database::execute("DELETE FROM position WHERE pos_id = :id", ['id' => (int)$id]);
    echo json_encode(['success' => true, 'message' => 'Position deleted successfully']);
}
