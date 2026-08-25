<?php
/**
 * Pusher private-channel authorization for authenticated POS users.
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!PusherService::isConfigured()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Realtime service is not configured.']);
    exit;
}

$channelName = trim((string) ($_POST['channel_name'] ?? ''));
$socketId = trim((string) ($_POST['socket_id'] ?? ''));

if ((!preg_match('/^private-user-(\d+)$/', $channelName)
        && $channelName !== 'private-notifications-global'
        && !preg_match('/^private-pos-branch-(\d+)$/', $channelName))
    || !preg_match('/^\d+\.\d+$/', $socketId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid realtime channel request.']);
    exit;
}

$user = Auth::user() ?: [];
$roleCode = (string) (Auth::userRoleCode() ?? ($user['role_code'] ?? ''));

if (preg_match('/^private-user-(\d+)$/', $channelName, $matches)) {
    if ((int) $matches[1] !== (int) Auth::id()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not authorized for this user channel.']);
        exit;
    }
} elseif ($channelName === 'private-notifications-global') {
    $canViewGlobalNotifications = $roleCode === 'SUPER_ADMIN' || Auth::can('VIEW_NOTIFICATIONS');
    if (!$canViewGlobalNotifications) {
        try {
            $settings = Database::fetch("SELECT notification_roles FROM system_settings WHERE setting_id = 1");
            $allowedRoles = array_filter(array_map('trim', explode(',', (string) ($settings['notification_roles'] ?? ''))));
            $canViewGlobalNotifications = in_array($roleCode, $allowedRoles, true);
        } catch (Throwable $e) {
            $canViewGlobalNotifications = false;
        }
    }
    if (!$canViewGlobalNotifications) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not authorized for global notifications.']);
        exit;
    }
} elseif (preg_match('/^private-pos-branch-(\d+)$/', $channelName, $matches)) {
    $branchId = (int) $matches[1];
    $assignedBranches = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) Auth::userBranchId())
    )));

    if ($roleCode !== 'SUPER_ADMIN'
        && !in_array($branchId, $assignedBranches, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not authorized for this branch.']);
        exit;
    }
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid realtime channel request.']);
    exit;
}

try {
    header('Content-Type: application/json');
    echo PusherService::authorize($channelName, $socketId);
} catch (Throwable $e) {
    error_log('[Pusher] Channel authorization failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Realtime authorization failed.']);
}
