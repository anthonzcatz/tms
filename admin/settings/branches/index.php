<?php
/**
 * Branch Management Controller
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

// Check permission with proper access-denied page
$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
// SUPER_ADMIN has access to everything
if ($user && $userRoleCode === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::canAccessModule('admin/settings/branches/')) {
    $message = 'You do not have permission to access the Branch Management module.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Get current user
$user = Auth::user();
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');

// Fetch branches based on user role
$branchWhere = [];
$params = [];
PosAccess::applyBranchScope($branchWhere, $params, 'bb.branch_id', $user, 'branch_management');
$branchFilter = $branchWhere ? 'WHERE ' . implode(' AND ', $branchWhere) : '';

$sql = "SELECT bb.*
    FROM business_branches bb
    $branchFilter
    ORDER BY bb.branch_name";

$branches = Database::fetchAll($sql, $params);

// Include the main view
include __DIR__ . '/views/index.php';
