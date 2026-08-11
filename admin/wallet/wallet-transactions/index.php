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
    $message = 'You do not have permission to access the Wallet Transactions module.';
    http_response_code(403);
    include dirname(dirname(dirname(__DIR__))) . '/admin/includes/access-denied.php';
    exit;
}

// Get current user
$user = Auth::user();
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$userRoleCode = $user['role_code'] ?? '';

// SUPER_ADMIN can see all wallets, others are restricted to their branch
$branchFilter = "";
$params = [];

if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $branchIds = array_filter(array_map('trim', explode(',', $userBranchId)));
    if (!empty($branchIds)) {
        $branchPlaceholders = [];
        foreach ($branchIds as $i => $branchId) {
            $branchPlaceholders[] = ':branch_' . $i;
            $params['branch_' . $i] = (int)$branchId;
        }
        $branchFilter = "WHERE pw.branch_id IN (" . implode(',', $branchPlaceholders) . ")";
    }
}

// Get all wallets for dropdown (filtered by user's branch if not SUPER_ADMIN)
$wallets = Database::fetchAll(
    "SELECT pw.wallet_id,
            pw.provider_id,
            pw.branch_id,
            pw.variant_id,
            pw.current_balance,
            tp.provider_name,
            ptp.provider_name as parent_provider_name,
            bb.branch_name,
            pv.variant_name,
            pv.display_color as variant_color,
            CONCAT(tp.provider_name,
                   IF(pv.variant_name IS NOT NULL, CONCAT(' - ', pv.variant_name), ''),
                   ' - ', bb.branch_name) as wallet_name,
            CASE
                WHEN pv.variant_name IS NOT NULL THEN CONCAT(tp.provider_name, ' - ', pv.variant_name, ' - ', bb.branch_name)
                WHEN ptp.provider_name IS NOT NULL THEN CONCAT(ptp.provider_name, ' - ', bb.branch_name)
                ELSE CONCAT(tp.provider_name, ' - ', bb.branch_name)
            END as add_wallet_name
     FROM provider_wallets pw
     LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
     LEFT JOIN ticket_providers ptp ON tp.parent_provider_id = ptp.provider_id
     LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
     LEFT JOIN provider_ticket_variants pv ON pw.variant_id = pv.variant_id
     $branchFilter
     ORDER BY tp.provider_name, pv.variant_name, bb.branch_name",
    $params
);

// Hide provider-level wallets for providers that also have variant wallets in the same branch
$hasVariantByProviderBranch = [];
foreach ($wallets as $wallet) {
    if (!empty($wallet['variant_id'])) {
        $key = $wallet['provider_id'] . '|' . $wallet['branch_id'];
        $hasVariantByProviderBranch[$key] = true;
    }
}

$wallets = array_values(array_filter($wallets, function ($wallet) use ($hasVariantByProviderBranch) {
    if (empty($wallet['variant_id'])) {
        $key = $wallet['provider_id'] . '|' . $wallet['branch_id'];
        return !isset($hasVariantByProviderBranch[$key]);
    }
    return true;
}));

$operatingProviders = Database::fetchAll(
    "SELECT provider_id, provider_name
     FROM ticket_providers
     WHERE status = 'active'
     ORDER BY provider_name"
);

// Include the main view
include __DIR__ . '/views/index.php';
