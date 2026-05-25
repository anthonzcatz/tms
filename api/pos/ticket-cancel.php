<?php
/**
 * POS Ticket Cancel/Return API — Handle ticket cancellation with wallet refund
 * Supports mixed payments (cash + CHARGE). On cancellation the CHARGE portion
 * is stored as charge_amount and reversed from customer_charges only when the
 * cancellation is confirmed (approved or immediate). Pending cancellations do
 * NOT touch customer_charges or pos_order_items until a decision is made.
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
 * Calculates the proportional charge reversal relative to the original charged amount.
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

    $newBalance   = max(0, floatval($chargeRow['balance']) - $chargeAmount);
    $newCharged   = max(0, floatval($chargeRow['total_charged']) - $chargeAmount);
    $newStatus    = $newBalance <= 0 ? 'CLEAR' : $chargeRow['status'];

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

// Validate
if (!$txnCode) { echo json_encode(['success' => false, 'error' => 'Transaction code required.']); exit; }
if (!$refundAmount || floatval($refundAmount) <= 0) { echo json_encode(['success' => false, 'error' => 'Refund amount must be greater than 0.']); exit; }

$refundAmount = floatval($refundAmount);

// Get system settings for cancellation
$settings = Database::fetch(
    "SELECT cancellation_requires_confirmation, cancellation_refund_processing_days
     FROM system_settings WHERE setting_id = 1"
);

$requiresConfirmation = $settings['cancellation_requires_confirmation'] ?? 1;

// Get ticket transaction by transaction code
$ticketTxn = Database::fetch(
    "SELECT * FROM ticket_transactions WHERE transaction_code = :code",
    ['code' => $txnCode]
);

if (!$ticketTxn) {
    echo json_encode(['success' => false, 'error' => 'Ticket transaction not found.']); exit;
}

// Check if already cancelled
if ($ticketTxn['status'] === 'cancelled' || $ticketTxn['status'] === 'refunded') {
    echo json_encode(['success' => false, 'error' => 'Ticket transaction is already cancelled/refunded.']); exit;
}

// Check if there's a pending cancellation
$pendingCancellation = Database::fetch(
    "SELECT * FROM ticket_cancellations WHERE transaction_id = :tid AND status = 'pending'",
    ['tid' => $ticketTxn['transaction_id']]
);

if ($pendingCancellation) {
    echo json_encode(['success' => false, 'error' => 'There is already a pending cancellation request for this ticket.']); exit;
}

// Check if wallet_id exists
$walletId = $ticketTxn['wallet_id'] ?? null;
if (!$walletId) {
    echo json_encode(['success' => false, 'error' => 'This ticket transaction has no associated wallet.']); exit;
}

// Get wallet
$wallet = Database::fetch(
    "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active'",
    ['wid' => $walletId]
);

if (!$wallet) {
    echo json_encode(['success' => false, 'error' => 'Wallet not found or inactive.']); exit;
}

// Validate refund amount (cannot exceed original total amount)
$totalAmount = floatval($ticketTxn['total_amount'] ?? 0);
if ($refundAmount > $totalAmount) {
    echo json_encode(['success' => false, 'error' => 'Refund amount cannot exceed the original Total Amount (₱' . number_format($totalAmount, 2) . ').']); exit;
}

$ticketTxnId = $ticketTxn['transaction_id'];
$passengerId = $ticketTxn['passenger_id'] ?? null;

// -------------------------------------------------------------------------
// Compute the CHARGE portion of this ticket's payments.
// transaction_payments holds one row per payment method per ticket.
// We sum only payments where the payment_method tracks_credit = 1.
// This is the debt amount posted to customer_charges on sale.
// For a split payment (e.g. ₱1,000 cash + ₱500 charge on a ₱1,500 ticket):
//   total_charge_paid = 500
//   refund_ratio      = refundAmount / totalAmount  (e.g. 600/1500 = 0.4)
//   charge_amount     = min(total_charge_paid, refundAmount)
//                       but capped proportionally so we never reverse more
//                       than what was actually charged.
// Simple rule: charge_amount = min(total_charge_paid, refundAmount)
// -------------------------------------------------------------------------
$chargePaymentsTotal = floatval(Database::fetch(
    "SELECT COALESCE(SUM(tp.amount), 0) AS total
     FROM transaction_payments tp
     JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
     WHERE tp.source_type = 'TICKET_TRANSACTION'
       AND tp.source_id   = :tid
       AND pm.tracks_credit = 1",
    ['tid' => $ticketTxnId]
)['total'] ?? 0);

// The charge amount to reverse = the smaller of (what was charged) and (refund being requested).
// Example A: ticket ₱1500, cash ₱1000, charge ₱500, cancel full ₱1500 → charge_amount = 500
// Example B: ticket ₱1500, cash ₱1000, charge ₱500, cancel partial ₱600 → charge_amount = min(500,600) = 500
// Example C: ticket ₱1500, cash ₱1000, charge ₱500, cancel partial ₱300 → charge_amount = min(500,300) = 300
$chargeAmount = min($chargePaymentsTotal, $refundAmount);

// Get active cashier session for this user
$cashierSession = Database::fetch(
    "SELECT session_id FROM cashier_sessions 
     WHERE cashier_user_id = :uid AND status = 'OPEN' 
     ORDER BY started_at DESC LIMIT 1",
    ['uid' => $user['user_id']]
);
$cashierSessionId = $cashierSession ? $cashierSession['session_id'] : null;

// Start database transaction
Database::connection()->beginTransaction();

try {
    $cancellationType = ($refundAmount < $totalAmount) ? 'partial' : 'full';

    if ($requiresConfirmation) {
        // -----------------------------------------------------------------
        // PENDING PATH — submit for manager approval.
        // Rules:
        //  • Store charge_amount so the approval handler knows what to reverse.
        //  • Do NOT touch customer_charges yet — the debt is real until approved.
        //  • Do NOT zero pos_order_items yet — the item is still live until approved.
        //  • Do NOT change ticket_transactions.status yet — ticket is still booked.
        //  • Track the pending refund in the cashier session totals.
        // -----------------------------------------------------------------

        Database::execute(
            "INSERT INTO ticket_cancellations
                (transaction_id, transaction_code, passenger_id, reason, cancellation_type,
                 refund_amount, charge_amount, status, requested_by, cashier_session_id, requested_at)
             VALUES (:tid, :code, :pid, :reason, :ctype,
                     :ramount, :camount, 'pending', :uid, :csid, NOW())",
            [
                'tid'     => $ticketTxnId,
                'code'    => $ticketTxn['transaction_code'],
                'pid'     => $passengerId,
                'reason'  => $reason,
                'ctype'   => $cancellationType,
                'ramount' => $refundAmount,
                'camount' => $chargeAmount,
                'uid'     => $user['user_id'],
                'csid'    => $cashierSessionId,
            ]
        );

        $cancellationId = Database::lastInsertId();

        // Only the cash portion leaves the drawer — charge reversal is system-only (not cash).
        $cashRefundAmount = $refundAmount - $chargeAmount;

        // Track pending cash refund in cashier session
        if ($cashierSessionId && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions SET total_refunds_wallet = total_refunds_wallet + :ramount WHERE session_id = :csid",
                ['ramount' => $cashRefundAmount, 'csid' => $cashierSessionId]
            );
        }

        logActivity($user['user_id'], 'TICKET_CANCEL_REQUEST', 'POS', $ticketTxn['transaction_code'], null,
            ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxnId,
             'refund_amount' => $refundAmount, 'charge_amount' => $chargeAmount, 'status' => 'pending']);

        Database::connection()->commit();

        echo json_encode([
            'success'              => true,
            'message'              => 'Cancellation request submitted. Awaiting manager approval.',
            'transaction_id'       => $ticketTxn['transaction_id'],
            'transaction_code'     => $ticketTxn['transaction_code'],
            'refund_amount'        => $refundAmount,
            'charge_amount'        => $chargeAmount,
            'cancellation_id'      => $cancellationId,
            'status'               => 'pending_confirmation',
            'requires_confirmation'=> true,
            'refund_source'        => 'cashier_cash',
        ]);

    } else {
        // -----------------------------------------------------------------
        // IMMEDIATE CANCEL PATH — no confirmation required.
        // Rules:
        //  • Mark ticket as cancelled.
        //  • Reverse customer_charges by charge_amount immediately.
        //  • Zero out pos_order_items and update total_refunded_amount.
        //  • Restore provider wallet balance.
        //  • Create ticket_refunds record.
        // -----------------------------------------------------------------

        // Mark ticket cancelled
        Database::execute(
            "UPDATE ticket_transactions SET status = 'cancelled' WHERE transaction_id = :tid",
            ['tid' => $ticketTxnId]
        );

        Database::execute(
            "INSERT INTO ticket_cancellations
                (transaction_id, transaction_code, passenger_id, reason, cancellation_type,
                 refund_amount, charge_amount, status, requested_by, cashier_session_id,
                 requested_at, approved_by, approved_at)
             VALUES (:tid, :code, :pid, :reason, :ctype,
                     :ramount, :camount, 'approved', :uid, :csid,
                     NOW(), :uid2, NOW())",
            [
                'tid'     => $ticketTxnId,
                'code'    => $ticketTxn['transaction_code'],
                'pid'     => $passengerId,
                'reason'  => $reason,
                'ctype'   => $cancellationType,
                'ramount' => $refundAmount,
                'camount' => $chargeAmount,
                'uid'     => $user['user_id'],
                'uid2'    => $user['user_id'],
                'csid'    => $cashierSessionId,
            ]
        );

        $cancellationId = Database::lastInsertId();

        // Only the cash portion leaves the drawer — charge reversal is system-only (not cash).
        $cashRefundAmount = $refundAmount - $chargeAmount;

        // Track cash refund in cashier session
        if ($cashierSessionId && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions SET total_refunds_wallet = total_refunds_wallet + :ramount WHERE session_id = :csid",
                ['ramount' => $cashRefundAmount, 'csid' => $cashierSessionId]
            );
        }

        // Reverse the CHARGE portion from customer_charges
        if ($chargeAmount > 0 && $passengerId) {
            reverseCustomerCharge((int)$passengerId, $chargeAmount);
        }

        // Re-fetch wallet inside transaction to get latest balance (prevent race conditions)
        $wallet = Database::fetch(
            "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active' FOR UPDATE",
            ['wid' => $walletId]
        );
        if (!$wallet) {
            throw new Exception('Wallet not found or inactive during refund processing.');
        }

        // Restore wallet balance
        $balanceBefore = floatval($wallet['current_balance']);
        $balanceAfter  = $balanceBefore + $refundAmount;

        Database::execute(
            "UPDATE provider_wallets SET current_balance = :new_balance, updated_at = NOW() WHERE wallet_id = :wid",
            ['new_balance' => $balanceAfter, 'wid' => $walletId]
        );

        // Create wallet transaction record for the refund
        $wTxnCode = 'REFUND-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
        $remarks  = 'Auto-approved cancellation refund for ticket ' . $ticketTxn['transaction_code']
            . ' | Cancellation #' . $cancellationId
            . ($reason ? ' | ' . $reason : '');
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
                'ref_id'  => $ticketTxnId,
                'remarks' => $remarks,
                'uid'     => $user['user_id'],
            ]
        );

        // Zero out the order item and record the refunded amount in pos_orders
        $orderItem = Database::fetch(
            "SELECT oi.item_id, oi.order_id, oi.total_amount
             FROM pos_order_items oi
             WHERE oi.reference_id = :tid AND oi.item_type = 'TICKET'
             LIMIT 1",
            ['tid' => $ticketTxnId]
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
                     'cash', :status, :uid, :csid, NOW(), :uid2, NOW())",
            [
                'tid'     => $ticketTxnId,
                'code'    => $ticketTxn['transaction_code'],
                'cid'     => $cancellationId,
                'pid'     => $passengerId,
                'ramount' => $refundAmount,
                'camount' => $cashRefundAmount,
                'cramount'=> $chargeAmount,
                'status'  => $refundStatus,
                'uid'     => $user['user_id'],
                'uid2'    => $user['user_id'],
                'csid'    => $cashierSessionId,
            ]
        );

        logActivity($user['user_id'], 'TICKET_CANCEL', 'POS', $ticketTxn['transaction_code'], null,
            ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxnId,
             'refund_amount' => $refundAmount, 'charge_amount' => $chargeAmount,
             'wallet_balance_before' => $balanceBefore, 'wallet_balance_after' => $balanceAfter,
             'wallet_txn_code' => $wTxnCode, 'requires_confirmation' => false]);

        Database::connection()->commit();

        echo json_encode([
            'success'              => true,
            'message'              => 'Ticket cancelled successfully. Refund to be given from cashier cash.',
            'transaction_id'       => $ticketTxn['transaction_id'],
            'transaction_code'     => $ticketTxn['transaction_code'],
            'refund_amount'        => $refundAmount,
            'charge_amount'        => $chargeAmount,
            'cancellation_id'      => $cancellationId,
            'refund_status'        => $refundStatus,
            'requires_confirmation'=> false,
        ]);
    }

} catch (Exception $e) {
    Database::connection()->rollBack();
    echo json_encode(['success' => false, 'error' => 'Cancellation failed: ' . $e->getMessage()]);
}

