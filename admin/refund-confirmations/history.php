<?php
/**
 * Refund History Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();
$user = Auth::user();
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');

if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::canAccessModule('admin/refund-confirmations/')) {
    $message = 'You do not have permission to access Refund History.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}

$allowedBranchIds = PosAccess::allowedBranchIds($user);
$realtimeBranchIds = $allowedBranchIds === null
    ? array_map('intval', array_column(Database::fetchAll("SELECT branch_id FROM business_branches WHERE status = 'active'"), 'branch_id'))
    : $allowedBranchIds;
$pusherConfigured = PusherService::isConfigured();
$pusherKey = $pusherConfigured ? env('PUSHER_KEY', '') : '';
$pusherCluster = $pusherConfigured ? env('PUSHER_CLUSTER', 'ap1') : 'ap1';

include __DIR__ . '/views/history.php';
