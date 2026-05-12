<?php
/**
 * _guard.php
 * Include this at the TOP of any admin page to require authentication.
 *
 * Usage:
 *   <?php require_once __DIR__ . '/_guard.php'; ?>
 *
 * This file:
 *   - Loads the bootstrap (env, db, session, auth helpers)
 *   - Checks if user is logged in
 *   - Redirects to login if not authenticated
 *   - Detects session hijacking (fingerprint mismatch)
 *   - Prevents caching of admin pages
 *
 * Place this file in the admin/ folder.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Require login
Auth::requireLogin();

// Role-based access control (comment out if you want all logged-in users to access)
$user = Auth::user();
$allowedRoles = ['SUPER_ADMIN', 'CEO', 'MANAGER', 'CASHIER', 'AUDITOR', 'ADMIN', 'STAFF']; // Add your admin role codes here
if (!in_array($user['role_code'] ?? '', $allowedRoles)) {
    http_response_code(403);
    die('Access denied. You do not have permission to access this area.');
}
