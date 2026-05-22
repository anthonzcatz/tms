<?php
/**
 * Customer Charges (Utang) Controller
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
} elseif (!Auth::canAccessModule('admin/charges/')) {
    $message = 'You do not have permission to access Customer Charges.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}

$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

// Fetch all customer charges with passenger details and branch from latest charge
$charges = Database::fetchAll(
    "SELECT cc.*,
            pa.fullname AS passenger_name,
            pa.mobile_number AS contact_number,
            bb.branch_name,
            bb.branch_id AS charge_branch_id
     FROM customer_charges cc
     JOIN passenger_accounts pa ON cc.passenger_id = pa.passenger_id
     LEFT JOIN transaction_payments tp ON tp.charged_to_passenger_id = cc.passenger_id
     LEFT JOIN cashier_sessions cs ON tp.cashier_session_id = cs.session_id
     LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
     GROUP BY cc.charge_id
     ORDER BY cc.balance DESC, cc.last_charge_date DESC"
);

// Stats
$stats = Database::fetch(
    "SELECT
        COUNT(*) AS total_customers,
        SUM(balance) AS total_outstanding,
        SUM(CASE WHEN status = 'OUTSTANDING' THEN 1 ELSE 0 END) AS outstanding_count,
        SUM(CASE WHEN status = 'OVERDUE' THEN 1 ELSE 0 END) AS overdue_count
     FROM customer_charges"
);

// Payment methods for collect payment modal (exclude credit tracking methods)
$paymentMethods = Database::fetchAll(
    "SELECT * FROM payment_methods WHERE is_active = 1 AND (tracks_credit = 0 OR tracks_credit IS NULL) ORDER BY sort_order ASC, method_name ASC"
);

// Bank accounts for collect payment modal
$bankAccounts = Database::fetchAll(
    "SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name ASC"
);

// System settings for charge payment confirmation
$systemSettings = Database::fetch("SELECT bank_charge_payments_require_confirmation FROM system_settings WHERE setting_id = 1");

// Charge history for selected passenger (loaded via JS)
include __DIR__ . '/views/index.php';
