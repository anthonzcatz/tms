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

// Process transaction with retry for duplicate key errors
$maxRetries = 50;
$lastError = null;

for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
    try {
        // Generate unique transaction code with date, time, microseconds, and random suffix
        // Format: SVC-YYYYMMDD-HHMMSS-XXX-YY (26 chars total)
        $microtime = microtime(true);
        $micro = sprintf('%03d', ($microtime - floor($microtime)) * 1000);
        $random = sprintf('%02d', mt_rand(0, 99));
        $txnCode = 'SVC-' . date('Ymd-His') . '-' . $micro . '-' . $random;

        // Start database transaction
        Database::connection()->beginTransaction();

        // --- BEGIN: Process each item as a service_transaction ---
        $createdTxnIds = [];

        foreach ($items as $item) {
            $serviceTypeId = $item['service_type_id'] ?? null;
            if (!$serviceTypeId) continue;

            $qty        = intval($item['quantity'] ?? 1);
            $unitPrice  = floatval($item['unit_price'] ?? 0);
            $totalAmt   = floatval($item['total_amount'] ?? ($qty * $unitPrice));
            $description = $item['description'] ?? null;

            Database::execute(
                "INSERT INTO service_transactions
                    (transaction_code, branch_id, service_type_id, description, quantity, unit_price, total_amount,
                     status, cashier_session_id, created_by, created_at)
                 VALUES (:code, :branch, :stype, :desc, :qty, :price, :total, 'completed', :session, :uid, NOW())",
                [
                    'code'    => $txnCode,
                    'branch'  => $branchId,
                    'stype'   => $serviceTypeId,
                    'desc'    => $description,
                    'qty'     => $qty,
                    'price'   => $unitPrice,
                    'total'   => $totalAmt,
                    'session' => $sessionId,
                    'uid'     => $user['user_id'],
                ]
            );
            $createdTxnIds[] = Database::connection()->lastInsertId();
        }

        if (empty($createdTxnIds)) {
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
            $methodType = $methodInfo['method_type'] ?? '';

            // Handle CHARGE payments - create/update customer_charges
            if ($methodType === 'CHARGE' && $passengerId) {
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
                // Update customer_charges
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

        logActivity($user['user_id'], 'PROCESS_TRANSACTION', 'POS', $txnCode, null,
            ['transaction_code' => $txnCode, 'total' => $orderTotal, 'items' => count($createdTxnIds)]);

        // Commit transaction
        Database::connection()->commit();

        echo json_encode([
            'success'          => true,
            'message'          => 'Transaction processed successfully.',
            'transaction_code' => $txnCode,
            'total'            => $orderTotal,
            'paid'             => $totalPaid,
            'change'           => $totalPaid - $orderTotal,
        ]);

        // Success - exit the retry loop
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();
        }

        // Log the full error for debugging
        error_log('POS Transaction Error: ' . $e->getMessage());
        error_log('SQL Query: ' . $e->getCode());

        // Check if it's a duplicate key error
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'transaction_code') !== false) {
            $lastError = $e->getMessage();
            // Retry with a new code
            continue;
        }

        // Other error - don't retry
        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
        exit;
    }
}

// Max retries exceeded
echo json_encode(['success' => false, 'error' => 'Failed to process transaction after ' . $maxRetries . ' attempts due to duplicate transaction codes. Last error: ' . $lastError]);
