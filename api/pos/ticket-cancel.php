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
require_once dirname(dirname(__DIR__)) . '/app/helpers/CancellationService.php';
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

// Branch access control
if ($user['role_code'] !== 'SUPER_ADMIN' && (int)$user['branch_id'] !== (int)$ticketTxn['branch_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied: ticket does not belong to your branch']); exit;
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

// Validate refund amount (cannot exceed original total amount)
$totalAmount = floatval($ticketTxn['total_amount'] ?? 0);
if ($refundAmount > $totalAmount) {
    echo json_encode(['success' => false, 'error' => 'Refund amount cannot exceed the original Total Amount (₱' . number_format($totalAmount, 2) . ').']); exit;
}

$ticketTxnId = $ticketTxn['transaction_id'];
$passengerId = $ticketTxn['passenger_id'] ?? null;

if (!CancellationService::resolveProviderId($ticketTxn)) {
    echo json_encode(['success' => false, 'error' => 'This ticket transaction has no associated provider or wallet.']); exit;
}

// The charge amount to reverse = the smaller of (what was charged) and (refund being requested).
$chargePaymentsTotal = CancellationService::computeChargePaymentsTotal((int) $ticketTxnId);
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
    $cashRefundAmount = $refundAmount - $chargeAmount;

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
                 refund_amount, charge_amount, cash_refund_amount, status, requested_by, cashier_session_id, requested_at)
             VALUES (:tid, :code, :pid, :reason, :ctype,
                     :ramount, :camount, :cramount, 'pending', :uid, :csid, NOW())",
            [
                'tid'     => $ticketTxnId,
                'code'    => $ticketTxn['transaction_code'],
                'pid'     => $passengerId,
                'reason'  => $reason,
                'ctype'   => $cancellationType,
                'ramount' => $refundAmount,
                'camount' => $chargeAmount,
                'cramount' => $cashRefundAmount,
                'uid'     => $user['user_id'],
                'csid'    => $cashierSessionId,
            ]
        );

        $cancellationId = Database::lastInsertId();

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
        // Financial/restorative effects are handled centrally by CancellationService.
        // -----------------------------------------------------------------

        // Record the approved cancellation request.
        Database::execute(
            "INSERT INTO ticket_cancellations
                (transaction_id, transaction_code, passenger_id, reason, cancellation_type,
                 refund_amount, charge_amount, cash_refund_amount, status, requested_by, cashier_session_id,
                 requested_at, approved_by, approved_at)
             VALUES (:tid, :code, :pid, :reason, :ctype,
                     :ramount, :camount, :cramount, 'approved', :uid, :csid,
                     NOW(), :uid2, NOW())",
            [
                'tid'     => $ticketTxnId,
                'code'    => $ticketTxn['transaction_code'],
                'pid'     => $passengerId,
                'reason'  => $reason,
                'ctype'   => $cancellationType,
                'ramount' => $refundAmount,
                'camount' => $chargeAmount,
                'cramount' => $cashRefundAmount,
                'uid'     => $user['user_id'],
                'uid2'    => $user['user_id'],
                'csid'    => $cashierSessionId,
            ]
        );

        $cancellationId = Database::lastInsertId();

        // Track cash refund in cashier session.
        if ($cashierSessionId && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions SET total_refunds_wallet = total_refunds_wallet + :ramount WHERE session_id = :csid",
                ['ramount' => $cashRefundAmount, 'csid' => $cashierSessionId]
            );
        }

        $processingDays = $settings['cancellation_refund_processing_days'] ?? 0;

        $effects = CancellationService::processCancellationEffects(
            $ticketTxn,
            [
                'cancellation_id'      => $cancellationId,
                'requested_by'         => $user['user_id'],
                'cashier_session_id'   => $cashierSessionId,
                'requested_at'         => date('Y-m-d H:i:s'),
            ],
            $refundAmount,
            $cashRefundAmount,
            $chargeAmount,
            (int) $user['user_id'],
            (int) $processingDays,
            $reason ?? '',
            null
        );

        logActivity($user['user_id'], 'TICKET_CANCEL', 'POS', $ticketTxn['transaction_code'], null,
            ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxnId,
             'refund_amount' => $refundAmount, 'charge_amount' => $chargeAmount,
             'wallet_balance_before' => $effects['wallet_balance_before'],
             'wallet_balance_after'  => $effects['wallet_balance_after'],
             'wallet_txn_code' => $effects['wallet_txn_code'], 'requires_confirmation' => false]);

        Database::connection()->commit();

        echo json_encode([
            'success'              => true,
            'message'              => 'Ticket cancelled successfully. Refund to be given from cashier cash.',
            'transaction_id'       => $ticketTxn['transaction_id'],
            'transaction_code'     => $ticketTxn['transaction_code'],
            'refund_amount'        => $refundAmount,
            'charge_amount'        => $chargeAmount,
            'cancellation_id'      => $cancellationId,
            'refund_status'        => $effects['refund_status'],
            'requires_confirmation'=> false,
        ]);
    }

} catch (Exception $e) {
    Database::connection()->rollBack();
    echo json_encode(['success' => false, 'error' => 'Cancellation failed: ' . $e->getMessage()]);
}

