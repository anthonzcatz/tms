<?php
/**
 * Wallet Transactions Controller
 * Displays wallet transactions interface with list, search, and filters
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Require login and permission
Auth::requireLogin();
// SUPER_ADMIN has access to everything
$user = Auth::user();
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/wallet/wallet-transactions/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Get current user
$user = Auth::user();
$userBranchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

// SUPER_ADMIN can see all wallets, others are restricted to their branch
$branchFilter = "";
$params = [];

if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $branchFilter = "WHERE pw.branch_id = :branch_id";
    $params['branch_id'] = $userBranchId;
}

// Get all wallets for dropdown (filtered by user's branch if not SUPER_ADMIN)
$wallets = Database::fetchAll(
    "SELECT pw.wallet_id,
            tp.provider_name,
            bb.branch_name,
            CONCAT(tp.provider_name, ' - ', bb.branch_name) as wallet_name
     FROM provider_wallets pw
     LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
     LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
     $branchFilter
     ORDER BY tp.provider_name, bb.branch_name",
    $params
);

// Include the main view
include __DIR__ . '/views/index.php';
