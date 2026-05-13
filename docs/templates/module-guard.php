<?php
/**
 * <ModuleName> / <Category> Module Guard
 * Protects module access based on permissions
 *
 * IMPORTANT: Must include global admin/_guard.php FIRST
 * so NAVBAR_POSITION is properly defined from session.
 */

require_once dirname(__DIR__) . '/_guard.php';

// Get current user
$user = Auth::user();

// SUPER_ADMIN can access everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::can('VIEW_<PERMISSION>')) {
    $message = 'You do not have permission to access this module.';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}
