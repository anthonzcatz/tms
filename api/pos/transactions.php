<?php
/**
 * POS Transactions API — Process service transactions with mixed payments
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BIRHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BalanceLedgerService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/ChargeService.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';

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
$branchId  = $input['branch_id']  ?? null;
$items     = $input['items']      ?? [];
$payments  = $input['payments']   ?? [];

// Validate
if (!$sessionId)        { echo json_encode(['success' => false, 'error' => 'Session ID required.']); exit; }
if (!$branchId)         { echo json_encode(['success' => false, 'error' => 'Branch ID required.']); exit; }
if (empty($items))      { echo json_encode(['success' => false, 'error' => 'No items in order.']); exit; }
if (empty($payments))   { echo json_encode(['success' => false, 'error' => 'No payment provided.']); exit; }

// Verify the active session and branch against current server-side access.
try {
    $session = PosAccess::assertSessionForTransaction((int) $sessionId, (int) $branchId, $user);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Compute totals
$orderTotal = 0;
foreach ($items as $item) {
    $orderTotal += floatval($item['total_amount'] ?? ($item['quantity'] * $item['unit_price']));
}
$totalPaid = array_sum(array_column($payments, 'amount'));

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
    echo json_encode(['success' => false, 'error' => "Payment (₱{$totalPaid}) is less than total (₱{$orderTotal})."]); exit;
}

function generateServiceTxnCode() {
    return 'SVC-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
}

function generateOrderCode() {
    // Random suffix avoids the check-then-increment race under concurrent cashiers.
    return 'ORD-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(5)));
}

try {
        // Start database transaction
        Database::connection()->beginTransaction();

        // --- Create pos_orders record: ORD-YYYYMMDD-HHMM-### (sequential) ---
        $orderCode = generateOrderCode();

        // For service transactions: total_add_ons = order total, total_profit = order total (no costs)
        $totalCost = 0;
        $totalServiceFees = 0;
        $totalAddOns = $orderTotal;
        $totalProfit = $orderTotal;

        Database::execute(
            "INSERT INTO pos_orders
                (order_code, branch_id, cashier_session_id, created_by,
                 subtotal, discount_total, total_cost, total_service_fees, total_add_ons, total_profit,
                 grand_total, original_grand_total, amount_paid, change_amount,
                 payment_method, cashier_name, cashier_user_id,
                 payment_method_ids, payment_methods_json, status, created_at)
             VALUES (:code, :branch, :session, :uid,
                     :subtotal, 0, :total_cost, :total_service_fees, :total_add_ons, :total_profit,
                     :grand, :original, :paid, :change,
                     :payment_method, :cashier_name, :cashier_user_id,
                     :payment_method_ids, :payment_methods_json, 'completed', :created_at)",
            [
                'code'                => $orderCode,
                'branch'              => $branchId,
                'session'             => $sessionId,
                'uid'                 => $user['user_id'],
                'subtotal'            => $orderTotal,
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

        // --- BIR Integration: Assign OR Number and Create VAT Transaction ---
        $orData = null;
        $vatData = null;
        
        // Get VAT type from input (default to 12_percent)
        $vatType = $input['vat_type'] ?? '12_percent';
        $exemptionType = $input['exemption_type'] ?? null;
        $exemptionIdNumber = $input['exemption_id_number'] ?? null;
        $exemptionName = $input['exemption_name'] ?? null;
        
        // Assign OR number if auto-assignment is enabled
        if (BIRHelper::isAutoORAssignmentEnabled()) {
            $orData = BIRHelper::assignORNumber($orderId, $branchId, $user['user_id']);
            if (!$orData) {
                error_log("Failed to assign OR number for order $orderId");
            }
        }
        
        // Create VAT transaction
        $vatData = BIRHelper::createVATTransaction($orderId, $orderTotal, $vatType, $exemptionType, $exemptionIdNumber, $exemptionName);
        if (!$vatData) {
            error_log("Failed to create VAT transaction for order $orderId");
        }
        
        // Log audit trail for order creation
        BIRHelper::logAuditTrail($orderId, $user['user_id'], 'create', 'pos_orders', $orderId, null, null, null, 'POS order created');

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
                 VALUES (:code, :branch, :stype, :passenger, :desc, :qty, :price, :total, 'completed', :session, :uid, :created_at)",
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
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
            $serviceTxnId    = Database::connection()->lastInsertId();
            $createdTxnIds[] = $serviceTxnId;

            // --- Write pos_order_items for this service ---
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
                    'code'            => $itemCode,
                    'service_type_id' => $serviceTypeId,
                    'passenger_id'    => $passengerId,
                    'description'     => $description,
                    'quantity'        => $qty,
                    'unit_price'      => $unitPrice,
                    'total'           => $totalAmt,
                    'created_at'      => date('Y-m-d H:i:s'),
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

            if (in_array($methodType, ['BANK_TRANSFER', 'E_WALLET'], true) && !$bankAcctId) {
                Database::connection()->rollBack();
                echo json_encode(['success' => false, 'error' => 'Please select a bank account for ' . ($methodInfo['method_name'] ?? 'this payment method') . '.']); exit;
            }

            // Handle credit-tracking payments — post to customer_charges
            // Service charges are recorded as base only (service fee handling is ticket-only).
            if ($tracksCredit && $passengerId) {
                ChargeService::addToCustomerCharge($passengerId, $amount, $amount, 0);
            }

            Database::execute(
                "INSERT INTO transaction_payments
                    (source_type, source_id, payment_method_id, bank_account_id, amount, reference_number,
                     payment_date, confirmation_status, charged_to_passenger_id, cashier_session_id, created_by, created_at)
                 VALUES ('SERVICE_TRANSACTION', :src, :method, :bank, :amount, :ref, CURDATE(), :confirm, :passenger, :session, :uid, :created_at)",
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
                    'created_at' => date('Y-m-d H:i:s'),
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

        // Commit transaction before notifying other POS clients.
        Database::connection()->commit();

        PusherService::triggerBranch((int) $branchId, 'pos.transaction.completed', [
            'branch_id' => (int) $branchId,
            'order_id' => (int) $orderId,
            'transaction_code' => $orderCode,
            'wallet_ids' => [],
            'completed_at' => date(DATE_ATOM),
        ]);

        echo json_encode([
            'success'          => true,
            'message'          => 'Transaction processed successfully.',
            'transaction_code' => $orderCode,
            'order_id'         => $orderId,
            'total'            => $orderTotal,
            'paid'             => $totalPaid,
            'change'           => $totalPaid - $orderTotal,
            'or_number'        => $orData['or_full_number'] ?? null,
            'or_id'            => $orData['or_id'] ?? null,
            'vat_data'         => $vatData ?? null,
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
