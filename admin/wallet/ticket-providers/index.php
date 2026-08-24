<?php
/**
 * Ticket Providers Controller
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

// Check permission with proper access-denied page
$user = Auth::user();
// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::can('VIEW_TICKET_PROVIDERS') && !Auth::canAccessModule('admin/wallet/ticket-providers/')) {
    $message = 'You do not have permission to access the Ticket Providers module.';
    include dirname(dirname(dirname(__DIR__))) . '/admin/includes/access-denied.php';
    exit;
}

// Get current user
$user = Auth::user();

// Fetch all providers with main/sub-provider info, variant counts and sub-provider counts
$sql = "SELECT tp.*, ptp.provider_name as parent_provider_name, ptp.provider_code as parent_provider_code,
            (SELECT COUNT(*)
             FROM provider_ticket_variants v
             WHERE v.provider_id = tp.provider_id
               AND v.deleted_at IS NULL) AS variant_count,
            (SELECT COUNT(*)
             FROM ticket_providers sp
             WHERE sp.parent_provider_id = tp.provider_id) AS sub_provider_count
        FROM ticket_providers tp
        LEFT JOIN ticket_providers ptp ON tp.parent_provider_id = ptp.provider_id
        ORDER BY COALESCE(ptp.provider_name, tp.provider_name), tp.parent_provider_id IS NOT NULL ASC, tp.provider_name";
$providers = Database::fetchAll($sql);

// Build filter dropdown data from the database enum so new types appear
// immediately when the `provider_type` enum is extended.
$providerTypeValues = Database::getEnumValues('ticket_providers', 'provider_type');
$providerTypeOptions = [];
foreach ($providerTypeValues as $typeValue) {
    $providerTypeOptions[$typeValue] = ucwords(str_replace('_', ' ', $typeValue));
}

$types = array_keys($providerTypeOptions);

// Deterministic badge color map for provider types.
$badgePalette = ['bg-primary', 'bg-info', 'bg-warning text-dark', 'bg-success', 'bg-danger', 'bg-dark', 'bg-secondary'];
$providerTypeColors = [];
$sortedTypeValues = array_keys($providerTypeOptions);
foreach ($sortedTypeValues as $index => $typeValue) {
    $providerTypeColors[$typeValue] = $badgePalette[$index % count($badgePalette)];
}

$mainProviders = [];
foreach ($providers as $provider) {
    if (!empty($provider['parent_provider_id']) && !empty($provider['parent_provider_name'])) {
        $mainProviders[$provider['parent_provider_id']] = $provider['parent_provider_name'];
    }
}
asort($mainProviders);

// Include the main view
include __DIR__ . '/views/index.php';
