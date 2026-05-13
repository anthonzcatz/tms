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

if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::can('VIEW_CUSTOMER_CHARGES')) {
    $message = 'You do not have permission to access Customer Charges.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

$userRoleCode = $user['role_code'] ?? '';
$userBranchId = $user['branch_id'] ?? null;

// Fetch all customer charges with passenger details
$charges = Database::fetchAll(
    "SELECT cc.*,
            pa.fullname AS passenger_name,
            pa.mobile_number AS contact_number
     FROM customer_charges cc
     JOIN passenger_accounts pa ON cc.passenger_id = pa.passenger_id
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

// Payment methods for collect payment modal
$paymentMethods = Database::fetchAll(
    "SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, method_name ASC"
);

// Charge history for selected passenger (loaded via JS)
include __DIR__ . '/views/index.php';
