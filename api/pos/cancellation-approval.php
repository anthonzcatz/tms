<?php
/**
 * POS Cancellation Approval API — Handle approval/rejection of pending ticket cancellations
 *
 * APPROVE:
 *   • Marks ticket_transactions as cancelled.
 *   • Reverses customer_charges by the stored charge_amount (CHARGE payment portion).
 *   • Zeros out pos_order_items and increments pos_orders.total_refunded_amount.
 *   • Restores provider wallet balance and creates wallet_transactions record.
 *   • Creates ticket_refunds record.
 *
 * REJECT:
 *   • Marks the cancellation as rejected — ticket stays booked, no financial changes.
 *   • Reverses the pending total_refunds_wallet from the cashier session.
 *   • customer_charges is NOT touched (debt was never reversed on pending).
 *   • pos_order_items is NOT touched (was never zeroed on pending).
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

/**
 * Reverse the customer_charges balance for a passenger by the given charge amount.
 *
 * @param int   $passengerId
 * @param float $chargeAmount  The portion of the refund that was originally charged as debt.
 */
function reverseCustomerCharge(int $passengerId, float $chargeAmount): void {
    if ($chargeAmount <= 0) return;

    $chargeRow = Database::fetch(
        "SELECT * FROM customer_charges WHERE passenger_id = :pid",
        ['pid' => $passengerId]
    );
    if (!$chargeRow) return;

    $newBalance = max(0, floatval($chargeRow['balance'])       - $chargeAmount);
    $newCharged = max(0, floatval($chargeRow['total_charged']) - $chargeAmount);
    $newStatus  = $newBalance <= 0 ? 'CLEAR' : $chargeRow['status'];

    Database::execute(
        "UPDATE customer_charges
         SET total_charged = :charged,
             balance       = :balance,
             status        = :status,
             updated_at    = NOW()
         WHERE passenger_id = :pid",
        ['charged' => $newCharged, 'balance' => $newBalance, 'status' => $newStatus, 'pid' => $passengerId]
    );
}

Auth::requireLogin();
$user   = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input           = json_decode(file_get_contents('php://input'), true);
$cancellationId  = $input['cancellation_id']  ?? null;
$action          = $input['action']            ?? null;
$rejectionReason = $input['rejection_reason']  ?? null;
$remarks         = trim($input['remarks']      ?? '');

// Validate
if (!$cancellationId) { echo json_encode(['success' => false, 'error' => 'Cancellation ID required.']); exit; }
if (!$action || !in_array($action, ['approve', 'reject'])) { echo json_encode(['success' => false, 'error' => 'Action must be either "approve" or "reject".']); exit; }
if ($action === 'reject' && !$rejectionReason) { echo json_encode(['success' => false, 'error' => 'Rejection reason required when rejecting.']); exit; }

// Get cancellation record
$cancellation = Database::fetch(
    "SELECT * FROM ticket_cancellations WHERE cancellation_id = :cid",
    ['cid' => $cancellationId]
);

if (!$cancellation) {
    echo json_encode(['success' => false, 'error' => 'Cancellation request not found.']); exit;
}

if ($cancellation['status'] !== 'pending') {
    echo json_encode(['success' => false, 'error' => 'Cancellation request has already been processed.']); exit;
}

// Get ticket transaction
$ticketTxn = Database::fetch(
    "SELECT * FROM ticket_transactions WHERE transaction_id = :tid",
    ['tid' => $cancellation['transaction_id']]
);

if (!$ticketTxn) {
    echo json_encode(['success' => false, 'error' => 'Ticket transaction not found.']); exit;
}

// Identify the operating provider for wallet resolution
$providerId = $ticketTxn['provider_id'] ?? null;
$walletId   = $ticketTxn['wallet_id'] ?? null;

if (!$providerId && $walletId) {
    // Fallback for legacy records before migration
    $walletProvider = Database::fetch(
        "SELECT provider_id FROM provider_wallets WHERE wallet_id = :wid",
        ['wid' => $walletId]
    );
    if ($walletProvider) {
        $providerId = $walletProvider['provider_id'];
    }
}

if (!$providerId) {
    echo json_encode(['success' => false, 'error' => 'This ticket transaction has no associated provider or wallet.']); exit;
}

