<?php
/**
 * Provider Wallets Module Guard
 * Protects provider wallets module access based on permissions
 */

require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Get current user
$user = Auth::user();

// SUPER_ADMIN can access everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::canAccessModule('admin/wallet/provider-wallets/')) {
    $message = 'You do not have permission to access the Provider Wallets module.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}
