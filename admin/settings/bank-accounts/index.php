<?php
/**
 * Bank Accounts Management Controller
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();

$user = Auth::user();
// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/settings/bank-accounts/')) {
    $message = 'You do not have permission to access the Bank Accounts module.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);

// Fetch bank accounts with branch and payment method info
$accountWhere = [];
$params = [];
PosAccess::applyBranchScope($accountWhere, $params, 'ba.branch_id', $user, 'bank_account_branch');
$branchFilter = $accountWhere ? 'WHERE ' . implode(' AND ', $accountWhere) : '';


$accounts = Database::fetchAll(
    "SELECT ba.*,
            bb.branch_name,
            pm.method_name,
            pm.method_code,
            pm.method_type
     FROM bank_accounts ba
     LEFT JOIN business_branches bb ON ba.branch_id = bb.branch_id
     LEFT JOIN payment_methods pm ON ba.payment_method_id = pm.method_id
     $branchFilter
     ORDER BY bb.branch_name ASC, ba.bank_name ASC"
, $params);

// Fetch branches and payment methods for dropdowns
$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'bank_account_dropdown_branch');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);
$paymentMethods = Database::fetchAll(
    "SELECT method_id, method_name, method_code, method_type
     FROM payment_methods
     WHERE is_active = 1 AND method_type IN ('BANK_TRANSFER','E_WALLET','OTHER')
     ORDER BY sort_order ASC, method_name ASC"
);

include __DIR__ . '/views/index.php';
