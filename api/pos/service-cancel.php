<?php
/**
 * POS Service Cancel/Return API — Handle service transaction cancellation with charge reversal.
 *
 * Services are not wallet-backed or stock-controlled, so cancellation only:
 *  - Marks the service_transaction as cancelled
 *  - Reverses the CHARGE payment portion from customer_charges
 *  - Zeros the pos_order_items row
 *  - Updates pos_orders.total_refunded_amount
 *  - Inserts a service_refunds record
 *  - Tracks the pending cash refund in the cashier session
 *
 * Note: In the current POS flow, all payments for a service order are recorded
 * against the FIRST service transaction of the order. Cancelling a non-primary
 * service transaction therefore reverses no charge debt (correct: the debt belongs
 * to the order). Cancelling the primary transaction reverses the entire order's
 * charge payments; use this endpoint for per-item refunds only when the charge
 * portion is not affected.
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/RefundService.php';
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

/**
 * Compute total CHARGE payment amount recorded for a service transaction.
 */
function computeServiceChargePaymentsTotal(int $serviceTxnId): float
{
    return floatval(Database::fetch(
        "SELECT COALESCE(SUM(tp.amount), 0) AS total
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         WHERE tp.source_type = 'SERVICE_TRANSACTION'
           AND tp.source_id   = :sid
           AND pm.tracks_credit = 1",
        ['sid' => $serviceTxnId]
    )['total'] ?? 0);
}

/**
 * Reverse a customer's outstanding charge balance by the given amount.
 */
function reverseCustomerCharge(?int $passengerId, float $chargeAmount): void
{
    if ($chargeAmount <= 0 || !$passengerId) {
        return;
    }

    $chargeRow = Database::fetch(
        "SELECT * FROM customer_charges WHERE passenger_id = :pid FOR UPDATE",
        ['pid' => $passengerId]
    );

    if (!$chargeRow) {
        return;
    }

    $newBalance = max(0, floatval($chargeRow['balance']) - $chargeAmount);
    $newCharged = max(0, floatval($chargeRow['total_charged']) - $chargeAmount);
    $newStatus  = $newBalance <= 0 ? 'CLEAR' : $chargeRow['status'];

    Database::execute(
        "UPDATE customer_charges
         SET total_charged = :charged,
             balance       = :balance,
             status        = :status,
             updated_at    = NOW()
         WHERE passenger_id = :pid",
        [
            'charged' => $newCharged,
            'balance' => $newBalance,
            'status'  => $newStatus,
            'pid'     => $passengerId,
        ]
    );
}

Auth::requireLogin();
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'Service cancellation is disabled. POS cancellation is available for tickets only.'
]);
exit;

$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$txnCode      = $input['transaction_code'] ?? null;
$refundAmount = $input['refund_amount'] ?? null;
$reason       = $input['reason'] ?? null;

if (!$txnCode) { echo json_encode(['success' => false, 'error' => 'Transaction code required.']); exit; }
if (!$refundAmount || floatval($refundAmount) <= 0) { echo json_encode(['success' => false, 'error' => 'Refund amount must be greater than 0.']); exit; }

$refundAmount = floatval($refundAmount);

$settings = Database::fetch(
    "SELECT cancellation_requires_confirmation, cancellation_refund_processing_days
     FROM system_settings WHERE setting_id = 1"
);
$requiresConfirmation = $settings['cancellation_requires_confirmation'] ?? 1;

$serviceTxn = Database::fetch(
    "SELECT * FROM service_transactions WHERE transaction_code = :code",
    ['code' => $txnCode]
);

if (!$serviceTxn) {
    echo json_encode(['success' => false, 'error' => 'Service transaction not found.']); exit;
}

if ($serviceTxn['status'] === 'cancelled' || $serviceTxn['status'] === 'refunded') {
    echo json_encode(['success' => false, 'error' => 'Service transaction is already cancelled/refunded.']); exit;
}

if ($user['role_code'] !== 'SUPER_ADMIN' && (int)$user['branch_id'] !== (int)$serviceTxn['branch_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied: transaction does not belong to your branch']); exit;
}

$serviceTxnId = (int) $serviceTxn['service_txn_id'];
$passengerId  = $serviceTxn['passenger_id'] ?? null;
$totalAmount  = floatval($serviceTxn['total_amount'] ?? 0);

if ($refundAmount > $totalAmount) {
    echo json_encode(['success' => false, 'error' => 'Refund amount cannot exceed the original Total Amount (₱' . number_format($totalAmount, 2) . ').']); exit;
}

