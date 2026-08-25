<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();
$user = Auth::user();

// Main branch ID that all users can view for employees (legacy/default branch).
const EMPLOYEE_MAIN_BRANCH_ID = 1;
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
if ($user && $userRoleCode === 'SUPER_ADMIN') {
} elseif (!Auth::canAccessModule('admin/settings/employees/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$employeeWhere = [];
$employeeParams = [];
PosAccess::applyBranchScope($employeeWhere, $employeeParams, 'e.branch_id', $user, 'employee_branch');
// Always include the main branch employees so legacy records are visible to all users.
if ($employeeWhere) {
    $employeeWhere[0] = '(' . $employeeWhere[0] . ' OR e.branch_id = ' . EMPLOYEE_MAIN_BRANCH_ID . ')';
}
$employeeFilter = $employeeWhere ? 'WHERE ' . implode(' AND ', $employeeWhere) : '';
$employeeSql = "SELECT e.*, p.position_name, d.department_name, sd.sub_department_name, c.comp_name AS company_name, es.emp_stat_name, bb.branch_name
     FROM employees e
     LEFT JOIN position p ON e.job_title = p.pos_id
     LEFT JOIN department d ON e.b_department_id = d.dept_id
     LEFT JOIN sub_department sd ON e.b_sub_department_id = sd.sub_depart_id
     LEFT JOIN companies c ON e.b_company_id = c.comp_id
     LEFT JOIN employment_status es ON e.b_employment_status_id = es.emp_stat_id
     LEFT JOIN business_branches bb ON e.branch_id = bb.branch_id
     {$employeeFilter}
     ORDER BY e.first_name, e.last_name";
$employees = Database::fetchAll($employeeSql, $employeeParams);

$positions = Database::fetchAll("SELECT pos_id, position_name FROM position ORDER BY position_name");
$departments = Database::fetchAll("SELECT dept_id, department_name FROM department ORDER BY department_name");
$subDepartments = Database::fetchAll("SELECT sub_depart_id, sub_department_name, main_department_id FROM sub_department ORDER BY sub_department_name");
$companies = Database::fetchAll("SELECT comp_id, comp_name FROM companies ORDER BY comp_name");
$employmentStatuses = Database::fetchAll("SELECT emp_stat_id, emp_stat_name FROM employment_status ORDER BY emp_stat_name");
$branchWhere = [];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'employee_dropdown_branch');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     " . ($branchWhere ? 'WHERE ' . implode(' AND ', $branchWhere) : '') . "
     ORDER BY branch_name",
    $branchParams
);

include __DIR__ . '/views/index.php';
