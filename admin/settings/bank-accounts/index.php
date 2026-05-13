<?php
/**
 * Bank Accounts Management Controller
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
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
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::can('VIEW_SETTINGS')) {
    $message = 'You do not have permission to access the Bank Accounts module.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(dirname(__DIR__))) . '/includes/access-denied.php';
    exit;
}

$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

// Fetch bank accounts with branch and payment method info
$branchFilter = '';
$params = [];
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $branchFilter = 'WHERE ba.branch_id = :branch_id';
    $params['branch_id'] = $userBranchId;
}

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
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
);
$paymentMethods = Database::fetchAll(
    "SELECT method_id, method_name, method_code, method_type
     FROM payment_methods
     WHERE is_active = 1 AND method_type IN ('BANK_TRANSFER','E_WALLET','OTHER')
     ORDER BY sort_order ASC, method_name ASC"
);

include __DIR__ . '/views/index.php';
