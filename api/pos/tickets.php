<?php
/**
 * POS Tickets API — Process ticket bookings with mixed payments
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

function logActivity($userId, $action, $module, $ref = null, $old = null, $new = null) {
    Database::execute(
        "INSERT INTO activity_logs (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES (:uid, NULL, :action, :mod, :ref, :ip, :old, :new, NOW())",
        ['uid' => $userId, 'action' => $action, 'mod' => $module, 'ref' => $ref,
         'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
         'old' => $old ? json_encode($old) : null, 'new' => $new ? json_encode($new) : null]
    );
}

Auth::requireLogin();
$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$sessionId = $input['session_id'] ?? null;
$branchId  = $input['branch_id'] ?? null;
$ticket    = $input['ticket'] ?? [];
$services  = $input['services'] ?? [];
$payments  = $input['payments'] ?? [];

// Validate
if (!$sessionId)        { echo json_encode(['success' => false, 'error' => 'Session ID required.']); exit; }
if (!$branchId)         { echo json_encode(['success' => false, 'error' => 'Branch ID required.']); exit; }
if (empty($ticket))     { echo json_encode(['success' => false, 'error' => 'Ticket information required.']); exit; }
if (empty($payments))   { echo json_encode(['success' => false, 'error' => 'No payment provided.']); exit; }

// Verify session is open
$session = Database::fetch("SELECT * FROM cashier_sessions WHERE session_id = :id AND status = 'OPEN'", ['id' => $sessionId]);
if (!$session) { echo json_encode(['success' => false, 'error' => 'No active session found.']); exit; }

try {
    // Generate transaction code
    $txnCode = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    // --- Create ticket transaction ---
    Database::execute(
        "INSERT INTO ticket_transactions
            (transaction_code, branch_id, passenger_id, origin, destination, travel_date,
             base_amount, service_fee, discount_amount, total_amount, status,
             cashier_session_id, created_by, created_at)
         VALUES (:code, :branch, :passenger, :origin, :destination, :travel_date,
                 :base_amount, :service_fee, :discount_amount, :total_amount, 'booked',
                 :session, :uid, NOW())",
        [
            'code'          => $txnCode,
            'branch'        => $branchId,
            'passenger'     => $ticket['passenger_id'] ?? null,
            'origin'        => $ticket['origin'] ?? null,
            'destination'   => $ticket['destination'] ?? null,
            'travel_date'   => $ticket['travel_date'] ?? null,
            'base_amount'   => floatval($ticket['base_amount'] ?? 0),
            'service_fee'   => floatval($ticket['service_fee'] ?? 0),
            'discount_amount'=> floatval($ticket['discount_amount'] ?? 0),
            'total_amount'  => floatval($ticket['total_amount'] ?? 0),
            'session'       => $sessionId,
            'uid'           => $user['user_id'],
        ]
    );
    $ticketTxnId = Database::connection()->lastInsertId();

    // --- Create service transactions (add-ons) ---
    $serviceTxnIds = [];
    foreach ($services as $svc) {
        $serviceTypeId = $svc['service_type_id'] ?? null;
        if (!$serviceTypeId) continue;

        Database::execute(
            "INSERT INTO service_transactions
                (transaction_code, branch_id, service_type_id, passenger_id, description,
                 quantity, unit_price, total_amount, status, cashier_session_id, created_by, created_at)
             VALUES (:code, :branch, :stype, :passenger, :desc, :qty, :price, :total, 'completed', :session, :uid, NOW())",
            [
                'code'    => $txnCode,
                'branch'  => $branchId,
                'stype'   => $serviceTypeId,
                'passenger' => $ticket['passenger_id'] ?? null,
                'desc'    => $svc['description'] ?? null,
                'qty'     => intval($svc['quantity'] ?? 1),
                'price'   => floatval($svc['unit_price'] ?? 0),
                'total'   => floatval($svc['total_amount'] ?? 0),
                'session' => $sessionId,
                'uid'     => $user['user_id'],
            ]
        );
        $serviceTxnIds[] = Database::connection()->lastInsertId();
    }

    // --- Compute total amount (ticket + services) ---
    $ticketTotal = floatval($ticket['total_amount'] ?? 0);
    $servicesTotal = array_sum(array_column($services, 'total_amount'));
    $orderTotal = $ticketTotal + $servicesTotal;
    $totalPaid = array_sum(array_column($payments, 'amount'));

    if ($totalPaid < $orderTotal) {
        echo json_encode(['success' => false, 'error' => "Payment (₱{$totalPaid}) is less than total (₱{$orderTotal})."]); exit;
    }

    // --- Record payments against ticket transaction ---
    foreach ($payments as $pay) {
        $methodId     = $pay['payment_method_id'] ?? null;
        $amount       = floatval($pay['amount'] ?? 0);
        $refNum       = $pay['reference_number'] ?? null;
        $bankAcctId   = $pay['bank_account_id'] ?? null;
        $passengerId  = $pay['passenger_id'] ?? null;
        if (!$methodId || $amount <= 0) continue;

        // Determine confirmation status
        $methodInfo = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $methodId]);
        $confirmStatus = ($methodInfo && $methodInfo['requires_confirmation']) ? 'PENDING' : 'NOT_REQUIRED';
        $methodType = $methodInfo['method_type'] ?? '';

        // Handle CHARGE payments
        if ($methodType === 'CHARGE' && $passengerId) {
            $existingCharge = Database::fetch(
                "SELECT * FROM customer_charges WHERE passenger_id = :pid",
                ['pid' => $passengerId]
            );
            if (!$existingCharge) {
                Database::execute(
                    "INSERT INTO customer_charges (passenger_id, total_charged, total_paid, balance, status, last_charge_date)
                     VALUES (:pid, 0, 0, 0, 'CLEAR', NOW())",
                    ['pid' => $passengerId]
                );
            }
            Database::execute(
                "UPDATE customer_charges
                 SET total_charged = total_charged + :amt,
                     balance = balance + :amt,
                     status = CASE WHEN balance + :amt > 0 THEN 'OUTSTANDING' ELSE 'CLEAR',
                     last_charge_date = NOW()
                 WHERE passenger_id = :pid",
                ['pid' => $passengerId, 'amt' => $amount]
            );
        }

        Database::execute(
            "INSERT INTO transaction_payments
                (source_type, source_id, payment_method_id, bank_account_id, amount, reference_number,
                 payment_date, confirmation_status, charged_to_passenger_id, cashier_session_id, created_by, created_at)
             VALUES ('TICKET_TRANSACTION', :src, :method, :bank, :amount, :ref, CURDATE(), :confirm, :passenger, :session, :uid, NOW())",
            [
                'src'       => $ticketTxnId,
                'method'    => $methodId,
                'bank'      => $bankAcctId ?: null,
                'amount'    => $amount,
                'ref'       => $refNum,
                'confirm'   => $confirmStatus,
                'passenger' => $passengerId ?: null,
                'session'   => $sessionId,
                'uid'       => $user['user_id'],
            ]
        );
    }

    // --- Update cashier session totals ---
    Database::execute(
        "UPDATE cashier_sessions SET total_sales = total_sales + :total WHERE session_id = :id",
        ['total' => $orderTotal, 'id' => $sessionId]
    );

    logActivity($user['user_id'], 'PROCESS_TICKET_TRANSACTION', 'POS', $txnCode, null,
        ['transaction_code' => $txnCode, 'total' => $orderTotal, 'ticket_id' => $ticketTxnId, 'services_count' => count($serviceTxnIds)]);

    echo json_encode([
        'success'          => true,
        'message'          => 'Ticket transaction processed successfully.',
        'transaction_code' => $txnCode,
        'ticket_id'        => $ticketTxnId,
        'total'            => $orderTotal,
        'paid'             => $totalPaid,
        'change'           => $totalPaid - $orderTotal,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
}
