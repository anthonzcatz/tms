<?php
/**
 * POS Transactions API — Process service transactions with mixed payments
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
$branchId  = $input['branch_id']  ?? null;
$items     = $input['items']      ?? [];
$payments  = $input['payments']   ?? [];

// Validate
if (!$sessionId)        { echo json_encode(['success' => false, 'error' => 'Session ID required.']); exit; }
if (!$branchId)         { echo json_encode(['success' => false, 'error' => 'Branch ID required.']); exit; }
if (empty($items))      { echo json_encode(['success' => false, 'error' => 'No items in order.']); exit; }
if (empty($payments))   { echo json_encode(['success' => false, 'error' => 'No payment provided.']); exit; }

// Verify session is open
$session = Database::fetch("SELECT * FROM cashier_sessions WHERE session_id = :id AND status = 'OPEN'", ['id' => $sessionId]);
if (!$session) { echo json_encode(['success' => false, 'error' => 'No active session found.']); exit; }

// Compute totals
$orderTotal = 0;
foreach ($items as $item) {
    $orderTotal += floatval($item['total_amount'] ?? ($item['quantity'] * $item['unit_price']));
}
$totalPaid = array_sum(array_column($payments, 'amount'));

if ($totalPaid < $orderTotal) {
    echo json_encode(['success' => false, 'error' => "Payment (₱{$totalPaid}) is less than total (₱{$orderTotal})."]); exit;
}

function generateServiceTxnCode() {
    return 'SVC-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
}

try {
        // Start database transaction
        Database::connection()->beginTransaction();

        // --- Create pos_orders record: ORD-YYYYMMDD-HHMM-### ---
        $orderCode = 'ORD-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));

        Database::execute(
            "INSERT INTO pos_orders
                (order_code, branch_id, cashier_session_id, created_by, subtotal, discount_total, grand_total, amount_paid, change_amount, status, created_at)
             VALUES (:code, :branch, :session, :uid, :subtotal, 0, :grand, :paid, :change, 'completed', NOW())",
            [
                'code'    => $orderCode,
                'branch'  => $branchId,
                'session' => $sessionId,
                'uid'     => $user['user_id'],
                'subtotal'=> $orderTotal,
                'grand'   => $orderTotal,
                'paid'    => $totalPaid,
                'change'  => $totalPaid - $orderTotal,
            ]
        );
        $orderId = Database::connection()->lastInsertId();

        // --- BEGIN: Process each item as a service_transaction ---
        $createdTxnIds = [];
        $txnCode = null;

        foreach ($items as $item) {
            $serviceTypeId = $item['service_type_id'] ?? null;
            if (!$serviceTypeId) continue;

            $qty         = intval($item['quantity'] ?? 1);
            $unitPrice   = floatval($item['unit_price'] ?? 0);
            $totalAmt    = floatval($item['total_amount'] ?? ($qty * $unitPrice));
            $description = $item['description'] ?? null;
            $passengerId = $item['passenger_id'] ?? null;

            // Generate a unique code per item, retry if duplicate
            $itemCode = null;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $itemCode = generateServiceTxnCode();
                $existing = Database::fetch(
                    "SELECT transaction_code FROM service_transactions WHERE transaction_code = :code",
                    ['code' => $itemCode]
                );
                if (!$existing) break;
                $itemCode = null;
                usleep(1000);
            }
            if (!$itemCode) {
                throw new Exception('Could not generate a unique transaction code after 10 attempts.');
            }
            if (!$txnCode) $txnCode = $itemCode;

            Database::execute(
                "INSERT INTO service_transactions
                    (transaction_code, branch_id, service_type_id, passenger_id, description, quantity, unit_price, total_amount,
                     status, cashier_session_id, created_by, created_at)
                 VALUES (:code, :branch, :stype, :passenger, :desc, :qty, :price, :total, 'completed', :session, :uid, NOW())",
                [
                    'code'      => $itemCode,
                    'branch'    => $branchId,
                    'stype'     => $serviceTypeId,
                    'passenger' => $passengerId,
                    'desc'      => $description,
                    'qty'       => $qty,
                    'price'     => $unitPrice,
                    'total'     => $totalAmt,
                    'session'   => $sessionId,
                    'uid'       => $user['user_id'],
                ]
            );
            $serviceTxnId    = Database::connection()->lastInsertId();
            $createdTxnIds[] = $serviceTxnId;

            // --- Write pos_order_items for this service ---
            Database::execute(
                "INSERT INTO pos_order_items
                    (order_id, item_type, reference_id, transaction_code, total_amount, created_at)
                 VALUES (:oid, 'SERVICE', :ref, :code, :total, NOW())",
                [
                    'oid'   => $orderId,
                    'ref'   => $serviceTxnId,
                    'code'  => $itemCode,
                    'total' => $totalAmt,
                ]
            );
        }

        if (empty($createdTxnIds)) {
            Database::connection()->rollBack();
            echo json_encode(['success' => false, 'error' => 'No valid items processed.']); exit;
        }

        // --- Record payments against first transaction (polymorphic: SERVICE_TRANSACTION) ---
        $primaryTxnId = $createdTxnIds[0];

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
            $methodType    = $methodInfo['method_type'] ?? '';
            $tracksCredit  = !empty($methodInfo['tracks_credit']);

            // Handle credit-tracking payments — post to customer_charges
            if ($tracksCredit && $passengerId) {
                // Ensure customer_charges record exists
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
                // Update customer_charges — fixed CASE WHEN END
                Database::execute(
                    "UPDATE customer_charges
                     SET total_charged = total_charged + :amt,
                         balance = balance + :amt,
                         status = CASE WHEN (balance + :amt) > 0 THEN 'OUTSTANDING' ELSE 'CLEAR' END,
                         last_charge_date = NOW(),
                         updated_at = NOW()
                     WHERE passenger_id = :pid",
                    ['pid' => $passengerId, 'amt' => $amount]
                );
            }

            Database::execute(
                "INSERT INTO transaction_payments
                    (source_type, source_id, payment_method_id, bank_account_id, amount, reference_number,
                     payment_date, confirmation_status, charged_to_passenger_id, cashier_session_id, created_by, created_at)
                 VALUES ('SERVICE_TRANSACTION', :src, :method, :bank, :amount, :ref, CURDATE(), :confirm, :passenger, :session, :uid, NOW())",
                [
                    'src'       => $primaryTxnId,
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

            // Update cashier session payment type breakdown based on method type
            switch ($methodType) {
                case 'CASH':
                    Database::execute("UPDATE cashier_sessions SET total_cash = total_cash + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                    break;
                case 'BANK_TRANSFER':
                    Database::execute("UPDATE cashier_sessions SET total_bank_transfer = total_bank_transfer + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                    break;
                case 'E_WALLET':
                    Database::execute("UPDATE cashier_sessions SET total_e_wallet = total_e_wallet + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                    break;
                case 'CHARGE':
                    Database::execute("UPDATE cashier_sessions SET total_charge = total_charge + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                    break;
                default:
                    Database::execute("UPDATE cashier_sessions SET total_other = total_other + :amt WHERE session_id = :id", ['amt' => $amount, 'id' => $sessionId]);
                    break;
            }
        }

        // --- Update cashier session totals ---
        Database::execute(
            "UPDATE cashier_sessions SET total_sales = total_sales + :total WHERE session_id = :id",
            ['total' => $orderTotal, 'id' => $sessionId]
        );

        logActivity($user['user_id'], 'PROCESS_TRANSACTION', 'POS', $orderCode, null,
            ['order_code' => $orderCode, 'total' => $orderTotal, 'items' => count($createdTxnIds)]);

        // Commit transaction
        Database::connection()->commit();

        echo json_encode([
            'success'          => true,
            'message'          => 'Transaction processed successfully.',
            'transaction_code' => $orderCode,
            'order_id'         => $orderId,
            'total'            => $orderTotal,
            'paid'             => $totalPaid,
            'change'           => $totalPaid - $orderTotal,
        ]);

        exit;

} catch (Exception $e) {
    // Rollback transaction on error
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }

    error_log('POS Transaction Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
    exit;
}
