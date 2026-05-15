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
    "SELECT cancellation_requires_confirmation, cancellation_auto_approve, cancellation_refund_to_wallet, cancellation_refund_processing_days 
     FROM system_settings WHERE setting_id = 1"
);

$requiresConfirmation = $settings['cancellation_requires_confirmation'] ?? 1;
$autoApprove = $settings['cancellation_auto_approve'] ?? 0;
$refundToWallet = $settings['cancellation_refund_to_wallet'] ?? 1;

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
    if ($requiresConfirmation && !$autoApprove) {
        // Create cancellation record with pending status
        $cancellationType = ($refundAmount < $totalAmount) ? 'partial' : 'full';
        
        Database::execute(
            "INSERT INTO ticket_cancellations 
                (transaction_id, transaction_code, passenger_id, reason, cancellation_type, refund_amount, status, requested_by, cashier_session_id, requested_at, wallet_impact_applied)
             VALUES (:tid, :code, :pid, :reason, :ctype, :ramount, 'pending', :uid, :csid, NOW(), 0)",
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
        
        // Track refund in cashier session (deduct from cashier's cash)
        if ($cashierSessionId) {
            Database::execute(
                "UPDATE cashier_sessions SET total_refunds = total_refunds + :ramount WHERE session_id = :csid",
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
                (transaction_id, transaction_code, passenger_id, reason, cancellation_type, refund_amount, status, requested_by, cashier_session_id, requested_at, approved_by, approved_at, wallet_impact_applied)
             VALUES (:tid, :code, :pid, :reason, :ctype, :ramount, 'approved', :uid, :csid, NOW(), :uid, NOW(), 0)",
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
        
        // Track refund in cashier session (deduct from cashier's cash)
        if ($cashierSessionId) {
            Database::execute(
                "UPDATE cashier_sessions SET total_refunds = total_refunds + :ramount WHERE session_id = :csid",
                ['ramount' => $refundAmount, 'csid' => $cashierSessionId]
            );
        }
        
        // Only refund to wallet balance if setting is enabled
        if ($refundToWallet) {
            $balanceBefore = $wallet['current_balance'];
            $balanceAfter = $balanceBefore + $refundAmount;
            
            Database::execute(
                "UPDATE provider_wallets SET current_balance = :new_balance, updated_at = NOW() WHERE wallet_id = :wid",
                ['new_balance' => $balanceAfter, 'wid' => $walletId]
            );
            
            // Create refund wallet transaction record
            $walletTxnCode = 'REF-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
            Database::execute(
                "INSERT INTO wallet_transactions
                    (wallet_id, txn_code, txn_type, direction, amount, balance_before, balance_after, reference_table, reference_id, remarks, created_by, created_at)
                 VALUES (:wid, :code, 'REFUND', 'IN', :amount, :before, :after, 'ticket_transactions', :ref_id, :remarks, :uid, NOW())",
                [
                    'wid' => $walletId,
                    'code' => $walletTxnCode,
                    'amount' => $refundAmount,
                    'before' => $balanceBefore,
                    'after' => $balanceAfter,
                    'ref_id' => $ticketTxnId,
                    'remarks' => "Ticket cancellation/return refund. Transaction: {$ticketTxn['transaction_code']}. Reason: " . ($reason ?: 'Not specified'),
                    'uid' => $user['user_id']
                ]
            );
            
            // Track wallet refund in cashier session
            if ($cashierSessionId) {
                Database::execute(
                    "UPDATE cashier_sessions SET total_refunds_wallet = total_refunds_wallet + :ramount WHERE session_id = :csid",
                    ['ramount' => $refundAmount, 'csid' => $cashierSessionId]
                );
            }
            
            // Create refund record
            Database::execute(
                "INSERT INTO ticket_refunds 
                    (transaction_id, transaction_code, cancellation_id, passenger_id, refund_amount, refund_method, status, requested_by, cashier_session_id, requested_at, processed_by, processed_at, wallet_impact_applied)
                 VALUES (:tid, :code, :cid, :pid, :ramount, 'wallet', 'completed', :uid, :csid, NOW(), :uid, NOW(), 1)",
                [
                    'tid' => $ticketTxnId,
                    'code' => $ticketTxn['transaction_code'],
                    'cid' => $cancellationId,
                    'pid' => $ticketTxn['passenger_id'],
                    'ramount' => $refundAmount,
                    'uid' => $user['user_id'],
                    'csid' => $cashierSessionId
                ]
            );
            
            // Update cancellation record to mark wallet impact as applied
            Database::execute(
                "UPDATE ticket_cancellations SET wallet_impact_applied = 1, processed_at = NOW() WHERE cancellation_id = :cid",
                ['cid' => $cancellationId]
            );
            
            logActivity($user['user_id'], 'TICKET_CANCEL', 'POS', $ticketTxn['transaction_code'], null,
                ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxnId, 'refund_amount' => $refundAmount, 'wallet_txn_code' => $walletTxnCode, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter]);
            
            Database::connection()->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Ticket cancelled successfully. Refund given from cashier cash and added to wallet.',
                'transaction_id' => $ticketTxn['transaction_id'],
                'transaction_code' => $ticketTxn['transaction_code'],
                'refund_amount' => $refundAmount,
                'wallet_txn_code' => $walletTxnCode,
                'new_wallet_balance' => $balanceAfter,
                'cancellation_id' => $cancellationId,
                'status' => 'completed',
                'requires_confirmation' => false,
                'refund_source' => 'cashier_cash_and_wallet'
            ]);
        } else {
            // Refund to wallet is disabled - only give cash refund
            Database::execute(
                "UPDATE ticket_cancellations SET wallet_impact_applied = 1, processed_at = NOW() WHERE cancellation_id = :cid",
                ['cid' => $cancellationId]
            );
            
            // Create refund record (cash only)
            Database::execute(
                "INSERT INTO ticket_refunds 
                    (transaction_id, transaction_code, cancellation_id, passenger_id, refund_amount, refund_method, status, requested_by, cashier_session_id, requested_at, processed_by, processed_at, wallet_impact_applied)
                 VALUES (:tid, :code, :cid, :pid, :ramount, 'cash', 'completed', :uid, :csid, NOW(), :uid, NOW(), 0)",
                [
                    'tid' => $ticketTxnId,
                    'code' => $ticketTxn['transaction_code'],
                    'cid' => $cancellationId,
                    'pid' => $ticketTxn['passenger_id'],
                    'ramount' => $refundAmount,
                    'uid' => $user['user_id'],
                    'csid' => $cashierSessionId
                ]
            );
            
            logActivity($user['user_id'], 'TICKET_CANCEL', 'POS', $ticketTxn['transaction_code'], null,
                ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxnId, 'refund_amount' => $refundAmount, 'wallet_impact' => 'skipped']);
            
            Database::connection()->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Ticket cancelled successfully. Refund given from cashier cash only.',
                'transaction_id' => $ticketTxn['transaction_id'],
                'transaction_code' => $ticketTxn['transaction_code'],
                'refund_amount' => $refundAmount,
                'cancellation_id' => $cancellationId,
                'status' => 'completed',
                'requires_confirmation' => false,
                'refund_source' => 'cashier_cash_only'
            ]);
        }
    }

} catch (Exception $e) {
    Database::connection()->rollBack();
    echo json_encode(['success' => false, 'error' => 'Cancellation failed: ' . $e->getMessage()]);
}

