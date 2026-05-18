<?php
/**
 * POS Ticket Cancel/Return API — Handle ticket cancellation with wallet refund
 * Updated to support confirmation workflow and proper tracking
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

$txnCode = $input['transaction_code'] ?? null;
$refundAmount = $input['refund_amount'] ?? null;
$reason = $input['reason'] ?? null;

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
    echo json_encode(['success' => false, 'error' => 'Refund amount cannot exceed the original Total Amount (₱' . number_format($totalAmount, 2) + ').']); exit;
}

$ticketTxnId = $ticketTxn['transaction_id'];

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
    // If confirmation is required, create pending cancellation record
    if ($requiresConfirmation) {
        // Create cancellation record with pending status
        $cancellationType = ($refundAmount < $totalAmount) ? 'partial' : 'full';
        
        Database::execute(
            "INSERT INTO ticket_cancellations
                (transaction_id, transaction_code, passenger_id, reason, cancellation_type, refund_amount, status, requested_by, cashier_session_id, requested_at)
             VALUES (:tid, :code, :pid, :reason, :ctype, :ramount, 'pending', :uid, :csid, NOW())",
            [
                'tid' => $ticketTxnId,
                'code' => $ticketTxn['transaction_code'],
                'pid' => $ticketTxn['passenger_id'],
                'reason' => $reason,
                'ctype' => $cancellationType,
                'ramount' => $refundAmount,
                'uid' => $user['user_id'],
                'csid' => $cashierSessionId
            ]
        );

        $cancellationId = Database::lastInsertId();

        // Track refund in cashier session (cash out from drawer - always to total_refunds_wallet)
        if ($cashierSessionId) {
            Database::execute(
                "UPDATE cashier_sessions SET total_refunds_wallet = total_refunds_wallet + :ramount WHERE session_id = :csid",
                ['ramount' => $refundAmount, 'csid' => $cashierSessionId]
            );
        }
        
        logActivity($user['user_id'], 'TICKET_CANCEL_REQUEST', 'POS', $ticketTxn['transaction_code'], null,
            ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxnId, 'refund_amount' => $refundAmount, 'status' => 'pending']);
        
        Database::connection()->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cancellation request submitted successfully. Awaiting approval. Refund will be given from cashier cash.',
            'transaction_id' => $ticketTxn['transaction_id'],
            'transaction_code' => $ticketTxn['transaction_code'],
            'refund_amount' => $refundAmount,
            'cancellation_id' => $cancellationId,
            'status' => 'pending_confirmation',
            'requires_confirmation' => true,
            'refund_source' => 'cashier_cash'
        ]);
    } else {
        // Auto-approve or no confirmation required - process immediately
        // Update ticket transaction status to cancelled
        Database::execute(
            "UPDATE ticket_transactions SET status = 'cancelled' WHERE transaction_id = :tid",
            ['tid' => $ticketTxnId]
        );
        
        // Create cancellation record with approved status
        $cancellationType = ($refundAmount < $totalAmount) ? 'partial' : 'full';
        
        Database::execute(
            "INSERT INTO ticket_cancellations
                (transaction_id, transaction_code, passenger_id, reason, cancellation_type, refund_amount, status, requested_by, cashier_session_id, requested_at, approved_by, approved_at)
             VALUES (:tid, :code, :pid, :reason, :ctype, :ramount, 'approved', :uid, :csid, NOW(), :uid2, NOW())",
            [
                'tid' => $ticketTxnId,
                'code' => $ticketTxn['transaction_code'],
                'pid' => $ticketTxn['passenger_id'],
                'reason' => $reason,
                'ctype' => $cancellationType,
                'ramount' => $refundAmount,
                'uid' => $user['user_id'],
                'uid2' => $user['user_id'],
                'csid' => $cashierSessionId
            ]
        );

        $cancellationId = Database::lastInsertId();

        // Track refund in cashier session (cash out from drawer)
        if ($cashierSessionId) {
            Database::execute(
                "UPDATE cashier_sessions SET total_refunds_wallet = total_refunds_wallet + :ramount WHERE session_id = :csid",
                ['ramount' => $refundAmount, 'csid' => $cashierSessionId]
            );
        }

        // Create refund record - status based on refund_processing_days setting
        $processingDays = $settings['cancellation_refund_processing_days'] ?? 0;
        $refundStatus = ($processingDays > 0) ? 'processing' : 'completed';
        Database::execute(
            "INSERT INTO ticket_refunds
                (transaction_id, transaction_code, cancellation_id, passenger_id, refund_amount, refund_method, status, requested_by, cashier_session_id, requested_at, processed_by, processed_at)
             VALUES (:tid, :code, :cid, :pid, :ramount, 'cash', :status, :uid, :csid, NOW(), :uid2, NOW())",
            [
                'tid' => $ticketTxnId,
                'code' => $ticketTxn['transaction_code'],
                'cid' => $cancellationId,
                'pid' => $ticketTxn['passenger_id'],
                'ramount' => $refundAmount,
                'status' => $refundStatus,
                'uid' => $user['user_id'],
                'uid2' => $user['user_id'],
                'csid' => $cashierSessionId
            ]
        );

        logActivity($user['user_id'], 'TICKET_CANCEL', 'POS', $ticketTxn['transaction_code'], null,
            ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxnId, 'refund_amount' => $refundAmount, 'refund_method' => 'cash']);

        Database::connection()->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Ticket cancelled successfully. Refund to be given from cashier cash.',
            'transaction_id' => $ticketTxn['transaction_id'],
            'transaction_code' => $ticketTxn['transaction_code'],
            'refund_amount' => $refundAmount,
            'cancellation_id' => $cancellationId,
            'refund_status' => $refundStatus,
            'requires_confirmation' => false
        ]);
    }

} catch (Exception $e) {
    Database::connection()->rollBack();
    echo json_encode(['success' => false, 'error' => 'Cancellation failed: ' . $e->getMessage()]);
}

