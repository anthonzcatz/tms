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
} elseif (!Auth::canAccessModule('admin/wallet/ticket-providers/')) {
    $message = 'You do not have permission to access the Ticket Providers module.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Get current user
$user = Auth::user();

// Fetch all providers
$sql = "SELECT * FROM ticket_providers ORDER BY provider_name";
$providers = Database::fetchAll($sql);

// Include the main view
include __DIR__ . '/views/index.php';
