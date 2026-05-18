<?php
/**
 * Refund Confirmations Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
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
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$userRoleCode  = $user['role_code'] ?? '';
$userBranchId  = $user['branch_id'] ?? null;

// Dropdown data for filter selects
$allBranches = ($userRoleCode === 'SUPER_ADMIN')
    ? Database::fetchAll("SELECT branch_id, branch_name FROM business_branches ORDER BY branch_name")
    : [];

$walletBranchFilter = ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) ? "WHERE pw.branch_id = :bid" : "";
$walletBranchParams = ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) ? ['bid' => $userBranchId] : [];
$allWallets = Database::fetchAll(
    "SELECT pw.wallet_id, CONCAT(tp.provider_name, ' - ', bb.branch_name) as wallet_label
     FROM provider_wallets pw
     LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
     LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
     $walletBranchFilter
     ORDER BY tp.provider_name, bb.branch_name",
    $walletBranchParams
);

include __DIR__ . '/views/index.php';
