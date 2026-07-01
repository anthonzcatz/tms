<?php
/**
 * Notifications Controller
 * Displays all notifications for SUPER_ADMIN and ADMIN
 */
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/NotificationService.php';

require_once dirname(__DIR__) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();
$user = Auth::user();

// Only users with VIEW_NOTIFICATIONS permission can access notifications
if ($user['role_code'] !== 'SUPER_ADMIN' && !Auth::can('VIEW_NOTIFICATIONS')) {
    $message = 'You do not have permission to access Notifications.';
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Fetch notifications from database
$notifications = NotificationService::getNotifications($user['user_id'], [
    'limit' => 100
]);

// Pass data to view
$viewData = [
    'notifications' => $notifications,
    'user' => $user
];

extract($viewData);
include __DIR__ . '/views/index.php';
