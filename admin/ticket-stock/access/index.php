<?php
require_once dirname(dirname(__DIR__)) . '/_guard.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/TicketStockAccess.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('MANAGE_TICKET_STOCK_ACCESS')) {
    $message = 'You do not have permission to manage Ticket Stock Access.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$canManageAllBranches = TicketStockAccess::canManageAllBranches($user);
$branchWhere = ["status = 'active'"];
$branchParams = [];
if (!$canManageAllBranches) {
    PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'ticket_stock_access_branch');
}
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);

$users = Database::fetchAll(
    "SELECT ua.user_id, ua.user_code, ua.username, ua.branch_id,
            ur.role_code, ur.role_name,
            CONCAT_WS(' ', e.first_name, e.last_name) AS full_name,
            GROUP_CONCAT(DISTINCT b.branch_name ORDER BY b.branch_name SEPARATOR ', ') AS assigned_branch_names
     FROM user_accounts ua
     LEFT JOIN user_roles ur ON ur.role_id = ua.role_id
     LEFT JOIN employees e ON e.emp_id = ua.emp_id
     LEFT JOIN business_branches b ON FIND_IN_SET(b.branch_id, ua.branch_id)
     WHERE ua.status = 'active'
     GROUP BY ua.user_id
     ORDER BY COALESCE(NULLIF(CONCAT_WS(' ', e.first_name, e.last_name), ''), ua.username), ua.username"
);

include __DIR__ . '/views/index.php';
