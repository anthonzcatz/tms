<?php
/**
 * POS Tickets API — Process ticket bookings with mixed payments
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

function logActivity($userId, $action, $module, $ref = null, $old = null, $new = null) {
    $now = date('Y-m-d H:i:s');
    Database::execute(
        "INSERT INTO activity_logs (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES (:uid, NULL, :action, :mod, :ref, :ip, :old, :new, :created_at)",
        ['uid' => $userId, 'action' => $action, 'mod' => $module, 'ref' => $ref,
         'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
         'old' => $old ? json_encode($old) : null, 'new' => $new ? json_encode($new) : null,
         'created_at' => $now]
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

        // Pre-compute profit columns
        $totalCost        = array_sum(array_column($tickets, 'base_amount'));  // Cost paid to provider
        $totalServiceFees = array_sum(array_column($tickets, 'service_fee'));  // Service fees = profit
        $totalAddOns      = array_sum(array_column($services, 'total_amount')); // Add-ons = profit
        $totalProfit      = $totalServiceFees + $totalAddOns;

        // Denormalize cashier name from employee record
        $cashierName = null;
        $cashierUserId = $user['user_id'];
        $empRow = Database::fetch(
            "SELECT e.first_name, e.middle_name, e.last_name
             FROM user_accounts ua
             JOIN employees e ON e.emp_id = ua.emp_id
             WHERE ua.user_id = :uid",
            ['uid' => $cashierUserId]
        );
        if ($empRow) {
            $mid = !empty($empRow['middle_name']) ? ' ' . substr($empRow['middle_name'],0,1) . '.' : '';
            $cashierName = trim($empRow['first_name'] . $mid . ' ' . $empRow['last_name']);
        }
        if (!$cashierName) $cashierName = $user['username'] ?? null;

        // Denormalize all payment methods (handles multi-payment)
        $uniqueMethodIds = array_unique(array_filter(array_column($payments, 'payment_method_id')));
        $paymentMethodsDetail = [];
        foreach ($uniqueMethodIds as $pmId) {
            $pmRow = Database::fetch("SELECT method_id, method_name FROM payment_methods WHERE method_id = :id", ['id' => $pmId]);
            if ($pmRow) {
                $totalForMethod = array_sum(array_column(array_filter($payments, fn($p) => $p['payment_method_id'] == $pmId), 'amount'));
                $paymentMethodsDetail[] = ['method_id' => (int)$pmRow['method_id'], 'method_name' => $pmRow['method_name'], 'amount' => $totalForMethod];
            }
        }
        $primaryPaymentMethod  = implode(' + ', array_column($paymentMethodsDetail, 'method_name')) ?: null;
        $paymentMethodIds      = implode(',', array_column($paymentMethodsDetail, 'method_id')) ?: null;
        $paymentMethodsJson    = !empty($paymentMethodsDetail) ? json_encode($paymentMethodsDetail) : null;

        if ($totalPaid < $orderTotal) {
            Database::connection()->rollBack();
            echo json_encode(['success' => false, 'error' => "Payment (₱{$totalPaid}) is less than total (₱{$orderTotal})."]); exit;
        }

        // --- Create pos_orders record ---
        Database::execute(
            "INSERT INTO pos_orders
                (order_code, branch_id, cashier_session_id, created_by,
                 subtotal, discount_total, total_cost, total_service_fees, total_add_ons, total_profit,
                 grand_total, original_grand_total, amount_paid, change_amount,
                 payment_method, cashier_name, cashier_user_id,
                 payment_method_ids, payment_methods_json, status, created_at)
             VALUES (:code, :branch, :session, :uid,
                     :subtotal, :discount, :total_cost, :total_service_fees, :total_add_ons, :total_profit,
                     :grand, :original, :paid, :change,
                     :payment_method, :cashier_name, :cashier_user_id,
                     :payment_method_ids, :payment_methods_json, 'completed', :created_at)",
            [
                'code'                => $orderCode,
                'branch'              => $branchId,
                'session'             => $sessionId,
                'uid'                 => $user['user_id'],
                'subtotal'            => $orderTotal + $discountTotal,
                'discount'            => $discountTotal,
                'total_cost'          => $totalCost,
                'total_service_fees'  => $totalServiceFees,
                'total_add_ons'       => $totalAddOns,
                'total_profit'        => $totalProfit,
                'grand'               => $orderTotal,
                'original'            => $orderTotal,
                'paid'                => $totalPaid,
                'change'              => $totalPaid - $orderTotal,
                'payment_method'      => $primaryPaymentMethod,
                'cashier_name'        => $cashierName,
                'cashier_user_id'     => $cashierUserId,
                'payment_method_ids'  => $paymentMethodIds,
                'payment_methods_json'=> $paymentMethodsJson,
                'created_at'          => date('Y-m-d H:i:s'),
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
                    (transaction_code, wallet_id, branch_id, passenger_id, accommodation_id, discount_id,
                     origin, destination, travel_date, ticket_number,
                     base_amount, service_fee, discount_amount, total_amount, status,
                     cashier_session_id, created_by, created_at)
                 VALUES (:code, :wallet, :branch, :passenger, :accommodation_id, :discount_id,
                         :origin, :destination, :travel_date, :ticket_number,
                         :base_amount, :service_fee, :discount_amount, :total_amount, 'booked',
                         :session, :uid, :created_at)",
                [
                    'code'            => $txnCode,
                    'wallet'          => $ticket['wallet_id'] ?? null,
                    'branch'          => $branchId,
                    'passenger'       => $ticket['passenger_id'] ?? null,
                    'accommodation_id'=> $ticket['accommodation_id'] ?? null,
                    'discount_id'     => $ticket['discount_id'] ?? null,
                    'origin'          => $ticket['origin'] ?? null,
                    'destination'     => $ticket['destination'] ?? null,
                    'travel_date'     => $ticket['travel_date'] ?? null,
                    'ticket_number'   => $ticket['ticket_number'] ?? null,
                    'base_amount'     => floatval($ticket['base_amount'] ?? 0),
                    'service_fee'     => floatval($ticket['service_fee'] ?? 0),
                    'discount_amount' => floatval($ticket['discount_amount'] ?? 0),
                    'total_amount'    => floatval($ticket['total_amount'] ?? 0),
                    'session'         => $sessionId,
                    'uid'             => $user['user_id'],
                    'created_at'      => date('Y-m-d H:i:s'),
                ]
            );
            $ticketTxnId = Database::connection()->lastInsertId();
            $ticketTxnIds[] = $ticketTxnId;

            // --- Write order item for this ticket ---
            Database::execute(
                "INSERT INTO pos_order_items
                    (order_id, item_type, reference_id, transaction_code, ticket_number,
                     wallet_id, passenger_id, description,
                     unit_price, service_fee, discount_amount, total_amount,
                     origin, destination, travel_date, created_at)
                 VALUES (:oid, 'TICKET', :ref, :code, :ticket_number,
                         :wallet_id, :passenger_id, :description,
                         :unit_price, :service_fee, :discount_amount, :total,
                         :origin, :destination, :travel_date, :created_at)",
                [
                    'oid'             => $orderId,
                    'ref'             => $ticketTxnId,
                    'code'            => $txnCode,
                    'ticket_number'   => $ticket['ticket_number'] ?? $txnCode, // Fallback to transaction code if no ticket number
                    'wallet_id'       => $ticket['wallet_id'] ?? null,
                    'passenger_id'    => $ticket['passenger_id'] ?? null,
                    'description'     => $ticket['description'] ?? null,
                    'unit_price'      => floatval($ticket['base_amount'] ?? 0),
                    'service_fee'     => floatval($ticket['service_fee'] ?? 0),
                    'discount_amount' => floatval($ticket['discount_amount'] ?? 0),
                    'total'           => floatval($ticket['total_amount'] ?? 0),
                    'origin'          => $ticket['origin'] ?? null,
                    'destination'     => $ticket['destination'] ?? null,
                    'travel_date'     => $ticket['travel_date'] ?? null,
                    'created_at'      => date('Y-m-d H:i:s'),
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
                    "UPDATE provider_wallets SET current_balance = :new_balance, updated_at = :updated_at WHERE wallet_id = :wid",
                    ['new_balance' => $balanceAfter, 'updated_at' => date('Y-m-d H:i:s'), 'wid' => $walletId]
                );

                $walletTxnCode = 'ADJ-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
                Database::execute(
                    "INSERT INTO wallet_transactions
                        (wallet_id, txn_code, txn_type, direction, amount, balance_before, balance_after, reference_table, reference_id, remarks, created_by, created_at)
                     VALUES (:wid, :code, 'ADJUSTMENT', 'OUT', :amount, :before, :after, 'ticket_transactions', :ref_id, :remarks, :uid, :created_at)",
                    [
                        'wid'    => $walletId,
                        'code'   => $walletTxnCode,
                        'amount' => $baseAmount,
                        'before' => $balanceBefore,
                        'after'  => $balanceAfter,
                        'ref_id' => $ticketTxnId,
                        'remarks'=> "Ticket sale - Base Amount only. Order: {$orderCode}, Txn: {$txnCode}",
                        'uid'    => $user['user_id'],
                        'created_at' => date('Y-m-d H:i:s'),
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
                             VALUES (:pid, 0, 0, 0, 'CLEAR', :last_charge_date)",
                            ['pid' => $resolvedPassengerId, 'last_charge_date' => date('Y-m-d H:i:s')]
                        );
                    }
                    // Fixed CASE WHEN END
                    Database::execute(
                        "UPDATE customer_charges
                         SET total_charged = total_charged + :amt1,
                             balance = balance + :amt2,
                             status = CASE WHEN (balance + :amt3) > 0 THEN 'OUTSTANDING' ELSE 'CLEAR' END,
                             last_charge_date = :last_charge_date,
                             updated_at = :updated_at
                         WHERE passenger_id = :pid",
                        ['pid' => $resolvedPassengerId, 'amt1' => $amount, 'amt2' => $amount, 'amt3' => $amount, 'last_charge_date' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                    );
                }

                Database::execute(
                    "INSERT INTO transaction_payments
                        (source_type, source_id, payment_method_id, bank_account_id, amount, reference_number,
                         payment_date, confirmation_status, charged_to_passenger_id, cashier_session_id, created_by, created_at)
                     VALUES ('TICKET_TRANSACTION', :src, :method, :bank, :amount, :ref, CURDATE(), :confirm, :passenger, :session, :uid, :created_at)",
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
                        'created_at' => date('Y-m-d H:i:s'),
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
                 VALUES (:code, :branch, :stype, :passenger, :desc, :qty, :price, :total, 'completed', :session, :uid, :created_at)",
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
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
            $serviceTxnId    = Database::connection()->lastInsertId();
            $serviceTxnIds[] = $serviceTxnId;

            // --- Write order item for this service ---
            Database::execute(
                "INSERT INTO pos_order_items
                    (order_id, item_type, reference_id, transaction_code,
                     service_type_id, passenger_id, description,
                     quantity, unit_price, service_fee, discount_amount, total_amount, created_at)
                 VALUES (:oid, 'SERVICE', :ref, :code,
                         :service_type_id, :passenger_id, :description,
                         :quantity, :unit_price, 0, 0, :total, :created_at)",
                [
                    'oid'             => $orderId,
                    'ref'             => $serviceTxnId,
                    'code'            => $svcCode,
                    'service_type_id' => $svc['service_type_id'] ?? null,
                    'passenger_id'    => $svc['passenger_id'] ?? null,
                    'description'     => $svc['description'] ?? null,
                    'quantity'        => intval($svc['quantity'] ?? 1),
                    'unit_price'      => floatval($svc['unit_price'] ?? 0),
                    'total'           => floatval($svc['total_amount'] ?? 0),
                    'created_at'      => date('Y-m-d H:i:s'),
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
