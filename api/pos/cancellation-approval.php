<?php
/**
 * POS Cancellation Approval API — Handle approval/rejection of pending ticket cancellations
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

$cancellationId = $input['cancellation_id'] ?? null;
$action = $input['action'] ?? null; // 'approve' or 'reject'
$rejectionReason = $input['rejection_reason'] ?? null;

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

// Check if already processed
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

// Get wallet
$wallet = Database::fetch(
    "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active'",
    ['wid' => $ticketTxn['wallet_id']]
);

if (!$wallet) {
    echo json_encode(['success' => false, 'error' => 'Wallet not found or inactive.']); exit;
}

// Get system settings
$settings = Database::fetch(
    "SELECT cancellation_refund_to_wallet FROM system_settings WHERE setting_id = 1"
);
$refundToWallet = $settings['cancellation_refund_to_wallet'] ?? 1;

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
    if ($action === 'approve') {
        // Update cancellation status to approved
        Database::execute(
            "UPDATE ticket_cancellations SET status = 'approved', approved_by = :uid, approved_at = NOW() WHERE cancellation_id = :cid",
            ['cid' => $cancellationId, 'uid' => $user['user_id']]
        );
        
        // Update ticket transaction status to cancelled
        Database::execute(
            "UPDATE ticket_transactions SET status = 'cancelled' WHERE transaction_id = :tid",
            ['tid' => $ticketTxn['transaction_id']]
        );
        
        // Track refund in cashier session (deduct from cashier's cash)
        if ($cancellation['cashier_session_id']) {
            Database::execute(
                "UPDATE cashier_sessions SET total_refunds = total_refunds + :ramount WHERE session_id = :csid",
                ['ramount' => $cancellation['refund_amount'], 'csid' => $cancellation['cashier_session_id']]
            );
        }
        
        // Process refund to wallet if enabled
        if ($refundToWallet) {
            $balanceBefore = $wallet['current_balance'];
            $balanceAfter = $balanceBefore + $cancellation['refund_amount'];
            
            Database::execute(
                "UPDATE provider_wallets SET current_balance = :new_balance, updated_at = NOW() WHERE wallet_id = :wid",
                ['new_balance' => $balanceAfter, 'wid' => $wallet['wallet_id']]
            );
            
            // Create refund wallet transaction record
            $walletTxnCode = 'REF-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
            Database::execute(
                "INSERT INTO wallet_transactions
                    (wallet_id, txn_code, txn_type, direction, amount, balance_before, balance_after, reference_table, reference_id, remarks, created_by, created_at)
                 VALUES (:wid, :code, 'REFUND', 'IN', :amount, :before, :after, 'ticket_transactions', :ref_id, :remarks, :uid, NOW())",
                [
                    'wid' => $wallet['wallet_id'],
                    'code' => $walletTxnCode,
                    'amount' => $cancellation['refund_amount'],
                    'before' => $balanceBefore,
                    'after' => $balanceAfter,
                    'ref_id' => $ticketTxn['transaction_id'],
                    'remarks' => "Ticket cancellation refund approved. Transaction: {$ticketTxn['transaction_code']}. Reason: " . ($cancellation['reason'] ?: 'Not specified'),
                    'uid' => $user['user_id']
                ]
            );
            
            // Track wallet refund in cashier session
            if ($cancellation['cashier_session_id']) {
                Database::execute(
                    "UPDATE cashier_sessions SET total_refunds_wallet = total_refunds_wallet + :ramount WHERE session_id = :csid",
                    ['ramount' => $cancellation['refund_amount'], 'csid' => $cancellation['cashier_session_id']]
                );
            }
            
            // Create refund record
            Database::execute(
                "INSERT INTO ticket_refunds 
                    (transaction_id, transaction_code, cancellation_id, passenger_id, refund_amount, refund_method, status, requested_by, cashier_session_id, requested_at, processed_by, processed_at, wallet_impact_applied)
                 VALUES (:tid, :code, :cid, :pid, :ramount, 'wallet', 'completed', :ruid, :rcsid, :rtime, :puid, NOW(), 1)",
                [
                    'tid' => $ticketTxn['transaction_id'],
                    'code' => $ticketTxn['transaction_code'],
                    'cid' => $cancellationId,
                    'pid' => $ticketTxn['passenger_id'],
                    'ramount' => $cancellation['refund_amount'],
                    'ruid' => $cancellation['requested_by'],
                    'rcsid' => $cancellation['cashier_session_id'],
                    'rtime' => $cancellation['requested_at'],
                    'puid' => $user['user_id']
                ]
            );
            
            // Update cancellation record to mark wallet impact as applied
            Database::execute(
                "UPDATE ticket_cancellations SET wallet_impact_applied = 1, processed_at = NOW() WHERE cancellation_id = :cid",
                ['cid' => $cancellationId]
            );
            
            logActivity($user['user_id'], 'CANCELLATION_APPROVED', 'POS', $ticketTxn['transaction_code'], null,
                ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxn['transaction_id'], 'refund_amount' => $cancellation['refund_amount'], 'wallet_txn_code' => $walletTxnCode, 'balance_before' => $balanceBefore, 'balance_after' => $balanceAfter]);
            
            Database::connection()->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cancellation approved and refund processed successfully. Refund given from cashier cash and added to wallet.',
                'cancellation_id' => $cancellationId,
                'transaction_code' => $ticketTxn['transaction_code'],
                'refund_amount' => $cancellation['refund_amount'],
                'wallet_txn_code' => $walletTxnCode,
                'new_wallet_balance' => $balanceAfter
            ]);
        } else {
            // Refund to wallet is disabled - just mark as completed without wallet impact
            Database::execute(
                "UPDATE ticket_cancellations SET wallet_impact_applied = 1, processed_at = NOW() WHERE cancellation_id = :cid",
                ['cid' => $cancellationId]
            );
            
            // Create refund record (cash only)
            Database::execute(
                "INSERT INTO ticket_refunds 
                    (transaction_id, transaction_code, cancellation_id, passenger_id, refund_amount, refund_method, status, requested_by, cashier_session_id, requested_at, processed_by, processed_at, wallet_impact_applied)
                 VALUES (:tid, :code, :cid, :pid, :ramount, 'cash', 'completed', :ruid, :rcsid, :rtime, :puid, NOW(), 0)",
                [
                    'tid' => $ticketTxn['transaction_id'],
                    'code' => $ticketTxn['transaction_code'],
                    'cid' => $cancellationId,
                    'pid' => $ticketTxn['passenger_id'],
                    'ramount' => $cancellation['refund_amount'],
                    'ruid' => $cancellation['requested_by'],
                    'rcsid' => $cancellation['cashier_session_id'],
                    'rtime' => $cancellation['requested_at'],
                    'puid' => $user['user_id']
                ]
            );
            
            logActivity($user['user_id'], 'CANCELLATION_APPROVED', 'POS', $ticketTxn['transaction_code'], null,
                ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxn['transaction_id'], 'refund_amount' => $cancellation['refund_amount'], 'wallet_impact' => 'skipped']);
            
            Database::connection()->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cancellation approved successfully. Refund given from cashier cash only.',
                'cancellation_id' => $cancellationId,
                'transaction_code' => $ticketTxn['transaction_code'],
                'refund_amount' => $cancellation['refund_amount']
            ]);
        }
    } else {
        // Reject cancellation
        Database::execute(
            "UPDATE ticket_cancellations SET status = 'rejected', approved_by = :uid, approved_at = NOW(), rejection_reason = :reason WHERE cancellation_id = :cid",
            ['cid' => $cancellationId, 'uid' => $user['user_id'], 'reason' => $rejectionReason]
        );
        
        logActivity($user['user_id'], 'CANCELLATION_REJECTED', 'POS', $ticketTxn['transaction_code'], null,
            ['cancellation_id' => $cancellationId, 'ticket_txn_id' => $ticketTxn['transaction_id'], 'rejection_reason' => $rejectionReason]);
        
        Database::connection()->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cancellation request rejected.',
            'cancellation_id' => $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'rejection_reason' => $rejectionReason
        ]);
    }

} catch (Exception $e) {
    Database::connection()->rollBack();
    echo json_encode(['success' => false, 'error' => 'Approval failed: ' . $e->getMessage()]);
}
