<?php
/**
 * Notification Preferences Controller
 * Manages user notification preferences
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

// Require login
Auth::requireLogin();

// Require SUPER_ADMIN access only
if (!Auth::can('SUPER_ADMIN')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>Access Denied</h1><p>You do not have permission to access this page. Only SUPER_ADMIN users can access notification preferences.</p><a href="' . BASE_URL . '/admin/dashboard">Return to Dashboard</a></body></html>';
    exit;
}

// Get current user
$user = Auth::user();
$userId = $user['user_id'];

// Get available notification types from templates
$notificationTypes = Database::fetchAll(
    "SELECT DISTINCT type, title_template FROM notification_templates WHERE enabled = TRUE ORDER BY type"
);

// Get available channels (show all channels regardless of enabled status so user can pick)
$channels = Database::fetchAll(
    "SELECT * FROM notification_channels ORDER BY FIELD(name, 'in-app', 'email', 'sms', 'push')"
);

// Get user's current preferences
$userPreferences = Database::fetchAll(
    "SELECT * FROM notification_preferences WHERE user_id = :user_id",
    ['user_id' => (int)$userId]
);

// Convert to associative array for easy lookup
$preferencesMap = [];
foreach ($userPreferences as $pref) {
    $preferencesMap[$pref['type']] = [
        'channels' => json_decode($pref['channels'], true) ?? ['in-app'],
        'enabled' => (bool)$pref['enabled']
    ];
}

// Include the main view
include __DIR__ . '/views/index.php';
