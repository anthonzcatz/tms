<?php
/**
 * Refund Confirmations Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

require_once dirname(__DIR__) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();

$user = Auth::user();
// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/refund-confirmations/')) {
    $message = 'You do not have permission to access Refund Confirmations.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}

$userRoleCode  = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$userBranchId  = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$allowedBranchIds = PosAccess::allowedBranchIds($user);
$realtimeBranchIds = $allowedBranchIds === null
    ? array_map('intval', array_column(Database::fetchAll("SELECT branch_id FROM business_branches WHERE status = 'active'"), 'branch_id'))
    : $allowedBranchIds;
$pusherConfigured = PusherService::isConfigured();
$pusherKey = $pusherConfigured ? env('PUSHER_KEY', '') : '';
$pusherCluster = $pusherConfigured ? env('PUSHER_CLUSTER', 'ap1') : 'ap1';

// Dropdown data for filter selects
$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'refund_dropdown_branch');
$allBranches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);

$walletWhere = [];
$walletParams = [];
PosAccess::applyBranchScope($walletWhere, $walletParams, 'pw.branch_id', $user, 'refund_wallet_branch');
$walletBranchFilter = $walletWhere ? 'WHERE ' . implode(' AND ', $walletWhere) : '';
$allWallets = Database::fetchAll(
    "SELECT pw.wallet_id, CONCAT(tp.provider_name, ' - ', bb.branch_name) as wallet_label
     FROM provider_wallets pw
     LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
     LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
     $walletBranchFilter
     ORDER BY tp.provider_name, bb.branch_name",
    $walletParams
);

include __DIR__ . '/views/index.php';
