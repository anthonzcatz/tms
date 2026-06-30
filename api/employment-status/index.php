<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$user = Auth::user(); $userRoleCode = $user['role_code'] ?? '';
$canView = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('MANAGE_EMPLOYMENT_STATUS');
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
} catch (Exception $e) { error_log('Employment Status API Error: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success' => false, 'error' => 'Internal server error']); }

function handleGet() {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $es = Database::fetch('SELECT * FROM employment_status WHERE emp_stat_id = :id', ['id' => (int)$id]);
        echo json_encode($es ? ['success' => true, 'data' => $es] : ['success' => false, 'error' => 'Employment status not found']);
        return;
    }
    $statuses = Database::fetchAll('SELECT * FROM employment_status ORDER BY emp_stat_name');
    echo json_encode(['success' => true, 'data' => ['employment_status' => $statuses]]);
}

function handlePost() {
    global $user, $userRoleCode;
    $canCreate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('CREATE_EMPLOYMENT_STATUS');
    if (!$canCreate) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['emp_stat_name'] ?? '');
    $status = $input['status'] ?? 'active';
    if (!$name) { echo json_encode(['success' => false, 'error' => 'Employment status name is required']); return; }
    $existing = Database::fetch('SELECT emp_stat_id FROM employment_status WHERE emp_stat_name = :name', ['name' => $name]);
    if ($existing) { echo json_encode(['success' => false, 'error' => 'Employment status already exists']); return; }
    Database::execute('INSERT INTO employment_status (emp_stat_name, status, emp_stat_addedby, emp_stat_dateadded) VALUES (:name, :status, :addedby, NOW())', ['name' => $name, 'status' => $status, 'addedby' => $user['user_id'] ?? 1]);
    echo json_encode(['success' => true, 'message' => 'Employment status created successfully', 'emp_stat_id' => Database::connection()->lastInsertId()]);
}

function handlePut() {
    global $user, $userRoleCode;
    $canUpdate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('UPDATE_EMPLOYMENT_STATUS');
    if (!$canUpdate) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['emp_stat_id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing employment status ID']); return; }
    $name = trim($input['emp_stat_name'] ?? '');
    if (!$name) { echo json_encode(['success' => false, 'error' => 'Employment status name is required']); return; }
    $existing = Database::fetch('SELECT emp_stat_id FROM employment_status WHERE emp_stat_name = :name AND emp_stat_id != :id', ['name' => $name, 'id' => (int)$id]);
    if ($existing) { echo json_encode(['success' => false, 'error' => 'Employment status already exists']); return; }
    $fields = ['emp_stat_name = :name'];
    $params = ['name' => $name, 'id' => (int)$id];
    if (isset($input['status'])) { $fields[] = 'status = :status'; $params['status'] = $input['status']; }
    Database::execute('UPDATE employment_status SET ' . implode(', ', $fields) . ' WHERE emp_stat_id = :id', $params);
    echo json_encode(['success' => true, 'message' => 'Employment status updated successfully']);
}

function handleDelete() {
    global $user, $userRoleCode;
    $canDelete = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('DELETE_EMPLOYMENT_STATUS');
    if (!$canDelete) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $id = $_GET['id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing employment status ID']); return; }
    $hasEmployees = Database::fetch('SELECT COUNT(*) as count FROM employees WHERE b_employment_status_id = :id', ['id' => (int)$id]);
    if ($hasEmployees && $hasEmployees['count'] > 0) { echo json_encode(['success' => false, 'error' => 'Cannot delete employment status with existing employees']); return; }
    Database::execute('DELETE FROM employment_status WHERE emp_stat_id = :id', ['id' => (int)$id]);
    echo json_encode(['success' => true, 'message' => 'Employment status deleted successfully']);
}
