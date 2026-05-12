<?php
/**
 * User Management Controller
 * Displays user management interface with list, search, and filters
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Require login and permission
Auth::requireLogin();
Auth::requirePermission('MANAGE_USERS');

// Check if user is SUPER_ADMIN
$currentUser = Auth::user();
$isSuperAdmin = ($currentUser['role_code'] === 'SUPER_ADMIN');

// Get all roles for dropdown
$roles = Database::fetchAll(
    "SELECT role_id, role_name, role_code 
     FROM user_roles 
     ORDER BY role_name"
);

// Include the main view
include __DIR__ . '/views/index.php';
