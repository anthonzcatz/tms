<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$user = Auth::user(); $userRoleCode = $user['role_code'] ?? '';
$canView = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('MANAGE_SUB_DEPARTMENTS');
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
} catch (Exception $e) { error_log("Sub-departments API Error: " . $e->getMessage()); http_response_code(500); echo json_encode(['success' => false, 'error' => 'Internal server error']); }

function handleGet() {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $sd = Database::fetch(
            "SELECT sd.*, d.department_name AS main_department_name 
             FROM sub_department sd 
             LEFT JOIN department d ON sd.main_department_id = d.dept_id 
             WHERE sd.sub_depart_id = :id",
            ['id' => (int)$id]
        );
        echo json_encode($sd ? ['success' => true, 'data' => $sd] : ['success' => false, 'error' => 'Sub-department not found']);
        return;
    }
    $deptId = $_GET['department_id'] ?? null;
    $where = $deptId ? "WHERE sd.main_department_id = :dept_id" : "";
    $params = $deptId ? ['dept_id' => (int)$deptId] : [];
    $subDepartments = Database::fetchAll(
        "SELECT sd.*, d.department_name AS main_department_name 
         FROM sub_department sd 
         LEFT JOIN department d ON sd.main_department_id = d.dept_id 
         {$where} 
         ORDER BY d.department_name, sd.sub_department_name",
        $params
    );
    echo json_encode(['success' => true, 'data' => ['sub_departments' => $subDepartments]]);
}

function handlePost() {
    global $user, $userRoleCode;
    $canCreate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('CREATE_SUB_DEPARTMENT');
    if (!$canCreate) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['sub_department_name'] ?? '');
    $deptId = (int)($input['main_department_id'] ?? 0);
    if (!$name || !$deptId) { echo json_encode(['success' => false, 'error' => 'Sub-department name and department are required']); return; }
    $existing = Database::fetch("SELECT sub_depart_id FROM sub_department WHERE sub_department_name = :name AND main_department_id = :dept_id", ['name' => $name, 'dept_id' => $deptId]);
    if ($existing) { echo json_encode(['success' => false, 'error' => 'Sub-department name already exists in this department']); return; }
    Database::execute(
        "INSERT INTO sub_department (sub_department_name, main_department_id, sub_depart_addedby, sub_depart_dateadded) VALUES (:name, :dept_id, :addedby, NOW())",
        ['name' => $name, 'dept_id' => $deptId, 'addedby' => $user['user_id'] ?? 1]
    );
    echo json_encode(['success' => true, 'message' => 'Sub-department created successfully', 'sub_depart_id' => Database::connection()->lastInsertId()]);
}

function handlePut() {
    global $user, $userRoleCode;
    $canUpdate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('UPDATE_SUB_DEPARTMENT');
    if (!$canUpdate) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['sub_depart_id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing sub-department ID']); return; }
    $fields = []; $params = ['id' => (int)$id];
    if (isset($input['sub_department_name'])) {
        $name = trim($input['sub_department_name']);
        if (!$name) { echo json_encode(['success' => false, 'error' => 'Sub-department name is required']); return; }
        $deptId = (int)($input['main_department_id'] ?? 0);
        if (!$deptId) { echo json_encode(['success' => false, 'error' => 'Department is required']); return; }
        $existing = Database::fetch(
            "SELECT sub_depart_id FROM sub_department WHERE sub_department_name = :name AND main_department_id = :dept_id AND sub_depart_id != :id",
            ['name' => $name, 'dept_id' => $deptId, 'id' => (int)$id]
        );
        if ($existing) { echo json_encode(['success' => false, 'error' => 'Sub-department name already exists in this department']); return; }
        $fields[] = "sub_department_name = :name"; $params['name'] = $name;
        $fields[] = "main_department_id = :dept_id"; $params['dept_id'] = $deptId;
    }
    if (empty($fields)) { echo json_encode(['success' => false, 'error' => 'No fields to update']); return; }
    Database::execute("UPDATE sub_department SET " . implode(', ', $fields) . " WHERE sub_depart_id = :id", $params);
    echo json_encode(['success' => true, 'message' => 'Sub-department updated successfully']);
}

function handleDelete() {
    global $user, $userRoleCode;
    $canDelete = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('DELETE_SUB_DEPARTMENT');
    if (!$canDelete) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $id = $_GET['id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing sub-department ID']); return; }
    $hasEmployees = Database::fetch("SELECT COUNT(*) as count FROM employees WHERE b_sub_department_id = :id", ['id' => (int)$id]);
    if ($hasEmployees && $hasEmployees['count'] > 0) { echo json_encode(['success' => false, 'error' => 'Cannot delete sub-department with existing employees']); return; }
    Database::execute("DELETE FROM sub_department WHERE sub_depart_id = :id", ['id' => (int)$id]);
    echo json_encode(['success' => true, 'message' => 'Sub-department deleted successfully']);
}
