<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Permission gatekeeper - only SUPER_ADMIN can manage role dashboards
$user = Auth::user();
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/settings/role-dashboards/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Fetch all roles with their default dashboards
$roles = Database::fetchAll(
    "SELECT * FROM user_roles ORDER BY role_code"
);

// Load view
require __DIR__ . '/views/index.php';
