<?php
/**
 * Provider Wallets Controller
 * Displays provider wallets interface with list, search, and filters
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

// Get current user
$user = Auth::user();
$userBranchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

// Check if user can create wallets
$canCreateWallet = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('CREATE_WALLET');

// Get all providers and branches for "Add Wallet" dropdown (not for filter)
$allProviders = Database::fetchAll(
    "SELECT provider_id, provider_name FROM ticket_providers WHERE status = 'active' ORDER BY provider_name"
);

$allBranches = Database::fetchAll(
    "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
);

// Get filter values from GET parameters (like shifts page)
$filterProvider = $_GET['provider'] ?? '';
$filterBranch = $_GET['branch'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build WHERE clauses for filtering
$whereConditions = [];
$params = [];

// User branch restriction (for non-SUPER_ADMIN)
if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $branchIds = explode(',', $userBranchId);
    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
    $whereConditions[] = "pw.branch_id IN ($placeholders)";
    $params = $branchIds;
}

// Provider filter
if ($filterProvider) {
    $whereConditions[] = "tp.provider_name = ?";
    $params[] = $filterProvider;
}

// Branch filter (only apply if user is SUPER_ADMIN or has access to this branch)
if ($filterBranch && ($userRoleCode === 'SUPER_ADMIN' || in_array($filterBranch, explode(',', $userBranchId ?? '')))) {
    // Check if we already have branch restriction
    if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        // Branch already restricted by user access, no additional filter needed
    } else {
        $whereConditions[] = "bb.branch_name = ?";
        $params[] = $filterBranch;
    }
}

// Status filter
if ($filterStatus) {
    $whereConditions[] = "pw.status = ?";
    $params[] = $filterStatus;
}

// Build WHERE clause
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get filtered wallets
$wallets = Database::fetchAll(
    "SELECT pw.*,
            tp.provider_name,
            bb.branch_name,
            CONCAT(tp.provider_name, ' - ', bb.branch_name) as wallet_name
     FROM provider_wallets pw
     LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
     LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
     $whereClause
     ORDER BY tp.provider_name, bb.branch_name",
    $params
);

// Derive filter providers and branches from the wallets the user can access
$filterProviders = [];
$filterBranches = [];
$providerMap = [];
$branchMap = [];

// Get base wallets for filter options (without filters applied, but with user restrictions)
$baseWhere = $userRoleCode !== 'SUPER_ADMIN' && $userBranchId 
    ? 'WHERE pw.branch_id IN (' . implode(',', array_fill(0, count(explode(',', $userBranchId)), '?')) . ')'
    : '';
$baseParams = $userRoleCode !== 'SUPER_ADMIN' && $userBranchId ? explode(',', $userBranchId) : [];

$baseWallets = Database::fetchAll(
    "SELECT DISTINCT tp.provider_name, bb.branch_name, pw.provider_id, pw.branch_id
     FROM provider_wallets pw
     LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
     LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
     $baseWhere",
    $baseParams
);

foreach ($baseWallets as $wallet) {
    if (!empty($wallet['provider_name'])) {
        $providerMap[$wallet['provider_name']] = [
            'provider_id' => $wallet['provider_id'],
            'provider_name' => $wallet['provider_name']
        ];
    }
    if (!empty($wallet['branch_name'])) {
        $branchMap[$wallet['branch_name']] = [
            'branch_id' => $wallet['branch_id'],
            'branch_name' => $wallet['branch_name']
        ];
    }
}

ksort($providerMap);
ksort($branchMap);
$filterProviders = array_values($providerMap);
$filterBranches = array_values($branchMap);

// Pass filter values to view
$viewData = [
    'wallets' => $wallets,
    'filterProviders' => $filterProviders,
    'filterBranches' => $filterBranches,
    'allProviders' => $allProviders,
    'allBranches' => $allBranches,
    'canCreateWallet' => $canCreateWallet,
    'filterProvider' => $filterProvider,
    'filterBranch' => $filterBranch,
    'filterStatus' => $filterStatus
];

extract($viewData);
include __DIR__ . '/views/index.php';