$settings = Database::fetch(
    "SELECT cancellation_refund_processing_days FROM system_settings WHERE setting_id = 1"
);

$refundAmount     = floatval($cancellation['refund_amount']);
$chargeAmount     = floatval($cancellation['charge_amount'] ?? 0);
$cashRefundAmount = floatval($cancellation['cash_refund_amount'] ?? ($refundAmount - $chargeAmount)); // Use stored value, fallback to calculation
$passengerId      = $ticketTxn['passenger_id'] ?? null;

// Start database transaction
Database::connection()->beginTransaction();

try {
    if ($action === 'approve') {
        // -----------------------------------------------------------------
        // APPROVE PATH
        // -----------------------------------------------------------------

        $updateSql    = "UPDATE ticket_cancellations SET status = 'approved', approved_by = :uid, approved_at = NOW()";
        $updateParams = ['cid' => $cancellationId, 'uid' => $user['user_id']];
        if ($remarks) {
            $updateSql               .= ", remarks = :remarks";
            $updateParams['remarks']  = $remarks;
        }
        $updateSql .= " WHERE cancellation_id = :cid";
        Database::execute($updateSql, $updateParams);

        // Mark ticket as cancelled
        Database::execute(
            "UPDATE ticket_transactions SET status = 'cancelled' WHERE transaction_id = :tid",
            ['tid' => $ticketTxn['transaction_id']]
        );

        // Reverse the CHARGE portion from customer_charges
        if ($chargeAmount > 0 && $passengerId) {
            reverseCustomerCharge((int)$passengerId, $chargeAmount);
        }

        // Resolve the correct wallet to credit (walks up parent chain if needed)
        $resolvedWallet = WalletResolver::resolve((int)$providerId, (int)$ticketTxn['branch_id']);
        if (!$resolvedWallet) {
            throw new Exception('No active wallet found for the provider and branch to process refund.');
        }
        $walletId = $resolvedWallet['wallet_id'];

        // Fetch the wallet inside the transaction for accurate balance
        $wallet = Database::fetch(
            "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active' FOR UPDATE",
            ['wid' => $walletId]
        );
        if (!$wallet) {
            throw new Exception('Wallet not found or inactive during refund processing.');
        }

        // Restore provider wallet balance
        $balanceBefore = floatval($wallet['current_balance']);
        $balanceAfter  = $balanceBefore + $refundAmount;

        Database::execute(
            "UPDATE provider_wallets SET current_balance = :new_balance WHERE wallet_id = :wid",
            ['new_balance' => $balanceAfter, 'wid' => $walletId]
        );

        // Record wallet transaction
        $wTxnCode    = 'RF-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
        $wTxnRemarks = 'Refund: ' . $ticketTxn['transaction_code']
            . ' | Cancellation #' . $cancellationId
            . ($remarks ? ' | ' . $remarks : '');
        Database::execute(
            "INSERT INTO wallet_transactions
                (wallet_id, txn_code, txn_type, direction, amount, balance_before, balance_after,
                 reference_table, reference_id, remarks, created_by, created_at)
             VALUES (:wid, :code, 'REFUND', 'IN', :amount, :before, :after,
                     'ticket_transactions', :ref_id, :remarks, :uid, NOW())",
            [
                'wid'     => $walletId,
                'code'    => $wTxnCode,
                'amount'  => $refundAmount,
                'before'  => $balanceBefore,
                'after'   => $balanceAfter,
                'ref_id'  => $ticketTxn['transaction_id'],
                'remarks' => $wTxnRemarks,
                'uid'     => $user['user_id'],
            ]
        );

        // Zero out order item and increment total_refunded_amount (deferred from pending step)
        $orderItem = Database::fetch(
            "SELECT oi.item_id, oi.order_id FROM pos_order_items oi
             WHERE oi.reference_id = :tid AND oi.item_type = 'TICKET' LIMIT 1",
            ['tid' => $ticketTxn['transaction_id']]
        );

        if ($orderItem) {
            Database::execute(
                "UPDATE pos_order_items SET total_amount = 0 WHERE item_id = :iid",
                ['iid' => $orderItem['item_id']]
            );
            Database::execute(
                "UPDATE pos_orders SET total_refunded_amount = COALESCE(total_refunded_amount, 0) + :ramount WHERE order_id = :oid",
                ['ramount' => $refundAmount, 'oid' => $orderItem['order_id']]
            );
        }

        // Create refund record
        $processingDays = $settings['cancellation_refund_processing_days'] ?? 0;
        $refundStatus   = ($processingDays > 0) ? 'processing' : 'completed';
        Database::execute(
            "INSERT INTO ticket_refunds
                (transaction_id, transaction_code, cancellation_id, passenger_id, refund_amount,
                 cash_amount, charge_reversal_amount,
                 refund_method, status, requested_by, cashier_session_id, requested_at, processed_by, processed_at)
             VALUES (:tid, :code, :cid, :pid, :ramount,
                     :camount, :cramount,
                     'cash', :status, :ruid, :rcsid, :rtime, :puid, NOW())",
            [
                'tid'     => $ticketTxn['transaction_id'],
                'code'    => $ticketTxn['transaction_code'],
                'cid'     => $cancellationId,
                'pid'     => $passengerId,
                'ramount' => $refundAmount,
                'camount' => $cashRefundAmount,
                'cramount'=> $chargeAmount,
                'status'  => $refundStatus,
                'ruid'    => $cancellation['requested_by'],
                'rcsid'   => $cancellation['cashier_session_id'],
                'rtime'   => $cancellation['requested_at'],
                'puid'    => $user['user_id'],
            ]
        );

        logActivity($user['user_id'], 'CANCELLATION_APPROVED', 'POS', $ticketTxn['transaction_code'],
            ['status' => 'pending'],
            ['status' => 'approved', 'cancellation_id' => $cancellationId,
             'ticket_txn_id' => $ticketTxn['transaction_id'],
             'refund_amount' => $refundAmount, 'charge_amount' => $chargeAmount,
             'wallet_balance_before' => $balanceBefore, 'wallet_balance_after' => $balanceAfter,
             'wallet_txn_code' => $wTxnCode, 'remarks' => $remarks]);

        Database::connection()->commit();

        echo json_encode([
            'success'          => true,
            'message'          => 'Cancellation approved. Refund to be given from cashier cash.',
            'cancellation_id'  => $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'refund_amount'    => $refundAmount,
            'charge_amount'    => $chargeAmount,
            'refund_status'    => $refundStatus,
        ]);

    } else {
        // -----------------------------------------------------------------
        // REJECT PATH
        // Ticket stays booked. Debt was never reversed (pending didn't touch it).
        // Only reverse the pending cashier session refund counter.
        // -----------------------------------------------------------------

        $updateSql    = "UPDATE ticket_cancellations SET status = 'rejected', approved_by = :uid, approved_at = NOW(), rejection_reason = :reason";
        $updateParams = ['cid' => $cancellationId, 'uid' => $user['user_id'], 'reason' => $rejectionReason];
        if ($remarks) {
            $updateSql               .= ", remarks = :remarks";
            $updateParams['remarks']  = $remarks;
        }
        $updateSql .= " WHERE cancellation_id = :cid";
        Database::execute($updateSql, $updateParams);

        // Reverse only the cash portion that was pre-counted in the cashier session.
        // The charge portion was never added (it was a debt reversal, not cash).
        if ($cancellation['cashier_session_id'] && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions
                 SET total_refunds_wallet = GREATEST(0, total_refunds_wallet - :ramount)
                 WHERE session_id = :csid",
                ['ramount' => $cashRefundAmount, 'csid' => $cancellation['cashier_session_id']]
            );
        }

        logActivity($user['user_id'], 'CANCELLATION_REJECTED', 'POS', $ticketTxn['transaction_code'],
            ['status' => 'pending'],
            ['status' => 'rejected', 'cancellation_id' => $cancellationId,
             'ticket_txn_id' => $ticketTxn['transaction_id'],
             'rejection_reason' => $rejectionReason, 'remarks' => $remarks]);

        Database::connection()->commit();

        echo json_encode([
            'success'          => true,
            'message'          => 'Cancellation request rejected. Ticket remains booked.',
            'cancellation_id'  => $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'rejection_reason' => $rejectionReason,
        ]);
    }

} catch (Exception $e) {
    Database::connection()->rollBack();
    echo json_encode(['success' => false, 'error' => 'Approval failed: ' . $e->getMessage()]);
}
