<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

if (!Auth::check()) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
$user = Auth::user(); $userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');

// Main branch ID that all users can view for employees (legacy/default branch).
const EMPLOYEE_MAIN_BRANCH_ID = 1;
$canView = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('MANAGE_EMPLOYEES');
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
} catch (Exception $e) { error_log('Employees API Error: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success' => false, 'error' => 'Internal server error']); }

function enforceEmployeeBranchAccess(?int $branchId): void
{
    global $user;
    // Legacy employee records may not have a branch assigned yet.
    if ($branchId === null) {
        return;
    }
    // Main branch is shared across all users.
    if ($branchId === EMPLOYEE_MAIN_BRANCH_ID) {
        return;
    }
    $allowedBranchIds = PosAccess::allowedBranchIds($user);
    if ($allowedBranchIds === null) {
        return;
    }
    try {
        PosAccess::assertBranchAccess($user, $branchId);
    } catch (Throwable $e) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function defaultEmployeeBranch(?int $branchId): int
{
    if ($branchId !== null && $branchId > 0) {
        return $branchId;
    }
    global $user;
    $allowedBranchIds = PosAccess::allowedBranchIds($user);
    if (!empty($allowedBranchIds)) {
        return (int) $allowedBranchIds[0];
    }
    // Fallback to main branch
    return EMPLOYEE_MAIN_BRANCH_ID;
}

function handleGet() {
    global $user;
    $id = $_GET['id'] ?? null;
    if ($id) {
        $emp = Database::fetch(
            "SELECT e.*, p.position_name, d.department_name, sd.sub_department_name, c.comp_name AS company_name, es.emp_stat_name, bb.branch_name
             FROM employees e
             LEFT JOIN position p ON e.job_title = p.pos_id
             LEFT JOIN department d ON e.b_department_id = d.dept_id
             LEFT JOIN sub_department sd ON e.b_sub_department_id = sd.sub_depart_id
             LEFT JOIN companies c ON e.b_company_id = c.comp_id
             LEFT JOIN employment_status es ON e.b_employment_status_id = es.emp_stat_id
             LEFT JOIN business_branches bb ON e.branch_id = bb.branch_id
             WHERE e.emp_id = :id",
            ['id' => (int)$id]
        );
        if ($emp) {
            enforceEmployeeBranchAccess($emp['branch_id'] !== null ? (int) $emp['branch_id'] : null);
        }
        echo json_encode($emp ? ['success' => true, 'data' => $emp] : ['success' => false, 'error' => 'Employee not found']);
        return;
    }
    $employeeWhere = [];
    $employeeParams = [];
    PosAccess::applyBranchScope($employeeWhere, $employeeParams, 'e.branch_id', $user, 'employee_api_branch');
    // Always include the main branch employees so legacy records are visible to all users.
    if ($employeeWhere) {
        $employeeWhere[0] = '(' . $employeeWhere[0] . ' OR e.branch_id = ' . EMPLOYEE_MAIN_BRANCH_ID . ')';
    }
    $employeeFilter = $employeeWhere ? 'WHERE ' . implode(' AND ', $employeeWhere) : '';
    $employees = Database::fetchAll(
        "SELECT e.*, p.position_name, d.department_name, sd.sub_department_name, c.comp_name AS company_name, es.emp_stat_name, bb.branch_name
         FROM employees e
         LEFT JOIN position p ON e.job_title = p.pos_id
         LEFT JOIN department d ON e.b_department_id = d.dept_id
         LEFT JOIN sub_department sd ON e.b_sub_department_id = sd.sub_depart_id
         LEFT JOIN companies c ON e.b_company_id = c.comp_id
         LEFT JOIN employment_status es ON e.b_employment_status_id = es.emp_stat_id
         LEFT JOIN business_branches bb ON e.branch_id = bb.branch_id
         {$employeeFilter}
         ORDER BY e.first_name, e.last_name",
        $employeeParams
    );
    echo json_encode(['success' => true, 'data' => ['employees' => $employees]]);
}

function handlePost() {
    global $user, $userRoleCode;
    $canCreate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('CREATE_EMPLOYEE');
    if (!$canCreate) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['first_name', 'b_date', 'b_cont_no', 'b_email', 'b_address', 'date_hired', 'daily_rate', 'job_title', 'b_department_id', 'b_company_id', 'b_employment_status_id'];
    foreach ($required as $field) {
        if (empty($input[$field])) { echo json_encode(['success' => false, 'error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']); return; }
    }
    $branchId = defaultEmployeeBranch(!empty($input['branch_id']) ? (int) $input['branch_id'] : null);
    enforceEmployeeBranchAccess($branchId);
    $params = [
        'first_name' => trim($input['first_name']),
        'last_name' => trim($input['last_name'] ?? ''),
        'middle_name' => trim($input['middle_name'] ?? ''),
        'b_date' => $input['b_date'],
        'b_permanent_address' => trim($input['b_permanent_address'] ?? ($input['b_address'] ?? '')),
        'b_address' => trim($input['b_address'] ?? ''),
        'emp_street_address' => trim($input['emp_street_address'] ?? ''),
        'emp_province_code' => !empty($input['emp_province_code']) ? $input['emp_province_code'] : null,
        'emp_city_code' => !empty($input['emp_city_code']) ? $input['emp_city_code'] : null,
        'emp_barangay_code' => !empty($input['emp_barangay_code']) ? $input['emp_barangay_code'] : null,
        'b_cont_no' => trim($input['b_cont_no']),
        'b_email' => trim($input['b_email']),
        'b_citizenship' => trim($input['b_citizenship'] ?? 'Filipino'),
        'b_placebirth' => trim($input['b_placebirth'] ?? ''),
        'b_religion' => trim($input['b_religion'] ?? ''),
        'b_sex' => trim($input['b_sex'] ?? ''),
        'b_civil_status' => trim($input['b_civil_status'] ?? ''),
        'b_height' => trim($input['b_height'] ?? ''),
        'b_weight' => trim($input['b_weight'] ?? ''),
        'job_title' => (int)$input['job_title'],
        'date_hired' => $input['date_hired'],
        'daily_rate' => (int)$input['daily_rate'],
        'cola' => (int)($input['cola'] ?? 0),
        'b_department_id' => (int)$input['b_department_id'],
        'b_sub_department_id' => (int)($input['b_sub_department_id'] ?? 0),
        'b_company_id' => (int)$input['b_company_id'],
        'b_employment_status_id' => (int)$input['b_employment_status_id'],
        'b_philhealth' => trim($input['b_philhealth'] ?? ''),
        'b_sss' => trim($input['b_sss'] ?? ''),
        'b_pagibig' => trim($input['b_pagibig'] ?? ''),
        'b_tinnumber' => trim($input['b_tinnumber'] ?? ''),
        'emergency_contact_name' => trim($input['emergency_contact_name'] ?? ''),
        'emergency_contact_relationship' => trim($input['emergency_contact_relationship'] ?? ''),
        'emergency_contact_number' => trim($input['emergency_contact_number'] ?? ''),
        'remarks' => trim($input['remarks'] ?? ''),
        'branch_id' => $branchId,
        'user_img' => 'male.jpg',
        'type' => '',
        'notifications' => 0,
        'b_addedby' => $user['user_id'] ?? 1,
        'employment_remarks' => trim($input['employment_remarks'] ?? '')
    ];
    $sql = "INSERT INTO employees (first_name, last_name, middle_name, b_date, b_permanent_address, b_address, emp_street_address, emp_province_code, emp_city_code, emp_barangay_code, b_cont_no, b_email, b_citizenship, b_placebirth, b_religion, b_sex, b_civil_status, b_height, b_weight, job_title, date_hired, daily_rate, cola, b_department_id, b_sub_department_id, b_company_id, b_employment_status_id, b_philhealth, b_sss, b_pagibig, b_tinnumber, emergency_contact_name, emergency_contact_relationship, emergency_contact_number, remarks, branch_id, user_img, type, notifications, b_addedby, b_dateadded, employment_remarks)
            VALUES (:first_name, :last_name, :middle_name, :b_date, :b_permanent_address, :b_address, :emp_street_address, :emp_province_code, :emp_city_code, :emp_barangay_code, :b_cont_no, :b_email, :b_citizenship, :b_placebirth, :b_religion, :b_sex, :b_civil_status, :b_height, :b_weight, :job_title, :date_hired, :daily_rate, :cola, :b_department_id, :b_sub_department_id, :b_company_id, :b_employment_status_id, :b_philhealth, :b_sss, :b_pagibig, :b_tinnumber, :emergency_contact_name, :emergency_contact_relationship, :emergency_contact_number, :remarks, :branch_id, :user_img, :type, :notifications, :b_addedby, NOW(), :employment_remarks)";
    Database::execute($sql, $params);
    echo json_encode(['success' => true, 'message' => 'Employee created successfully', 'emp_id' => Database::connection()->lastInsertId()]);
}

function handlePut() {
    global $user, $userRoleCode;
    $canUpdate = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('UPDATE_EMPLOYEE');
    if (!$canUpdate) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['emp_id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing employee ID']); return; }
    $currentEmployee = Database::fetch("SELECT branch_id FROM employees WHERE emp_id = :id", ['id' => (int) $id]);
    if (!$currentEmployee) { echo json_encode(['success' => false, 'error' => 'Employee not found']); return; }
    enforceEmployeeBranchAccess($currentEmployee['branch_id'] !== null ? (int) $currentEmployee['branch_id'] : null);
    $branchId = defaultEmployeeBranch(
        array_key_exists('branch_id', $input) && $input['branch_id'] !== ''
            ? (int) $input['branch_id']
            : (array_key_exists('branch_id', $input) ? null : ($currentEmployee['branch_id'] !== null ? (int) $currentEmployee['branch_id'] : null))
    );
    enforceEmployeeBranchAccess($branchId);
    $fields = [];
    $params = ['id' => (int)$id];
    $mappings = [
        'first_name' => 'first_name', 'last_name' => 'last_name', 'middle_name' => 'middle_name', 'b_date' => 'b_date',
        'b_address' => 'b_address', 'b_permanent_address' => 'b_permanent_address', 'emp_street_address' => 'emp_street_address',
        'b_cont_no' => 'b_cont_no', 'b_email' => 'b_email',
        'b_citizenship' => 'b_citizenship', 'b_placebirth' => 'b_placebirth', 'b_religion' => 'b_religion', 'b_sex' => 'b_sex',
        'b_civil_status' => 'b_civil_status', 'b_height' => 'b_height', 'b_weight' => 'b_weight', 'date_hired' => 'date_hired',
        'b_philhealth' => 'b_philhealth', 'b_sss' => 'b_sss', 'b_pagibig' => 'b_pagibig', 'b_tinnumber' => 'b_tinnumber',
        'emergency_contact_name' => 'emergency_contact_name', 'emergency_contact_relationship' => 'emergency_contact_relationship',
        'emergency_contact_number' => 'emergency_contact_number', 'remarks' => 'remarks', 'employment_remarks' => 'employment_remarks'
    ];
    foreach ($mappings as $in => $db) {
        if (isset($input[$in])) {
            $fields[] = "$db = :$db";
            $params[$db] = trim($input[$in]);
        }
    }
    if (isset($input['job_title'])) { $fields[] = "job_title = :job_title"; $params['job_title'] = (int)$input['job_title']; }
    if (isset($input['daily_rate'])) { $fields[] = "daily_rate = :daily_rate"; $params['daily_rate'] = (int)$input['daily_rate']; }
    if (isset($input['cola'])) { $fields[] = "cola = :cola"; $params['cola'] = (int)$input['cola']; }
    if (isset($input['b_department_id'])) { $fields[] = "b_department_id = :b_department_id"; $params['b_department_id'] = (int)$input['b_department_id']; }
    if (isset($input['b_sub_department_id'])) { $fields[] = "b_sub_department_id = :b_sub_department_id"; $params['b_sub_department_id'] = (int)$input['b_sub_department_id']; }
    if (isset($input['b_company_id'])) { $fields[] = "b_company_id = :b_company_id"; $params['b_company_id'] = (int)$input['b_company_id']; }
    if (isset($input['b_employment_status_id'])) { $fields[] = "b_employment_status_id = :b_employment_status_id"; $params['b_employment_status_id'] = (int)$input['b_employment_status_id']; }
    if (isset($input['branch_id'])) { $fields[] = "branch_id = :branch_id"; $params['branch_id'] = $branchId; }
    if (isset($input['emp_province_code'])) { $fields[] = "emp_province_code = :emp_province_code"; $params['emp_province_code'] = !empty($input['emp_province_code']) ? $input['emp_province_code'] : null; }
    if (isset($input['emp_city_code'])) { $fields[] = "emp_city_code = :emp_city_code"; $params['emp_city_code'] = !empty($input['emp_city_code']) ? $input['emp_city_code'] : null; }
    if (isset($input['emp_barangay_code'])) { $fields[] = "emp_barangay_code = :emp_barangay_code"; $params['emp_barangay_code'] = !empty($input['emp_barangay_code']) ? $input['emp_barangay_code'] : null; }
    if (empty($fields)) { echo json_encode(['success' => false, 'error' => 'No fields to update']); return; }
    $sql = "UPDATE employees SET " . implode(', ', $fields) . " WHERE emp_id = :id";
    Database::execute($sql, $params);
    echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
}

function handleDelete() {
    global $user, $userRoleCode;
    $canDelete = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('DELETE_EMPLOYEE');
    if (!$canDelete) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied']); exit; }
    $id = $_GET['id'] ?? null;
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing employee ID']); return; }
    $currentEmployee = Database::fetch("SELECT branch_id FROM employees WHERE emp_id = :id", ['id' => (int) $id]);
    if (!$currentEmployee) { echo json_encode(['success' => false, 'error' => 'Employee not found']); return; }
    enforceEmployeeBranchAccess($currentEmployee['branch_id'] !== null ? (int) $currentEmployee['branch_id'] : null);
    Database::execute("DELETE FROM employees WHERE emp_id = :id", ['id' => (int)$id]);
    echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
}
