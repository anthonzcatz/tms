<?php
require_once dirname(dirname(__DIR__)) . '/_guard.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PusherService.php';

$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
if ($userRoleCode !== 'SUPER_ADMIN'
    && !Auth::can('VIEW_TICKET_STOCK_BALANCES')
    && !Auth::canAccessModule('admin/ticket-stock/balances/')) {
    $message = 'You do not have permission to access Ticket Stock Balances.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$allowedBranchIds = PosAccess::allowedBranchIds($user);
$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'balance_branch');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);
$providers = Database::fetchAll("SELECT provider_id, provider_code, provider_name FROM ticket_providers WHERE status = 'active' ORDER BY provider_name");
$variants = Database::fetchAll("SELECT variant_id, provider_id, variant_code, variant_name FROM provider_ticket_variants WHERE is_active = 1 AND deleted_at IS NULL ORDER BY variant_name");

$realtimeBranchIds = $allowedBranchIds === null
    ? array_map('intval', array_column(Database::fetchAll("SELECT branch_id FROM business_branches WHERE status = 'active'"), 'branch_id'))
    : $allowedBranchIds;
$realtimeBranchIds = array_values(array_unique(array_filter($realtimeBranchIds)));
$pusherConfigured = PusherService::isConfigured();
$pusherKey = $pusherConfigured ? env('PUSHER_KEY', '') : '';
$pusherCluster = $pusherConfigured ? env('PUSHER_CLUSTER', 'ap1') : 'ap1';

include __DIR__ . '/views/index.php';
