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
$payments  = $input['payments'] ?? [];

// Support both single ticket (legacy) and multiple tickets array
$tickets   = $input['tickets'] ?? [];
if (empty($tickets) && !empty($input['ticket'])) {
    $tickets = [$input['ticket']]; // backward-compat: wrap single ticket
}
$services  = $input['services'] ?? [];

// Validate
if (!$sessionId)        { echo json_encode(['success' => false, 'error' => 'Session ID required.']); exit; }
if (!$branchId)         { echo json_encode(['success' => false, 'error' => 'Branch ID required.']); exit; }
if (empty($tickets))    { echo json_encode(['success' => false, 'error' => 'At least one ticket is required.']); exit; }
if (empty($payments))   { echo json_encode(['success' => false, 'error' => 'No payment provided.']); exit; }

// Verify session is open
$session = Database::fetch("SELECT * FROM cashier_sessions WHERE session_id = :id AND status = 'OPEN'", ['id' => $sessionId]);
if (!$session) { echo json_encode(['success' => false, 'error' => 'No active session found.']); exit; }

// Process transaction with retry for duplicate key errors
$maxRetries = 50;
$lastError = null;

for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    try {
        // Generate unique order code: ORD-YYYYMMDD-HHMM-###
        $orderCode = 'ORD-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));

        // Start database transaction
        Database::connection()->beginTransaction();

        // --- Compute totals across all tickets + services ---
        $ticketsTotal   = array_sum(array_column($tickets, 'total_amount'));
        $servicesTotal  = array_sum(array_column($services, 'total_amount'));
        $discountTotal  = array_sum(array_column($tickets, 'discount_amount'));
        $orderTotal     = $ticketsTotal + $servicesTotal;
        $totalPaid      = array_sum(array_column($payments, 'amount'));

        if ($totalPaid < $orderTotal) {
            Database::connection()->rollBack();
            echo json_encode(['success' => false, 'error' => "Payment (₱{$totalPaid}) is less than total (₱{$orderTotal})."]); exit;
        }

        // --- Create pos_orders record ---
        Database::execute(
            "INSERT INTO pos_orders
                (order_code, branch_id, cashier_session_id, created_by, subtotal, discount_total, grand_total, original_grand_total, amount_paid, change_amount, status, created_at)
             VALUES (:code, :branch, :session, :uid, :subtotal, :discount, :grand, :original, :paid, :change, 'completed', NOW())",
            [
                'code'     => $orderCode,
                'branch'   => $branchId,
                'session'  => $sessionId,
                'uid'      => $user['user_id'],
                'subtotal' => $orderTotal + $discountTotal,
                'discount' => $discountTotal,
                'grand'    => $orderTotal,
                'original' => $orderTotal,
                'paid'     => $totalPaid,
                'change'   => $totalPaid - $orderTotal,
            ]
        );
        $orderId = Database::connection()->lastInsertId();

        // --- Process each ticket ---
        $ticketTxnIds = [];
        $firstTxnCode = null;
        foreach ($tickets as $ticket) {
            // Generate per-ticket transaction code: TKT-YYYYMMDD-HHMM-###
            $txnCode = 'TKT-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
            if (!$firstTxnCode) $firstTxnCode = $txnCode;

            Database::execute(
                "INSERT INTO ticket_transactions
                    (transaction_code, wallet_id, passenger_id, origin, destination,
                     base_amount, service_fee, discount_amount, total_amount, status,
                     cashier_session_id, created_by, created_at)
                 VALUES (:code, :wallet, :passenger, :origin, :destination,
                         :base_amount, :service_fee, :discount_amount, :total_amount, 'booked',
                         :session, :uid, NOW())",
                [
                    'code'           => $txnCode,
                    'wallet'         => $ticket['wallet_id'] ?? null,
                    'passenger'      => $ticket['passenger_id'] ?? null,
                    'origin'         => $ticket['origin'] ?? null,
                    'destination'    => $ticket['destination'] ?? null,
                    'base_amount'    => floatval($ticket['base_amount'] ?? 0),
                    'service_fee'    => floatval($ticket['service_fee'] ?? 0),
                    'discount_amount'=> floatval($ticket['discount_amount'] ?? 0),
                    'total_amount'   => floatval($ticket['total_amount'] ?? 0),
                    'session'        => $sessionId,
                    'uid'            => $user['user_id'],
                ]
            );
            $ticketTxnId = Database::connection()->lastInsertId();
            $ticketTxnIds[] = $ticketTxnId;

            // --- Write order item for this ticket ---
            Database::execute(
                "INSERT INTO pos_order_items
                    (order_id, item_type, reference_id, transaction_code, total_amount, created_at)
                 VALUES (:oid, 'TICKET', :ref, :code, :total, NOW())",
                [
                    'oid'   => $orderId,
                    'ref'   => $ticketTxnId,
                    'code'  => $txnCode,
                    'total' => floatval($ticket['total_amount'] ?? 0),
                ]
            );

            logActivity($user['user_id'], 'CREATE_TICKET_TRANSACTION', 'POS', $txnCode, null,
                ['order_code' => $orderCode, 'ticket_id' => $ticketTxnId, 'wallet_id' => $ticket['wallet_id'] ?? null]);

            // --- Wallet balance deduction (Base Amount only, NOT Service Fee) ---
            $walletId   = $ticket['wallet_id'] ?? null;
            $baseAmount = floatval($ticket['base_amount'] ?? 0);

            if ($walletId && $baseAmount > 0) {
                $wallet = Database::fetch(
                    "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active'",
                    ['wid' => $walletId]
                );
                if (!$wallet) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Wallet not found or inactive.']); exit;
                }
                if ($wallet['current_balance'] < $baseAmount) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Insufficient wallet balance. Required: ₱' . number_format($baseAmount, 2) . ', Available: ₱' . number_format($wallet['current_balance'], 2)]); exit;
                }

                $balanceBefore = $wallet['current_balance'];
                $balanceAfter  = $balanceBefore - $baseAmount;

                Database::execute(
                    "UPDATE provider_wallets SET current_balance = :new_balance, updated_at = NOW() WHERE wallet_id = :wid",
                    ['new_balance' => $balanceAfter, 'wid' => $walletId]
                );

                $walletTxnCode = 'ADJ-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
                Database::execute(
                    "INSERT INTO wallet_transactions
                        (wallet_id, txn_code, txn_type, direction, amount, balance_before, balance_after, reference_table, reference_id, remarks, created_by, created_at)
                     VALUES (:wid, :code, 'ADJUSTMENT', 'OUT', :amount, :before, :after, 'ticket_transactions', :ref_id, :remarks, :uid, NOW())",
                    [
                        'wid'    => $walletId,
                        'code'   => $walletTxnCode,
                        'amount' => $baseAmount,
                        'before' => $balanceBefore,
                        'after'  => $balanceAfter,
                        'ref_id' => $ticketTxnId,
                        'remarks'=> "Ticket sale - Base Amount only. Order: {$orderCode}, Txn: {$txnCode}",
                        'uid'    => $user['user_id'],
                    ]
                );

                logActivity($user['user_id'], 'WALLET_DEDUCTION', 'POS', $txnCode, null,
                    ['wallet_id' => $walletId, 'amount' => $baseAmount, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter]);
            }

            // --- Record payment against ticket ---
            foreach ($payments as $pay) {
                $methodId    = $pay['payment_method_id'] ?? null;
                $amount      = floatval($pay['amount'] ?? 0);
                $refNum      = $pay['reference_number'] ?? null;
                $bankAcctId  = $pay['bank_account_id'] ?? null;
                $passengerId = $pay['passenger_id'] ?? null;
                if (!$methodId || $amount <= 0) continue;

                $methodInfo    = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $methodId]);
                $confirmStatus = ($methodInfo && $methodInfo['requires_confirmation']) ? 'PENDING' : 'NOT_REQUIRED';
                $methodType    = $methodInfo['method_type'] ?? '';
                $tracksCredit  = !empty($methodInfo['tracks_credit']);

                // Auto-resolve passenger_id: use payment passenger_id or fall back to ticket's passenger
                $resolvedPassengerId = $passengerId ?: ($ticket['passenger_id'] ?? null);

                // Handle credit-tracking payments — post to customer_charges
                if ($tracksCredit && $resolvedPassengerId) {
                    $existingCharge = Database::fetch("SELECT * FROM customer_charges WHERE passenger_id = :pid", ['pid' => $resolvedPassengerId]);
                    if (!$existingCharge) {
                        Database::execute(
                            "INSERT INTO customer_charges (passenger_id, total_charged, total_paid, balance, status, last_charge_date)
                             VALUES (:pid, 0, 0, 0, 'CLEAR', NOW())",
                            ['pid' => $resolvedPassengerId]
                        );
                    }
                    // Fixed CASE WHEN END
                    Database::execute(
                        "UPDATE customer_charges
                         SET total_charged = total_charged + :amt1,
                             balance = balance + :amt2,
                             status = CASE WHEN (balance + :amt3) > 0 THEN 'OUTSTANDING' ELSE 'CLEAR' END,
                             last_charge_date = NOW(),
                             updated_at = NOW()
                         WHERE passenger_id = :pid",
                        ['pid' => $resolvedPassengerId, 'amt1' => $amount, 'amt2' => $amount, 'amt3' => $amount]
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
                        'passenger' => $resolvedPassengerId ?: null,
                        'session'   => $sessionId,
                        'uid'       => $user['user_id'],
                    ]
                );
            }
        }

        // --- Create service transactions ---
        $serviceTxnIds = [];
        foreach ($services as $svc) {
            $serviceTypeId = $svc['service_type_id'] ?? null;
            if (!$serviceTypeId) continue;

            // Generate per-service transaction code: SVC-YYYYMMDD-HHMM-###
            $svcCode = 'SVC-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));

            Database::execute(
                "INSERT INTO service_transactions
                    (transaction_code, branch_id, service_type_id, passenger_id, description,
                     quantity, unit_price, total_amount, status, cashier_session_id, created_by, created_at)
                 VALUES (:code, :branch, :stype, :passenger, :desc, :qty, :price, :total, 'completed', :session, :uid, NOW())",
                [
                    'code'      => $svcCode,
                    'branch'    => $branchId,
                    'stype'     => $serviceTypeId,
                    'passenger' => $svc['passenger_id'] ?? null,
                    'desc'      => $svc['description'] ?? null,
                    'qty'       => intval($svc['quantity'] ?? 1),
                    'price'     => floatval($svc['unit_price'] ?? 0),
                    'total'     => floatval($svc['total_amount'] ?? 0),
                    'session'   => $sessionId,
                    'uid'       => $user['user_id'],
                ]
            );
            $serviceTxnId    = Database::connection()->lastInsertId();
            $serviceTxnIds[] = $serviceTxnId;

            // --- Write order item for this service ---
            Database::execute(
                "INSERT INTO pos_order_items
                    (order_id, item_type, reference_id, transaction_code, total_amount, created_at)
                 VALUES (:oid, 'SERVICE', :ref, :code, :total, NOW())",
                [
                    'oid'   => $orderId,
                    'ref'   => $serviceTxnId,
                    'code'  => $svcCode,
                    'total' => floatval($svc['total_amount'] ?? 0),
                ]
            );

            logActivity($user['user_id'], 'CREATE_SERVICE_TRANSACTION', 'POS', $svcCode, null,
                ['order_code' => $orderCode, 'service_txn_id' => $serviceTxnId, 'service_type_id' => $serviceTypeId]);
        }

        // --- Update cashier session totals ---
        Database::execute(
            "UPDATE cashier_sessions SET total_sales = total_sales + :total WHERE session_id = :id",
            ['total' => $orderTotal, 'id' => $sessionId]
        );

        logActivity($user['user_id'], 'PROCESS_ORDER', 'POS', $orderCode, null,
            ['order_code' => $orderCode, 'order_id' => $orderId, 'total' => $orderTotal, 'tickets' => count($ticketTxnIds), 'services' => count($serviceTxnIds)]);

        // Commit transaction
        Database::connection()->commit();

        echo json_encode([
            'success'          => true,
            'message'          => 'Order processed successfully.',
            'transaction_code' => $orderCode,
            'order_id'         => $orderId,
            'ticket_ids'       => $ticketTxnIds,
            'total'            => $orderTotal,
            'paid'             => $totalPaid,
            'change'           => $totalPaid - $orderTotal,
        ]);

        exit;

    } catch (Exception $e) {
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();
        }

        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $lastError = $e->getMessage();
            continue;
        }

        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage(), 'debug' => $e->getTraceAsString()]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Failed to process order after ' . $maxRetries . ' attempts. Last error: ' . $lastError]);
