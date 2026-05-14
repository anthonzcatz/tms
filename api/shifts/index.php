<?php
/**
 * Cashier Shifts API
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method not allowed']); exit;
}

$sessionId = $_GET['session_id'] ?? null;
if (!$sessionId) { echo json_encode(['success' => false, 'error' => 'session_id required']); exit; }

$session = Database::fetch(
    "SELECT cs.*,
            COALESCE(CONCAT_WS(' ', e.first_name, e.last_name), ua.username) AS cashier_name,
            bb.branch_name,
            COALESCE(CONCAT_WS(' ', e_rev.first_name, e_rev.last_name), ua_rev.username) AS reviewed_by_name
     FROM cashier_sessions cs
     JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
     LEFT JOIN employees e ON ua.emp_id = e.emp_id
     LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
     LEFT JOIN user_accounts ua_rev ON cs.reviewed_by = ua_rev.user_id
     LEFT JOIN employees e_rev ON ua_rev.emp_id = e_rev.emp_id
     WHERE cs.session_id = :id",
    ['id' => $sessionId]
);

if (!$session) { echo json_encode(['success' => false, 'error' => 'Session not found']); exit; }

// Transactions in this session
$transactions = Database::fetchAll(
    "SELECT st.*, stype.name AS service_type_name
     FROM service_transactions st
     LEFT JOIN service_types stype ON st.service_type_id = stype.service_type_id
     WHERE st.cashier_session_id = :sid AND st.status = 'completed'
     ORDER BY st.created_at ASC",
    ['sid' => $sessionId]
);

// Payment breakdown by method
$paymentWhere = "AND tp.created_at >= :start";
$paymentParams = [
    'uid'   => $session['cashier_user_id'],
    'start' => $session['started_at']
];
if ($session['ended_at']) {
    $paymentWhere .= " AND tp.created_at <= :end";
    $paymentParams['end'] = $session['ended_at'];
}

$payments = Database::fetchAll(
    "SELECT pm.method_name, pm.method_type, pm.include_in_expected_cash, SUM(tp.amount) AS total_amount
     FROM transaction_payments tp
     JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
     WHERE tp.created_by = :uid
       $paymentWhere
     GROUP BY pm.method_id, pm.method_name, pm.method_type, pm.include_in_expected_cash
     ORDER BY total_amount DESC",
    $paymentParams
);

// Calculate expected cash based on payment methods with include_in_expected_cash flag
$expectedCashPayments = 0;
foreach ($payments as $payment) {
    if ($payment['include_in_expected_cash']) {
        $expectedCashPayments += $payment['total_amount'];
    }
}

// Add expected cash to session data
$session['expected_cash'] = $session['starting_cash'] + $expectedCashPayments;

echo json_encode(['success' => true, 'data' => ['session' => $session, 'transactions' => $transactions, 'payments' => $payments]]);
