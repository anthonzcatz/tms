<?php
/**
 * POS Cancellation Approval API — Handle approval/rejection of pending ticket cancellations
 *
 * APPROVE:
 *   • Applies the requested ticket Void or Refund operation.
 *   • Reverses the original CHARGE account portion when applicable.
 *   • Restores provider wallet balance only for eligible non-variant tickets.
 *   • Applies an approved cashier responsibility deduction to the selected open session.
 *   • Creates the source refund record for real refunds.
 *
 * REJECT:
 *   • Marks the cancellation as rejected — ticket stays booked, no financial changes.
 *   • Releases any pending cash-refund reservation from the requesting cashier session.
 *   • customer_charges is NOT touched (debt was never reversed on pending).
 *   • pos_order_items is NOT touched (was never zeroed on pending).
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/CancellationService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';
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

function resolveResponsibleCashier(int $cashierUserId, int $branchId, ?int $sessionId = null): ?array
{
    if ($cashierUserId <= 0 || $branchId <= 0) return null;

    $sessionFilter = '';
    $params = [
        'cashier_user_id' => $cashierUserId,
        'branch_id' => $branchId,
        'assigned_branch_id' => $branchId,
    ];
    if ($sessionId !== null) {
        $sessionFilter = ' AND cs.session_id = :session_id';
        $params['session_id'] = $sessionId;
    }

    return Database::fetch(
        "SELECT ua.user_id, cs.session_id, cs.cashier_user_id, cs.branch_id
         FROM user_accounts ua
         JOIN user_roles ur ON ur.role_id = ua.role_id
         LEFT JOIN cashier_sessions cs
           ON cs.cashier_user_id = ua.user_id
          AND cs.branch_id = :branch_id
          AND cs.status = 'OPEN'
         WHERE ua.user_id = :cashier_user_id
           AND ua.status = 'active'
           AND FIND_IN_SET(:assigned_branch_id, ua.branch_id) > 0
           AND ur.role_code = 'CASHIER'
           {$sessionFilter}
         ORDER BY cs.started_at DESC
         LIMIT 1",
        $params
    ) ?: null;
}

function applyTicketAdjustmentSettlement(array $cancellation, int $approverId, ?int $ticketBranchId = null): void
{
    $adjustmentId = (int) ($cancellation['adjustment_id'] ?? 0);
    if ($adjustmentId <= 0) return;

    $responsibility = strtoupper((string) ($cancellation['responsibility'] ?? 'NONE'));
    $amount = max(0, round((float) ($cancellation['responsibility_amount'] ?? 0), 2));
    $settlementStatus = 'NOT_APPLICABLE';
    $settledAt = null;
    $settledBy = null;

    if ($responsibility === 'CASHIER' && $amount > 0) {
        $targetUserId = (int) ($cancellation['responsible_user_id'] ?? 0);
        $targetSessionId = (int) ($cancellation['responsibility_cashier_session_id'] ?? 0);
        if ($targetUserId <= 0) {
            throw new RuntimeException('A responsible cashier is required.');
        }

        $targetCashier = resolveResponsibleCashier(
            $targetUserId,
            (int) $ticketBranchId,
            $targetSessionId > 0 ? $targetSessionId : null
        );
        if (!$targetCashier && $targetSessionId > 0) {
            $targetCashier = resolveResponsibleCashier($targetUserId, (int) $ticketBranchId);
        }
        if (!$targetCashier) {
            throw new RuntimeException('The selected cashier is no longer active or assigned to the ticket branch.');
        }

        $targetSessionId = (int) ($targetCashier['session_id'] ?? 0);
        if ($targetSessionId > 0) {
            Database::execute(
                "UPDATE cashier_sessions
                 SET total_cash_adjustments = COALESCE(total_cash_adjustments, 0) + :amount
                 WHERE session_id = :session_id",
                ['amount' => $amount, 'session_id' => $targetSessionId]
            );
            $settlementStatus = 'DEDUCTED';
            $settledAt = date('Y-m-d H:i:s');
            $settledBy = $approverId;
        } else {
            $settlementStatus = 'RECORDED';
        }
    } elseif ($responsibility === 'CUSTOMER' && $amount > 0) {
        $settlementStatus = 'AUDIT_ONLY';
        $settledAt = date('Y-m-d H:i:s');
        $settledBy = $approverId;
    }

    Database::execute(
        "UPDATE ticket_adjustments
         SET approval_status = 'APPROVED',
             approved_by = :approved_by,
             approved_at = NOW(),
             settlement_status = :settlement_status,
             settled_at = :settled_at,
             settled_by = :settled_by
         WHERE adjustment_id = :adjustment_id
           AND approval_status = 'PENDING'",
        [
            'approved_by' => $approverId,
            'settlement_status' => $settlementStatus,
            'settled_at' => $settledAt,
            'settled_by' => $settledBy,
            'adjustment_id' => $adjustmentId,
        ]
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
$requestedResponsibleUserId = !empty($input['responsible_user_id']) ? (int) $input['responsible_user_id'] : null;

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

$isManager = in_array($user['role_code'] ?? '', ['SUPER_ADMIN', 'MANAGER'], true);
if ($action === 'approve'
    && strtoupper((string) ($cancellation['responsibility'] ?? 'NONE')) === 'CASHIER'
    && !$isManager) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Manager approval is required for cashier responsibility.']);
    exit;
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
try {
    PosAccess::assertBranchAccess($user, (int) $ticketTxn['branch_id']);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit;
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

$operationType = strtoupper((string) ($cancellation['operation_type'] ?? 'REFUND'));
$refundAmount = max(0, round((float) ($cancellation['refund_amount'] ?? 0), 2));
$grossRefundAmount = max(0, round((float) ($cancellation['gross_refund_amount'] ?? $refundAmount), 2));
$chargeAmount = floatval($cancellation['charge_amount'] ?? 0);
$cashRefundAmount = floatval($cancellation['cash_refund_amount'] ?? ($refundAmount - $chargeAmount));

// Start database transaction
Database::connection()->beginTransaction();

try {
    $lockedCancellation = Database::fetch(
        "SELECT * FROM ticket_cancellations WHERE cancellation_id = :cancellation_id FOR UPDATE",
        ['cancellation_id' => $cancellationId]
    );
    if (!$lockedCancellation || $lockedCancellation['status'] !== 'pending') {
        throw new RuntimeException('Cancellation request has already been processed.');
    }
    $cancellation = $lockedCancellation;

    if ($action === 'approve'
        && strtoupper((string) ($cancellation['responsibility'] ?? 'NONE')) === 'CASHIER'
        && $requestedResponsibleUserId) {
        $targetCashier = resolveResponsibleCashier(
            $requestedResponsibleUserId,
            (int) $ticketTxn['branch_id']
        );
        if (!$targetCashier) {
            throw new RuntimeException('The selected cashier must be active and assigned to the ticket branch.');
        }

        Database::execute(
            "UPDATE ticket_cancellations
             SET responsible_user_id = :responsible_user_id,
                 responsibility_cashier_session_id = :cashier_session_id
             WHERE cancellation_id = :cancellation_id",
            [
                'responsible_user_id' => $requestedResponsibleUserId,
                'cashier_session_id' => $targetCashier['session_id'] ?? null,
                'cancellation_id' => (int) $cancellationId,
            ]
        );
        if (!empty($cancellation['adjustment_id'])) {
            Database::execute(
                "UPDATE ticket_adjustments
                 SET responsible_user_id = :responsible_user_id,
                     cashier_session_id = :cashier_session_id
                 WHERE adjustment_id = :adjustment_id",
                [
                    'responsible_user_id' => $requestedResponsibleUserId,
                    'cashier_session_id' => $targetCashier['session_id'] ?? null,
                    'adjustment_id' => (int) $cancellation['adjustment_id'],
                ]
            );
        }
        $cancellation['responsible_user_id'] = $requestedResponsibleUserId;
        $cancellation['responsibility_cashier_session_id'] = $targetCashier['session_id'] ?? null;
    }

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

        // Release the pending cash reservation before the shared refund service
        // posts the finalized cash amount into total_refunds.
        if ($cancellation['cashier_session_id'] && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions
                 SET pending_refunds_cash = GREATEST(0, COALESCE(pending_refunds_cash, 0) - :amount)
                 WHERE session_id = :session_id",
                ['amount' => $cashRefundAmount, 'session_id' => $cancellation['cashier_session_id']]
            );
        }

        applyTicketAdjustmentSettlement($cancellation, (int) $user['user_id'], (int) $ticketTxn['branch_id']);

        $processingDays = $settings['cancellation_refund_processing_days'] ?? 0;
        $effects = $operationType === 'VOID'
            ? CancellationService::processVoidEffects(
                $ticketTxn,
                $cancellation,
                (int) $user['user_id'],
                $remarks ?: ($cancellation['reason'] ?? null)
            )
            : CancellationService::processCancellationEffects(
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

        logActivity($user['user_id'], 'TICKET_ADJUSTMENT_APPROVED', 'POS', $ticketTxn['transaction_code'],
            ['status' => 'pending'],
            ['status' => 'completed', 'cancellation_id' => $cancellationId,
             'ticket_txn_id' => $ticketTxn['transaction_id'],
             'operation_type' => $operationType,
             'responsibility' => $cancellation['responsibility'] ?? 'NONE',
             'responsible_user_id' => $cancellation['responsible_user_id'] ?? null,
             'responsibility_amount' => $cancellation['responsibility_amount'] ?? 0,
             'gross_refund_amount' => $grossRefundAmount,
             'refund_amount' => $refundAmount,
             'charge_amount' => $effects['charge_reversal_amount'],
             'cash_refund_amount' => $effects['cash_refund_amount'],
             'bank_refund_amount' => $effects['bank_refund_amount'] ?? 0,
             'wallet_balance_before' => $effects['wallet_balance_before'],
             'wallet_balance_after' => $effects['wallet_balance_after'],
             'wallet_txn_code' => $effects['wallet_txn_code'], 'remarks' => $remarks]);

        Database::connection()->commit();

        PusherService::triggerBranch((int) $ticketTxn['branch_id'], 'refund.updated', [
            'branch_id' => (int) $ticketTxn['branch_id'],
            'cancellation_id' => (int) $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'status' => 'completed',
        ]);
        if (!empty($effects['wallet_id'])) {
            PusherService::triggerBranch((int) $ticketTxn['branch_id'], 'wallet.updated', [
                'wallet_id' => (int) $effects['wallet_id'],
                'branch_id' => (int) $ticketTxn['branch_id'],
                'current_balance' => (float) ($effects['wallet_balance_after'] ?? 0),
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => $operationType === 'VOID'
                ? 'Void approved. No cash or bank refund was issued.'
                : 'Cancellation approved and source refund processed.',
            'cancellation_id' => $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'operation_type' => $operationType,
            'gross_refund_amount' => $grossRefundAmount,
            'refund_amount' => $refundAmount,
            'responsibility' => $cancellation['responsibility'] ?? 'NONE',
            'responsibility_amount' => $cancellation['responsibility_amount'] ?? 0,
            'charge_amount' => $effects['charge_reversal_amount'],
            'cash_refund_amount' => $effects['cash_refund_amount'],
            'bank_refund_amount' => $effects['bank_refund_amount'] ?? 0,
            'refund_status' => $effects['refund_status'] ?? null,
            'is_consumed_variant' => !empty($effects['is_consumed_variant']),
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

        if (!empty($cancellation['adjustment_id'])) {
            Database::execute(
                "UPDATE ticket_adjustments
                 SET approval_status = 'REJECTED', approved_by = :approved_by, approved_at = NOW()
                 WHERE adjustment_id = :adjustment_id AND approval_status = 'PENDING'",
                ['approved_by' => $user['user_id'], 'adjustment_id' => (int) $cancellation['adjustment_id']]
            );
        }

        // Release only the pending cash reservation. No finalized cash or
        // wallet/bank balance was changed on a rejected request.
        if ($cancellation['cashier_session_id'] && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions
                 SET pending_refunds_cash = GREATEST(0, COALESCE(pending_refunds_cash, 0) - :amount)
                 WHERE session_id = :session_id",
                ['amount' => $cashRefundAmount, 'session_id' => $cancellation['cashier_session_id']]
            );
        }

        logActivity($user['user_id'], 'CANCELLATION_REJECTED', 'POS', $ticketTxn['transaction_code'],
            ['status' => 'pending'],
            ['status' => 'rejected', 'cancellation_id' => $cancellationId,
             'ticket_txn_id' => $ticketTxn['transaction_id'],
             'rejection_reason' => $rejectionReason, 'remarks' => $remarks]);

        Database::connection()->commit();

        PusherService::triggerBranch((int) $ticketTxn['branch_id'], 'refund.updated', [
            'branch_id' => (int) $ticketTxn['branch_id'],
            'cancellation_id' => (int) $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'status' => 'rejected',
        ]);

        echo json_encode([
            'success'          => true,
            'message'          => 'Cancellation request rejected. Ticket remains booked.',
            'cancellation_id'  => $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'rejection_reason' => $rejectionReason,
        ]);
    }

} catch (Throwable $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Approval failed: ' . $e->getMessage()]);
}
