<?php
/**
 * API to fetch payment breakdown for a transaction
 * Used by Cancel Ticket modal to show original payment split and calculate refund distribution
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$transactionCode = $_GET['transaction_code'] ?? null;

if (!$transactionCode) {
    echo json_encode(['success' => false, 'error' => 'Transaction code required']);
    exit;
}

// Try to find the transaction (ticket or service)
$ticketTxn = Database::fetch(
    "SELECT transaction_id, transaction_code, total_amount, passenger_id
     FROM ticket_transactions WHERE transaction_code = :code",
    ['code' => $transactionCode]
);

$serviceTxn = null;
if (!$ticketTxn) {
    $serviceTxn = Database::fetch(
        "SELECT service_txn_id as transaction_id, transaction_code, total_amount, passenger_id
         FROM service_transactions WHERE transaction_code = :code",
        ['code' => $transactionCode]
    );
}

$txn = $ticketTxn ?: $serviceTxn;

if (!$txn) {
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

$txnId = $txn['transaction_id'];
$sourceType = $ticketTxn ? 'TICKET_TRANSACTION' : 'SERVICE_TRANSACTION';

// Fetch payment breakdown
$payments = Database::fetchAll(
    "SELECT pm.method_name,
            pm.method_type,
            pm.method_code,
            pm.tracks_credit,
            tp.amount,
            tp.charged_to_passenger_id,
            pa.fullname AS charged_to_passenger_name
     FROM transaction_payments tp
     JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
     LEFT JOIN passenger_accounts pa ON pa.passenger_id = tp.charged_to_passenger_id
     WHERE tp.source_type = :stype AND tp.source_id = :sid
     ORDER BY pm.sort_order ASC, pm.method_name ASC",
    ['stype' => $sourceType, 'sid' => $txnId]
);

$totalAmount = floatval($txn['total_amount'] ?? 0);
$totalPaid = array_sum(array_column($payments, 'amount'));

echo json_encode([
    'success' => true,
    'data' => [
        'transaction_id' => $txnId,
        'transaction_code' => $txn['transaction_code'],
        'total_amount' => $totalAmount,
        'total_paid' => $totalPaid,
        'passenger_id' => $txn['passenger_id'],
        'payments' => $payments
    ]
]);
