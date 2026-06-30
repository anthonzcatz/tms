<?php
/**
 * Organization Setup Hub Controller
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();
$user = Auth::user();

if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (
    !Auth::canAccessModule('admin/settings/departments/') &&
    !Auth::canAccessModule('admin/settings/sub-departments/') &&
    !Auth::canAccessModule('admin/settings/positions/') &&
    !Auth::canAccessModule('admin/settings/employment-status/') &&
    !Auth::canAccessModule('admin/settings/employees/') &&
    !Auth::canAccessModule('admin/settings/companies/')
) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$counts = Database::fetch(
    "SELECT
        (SELECT COUNT(*) FROM companies) AS company_count,
        (SELECT COUNT(*) FROM department) AS department_count,
        (SELECT COUNT(*) FROM sub_department) AS sub_department_count,
        (SELECT COUNT(*) FROM position) AS position_count,
        (SELECT COUNT(*) FROM employment_status) AS employment_status_count,
        (SELECT COUNT(*) FROM employees) AS employee_count"
);

$departments = Database::fetchAll(
    "SELECT d.dept_id, d.department_name, d.status,
            (SELECT COUNT(*) FROM sub_department sd WHERE sd.main_department_id = d.dept_id) AS sub_department_count,
            (SELECT COUNT(*) FROM employees e WHERE e.b_department_id = d.dept_id) AS employee_count
     FROM department d
     ORDER BY d.department_name"
);

include __DIR__ . '/views/index.php';