try {
    $refundPreview = RefundService::previewPaymentAllocations(
        'SERVICE_TRANSACTION',
        $serviceTxnId,
        $refundAmount
    );
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Refund allocation failed: ' . $e->getMessage()]);
    exit;
}
$chargeAmount = (float) ($refundPreview['charge_amount'] ?? 0);
$cashRefundAmount = (float) ($refundPreview['cash_amount'] ?? 0);

$cashierSession = Database::fetch(
    "SELECT session_id FROM cashier_sessions
     WHERE cashier_user_id = :uid AND status = 'OPEN'
     ORDER BY started_at DESC LIMIT 1",
    ['uid' => $user['user_id']]
);
$cashierSessionId = $cashierSession ? $cashierSession['session_id'] : null;

$orderItem = Database::fetch(
    "SELECT oi.item_id, oi.order_id FROM pos_order_items oi
     WHERE oi.reference_id = :sid AND oi.item_type = 'SERVICE' LIMIT 1",
    ['sid' => $serviceTxnId]
);

Database::connection()->beginTransaction();

try {
    $lockedServiceTxn = Database::fetch(
        "SELECT * FROM service_transactions WHERE service_txn_id = :service_txn_id FOR UPDATE",
        ['service_txn_id' => $serviceTxnId]
    );
    if (!$lockedServiceTxn || in_array($lockedServiceTxn['status'], ['cancelled', 'refunded'], true)) {
        throw new RuntimeException('Service transaction is already cancelled/refunded.');
    }
    $pendingCheck = Database::fetch(
        "SELECT service_cancellation_id FROM service_cancellations
         WHERE service_transaction_id = :service_txn_id AND status = 'pending'
         LIMIT 1",
        ['service_txn_id' => $serviceTxnId]
    );
    if ($pendingCheck) {
        throw new RuntimeException('There is already a pending cancellation request for this service.');
    }
    $serviceTxn = $lockedServiceTxn;

    $cancellationType = ($refundAmount < $totalAmount) ? 'partial' : 'full';

    // Insert the cancellation record.
    if ($requiresConfirmation) {
        Database::execute(
            "INSERT INTO service_cancellations
                (service_transaction_id, transaction_code, order_id, passenger_id, reason, cancellation_type,
                 refund_amount, charge_amount, cash_refund_amount, status, requested_by, cashier_session_id, requested_at)
             VALUES (:sid, :code, :oid, :pid, :reason, :ctype,
                     :ramount, :camount, :cramount, 'pending', :uid, :csid, NOW())",
            [
                'sid'     => $serviceTxnId,
                'code'    => $serviceTxn['transaction_code'],
                'oid'     => $orderItem['order_id'] ?? null,
                'pid'     => $passengerId,
                'reason'  => $reason,
                'ctype'   => $cancellationType,
                'ramount' => $refundAmount,
                'camount' => $chargeAmount,
                'cramount'=> $cashRefundAmount,
                'uid'     => $user['user_id'],
                'csid'    => $cashierSessionId,
            ]
        );

        if ($cashierSessionId && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions
                 SET pending_refunds_cash = COALESCE(pending_refunds_cash, 0) + :amount
                 WHERE session_id = :session_id",
                ['amount' => $cashRefundAmount, 'session_id' => $cashierSessionId]
            );
        }

        Database::connection()->commit();

        echo json_encode([
            'success'                => true,
            'message'                => 'Service cancellation request submitted. Awaiting manager approval.',
            'service_txn_id'         => $serviceTxnId,
            'transaction_code'       => $serviceTxn['transaction_code'],
            'refund_amount'          => $refundAmount,
            'charge_amount'          => $chargeAmount,
            'status'                 => 'pending_confirmation',
            'requires_confirmation'  => true,
            'refund_source'          => 'original_payment_sources',
        ]);
        exit;
    }

    // Immediate cancellation path.
    Database::execute(
        "INSERT INTO service_cancellations
            (service_transaction_id, transaction_code, order_id, passenger_id, reason, cancellation_type,
             refund_amount, charge_amount, cash_refund_amount, status, requested_by, cashier_session_id,
             requested_at, approved_by, approved_at)
         VALUES (:sid, :code, :oid, :pid, :reason, :ctype,
                 :ramount, :camount, :cramount, 'approved', :uid, :csid,
                 NOW(), :uid2, NOW())",
        [
            'sid'     => $serviceTxnId,
            'code'    => $serviceTxn['transaction_code'],
            'oid'     => $orderItem['order_id'] ?? null,
            'pid'     => $passengerId,
            'reason'  => $reason,
            'ctype'   => $cancellationType,
            'ramount' => $refundAmount,
            'camount' => $chargeAmount,
            'cramount'=> $cashRefundAmount,
            'uid'     => $user['user_id'],
            'uid2'    => $user['user_id'],
            'csid'    => $cashierSessionId,
        ]
    );

    $cancellationId = Database::connection()->lastInsertId();

    // Mark service transaction as cancelled.
    Database::execute(
        "UPDATE service_transactions SET status = 'cancelled' WHERE service_txn_id = :sid",
        ['sid' => $serviceTxnId]
    );

    // Reduce the order item by the entered refund amount and update the
    // parent order. Source-specific charge/bank/cash effects are handled by
    // RefundService below.
    if ($orderItem) {
        Database::execute(
            "UPDATE pos_order_items
             SET total_amount = GREATEST(0, total_amount - :ramount)
             WHERE item_id = :iid",
            ['ramount' => $refundAmount, 'iid' => $orderItem['item_id']]
        );
        Database::execute(
            "UPDATE pos_orders SET total_refunded_amount = COALESCE(total_refunded_amount, 0) + :ramount WHERE order_id = :oid",
            ['ramount' => $refundAmount, 'oid' => $orderItem['order_id']]
        );
    }

    $processingDays = $settings['cancellation_refund_processing_days'] ?? 0;
    $refundStatus   = $processingDays > 0 ? 'processing' : 'completed';

    Database::execute(
        "INSERT INTO service_refunds
            (service_transaction_id, transaction_code, service_cancellation_id, passenger_id, refund_amount,
             cash_amount, charge_reversal_amount, bank_amount, refund_method, status, requested_by, cashier_session_id,
             requested_at, processed_by, processed_at)
         VALUES (:sid, :code, :cid, :pid, :ramount,
                 :camount, :cramount, 0, 'mixed', :status, :ruid, :rcsid, NOW(), :puid, NOW())",
        [
            'sid'     => $serviceTxnId,
            'code'    => $serviceTxn['transaction_code'],
            'cid'     => $cancellationId,
            'pid'     => $passengerId,
            'ramount' => $refundAmount,
            'camount' => $cashRefundAmount,
            'cramount'=> $chargeAmount,
            'status'  => $refundStatus,
            'ruid'    => $user['user_id'],
            'rcsid'   => $cashierSessionId,
            'puid'    => $user['user_id'],
        ]
    );
    $serviceRefundId = (int) Database::lastInsertId();

    $allocationResult = RefundService::processPaymentAllocations(
        'SERVICE',
        $serviceRefundId,
        'SERVICE_TRANSACTION',
        $serviceTxnId,
        $refundAmount,
        (int) $user['user_id'],
        $cashierSessionId ? (int) $cashierSessionId : null,
        $passengerId ? (int) $passengerId : null,
        $reason
    );

    Database::execute(
        "UPDATE service_cancellations
         SET charge_amount = :charge_amount,
             cash_refund_amount = :cash_refund_amount,
             status = 'completed',
             processed_at = NOW()
         WHERE service_cancellation_id = :cancellation_id",
        [
            'charge_amount' => $allocationResult['charge_amount'],
            'cash_refund_amount' => $allocationResult['cash_amount'],
            'cancellation_id' => $cancellationId,
        ]
    );

    logActivity($user['user_id'], 'SERVICE_CANCEL', 'POS', $serviceTxn['transaction_code'], null,
        ['service_cancellation_id' => $cancellationId, 'service_txn_id' => $serviceTxnId,
         'refund_amount' => $refundAmount, 'charge_amount' => $allocationResult['charge_amount'],
         'cash_refund_amount' => $allocationResult['cash_amount'],
         'bank_refund_amount' => $allocationResult['bank_amount']]);

    Database::connection()->commit();

    echo json_encode([
        'success'              => true,
        'message'              => 'Service cancelled successfully and source refund processed.',
        'service_txn_id'       => $serviceTxnId,
        'transaction_code'     => $serviceTxn['transaction_code'],
        'refund_amount'        => $refundAmount,
        'charge_amount'        => $allocationResult['charge_amount'],
        'cash_refund_amount'   => $allocationResult['cash_amount'],
        'bank_refund_amount'   => $allocationResult['bank_amount'],
        'refund_status'        => $refundStatus,
        'requires_confirmation'=> false,
    ]);

} catch (Throwable $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Cancellation failed: ' . $e->getMessage()]);
}
