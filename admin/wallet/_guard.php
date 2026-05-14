<?php
/**
 * Wallet Module Guard
 * Protects wallet module access based on permissions
 */

require_once dirname(__DIR__) . '/_guard.php';

// Get current user
$user = Auth::user();

// SUPER_ADMIN can access everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::canAccessModule('admin/wallet/')) {
    $message = 'You do not have permission to access the Wallet module.';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}
