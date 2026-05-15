<?php
/**
 * System Settings Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

require_once dirname(__DIR__) . '/_guard.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();

$user = Auth::user();
// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/system-settings/')) {
    $message = 'You do not have permission to access System Settings.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Handle POST request to update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Database::connection()->beginTransaction();
    
    try {
        $data = [
            'company_name' => $_POST['company_name'] ?? null,
            'company_abbreviation' => $_POST['company_abbreviation'] ?? null,
            'company_address' => $_POST['company_address'] ?? null,
            'company_contact_number' => $_POST['company_contact_number'] ?? null,
            'company_email' => $_POST['company_email'] ?? null,
            'company_tagline' => $_POST['company_tagline'] ?? null,
            'receipt_footer' => $_POST['receipt_footer'] ?? null,
            'report_footer' => $_POST['report_footer'] ?? null,
            'system_timezone' => $_POST['system_timezone'] ?? 'Asia/Manila',
            'system_currency' => $_POST['system_currency'] ?? 'PHP',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'maintenance_message' => $_POST['maintenance_message'] ?? null,
            'maintenance_start' => !empty($_POST['maintenance_start']) ? $_POST['maintenance_start'] : null,
            'maintenance_end' => !empty($_POST['maintenance_end']) ? $_POST['maintenance_end'] : null,
            'allow_admin_during_maintenance' => isset($_POST['allow_admin_during_maintenance']) ? 1 : 0,
            'cancellation_requires_confirmation' => isset($_POST['cancellation_requires_confirmation']) ? 1 : 0,
            'cancellation_auto_approve' => isset($_POST['cancellation_auto_approve']) ? 1 : 0,
            'cancellation_refund_to_wallet' => isset($_POST['cancellation_refund_to_wallet']) ? 1 : 0,
            'cancellation_refund_processing_days' => intval($_POST['cancellation_refund_processing_days'] ?? 3),
            'cancellation_allow_partial' => isset($_POST['cancellation_allow_partial']) ? 1 : 0,
            'updated_by' => $user['user_id']
        ];
        
        Database::execute(
            "UPDATE system_settings SET 
                company_name = :company_name,
                company_abbreviation = :company_abbreviation,
                company_address = :company_address,
                company_contact_number = :company_contact_number,
                company_email = :company_email,
                company_tagline = :company_tagline,
                receipt_footer = :receipt_footer,
                report_footer = :report_footer,
                system_timezone = :system_timezone,
                system_currency = :system_currency,
                maintenance_mode = :maintenance_mode,
                maintenance_message = :maintenance_message,
                maintenance_start = :maintenance_start,
                maintenance_end = :maintenance_end,
                allow_admin_during_maintenance = :allow_admin_during_maintenance,
                cancellation_requires_confirmation = :cancellation_requires_confirmation,
                cancellation_auto_approve = :cancellation_auto_approve,
                cancellation_refund_to_wallet = :cancellation_refund_to_wallet,
                cancellation_refund_processing_days = :cancellation_refund_processing_days,
                cancellation_allow_partial = :cancellation_allow_partial,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE setting_id = 1",
            $data
        );
        
        // Clear maintenance settings cache so changes take effect immediately
        unset($_SESSION['maintenance_settings_cache']);
        unset($_SESSION['maintenance_settings_cache_time']);
        
        Database::connection()->commit();
        
        $_SESSION['success_message'] = 'System settings updated successfully.';
        header('Location: ' . BASE_URL . '/admin/system-settings/');
        exit;
    } catch (Exception $e) {
        Database::connection()->rollBack();
        $_SESSION['error_message'] = 'Failed to update settings: ' . $e->getMessage();
        header('Location: ' . BASE_URL . '/admin/system-settings/');
        exit;
    }
}

// Fetch current settings
$maintenanceCacheKey = 'maintenance_settings_cache';
unset($_SESSION[$maintenanceCacheKey]);
unset($_SESSION[$maintenanceCacheKey . '_time']);

$settings = Database::fetch(
    "SELECT * FROM system_settings WHERE setting_id = 1"
);

// Common timezones
$timezones = [
    'Asia/Manila' => 'Asia/Manila (PH)',
    'Asia/Tokyo' => 'Asia/Tokyo (JP)',
    'Asia/Singapore' => 'Asia/Singapore (SG)',
    'America/New_York' => 'America/New_York (US)',
    'Europe/London' => 'Europe/London (UK)',
    'Australia/Sydney' => 'Australia/Sydney (AU)'
];

include __DIR__ . '/views/index.php';
