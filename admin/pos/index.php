<?php
/**
 * Cashier POS Controller
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
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
$userBranchId = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
$allowedBranchIds = PosAccess::allowedBranchIds($user);

// Fetch active payment methods
$paymentMethods = Database::fetchAll(
    "SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, method_name ASC"
);

// Fetch active add-on service types (does NOT require a wallet)
$addonServiceTypes = Database::fetchAll(
    "SELECT * FROM service_types WHERE is_active = 1 AND (requires_wallet = 0 OR requires_wallet IS NULL) ORDER BY name ASC"
);

// Fetch active branches for this user
// Support multiple branch_ids (comma-separated like "1,2" or single "2")
$hasValidBranchId = $allowedBranchIds === null || !empty($allowedBranchIds);
// Handle comma-separated branch_ids
$branchWhere = ["status = 'active'"];
$branchParams = [];
PosAccess::applyBranchScope($branchWhere, $branchParams, 'branch_id', $user, 'pos_branch');
$branches = Database::fetchAll(
    "SELECT branch_id, branch_name
     FROM business_branches
     WHERE " . implode(' AND ', $branchWhere) . "
     ORDER BY branch_name",
    $branchParams
);

// Fetch open session for this cashier (any date) - BEFORE determining activeBranchId
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

// Determine active branch for receipt address
$activeBranchId = null;
if (!empty($activeSession) && !empty($activeSession['branch_id'])
    && ($allowedBranchIds === null || in_array((int) $activeSession['branch_id'], $allowedBranchIds, true))) {
    $activeBranchId = (int) $activeSession['branch_id'];
} elseif ($allowedBranchIds !== null && count($allowedBranchIds) === 1) {
    $activeBranchId = $allowedBranchIds[0];
}

error_log('[POS Controller] userBranchId: ' . var_export($userBranchId, true));
error_log('[POS Controller] hasValidBranchId: ' . var_export($hasValidBranchId, true));
error_log('[POS Controller] activeSession branch_id: ' . (!empty($activeSession) && !empty($activeSession['branch_id']) ? $activeSession['branch_id'] : 'none'));
error_log('[POS Controller] activeBranchId: ' . var_export($activeBranchId, true));

// Fetch full branch details for receipt address
$branchDetails = null;
if ($activeBranchId) {
    $branchDetails = Database::fetch(
        "SELECT branch_name, region_name, province_name, city_municipality_name,
                barangay_name, street_address, landmark, zip_code, contact_number
         FROM business_branches
         WHERE branch_id = :bid",
        ['bid' => $activeBranchId]
    );
    error_log('[POS Controller] branchDetails: ' . var_export($branchDetails, true));
} else {
    error_log('[POS Controller] No activeBranchId, branchDetails will be null');
}

// Fetch active bank accounts for bank transfer/e-wallet methods
$bankAccountWhere = ['ba.is_active = 1'];
$bankAccountParams = [];
if ($allowedBranchIds !== null) {
    if (!$allowedBranchIds) {
        $bankAccountWhere[] = '1 = 0';
    } else {
        $bankBranchPlaceholders = [];
        foreach (array_values($allowedBranchIds) as $index => $allowedBranchId) {
            $key = 'pos_bank_branch_' . $index;
            $bankBranchPlaceholders[] = ':' . $key;
            $bankAccountParams[$key] = $allowedBranchId;
        }
        $bankAccountWhere[] = '(ba.branch_id IS NULL OR ba.branch_id IN (' . implode(',', $bankBranchPlaceholders) . '))';
    }
}
$bankAccounts = Database::fetchAll(
    "SELECT ba.*, pm.method_code, pm.method_name, pm.method_type
     FROM bank_accounts ba
     LEFT JOIN payment_methods pm ON ba.payment_method_id = pm.method_id
     WHERE " . implode(' AND ', $bankAccountWhere) . "
     ORDER BY ba.bank_name ASC",
    $bankAccountParams
);

// Passenger records are loaded on demand by the POS search API.
$passengers = [];

// Fetch cancellation settings. Older deployments may not yet have the
// pending-refunds close-session setting.
try {
    $cancellationSettings = Database::fetch(
        "SELECT cancellation_requires_confirmation,
                void_requires_confirmation,
                return_requires_confirmation,
                cancellation_refund_processing_days,
                cancellation_allow_partial,
                show_pending_refunds_in_close_session
         FROM system_settings
         WHERE setting_id = 1"
    );
} catch (Throwable $e) {
    $cancellationSettings = Database::fetch(
        "SELECT cancellation_requires_confirmation,
                cancellation_refund_processing_days,
                cancellation_allow_partial
         FROM system_settings
         WHERE setting_id = 1"
    ) ?: [];
    $cancellationSettings['void_requires_confirmation'] = $cancellationSettings['cancellation_requires_confirmation'] ?? 1;
    $cancellationSettings['return_requires_confirmation'] = $cancellationSettings['cancellation_requires_confirmation'] ?? 1;
    $cancellationSettings['show_pending_refunds_in_close_session'] = 0;
}

// Fetch POS settings. Older deployments default to requiring ticket numbers
// until the optional setting migration has been applied.
try {
    $posSettings = Database::fetch(
        "SELECT pos_cashier_can_open_session,
                pos_cashier_can_close_session,
                pos_manager_can_open_for_cashier,
                pos_manager_can_close_for_cashier,
                allow_negative_ticket_stock,
                pos_allow_insufficient_wallet,
                pos_ticket_number_required
         FROM system_settings
         WHERE setting_id = 1"
    );
} catch (Throwable $e) {
    $posSettings = Database::fetch(
        "SELECT pos_cashier_can_open_session,
                pos_cashier_can_close_session,
                pos_manager_can_open_for_cashier,
                pos_manager_can_close_for_cashier,
                allow_negative_ticket_stock
         FROM system_settings
         WHERE setting_id = 1"
    ) ?: [];
    $posSettings['pos_ticket_number_required'] = 1;
}

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
            receipt_show_item_total,
            receipt_show_subtotal,
            receipt_show_tendered,
            receipt_show_service_fee_total,
            receipt_total_source,
            receipt_show_vat,
            receipt_qr_code_enabled,
            receipt_qr_format,
            receipt_logo_enabled,
            receipt_header_text,
            receipt_footer_text,
            receipt_footer,
            receipt_custom_footer,
            receipt_address_source,
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

// Supported transportation types for the open session form.
$cashierTransportTypeLabels = [];
$cashierTransportTypeIcons = [];
foreach (CashierTransportAccess::getSupportedTransportTypes() as $type) {
    $cashierTransportTypeLabels[$type] = $type;
    $cashierTransportTypeIcons[$type] = 'fa-circle';
}
foreach (Database::getProviderTypes() as $row) {
    $cashierTransportTypeLabels[$row['type_code']] = $row['type_label'];
    $cashierTransportTypeIcons[$row['type_code']] = $row['type_icon'] ?: 'fa-circle';
}

// Pass user to view
$viewData = [
    'userBranchId' => $userBranchId,
    'activeSession' => $activeSession,
    'addonServiceTypes' => $addonServiceTypes,
    'paymentMethods' => $paymentMethods,
    'bankAccounts' => $bankAccounts,
    'passengers' => $passengers,
    'cancellationSettings' => $cancellationSettings,
    'posSettings' => $posSettings,
    'printerSettings' => $printerSettings,
    'userRoleCode' => $userRoleCode,
    'branchDetails' => $branchDetails,
    'depositBankAccounts' => Database::fetchAll(
        "SELECT bank_account_id, bank_name, account_name, account_number
         FROM bank_accounts
         WHERE is_active = 1
         ORDER BY bank_name ASC"
    ),
    'cashierTransportTypes' => CashierTransportAccess::getSupportedTransportTypes(),
    'cashierTransportTypeLabels' => $cashierTransportTypeLabels,
    'cashierTransportTypeIcons' => $cashierTransportTypeIcons
];

extract($viewData);

include __DIR__ . '/views/index.php';
