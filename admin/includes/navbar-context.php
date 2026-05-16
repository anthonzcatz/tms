<?php
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/SidebarHelper.php';

// Fetch system settings for branding
$systemSettings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
$systemName = htmlspecialchars($systemSettings['system_name'] ?? 'Falcon', ENT_QUOTES, 'UTF-8');
$systemLogo = $systemSettings['system_logo'] ?? null;
$developerName = htmlspecialchars($systemSettings['developer_name'] ?? '', ENT_QUOTES, 'UTF-8');
$developerDetails = htmlspecialchars($systemSettings['developer_details'] ?? '', ENT_QUOTES, 'UTF-8');
$footerCopyright = htmlspecialchars($systemSettings['footer_copyright'] ?? '', ENT_QUOTES, 'UTF-8');

// Validate logo URL to prevent XSS attacks
if ($systemLogo) {
    // Only allow relative URLs starting with / or absolute URLs with http/https
    $systemLogo = trim($systemLogo);
    if (!preg_match('/^\/|https?:\/\//i', $systemLogo)) {
        $systemLogo = null; // Invalid URL, use default
    }
}

$currentUser = Auth::user();
$profileImage = null;
$initials = '?';

if ($currentUser) {
    $sql = "SELECT ua.profile_image, e.first_name, e.last_name
            FROM user_accounts ua
            LEFT JOIN employees e ON ua.emp_id = e.emp_id
            WHERE ua.user_id = :user_id";
    $user = Database::fetch(
        $sql,
        ['user_id' => $currentUser['user_id']]
    );
    if ($user) {
        $profileImage = $user['profile_image'];
        if ($user['first_name'] || $user['last_name']) {
            $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
        }
    }
}