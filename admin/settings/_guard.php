<?php
/**
 * Settings Module Guard
 * Includes the global admin guard and checks settings permissions
 */

require_once dirname(__DIR__) . '/_guard.php';  // Global admin/_guard.php

$user = Auth::user();

// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::can('VIEW_SETTINGS')) {
    $message = 'You do not have permission to access the Settings module.';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}
