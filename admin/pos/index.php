<?php
/**
 * Cashier POS Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

require_once __DIR__ . '/_guard.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

Auth::requireLogin();

$user = Auth::user();
$userBranchId = $user['branch_id'] ?? null;
$userRoleCode = $user['role_code'] ?? '';

// Fetch active payment methods
$paymentMethods = Database::fetchAll(
    "SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, method_name ASC"
);

// Fetch active service types
$serviceTypes = Database::fetchAll(
    "SELECT * FROM service_types WHERE is_active = 1 ORDER BY name ASC"
);

// Fetch active branches for this user
// Support multiple branch_ids (comma-separated like "1,2" or single "2")
$hasValidBranchId = !empty($userBranchId) && $userBranchId !== '0' && $userBranchId !== '';
if ($userRoleCode === 'SUPER_ADMIN' || !$hasValidBranchId) {
    $branches = Database::fetchAll(
        "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
    );
} else {
    // Handle comma-separated branch_ids
    $branchIds = array_map('trim', explode(',', $userBranchId));
    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
    $branches = Database::fetchAll(
        "SELECT branch_id, branch_name FROM business_branches WHERE branch_id IN ($placeholders) AND status = 'active' ORDER BY branch_name",
        $branchIds
    );
}

// Fetch active bank accounts for bank transfer/e-wallet methods
$bankAccounts = Database::fetchAll(
    "SELECT ba.*, pm.method_code, pm.method_name
     FROM bank_accounts ba
     LEFT JOIN payment_methods pm ON ba.payment_method_id = pm.method_id
     WHERE ba.is_active = 1
     ORDER BY ba.bank_name ASC"
);

// Fetch open session for this cashier (any date)
$todaySessions = Database::fetchAll(
    "SELECT cs.*, bb.branch_name
     FROM cashier_sessions cs
     LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
     WHERE cs.cashier_user_id = :uid
       AND cs.status = 'OPEN'
     ORDER BY cs.started_at DESC",
    ['uid' => $user['user_id']]
);

// Active session (not closed)
$activeSession = $todaySessions[0] ?? null;

// Fetch passengers for CHARGE payment selection
$passengers = Database::fetchAll(
    "SELECT passenger_id, fullname, mobile_number,
            (SELECT balance FROM customer_charges WHERE passenger_id = pa.passenger_id) AS balance
     FROM passenger_accounts pa
     ORDER BY fullname ASC"
);

// Fetch cancellation settings
$cancellationSettings = Database::fetch(
    "SELECT cancellation_requires_confirmation,
            cancellation_refund_processing_days,
            cancellation_allow_partial
     FROM system_settings
     WHERE setting_id = 1"
);

// Fetch POS settings
$posSettings = Database::fetch(
    "SELECT pos_cashier_can_open_session,
            pos_cashier_can_close_session,
            pos_manager_can_open_for_cashier,
            pos_manager_can_close_for_cashier
     FROM system_settings
     WHERE setting_id = 1"
);

// Fetch printer settings
$printerSettings = Database::fetch(
    "SELECT receipt_printing_enabled,
            receipt_paper_width,
            receipt_auto_print,
            receipt_show_preview,
            receipt_copies,
            receipt_auto_cut,
            receipt_open_cash_drawer,
            receipt_show_cashier,
            receipt_show_payment_method,
            receipt_show_branch,
            receipt_show_tin,
            receipt_show_service_fee,
            receipt_show_base_amount,
            receipt_show_discount,
            receipt_qr_code_enabled,
            receipt_qr_format,
            receipt_logo_enabled,
            receipt_header_text,
            receipt_footer_text,
            receipt_footer,
            receipt_custom_footer,
            printer_type,
            company_name,
            company_address,
            company_contact_number,
            company_email,
            company_tin,
            system_logo,
            bir_permit_number,
            bir_accreditation_number,
            bir_validity_from,
            bir_validity_to,
            bir_min,
            bir_machine_serial
     FROM system_settings
     WHERE setting_id = 1"
);

// Pass user to view
$viewData = [
    'userBranchId' => $userBranchId,
    'activeSession' => $activeSession,
    'serviceTypes' => $serviceTypes,
    'paymentMethods' => $paymentMethods,
    'bankAccounts' => $bankAccounts,
    'passengers' => $passengers,
    'cancellationSettings' => $cancellationSettings,
    'posSettings' => $posSettings,
    'printerSettings' => $printerSettings,
    'userRoleCode' => $userRoleCode,
    'depositBankAccounts' => Database::fetchAll(
        "SELECT bank_account_id, bank_name, account_name, account_number
         FROM bank_accounts
         WHERE is_active = 1
         ORDER BY bank_name ASC"
    )
];

extract($viewData);

include __DIR__ . '/views/index.php';
