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
// Only SUPER_ADMIN can access System Settings
if ($user['role_code'] !== 'SUPER_ADMIN') {
    $message = 'Only SUPER_ADMIN can access System Settings.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Handle POST request to update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Database::connection()->beginTransaction();
    
    try {
        // Validate and sanitize branding fields
        $systemName = trim($_POST['system_name'] ?? 'Falcon');
        
        $systemLogo = trim($_POST['system_logo'] ?? '');
        if ($systemLogo && !preg_match('/^\/|https?:\/\//i', $systemLogo)) {
            $systemLogo = ''; // Invalid URL, clear it
        }
        
        $developerName = trim($_POST['developer_name'] ?? '');
        $developerDetails = trim($_POST['developer_details'] ?? '');
        $footerCopyright = trim($_POST['footer_copyright'] ?? '');
        
        $data = [
            'company_name' => trim($_POST['company_name'] ?? ''),
            'company_abbreviation' => trim($_POST['company_abbreviation'] ?? ''),
            'company_address' => trim($_POST['company_address'] ?? ''),
            'company_contact_number' => trim($_POST['company_contact_number'] ?? ''),
            'company_email' => filter_var(trim($_POST['company_email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'company_tagline' => trim($_POST['company_tagline'] ?? ''),
            'system_name' => $systemName,
            'system_logo' => $systemLogo,
            'developer_name' => $developerName,
            'developer_details' => $developerDetails,
            'footer_copyright' => $footerCopyright,
            'receipt_footer' => trim($_POST['receipt_footer'] ?? ''),
            'report_footer' => trim($_POST['report_footer'] ?? ''),
            'system_timezone' => trim($_POST['system_timezone'] ?? 'Asia/Manila'),
            'system_currency' => trim($_POST['system_currency'] ?? 'PHP'),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'maintenance_message' => trim($_POST['maintenance_message'] ?? ''),
            'maintenance_start' => !empty($_POST['maintenance_start']) ? $_POST['maintenance_start'] : null,
            'maintenance_end' => !empty($_POST['maintenance_end']) ? $_POST['maintenance_end'] : null,
            'allow_admin_during_maintenance' => isset($_POST['allow_admin_during_maintenance']) ? 1 : 0,
            'cancellation_requires_confirmation' => isset($_POST['cancellation_requires_confirmation']) ? 1 : 0,
            'cancellation_refund_processing_days' => isset($_POST['cancellation_refund_processing_days']) && $_POST['cancellation_refund_processing_days'] !== '' ? intval($_POST['cancellation_refund_processing_days']) : 0,
            'cancellation_allow_partial' => isset($_POST['cancellation_allow_partial']) ? 1 : 0,
            'pos_cashier_can_open_session' => isset($_POST['pos_cashier_can_open_session']) ? 1 : 0,
            'pos_cashier_can_close_session' => isset($_POST['pos_cashier_can_close_session']) ? 1 : 0,
            'pos_manager_can_open_for_cashier' => isset($_POST['pos_manager_can_open_for_cashier']) ? 1 : 0,
            'pos_manager_can_close_for_cashier' => isset($_POST['pos_manager_can_close_for_cashier']) ? 1 : 0,
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
                system_name = :system_name,
                system_logo = :system_logo,
                developer_name = :developer_name,
                developer_details = :developer_details,
                footer_copyright = :footer_copyright,
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
                cancellation_refund_processing_days = :cancellation_refund_processing_days,
                cancellation_allow_partial = :cancellation_allow_partial,
                pos_cashier_can_open_session = :pos_cashier_can_open_session,
                pos_cashier_can_close_session = :pos_cashier_can_close_session,
                pos_manager_can_open_for_cashier = :pos_manager_can_open_for_cashier,
                pos_manager_can_close_for_cashier = :pos_manager_can_close_for_cashier,
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
