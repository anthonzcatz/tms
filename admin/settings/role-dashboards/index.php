<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Permission gatekeeper - only SUPER_ADMIN can manage role dashboards
if ($_SESSION['user']['role_code'] !== 'SUPER_ADMIN') {
    http_response_code(403);
    require __DIR__ . '/views/access_denied.php';
    exit;
}

// Fetch all roles with their default dashboards
$roles = Database::fetchAll(
    "SELECT * FROM user_roles ORDER BY role_code"
);

// Load view
require __DIR__ . '/views/index.php';
