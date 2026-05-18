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
if ($userRoleCode === 'SUPER_ADMIN' || !$userBranchId) {
    $branches = Database::fetchAll(
        "SELECT branch_id, branch_name FROM business_branches WHERE status = 'active' ORDER BY branch_name"
    );
} else {
    $branches = Database::fetchAll(
        "SELECT branch_id, branch_name FROM business_branches WHERE branch_id = :id AND status = 'active'",
        ['id' => $userBranchId]
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
    'userRoleCode' => $userRoleCode
];

extract($viewData);

include __DIR__ . '/views/index.php';
