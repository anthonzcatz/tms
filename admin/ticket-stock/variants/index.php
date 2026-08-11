<?php
/**
 * Ticket Variants Controller
 */

require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');

// Check permission
$user = Auth::user();
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow access
} elseif (!Auth::canAccessModule('admin/ticket-stock/variants/')) {
    $message = 'You do not have permission to access the Ticket Variants module.';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Fetch all ticket providers for filter dropdown
$providers = Database::fetchAll(
    "SELECT provider_id, provider_code, provider_name, provider_type 
     FROM ticket_providers 
     WHERE status = 'active'
     ORDER BY provider_name"
);

// Include the main view
include __DIR__ . '/views/index.php';
