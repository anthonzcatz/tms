<?php
/**
 * System Settings Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

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
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}

// Handle ?layout= param (same as _guard.php) so navbar position changes work on this page
$allowedNavbarPositions = ['vertical', 'top', 'combo', 'double-top'];
if (isset($_GET['layout']) && in_array($_GET['layout'], $allowedNavbarPositions, true)) {
    $_SESSION['navbarPosition'] = $_GET['layout'];
    $params = $_GET;
    unset($params['layout']);
    $redirectPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/';
    if (!empty($params)) {
        $redirectPath .= '?' . http_build_query($params);
    }
    header('Location: ' . $redirectPath);
    exit;
}
if (!isset($_SESSION['navbarPosition']) || !in_array($_SESSION['navbarPosition'], $allowedNavbarPositions, true)) {
    $_SESSION['navbarPosition'] = 'vertical';
}

// Define NAVBAR_POSITION if not already defined
if (!defined('NAVBAR_POSITION')) {
    define('NAVBAR_POSITION', $_SESSION['navbarPosition']);
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
            // company_tin moved to BIR Settings - removed from System Settings
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
            'session_warning_timeout' => isset($_POST['session_warning_timeout']) && $_POST['session_warning_timeout'] !== '' ? intval($_POST['session_warning_timeout']) : 15,
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
            'pos_allow_insufficient_wallet' => isset($_POST['pos_allow_insufficient_wallet']) ? 1 : 0,
            'bank_pos_payments_require_confirmation' => isset($_POST['bank_pos_payments_require_confirmation']) ? 1 : 0,
            'bank_charge_payments_require_confirmation' => isset($_POST['bank_charge_payments_require_confirmation']) ? 1 : 0,
            'bank_deposits_require_confirmation' => isset($_POST['bank_deposits_require_confirmation']) ? 1 : 0,
            'session_lifetime_minutes' => isset($_POST['session_lifetime_minutes']) && $_POST['session_lifetime_minutes'] !== '' ? max(5, min(1440, (int)$_POST['session_lifetime_minutes'])) : 120,
            'csrf_token_lifetime_minutes' => isset($_POST['csrf_token_lifetime_minutes']) && $_POST['csrf_token_lifetime_minutes'] !== '' ? max(5, min(1440, (int)$_POST['csrf_token_lifetime_minutes'])) : 480,
            'device_approval_required' => isset($_POST['device_approval_required']) ? 1 : 0,
            'max_concurrent_sessions'  => isset($_POST['max_concurrent_sessions']) && $_POST['max_concurrent_sessions'] !== '' ? max(1, min(10, (int)$_POST['max_concurrent_sessions'])) : 1,
            'encrypt_ids' => isset($_POST['encrypt_ids']) ? 1 : 0,
            // Printer Settings
            'receipt_printing_enabled' => isset($_POST['receipt_printing_enabled']) ? 1 : 0,
            'receipt_paper_width' => trim($_POST['receipt_paper_width'] ?? '80mm'),
            'receipt_auto_print' => isset($_POST['receipt_auto_print']) ? 1 : 0,
            'receipt_show_preview' => isset($_POST['receipt_show_preview']) ? 1 : 0,
            'receipt_copies' => isset($_POST['receipt_copies']) && $_POST['receipt_copies'] !== '' ? max(1, min(3, (int)$_POST['receipt_copies'])) : 1,
            'receipt_customer_copy' => isset($_POST['receipt_customer_copy']) ? 1 : 0,
            'receipt_merchant_copy' => isset($_POST['receipt_merchant_copy']) ? 1 : 0,
            'receipt_auto_cut' => isset($_POST['receipt_auto_cut']) ? 1 : 0,
            'receipt_open_cash_drawer' => isset($_POST['receipt_open_cash_drawer']) ? 1 : 0,
            'receipt_show_cashier' => isset($_POST['receipt_show_cashier']) ? 1 : 0,
            'receipt_show_payment_method' => isset($_POST['receipt_show_payment_method']) ? 1 : 0,
            'receipt_show_branch' => isset($_POST['receipt_show_branch']) ? 1 : 0,
            'receipt_qr_code_enabled' => isset($_POST['receipt_qr_code_enabled']) ? 1 : 0,
            'receipt_qr_format' => trim($_POST['receipt_qr_format'] ?? 'TRANSACTION_ID'),
            'receipt_logo_enabled' => isset($_POST['receipt_logo_enabled']) ? 1 : 0,
            'receipt_show_tin' => isset($_POST['receipt_show_tin']) ? 1 : 0,
            'receipt_show_service_fee' => isset($_POST['receipt_show_service_fee']) ? 1 : 0,
            'receipt_show_base_amount' => isset($_POST['receipt_show_base_amount']) ? 1 : 0,
            'receipt_show_discount' => isset($_POST['receipt_show_discount']) ? 1 : 0,
            'receipt_custom_footer' => trim($_POST['receipt_custom_footer'] ?? ''),
            'receipt_address_source' => (isset($_POST['receipt_address_source']) && $_POST['receipt_address_source'] === 'branch') ? 'branch' : 'company',
            'printer_type' => trim($_POST['printer_type'] ?? 'THERMAL'),
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
                session_warning_timeout = :session_warning_timeout,
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
                pos_allow_insufficient_wallet = :pos_allow_insufficient_wallet,
                bank_pos_payments_require_confirmation = :bank_pos_payments_require_confirmation,
                bank_charge_payments_require_confirmation = :bank_charge_payments_require_confirmation,
                bank_deposits_require_confirmation = :bank_deposits_require_confirmation,
                session_lifetime_minutes = :session_lifetime_minutes,
                csrf_token_lifetime_minutes = :csrf_token_lifetime_minutes,
                device_approval_required = :device_approval_required,
                max_concurrent_sessions  = :max_concurrent_sessions,
                encrypt_ids = :encrypt_ids,
                receipt_printing_enabled = :receipt_printing_enabled,
                receipt_paper_width = :receipt_paper_width,
                receipt_auto_print = :receipt_auto_print,
                receipt_show_preview = :receipt_show_preview,
                receipt_copies = :receipt_copies,
                receipt_customer_copy = :receipt_customer_copy,
                receipt_merchant_copy = :receipt_merchant_copy,
                receipt_auto_cut = :receipt_auto_cut,
                receipt_open_cash_drawer = :receipt_open_cash_drawer,
                receipt_show_cashier = :receipt_show_cashier,
                receipt_show_payment_method = :receipt_show_payment_method,
                receipt_show_branch = :receipt_show_branch,
                receipt_qr_code_enabled = :receipt_qr_code_enabled,
                receipt_qr_format = :receipt_qr_format,
                receipt_logo_enabled = :receipt_logo_enabled,
                receipt_show_tin = :receipt_show_tin,
                receipt_show_service_fee = :receipt_show_service_fee,
                receipt_show_base_amount = :receipt_show_base_amount,
                receipt_show_discount = :receipt_show_discount,
                receipt_custom_footer = :receipt_custom_footer,
                receipt_address_source = :receipt_address_source,
                printer_type = :printer_type,
                updated_by = :updated_by,
                updated_at = :updated_at
            WHERE setting_id = 1",
            array_merge($data, ['updated_at' => date('Y-m-d H:i:s')])
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
