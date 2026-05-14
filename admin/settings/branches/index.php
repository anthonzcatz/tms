<?php
/**
 * Branch Management Controller
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
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
// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::canAccessModule('admin/settings/branches/')) {
    $message = 'You do not have permission to access the Branch Management module.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Get current user
$user = Auth::user();
$userBranchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

// Fetch branches based on user role
$branchFilter = "";
$params = [];

if ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
    $branchFilter = "WHERE bb.branch_id = :user_branch_id";
    $params['user_branch_id'] = $userBranchId;
}

$sql = "SELECT bb.*,
           r.region_name,
           p.province_name,
           c.city_municipality_name,
           b.barangay_name
    FROM business_branches bb
    LEFT JOIN psgc_regions r ON bb.region_code = r.region_code
    LEFT JOIN psgc_provinces p ON bb.province_code = p.province_code
    LEFT JOIN psgc_cities_municipalities c ON bb.city_municipality_code = c.city_municipality_code
    LEFT JOIN psgc_barangays b ON bb.barangay_code = b.barangay_code
    $branchFilter
    ORDER BY bb.branch_name";

$branches = Database::fetchAll($sql, $params);

// Include the main view
include __DIR__ . '/views/index.php';
