<?php
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/SidebarHelper.php';

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