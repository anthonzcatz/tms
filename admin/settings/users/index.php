<?php
/**
 * User Management Controller
 * Displays user management interface with list, search, and filters
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Require login and permission
Auth::requireLogin();
// SUPER_ADMIN has access to everything
$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
if ($user && $userRoleCode === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/settings/users/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Check if user is SUPER_ADMIN
$currentUser = Auth::user();
$isSuperAdmin = ($userRoleCode === 'SUPER_ADMIN');

// Get all roles for dropdown
$roles = Database::fetchAll(
    "SELECT role_id, role_name, role_code 
     FROM user_roles 
     ORDER BY role_name"
);

// Get all branches for dropdown
$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'user_branch_dropdown');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);

// Get all ticket providers for cashier assignments (filtered by supported transport types)
$allowedTypes = CashierTransportAccess::getSupportedTransportTypes();
$typePlaceholders = [];
$typeParams = [];
foreach ($allowedTypes as $index => $type) {
    $key = 'provider_type_' . $index;
    $typePlaceholders[] = ':' . $key;
    $typeParams[$key] = $type;
}
$providers = Database::fetchAll(
    "SELECT tp.provider_id, tp.provider_code, tp.provider_name, tp.provider_type,
            tp.parent_provider_id, ptp.provider_name as parent_provider_name
     FROM ticket_providers tp
     LEFT JOIN ticket_providers ptp ON tp.parent_provider_id = ptp.provider_id
     WHERE tp.status = 'active'
       AND tp.provider_type IN (" . implode(',', $typePlaceholders) . ")
     ORDER BY tp.provider_type, tp.provider_name",
    $typeParams
);

// Get existing cashier transport assignments
$transportAssignments = Database::fetchAll(
    "SELECT cta.user_id, cta.provider_id, cta.transport_type, 
            tp.provider_name, tp.provider_code
     FROM cashier_transport_assignments cta
     LEFT JOIN ticket_providers tp ON cta.provider_id = tp.provider_id
     ORDER BY cta.user_id"
);

// Group assignments by user_id for easier access
$assignmentsByUser = [];
foreach ($transportAssignments as $assignment) {
    $assignmentsByUser[$assignment['user_id']][] = $assignment;
}

// Future-ready labels and icons for the supported transport/provider types.
$supportedTypes = $allowedTypes;
$providerTypeOptions = [];
$providerTypeIcons = [];
foreach ($allowedTypes as $type) {
    $providerTypeOptions[$type] = $type;
    $providerTypeIcons[$type] = 'fa-circle';
}
foreach (Database::getProviderTypes() as $row) {
    $providerTypeOptions[$row['type_code']] = $row['type_label'];
    $providerTypeIcons[$row['type_code']] = $row['type_icon'] ?: 'fa-circle';
}

// Include the main view
include __DIR__ . '/views/index.php';
