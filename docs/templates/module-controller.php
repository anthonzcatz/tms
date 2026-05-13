<?php
/**
 * <ModuleName> Controller
 * Description of what this module does
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

// For modules directly under admin/ (e.g., admin/dashboard/):
//   require_once dirname(__DIR__) . '/_guard.php';
// For modules under a category (e.g., admin/wallet/<module>/):
//   require_once dirname(__DIR__) . '/_guard.php';

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Require login and permission (uncomment/adjust as needed)
// Auth::requireLogin();
// Auth::requirePermission('VIEW_<PERMISSION>');

// Get current user
$user = Auth::user();
$userBranchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

// Your controller logic here...

// Include the main view
include __DIR__ . '/views/index.php';
