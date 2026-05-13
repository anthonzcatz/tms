<?php
/**
 * Provider Wallets Module Guard
 * Protects provider wallets module access based on permissions
 */

// Get current user
$user = Auth::user();

// SUPER_ADMIN can access everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::can('VIEW_WALLET_MANAGEMENT')) {
    $message = 'You do not have permission to access the Provider Wallets module.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}
