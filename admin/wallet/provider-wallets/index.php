<?php
/**
 * Provider Wallets Controller
 * Displays provider wallets interface with list, search, and filters
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PusherService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Get current user
$user = Auth::user();
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$allowedBranchIds = PosAccess::allowedBranchIds($user);

// Check if user can create wallets
$canCreateWallet = ($userRoleCode === 'SUPER_ADMIN') || Auth::can('CREATE_WALLET');

// Get all providers and branches for "Add Wallet" dropdown (only top-level providers)
$allProviders = Database::fetchAll(
    "SELECT tp.provider_id,
            tp.provider_code,
            tp.provider_name,
            tp.provider_type,
            (
                SELECT COUNT(*)
                FROM provider_ticket_variants v
                WHERE v.provider_id = tp.provider_id
                  AND v.deleted_at IS NULL
                  AND v.is_active = 1
            ) AS variant_count,
            (
                SELECT COUNT(*)
                FROM ticket_providers sp
                WHERE sp.parent_provider_id = tp.provider_id
            ) AS sub_provider_count
     FROM ticket_providers tp
     WHERE tp.status = 'active'
       AND tp.parent_provider_id IS NULL
     ORDER BY tp.provider_name"
);

// Get all branches for "Add Wallet" dropdown based on user access
$branchDropdownWhere = ["status = 'active'"];
$branchDropdownParams = [];
PosAccess::applyBranchScope($branchDropdownWhere, $branchDropdownParams, 'branch_id', $user, 'wallet_dropdown_branch');
$allBranches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchDropdownWhere) . "
     ORDER BY branch_name",
    $branchDropdownParams
);

// System settings for print layout
$systemSettings = Database::fetch("SELECT system_name, system_logo, company_name, company_address, company_contact_number, company_email, company_tin FROM system_settings WHERE setting_id = 1");

// Get filter values from GET parameters (like shifts page)
$filterProvider = $_GET['provider'] ?? '';
$filterBranch = $_GET['branch'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build WHERE clauses for filtering
$whereConditions = [];
$params = [];

// User branch restriction (for non-SUPER_ADMIN)
PosAccess::applyBranchScope($whereConditions, $params, 'pw.branch_id', $user, 'wallet_scope_branch');

// Provider filter
if ($filterProvider) {
    $whereConditions[] = "tp.provider_name = :provider_filter";
    $params['provider_filter'] = $filterProvider;
}

// Apply the requested branch filter after the user's access scope.
if ($filterBranch) {
    $whereConditions[] = "bb.branch_name = :branch_filter";
    $params['branch_filter'] = $filterBranch;
}

// Only main/standalone providers have wallets; sub-providers share the main provider wallet
$whereConditions[] = "tp.parent_provider_id IS NULL";

// Status filter
if ($filterStatus) {
    $whereConditions[] = "pw.status = :status_filter";
    $params['status_filter'] = $filterStatus;
}

// Build WHERE clause
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get filtered wallets
$wallets = Database::fetchAll(
    "SELECT pw.*,
            tp.provider_name,
            tp.provider_type,
            tp.parent_provider_id,
            ptp.provider_name as parent_provider_name,
            bb.branch_name,
            pv.variant_id as variant_id,
            pv.variant_code as variant_code,
            pv.variant_name as variant_name,
            pv.display_color as variant_color,
            CONCAT(tp.provider_name,
                   IF(pv.variant_name IS NOT NULL, CONCAT(' - ', pv.variant_name), '')) as wallet_name,
            (
                SELECT COALESCE(SUM(bts.on_hand_qty), 0)
                FROM branch_ticket_stocks bts
                WHERE bts.branch_id = pw.branch_id
                  AND bts.provider_id = pw.provider_id
                  AND bts.variant_id = pw.variant_id
            ) AS on_hand_qty,
            (
                SELECT GROUP_CONCAT(child.provider_name ORDER BY child.provider_name SEPARATOR ', ')
                FROM ticket_providers child
                WHERE child.parent_provider_id = pw.provider_id
                AND child.status = 'active'
            ) AS child_provider_names,
            (
                SELECT COUNT(*)
                FROM provider_ticket_variants v
                WHERE v.provider_id = pw.provider_id
                  AND v.deleted_at IS NULL
            ) AS variant_count
     FROM provider_wallets pw
     LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
     LEFT JOIN ticket_providers ptp ON tp.parent_provider_id = ptp.provider_id
     LEFT JOIN business_branches bb ON pw.branch_id = bb.branch_id
     LEFT JOIN provider_ticket_variants pv ON pw.variant_id = pv.variant_id
     $whereClause
     ORDER BY tp.provider_name, pv.variant_name, bb.branch_name",
    $params
);

// Keep monetary provider wallets visible alongside variant stock wallets.
// Their balances belong to separate domains and must not be merged or hidden.

// Derive filter providers and branches from the wallets the user can access
$filterProviders = [];
$filterBranches = [];
$providerMap = [];
$branchMap = [];

// Get base wallets for filter options (without filters applied, but with user restrictions)
$baseWhereConditions = [];
$baseParams = [];
PosAccess::applyBranchScope($baseWhereConditions, $baseParams, 'pw.branch_id', $user, 'wallet_filter_branch');
$baseWhere = $baseWhereConditions ? 'WHERE ' . implode(' AND ', $baseWhereConditions) : '';

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

// Build provider type labels, icons, and badge colors from provider_types table.
$providerTypeRows = Database::getProviderTypes();
$providerTypeOptions = [];
$providerTypeIcons = [];
foreach ($providerTypeRows as $row) {
    $providerTypeOptions[$row['type_code']] = $row['type_label'];
    $providerTypeIcons[$row['type_code']] = $row['type_icon'] ?: 'fa-circle';
}

$badgePalette = ['bg-primary', 'bg-info', 'bg-warning text-dark', 'bg-success', 'bg-danger', 'bg-dark', 'bg-secondary'];
$providerTypeColors = [];
foreach (array_keys($providerTypeOptions) as $index => $typeValue) {
    $providerTypeColors[$typeValue] = $badgePalette[$index % count($badgePalette)];
}

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
    'filterStatus' => $filterStatus,
    'systemSettings' => $systemSettings,
    'pusherConfigured' => PusherService::isConfigured(),
    'pusherKey' => PusherService::isConfigured() ? env('PUSHER_KEY', '') : '',
    'pusherCluster' => PusherService::isConfigured() ? env('PUSHER_CLUSTER', 'ap1') : 'ap1',
    'providerTypeOptions' => $providerTypeOptions,
    'providerTypeColors' => $providerTypeColors,
    'providerTypeIcons' => $providerTypeIcons
];

extract($viewData);
include __DIR__ . '/views/index.php';
