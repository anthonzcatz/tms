<?php
/**
 * POS Printer Setup Controller
 * 
 * This controller handles the printer setup page for POS terminals.
 * Each terminal can configure its local printer via QZ Tray connection.
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

require_once dirname(__DIR__) . '/_guard.php';

Auth::requireLogin();

$user = Auth::user();
$userBranchId = $user['branch_id'] ?? null;

// Fetch branch details for receipt address
$branchDetails = null;
if (!empty($userBranchId) && $userBranchId !== '0' && $userBranchId !== '') {
    $branchDetails = Database::fetch(
        "SELECT branch_name, region_name, province_name, city_municipality_name,
                barangay_name, street_address, landmark, zip_code, contact_number
         FROM business_branches
         WHERE branch_id = :bid",
        ['bid' => (int)$userBranchId]
    );
}

// Only allow access to users with POS access
$userRoleCode = $user['role_code'] ?? '';
if ($userRoleCode !== 'SUPER_ADMIN' && $userRoleCode !== 'MANAGER' && $userRoleCode !== 'CASHIER') {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access Denied: POS access required.</div>';
    exit;
}

// Get global printer settings from database
$printerSettings = Database::fetch(
    "SELECT receipt_printing_enabled, receipt_paper_width, printer_type, system_logo,
            receipt_auto_print, receipt_show_preview, receipt_copies,
            receipt_show_tin, receipt_show_service_fee, receipt_show_base_amount,
            receipt_show_discount, receipt_show_cashier, receipt_show_payment_method,
            receipt_show_branch, receipt_logo_enabled, receipt_qr_code_enabled,
            receipt_qr_format, receipt_footer, receipt_custom_footer, receipt_address_source,
            company_name, company_address, company_contact_number, company_email, company_tin
     FROM system_settings
     WHERE setting_id = 1"
);

$printingEnabled = $printerSettings['receipt_printing_enabled'] ?? 1;
$paperWidth = $printerSettings['receipt_paper_width'] ?? '80mm';
$printerType = $printerSettings['printer_type'] ?? 'THERMAL';

// Pass data to view
$viewData = [
    'printingEnabled' => $printingEnabled,
    'paperWidth' => $paperWidth,
    'printerType' => $printerType,
    'userRoleCode' => $userRoleCode,
    'branchDetails' => $branchDetails
];

extract($viewData);

include dirname(__DIR__) . '/views/printer-setup.php';
