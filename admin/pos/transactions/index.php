<?php
/**
 * POS Transactions Report Controller
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';

Auth::requireLogin();
$user         = Auth::user();
$userRoleCode = $user['role_code'] ?? '';
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$canManageFinancialReportSignatories = in_array($userRoleCode, ['SUPER_ADMIN', 'MANAGER'], true)
    || Auth::can('VIEW_SETTINGS');

$currentUserBranchId = null;
if (!empty($userBranchId) && $userBranchId !== '0' && $userBranchId !== '') {
    $branchParts = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string)$userBranchId)),
        static fn (int $id): bool => $id > 0
    )));
    if (count($branchParts) === 1) {
        $currentUserBranchId = $branchParts[0];
    }
}

// Fetch system settings for view
$systemSettings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
$systemName = htmlspecialchars($systemSettings['system_name'] ?? 'TMS', ENT_QUOTES, 'UTF-8');
$systemLogo = $systemSettings['system_logo'] ?? null;

// Fetch printer settings
$printerSettings = Database::fetch(
    "SELECT receipt_printing_enabled, receipt_paper_width, printer_type, system_logo,
            receipt_auto_print, receipt_show_preview, receipt_copies,
            receipt_show_tin, receipt_show_service_fee, receipt_show_base_amount,
            receipt_show_discount, receipt_show_item_total, receipt_show_subtotal,
            receipt_show_tendered, receipt_show_service_fee_total, receipt_total_source,
            receipt_show_vat,
            receipt_show_cashier, receipt_show_payment_method,
            receipt_show_branch, receipt_logo_enabled, receipt_qr_code_enabled,
            receipt_qr_format, receipt_footer, receipt_custom_footer, receipt_address_source,
            company_name, company_address, company_contact_number, company_email, company_tin
     FROM system_settings
     WHERE setting_id = 1"
);

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

// Fetch current user's name and position for financial report signature
$currentUserName = '';
$currentUserPosition = '';
if (!empty($user['user_id'])) {
    $currentUserRow = Database::fetch(
        "SELECT
            CONCAT_WS(' ',
                e.first_name,
                IF(e.middle_name IS NOT NULL AND e.middle_name != '',
                    CONCAT(UPPER(LEFT(e.middle_name, 1)), '.'), NULL),
                e.last_name
            ) AS full_name,
            COALESCE(p.position_name, '') AS position_name
         FROM user_accounts u
         LEFT JOIN employees e ON e.emp_id = u.emp_id
         LEFT JOIN position p ON p.pos_id = e.job_title
         WHERE u.user_id = :uid",
        ['uid' => (int)$user['user_id']]
    );
    $currentUserName = trim($currentUserRow['full_name'] ?? '');
    $currentUserPosition = $currentUserRow['position_name'] ?? '';
}

// Validate logo URL
if ($systemLogo) {
    $systemLogo = trim($systemLogo);
    if (!preg_match('/^\/|https?:\/\//i', $systemLogo)) {
        $systemLogo = null;
    }
}

include __DIR__ . '/views/index.php';
