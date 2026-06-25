<?php
/**
 * Departments API Endpoint
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';
$canView = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('MANAGE_DEPARTMENTS');

if (!$canView) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. You need MANAGE_DEPARTMENTS permission.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET': handleGet(); break;
        case 'POST': handlePost(); break;
        case 'PUT': handlePut(); break;
        case 'DELETE': handleDelete(); break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Departments API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function handleGet() {
    $deptId = $_GET['id'] ?? null;
    if ($deptId) {
        $dept = Database::fetch("SELECT * FROM department WHERE dept_id = :dept_id", ['dept_id' => (int)$deptId]);
        if ($dept) echo json_encode(['success' => true, 'data' => $dept]);
        else echo json_encode(['success' => false, 'error' => 'Department not found']);
        return;
    }
    $departments = Database::fetchAll("SELECT * FROM department ORDER BY department_name");
    echo json_encode(['success' => true, 'data' => ['departments' => $departments]]);
}

function handlePost() {
    global $user, $userRoleCode;
    $canCreate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('CREATE_DEPARTMENT');
    if (!$canCreate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $deptName = trim($input['department_name'] ?? '');
    $deptCode = trim($input['department_code'] ?? '');
    $status = $input['status'] ?? 'active';

    if (!$deptName) {
        echo json_encode(['success' => false, 'error' => 'Department name is required']);
        return;
    }
    $existing = Database::fetch("SELECT dept_id FROM department WHERE department_name = :department_name", ['department_name' => $deptName]);
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Department name already exists']);
        return;
    }
    Database::execute(
        "INSERT INTO department (department_name, department_code, dept_logo, status, dept_addedby, dept_dateadded) VALUES (:department_name, :department_code, NULL, :status, :dept_addedby, NOW())",
        ['department_name' => $deptName, 'department_code' => $deptCode ?: null, 'status' => $status, 'dept_addedby' => $user['user_id'] ?? 1]
    );
    $deptId = Database::connection()->lastInsertId();
    echo json_encode(['success' => true, 'message' => 'Department created successfully', 'dept_id' => $deptId]);
}

function handlePut() {
    global $user, $userRoleCode;
    $canUpdate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('UPDATE_DEPARTMENT');
    if (!$canUpdate) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $deptId = $input['dept_id'] ?? null;
    if (!$deptId) {
        echo json_encode(['success' => false, 'error' => 'Missing department ID']);
        return;
    }
    $current = Database::fetch("SELECT * FROM department WHERE dept_id = :dept_id", ['dept_id' => (int)$deptId]);
    if (!$current) {
        echo json_encode(['success' => false, 'error' => 'Department not found']);
        return;
    }
    $fields = [];
    $params = ['dept_id' => (int)$deptId];
    if (isset($input['department_name'])) {
        $name = trim($input['department_name']);
        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'Department name is required']);
            return;
        }
        $existing = Database::fetch("SELECT dept_id FROM department WHERE department_name = :name AND dept_id != :id", ['name' => $name, 'id' => (int)$deptId]);
        if ($existing) {
            echo json_encode(['success' => false, 'error' => 'Department name already exists']);
            return;
        }
        $fields[] = "department_name = :department_name";
        $params['department_name'] = $name;
    }
    if (isset($input['department_code'])) {
        $fields[] = "department_code = :department_code";
        $params['department_code'] = trim($input['department_code']) ?: null;
    }
    if (isset($input['status'])) {
        $fields[] = "status = :status";
        $params['status'] = $input['status'];
    }
    if (empty($fields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    $sql = "UPDATE department SET " . implode(', ', $fields) . " WHERE dept_id = :dept_id";
    Database::execute($sql, $params);
    echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
}

function handleDelete() {
    global $user, $userRoleCode;
    $canDelete = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('DELETE_DEPARTMENT');
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    $deptId = $_GET['id'] ?? null;
    if (!$deptId) {
        echo json_encode(['success' => false, 'error' => 'Missing department ID']);
        return;
    }
    $hasEmployees = Database::fetch("SELECT COUNT(*) as count FROM employees WHERE b_department_id = :dept_id", ['dept_id' => (int)$deptId]);
    if ($hasEmployees && $hasEmployees['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete department with existing employees']);
        return;
    }
    $hasSubDepts = Database::fetch("SELECT COUNT(*) as count FROM sub_department WHERE main_department_id = :dept_id", ['dept_id' => (int)$deptId]);
    if ($hasSubDepts && $hasSubDepts['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete department with existing sub-departments']);
        return;
    }
    Database::execute("DELETE FROM department WHERE dept_id = :dept_id", ['dept_id' => (int)$deptId]);
    echo json_encode(['success' => true, 'message' => 'Department deleted successfully']);
}
