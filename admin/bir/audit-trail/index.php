<?php
/**
 * BIR Audit Trail Controller
 * View comprehensive audit logs
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/_guard.php';

$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

$allowedRoles = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'ACCOUNTANT'];
if (!in_array($userRoleCode, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/admin/bir/');
    exit;
}

// Fetch audit trail with filters
$actionFilter = $_GET['action'] ?? '';
$tableFilter = $_GET['table'] ?? '';
$userFilter = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$query = "
    SELECT at.*, u.username as user_name, po.order_code, bb.branch_name
    FROM bir_audit_trail at
    LEFT JOIN user_accounts u ON at.user_id = u.user_id
    LEFT JOIN pos_orders po ON at.order_id = po.order_id
    LEFT JOIN business_branches bb ON po.branch_id = bb.branch_id
    WHERE DATE(at.created_at) BETWEEN ? AND ?
";
$params = [$dateFrom, $dateTo];

if ($actionFilter) {
    $query .= " AND at.action = ?";
    $params[] = $actionFilter;
}
if ($tableFilter) {
    $query .= " AND at.table_name = ?";
    $params[] = $tableFilter;
}
if ($userFilter) {
    $query .= " AND at.user_id = ?";
    $params[] = $userFilter;
}

$query .= " ORDER BY at.created_at DESC LIMIT 200";

$auditTrail = Database::fetchAll($query, $params);

// Fetch users for filter
$users = Database::fetchAll("SELECT user_id, username FROM user_accounts WHERE status = 'active' ORDER BY username");

$viewData = ['auditTrail' => $auditTrail, 'users' => $users, 'filters' => compact('actionFilter', 'tableFilter', 'userFilter', 'dateFrom', 'dateTo'), 'userRoleCode' => $userRoleCode];
extract($viewData);
include __DIR__ . '/views/index.php';
