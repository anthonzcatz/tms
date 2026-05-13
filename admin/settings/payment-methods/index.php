<?php
/**
 * Payment Methods Management Controller
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();

$user = Auth::user();
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::can('VIEW_SETTINGS')) {
    $message = 'You do not have permission to access the Payment Methods module.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(dirname(__DIR__))) . '/includes/access-denied.php';
    exit;
}

$userRoleCode = $user['role_code'] ?? '';

// Fetch all payment methods
$methods = Database::fetchAll(
    "SELECT * FROM payment_methods ORDER BY sort_order ASC, method_name ASC"
);

include __DIR__ . '/views/index.php';
