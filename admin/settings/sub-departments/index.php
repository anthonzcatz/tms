<?php
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
} elseif (!Auth::canAccessModule('admin/settings/sub-departments/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$subDepartments = Database::fetchAll(
    "SELECT sd.*, d.department_name AS main_department_name,
            ua.username AS added_by_name
     FROM sub_department sd
     LEFT JOIN department d ON sd.main_department_id = d.dept_id
     LEFT JOIN user_accounts ua ON sd.sub_depart_addedby = ua.user_id
     ORDER BY d.department_name, sd.sub_department_name"
);

$departments = Database::fetchAll("SELECT dept_id, department_name FROM department ORDER BY department_name");

include __DIR__ . '/views/index.php';
