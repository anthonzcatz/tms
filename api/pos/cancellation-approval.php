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

// Branch access control
if ($user['role_code'] !== 'SUPER_ADMIN' && (int)$user['branch_id'] !== (int)$ticketTxn['branch_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied: ticket does not belong to your branch']); exit;
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

        $processingDays = $settings['cancellation_refund_processing_days'] ?? 0;

        $effects = CancellationService::processCancellationEffects(
            $ticketTxn,
            $cancellation,
            $refundAmount,
            $cashRefundAmount,
            $chargeAmount,
            (int) $user['user_id'],
            (int) $processingDays,
            $cancellation['reason'] ?? '',
            $remarks
        );

        logActivity($user['user_id'], 'CANCELLATION_APPROVED', 'POS', $ticketTxn['transaction_code'],
            ['status' => 'pending'],
            ['status' => 'approved', 'cancellation_id' => $cancellationId,
             'ticket_txn_id' => $ticketTxn['transaction_id'],
             'refund_amount' => $refundAmount, 'charge_amount' => $chargeAmount,
             'wallet_balance_before' => $effects['wallet_balance_before'],
             'wallet_balance_after'  => $effects['wallet_balance_after'],
             'wallet_txn_code' => $effects['wallet_txn_code'], 'remarks' => $remarks]);

        Database::connection()->commit();

        echo json_encode([
            'success'          => true,
            'message'          => 'Cancellation approved. Refund to be given from cashier cash.',
            'cancellation_id'  => $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'refund_amount'    => $refundAmount,
            'charge_amount'    => $chargeAmount,
            'refund_status'    => $effects['refund_status'],
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
