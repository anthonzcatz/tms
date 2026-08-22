<?php
/**
 * POS Service Cancellation Approval API
 *
 * APPROVE:
 *   - Marks service_transactions as cancelled.
 *   - Reverses customer_charges by the stored charge_amount (CHARGE payment portion).
 *   - Zeros out pos_order_items and increments pos_orders.total_refunded_amount.
 *   - Inserts a service_refunds record.
 *
 * REJECT:
 *   - Marks the service_cancellation as rejected.
 *   - Reverses the pending total_refunds_wallet from the cashier session.
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
    'error' => 'Service cancellation approval is disabled. POS cancellation is available for tickets only.'
]);
exit;

$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input           = json_decode(file_get_contents('php://input'), true);
$cancellationId  = $input['service_cancellation_id'] ?? null;
$action          = $input['action']            ?? null;
$rejectionReason = $input['rejection_reason']  ?? null;
$remarks         = trim($input['remarks']      ?? '');

if (!$cancellationId) { echo json_encode(['success' => false, 'error' => 'Service cancellation ID required.']); exit; }
if (!$action || !in_array($action, ['approve', 'reject'])) { echo json_encode(['success' => false, 'error' => 'Action must be either "approve" or "reject".']); exit; }
if ($action === 'reject' && !$rejectionReason) { echo json_encode(['success' => false, 'error' => 'Rejection reason required when rejecting.']); exit; }

$cancellation = Database::fetch(
    "SELECT * FROM service_cancellations WHERE service_cancellation_id = :cid",
    ['cid' => $cancellationId]
);

if (!$cancellation) {
    echo json_encode(['success' => false, 'error' => 'Service cancellation request not found.']); exit;
}

if ($cancellation['status'] !== 'pending') {
    echo json_encode(['success' => false, 'error' => 'Service cancellation request has already been processed.']); exit;
}

$serviceTxn = Database::fetch(
    "SELECT * FROM service_transactions WHERE service_txn_id = :sid",
    ['sid' => $cancellation['service_transaction_id']]
);

if (!$serviceTxn) {
    echo json_encode(['success' => false, 'error' => 'Service transaction not found.']); exit;
}

if ($user['role_code'] !== 'SUPER_ADMIN' && (int)$user['branch_id'] !== (int)$serviceTxn['branch_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied: transaction does not belong to your branch']); exit;
}

$refundAmount     = floatval($cancellation['refund_amount']);
$chargeAmount     = floatval($cancellation['charge_amount'] ?? 0);
$cashRefundAmount = floatval($cancellation['cash_refund_amount'] ?? ($refundAmount - $chargeAmount));
$passengerId      = $serviceTxn['passenger_id'] ?? null;

Database::connection()->beginTransaction();

try {
    $lockedCancellation = Database::fetch(
        "SELECT * FROM service_cancellations WHERE service_cancellation_id = :cancellation_id FOR UPDATE",
        ['cancellation_id' => $cancellationId]
    );
    if (!$lockedCancellation || $lockedCancellation['status'] !== 'pending') {
        throw new RuntimeException('Service cancellation request has already been processed.');
    }
    $cancellation = $lockedCancellation;

    if ($action === 'approve') {
        $updateSql    = "UPDATE service_cancellations SET status = 'approved', approved_by = :uid, approved_at = NOW()";
        $updateParams = ['cid' => $cancellationId, 'uid' => $user['user_id']];
        if ($remarks) {
            $updateSql                .= ", remarks = :remarks";
            $updateParams['remarks']  = $remarks;
        }
        $updateSql .= " WHERE service_cancellation_id = :cid";
        Database::execute($updateSql, $updateParams);

        // Release the pending cash reservation before the shared refund
        // service posts the finalized cash amount.
        if ($cancellation['cashier_session_id'] && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions
                 SET pending_refunds_cash = GREATEST(0, COALESCE(pending_refunds_cash, 0) - :amount)
                 WHERE session_id = :session_id",
                ['amount' => $cashRefundAmount, 'session_id' => $cancellation['cashier_session_id']]
            );
        }

        $settings = Database::fetch(
            "SELECT cancellation_refund_processing_days FROM system_settings WHERE setting_id = 1"
        );
        $processingDays = $settings['cancellation_refund_processing_days'] ?? 0;
        $refundStatus   = $processingDays > 0 ? 'processing' : 'completed';

        // Mark service transaction as cancelled.
        Database::execute(
            "UPDATE service_transactions SET status = 'cancelled' WHERE service_txn_id = :sid",
            ['sid' => $serviceTxn['service_txn_id']]
        );

        $orderItem = Database::fetch(
            "SELECT oi.item_id, oi.order_id FROM pos_order_items oi
             WHERE oi.reference_id = :sid AND oi.item_type = 'SERVICE' LIMIT 1",
            ['sid' => $serviceTxn['service_txn_id']]
        );

        if ($orderItem) {
            Database::execute(
                "UPDATE pos_order_items
                 SET total_amount = GREATEST(0, total_amount - :ramount)
                 WHERE item_id = :iid",
                ['ramount' => $refundAmount, 'iid' => $orderItem['item_id']]
            );
            Database::execute(
                "UPDATE pos_orders
                 SET total_refunded_amount = COALESCE(total_refunded_amount, 0) + :ramount
                 WHERE order_id = :oid",
                ['ramount' => $refundAmount, 'oid' => $orderItem['order_id']]
            );
        }

        Database::execute(
            "INSERT INTO service_refunds
                (service_transaction_id, transaction_code, service_cancellation_id, passenger_id, refund_amount,
                 cash_amount, charge_reversal_amount, bank_amount, refund_method, status, requested_by, cashier_session_id,
                 requested_at, processed_by, processed_at)
             VALUES (:sid, :code, :cid, :pid, :ramount,
                     :camount, :cramount, 0, 'mixed', :status, :ruid, :rcsid, :rtime, :puid, NOW())",
            [
                'sid'     => $serviceTxn['service_txn_id'],
                'code'    => $serviceTxn['transaction_code'],
                'cid'     => $cancellationId,
                'pid'     => $passengerId,
                'ramount' => $refundAmount,
                'camount' => $cashRefundAmount,
                'cramount'=> $chargeAmount,
                'status'  => $refundStatus,
                'ruid'    => $cancellation['requested_by'],
                'rcsid'   => $cancellation['cashier_session_id'] ?? null,
                'rtime'   => $cancellation['requested_at'],
                'puid'    => $user['user_id'],
            ]
        );
        $serviceRefundId = (int) Database::lastInsertId();

        $allocationResult = RefundService::processPaymentAllocations(
            'SERVICE',
            $serviceRefundId,
            'SERVICE_TRANSACTION',
            (int) $serviceTxn['service_txn_id'],
            $refundAmount,
            (int) $user['user_id'],
            $cancellation['cashier_session_id'] ? (int) $cancellation['cashier_session_id'] : null,
            $passengerId ? (int) $passengerId : null,
            $remarks
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

        logActivity($user['user_id'], 'SERVICE_CANCELLATION_APPROVED', 'POS', $serviceTxn['transaction_code'],
            ['status' => 'pending'],
            ['status' => 'completed', 'service_cancellation_id' => $cancellationId,
             'service_txn_id' => $serviceTxn['service_txn_id'], 'refund_amount' => $refundAmount,
             'charge_amount' => $allocationResult['charge_amount'],
             'cash_refund_amount' => $allocationResult['cash_amount'],
             'bank_refund_amount' => $allocationResult['bank_amount'], 'remarks' => $remarks]);

        Database::connection()->commit();

        echo json_encode([
            'success'          => true,
            'message'          => 'Service cancellation approved and source refund processed.',
            'service_cancellation_id' => $cancellationId,
            'transaction_code' => $serviceTxn['transaction_code'],
            'refund_amount'    => $refundAmount,
            'charge_amount'    => $allocationResult['charge_amount'],
            'cash_refund_amount' => $allocationResult['cash_amount'],
            'bank_refund_amount' => $allocationResult['bank_amount'],
            'refund_status'    => $refundStatus,
        ]);

    } else {
        $updateSql    = "UPDATE service_cancellations SET status = 'rejected', approved_by = :uid, approved_at = NOW(), rejection_reason = :reason";
        $updateParams = ['cid' => $cancellationId, 'uid' => $user['user_id'], 'reason' => $rejectionReason];
        if ($remarks) {
            $updateSql                .= ", remarks = :remarks";
            $updateParams['remarks']  = $remarks;
        }
        $updateSql .= " WHERE service_cancellation_id = :cid";
        Database::execute($updateSql, $updateParams);

        if ($cancellation['cashier_session_id'] && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions
                 SET pending_refunds_cash = GREATEST(0, COALESCE(pending_refunds_cash, 0) - :amount)
                 WHERE session_id = :session_id",
                ['amount' => $cashRefundAmount, 'session_id' => $cancellation['cashier_session_id']]
            );
        }

        logActivity($user['user_id'], 'SERVICE_CANCELLATION_REJECTED', 'POS', $serviceTxn['transaction_code'],
            ['status' => 'pending'],
            ['status' => 'rejected', 'service_cancellation_id' => $cancellationId,
             'service_txn_id' => $serviceTxn['service_txn_id'],
             'rejection_reason' => $rejectionReason, 'remarks' => $remarks]);

        Database::connection()->commit();

        echo json_encode([
            'success'                => true,
            'message'                => 'Service cancellation request rejected. Service remains active.',
            'service_cancellation_id'=> $cancellationId,
            'transaction_code'     => $serviceTxn['transaction_code'],
            'rejection_reason'       => $rejectionReason,
        ]);
    }

} catch (Throwable $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Approval failed: ' . $e->getMessage()]);
}
