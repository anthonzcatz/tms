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
} elseif (!Auth::canAccessModule('admin/settings/employment-status/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$employmentStatuses = Database::fetchAll(
    "SELECT es.*, ua.username AS added_by_name,
            (SELECT COUNT(*) FROM employees e WHERE e.b_employment_status_id = es.emp_stat_id) AS employee_count
     FROM employment_status es LEFT JOIN user_accounts ua ON es.emp_stat_addedby = ua.user_id ORDER BY es.emp_stat_name"
);

include __DIR__ . '/views/index.php';
