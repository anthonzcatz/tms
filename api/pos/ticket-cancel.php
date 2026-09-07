<?php
/**
 * POS Ticket Adjustment API — Handle ticket Void and Refund operations.
 * Supports mixed payments (cash + CHARGE), customer responsibility deductions,
 * and manager-approved cashier responsibility. Pending operations do NOT touch
 * customer_charges, wallet balances, stock, or order items until approved.
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

function resolveTicketChargeAccountId(int $transactionId, ?int $fallbackPassengerId): ?int
{
    $row = Database::fetch(
        "SELECT tp.charged_to_passenger_id
         FROM transaction_payments tp
         JOIN payment_methods pm ON pm.method_id = tp.payment_method_id
         WHERE tp.source_type = 'TICKET_TRANSACTION'
           AND tp.source_id = :transaction_id
           AND pm.tracks_credit = 1
           AND tp.charged_to_passenger_id IS NOT NULL
           AND tp.confirmation_status <> 'REJECTED'
         ORDER BY tp.payment_id ASC
         LIMIT 1",
        ['transaction_id' => $transactionId]
    );

    return $row ? (int) $row['charged_to_passenger_id'] : ($fallbackPassengerId ?: null);
}

function resolveTargetCashier(int $cashierUserId, int $branchId): ?array
{
    if ($cashierUserId <= 0 || $branchId <= 0) return null;

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
         ORDER BY cs.started_at DESC
         LIMIT 1",
        ['cashier_user_id' => $cashierUserId, 'branch_id' => $branchId, 'assigned_branch_id' => $branchId]
    ) ?: null;
}

function createTicketAdjustment(
    int $transactionId,
    int $cancellationId,
    string $operationType,
    string $reasonCategory,
    string $responsibility,
    float $amount,
    ?int $chargeAccountId,
    ?int $responsibleUserId,
    ?int $cashierSessionId,
    string $approvalStatus,
    ?int $approvedBy,
    ?string $reason
): int {
    $adjustmentType = $operationType === 'VOID' ? 'VOID' : 'REFUND';
    $chargedTo = $responsibility === 'CUSTOMER'
        ? 'customer'
        : ($responsibility === 'CASHIER' ? 'cashier' : 'none');
    $isCashierDeductionReason = in_array(
        strtoupper($reasonCategory),
        ['PRINTER_ERROR', 'SYSTEM_ERROR', 'CASHIER_ERROR'],
        true
    );
    if ($approvalStatus === 'APPROVED' && $responsibility === 'CASHIER' && $amount > 0) {
        $settlementStatus = ($cashierSessionId && $isCashierDeductionReason) ? 'DEDUCTED' : 'RECORDED';
    } elseif ($approvalStatus === 'APPROVED' && $responsibility === 'CUSTOMER' && $amount > 0) {
        $settlementStatus = 'AUDIT_ONLY';
    } else {
        $settlementStatus = 'NOT_APPLICABLE';
    }
    $settledAt = in_array($settlementStatus, ['DEDUCTED', 'RECORDED', 'AUDIT_ONLY'], true)
        ? date('Y-m-d H:i:s')
        : null;
    $settledBy = $settledAt !== null ? $approvedBy : null;
    $idempotencyKey = 'ticket-adjustment:cancellation:' . $cancellationId;

    Database::execute(
        "INSERT INTO ticket_adjustments
            (transaction_id, cancellation_id, idempotency_key, type, amount, reason,
             charged_to, charged_to_passenger_id, responsible_user_id, cashier_session_id,
             approval_status, approved_by, approved_at, settlement_status, created_by, settled_at, settled_by, created_at)
         VALUES (:transaction_id, :cancellation_id, :idempotency_key, :type, :amount, :reason,
                 :charged_to, :charged_to_passenger_id, :responsible_user_id, :cashier_session_id,
                 :approval_status, :approved_by, :approved_at, :settlement_status, :created_by, :settled_at, :settled_by, NOW())",
        [
            'transaction_id' => $transactionId,
            'cancellation_id' => $cancellationId,
            'idempotency_key' => $idempotencyKey,
            'type' => $adjustmentType,
            'amount' => max(0, round($amount, 2)),
            'reason' => $reason,
            'charged_to' => $chargedTo,
            'charged_to_passenger_id' => $chargeAccountId,
            'responsible_user_id' => $responsibleUserId,
            'cashier_session_id' => $cashierSessionId,
            'approval_status' => $approvalStatus,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedBy ? date('Y-m-d H:i:s') : null,
            'settlement_status' => $settlementStatus,
            'created_by' => Auth::id(),
            'settled_at' => $settledAt,
            'settled_by' => $settledBy,
        ]
    );
    $adjustmentId = (int) Database::lastInsertId();

    if ($settlementStatus === 'DEDUCTED' && $cashierSessionId && $amount > 0) {
        Database::execute(
            "UPDATE cashier_sessions
             SET total_cash_adjustments = COALESCE(total_cash_adjustments, 0) + :amount
             WHERE session_id = :session_id",
            ['amount' => max(0, round($amount, 2)), 'session_id' => $cashierSessionId]
        );
    }

    return $adjustmentId;
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
$operationType = strtoupper(trim((string) ($input['operation_type'] ?? 'REFUND')));
$reasonCategory = strtoupper(trim((string) ($input['reason_category'] ?? 'OTHER')));
$responsibility = strtoupper(trim((string) ($input['responsibility'] ?? 'NONE')));
$reason = trim((string) ($input['reason'] ?? ''));
$grossRefundAmount = max(0, round((float) ($input['refund_amount'] ?? 0), 2));
$responsibilityAmount = max(0, round((float) ($input['responsibility_amount'] ?? 0), 2));
$enteredVoidFee = max(0, round((float) ($input['void_fee'] ?? 0), 2));
$enteredVoidServiceFee = max(0, round((float) ($input['void_service_fee'] ?? 0), 2));
$voidFee = 0.0;
$voidServiceFee = 0.0;
$lostSalesVoidFee = 0.0;
$lostSalesServiceFee = 0.0;
$responsibleUserId = !empty($input['responsible_user_id']) ? (int) $input['responsible_user_id'] : null;

$allowedOperations = ['REFUND', 'VOID'];
$allowedReasonCategories = ['CUSTOMER_REQUEST', 'CUSTOMER_ERROR', 'CASHIER_ERROR', 'PRINTER_ERROR', 'SYSTEM_ERROR', 'CANCEL', 'OTHER'];
$allowedResponsibilities = ['NONE', 'CUSTOMER', 'CASHIER'];

$requiredResponsibilityByReason = [
    'CUSTOMER_ERROR' => 'CUSTOMER',
    'CASHIER_ERROR'  => 'CASHIER',
    'PRINTER_ERROR'  => 'NONE',
    'SYSTEM_ERROR'   => 'NONE',
    'CANCEL'         => 'NONE',
    'CUSTOMER_REQUEST' => 'NONE',
    'OTHER'          => 'NONE',
];

if (!$txnCode) { echo json_encode(['success' => false, 'error' => 'Transaction code required.']); exit; }
if (!in_array($operationType, $allowedOperations, true)) { echo json_encode(['success' => false, 'error' => 'Invalid operation type.']); exit; }
if ($operationType === 'REFUND') {
    $reasonCategory = 'OTHER';
    $responsibility = 'NONE';
    $responsibilityAmount = 0.0;
    $responsibleUserId = null;
}
if ($operationType === 'VOID' && $reasonCategory === 'CANCEL') {
    // Cancel is a no-fee, no-responsibility Void category. Enforce this on
    // the server so crafted requests cannot create charges through this path.
    $responsibility = 'NONE';
    $responsibilityAmount = 0.0;
    $responsibleUserId = null;
    $enteredVoidFee = 0.0;
    $enteredVoidServiceFee = 0.0;
}
if (!in_array($reasonCategory, $allowedReasonCategories, true)) { echo json_encode(['success' => false, 'error' => 'Invalid reason category.']); exit; }
if (!in_array($responsibility, $allowedResponsibilities, true)) { echo json_encode(['success' => false, 'error' => 'Invalid responsibility type.']); exit; }
if (!$reason) { echo json_encode(['success' => false, 'error' => 'Reason is required.']); exit; }
if ($operationType === 'REFUND' && $grossRefundAmount <= 0) { echo json_encode(['success' => false, 'error' => 'Refund amount must be greater than 0.']); exit; }
if ($operationType === 'VOID') {
    $grossRefundAmount = 0.0;
    if (in_array($reasonCategory, ['PRINTER_ERROR', 'SYSTEM_ERROR'], true)) {
        $lostSalesVoidFee = $enteredVoidFee;
    } else {
        $voidFee = $enteredVoidFee;
        $voidServiceFee = $enteredVoidServiceFee;
    }
}
if ($operationType === 'VOID' && $responsibility !== 'NONE') {
    $responsibilityAmount = round($voidFee + $voidServiceFee, 2);
}
if ($responsibility === 'NONE' && $responsibilityAmount > 0) { echo json_encode(['success' => false, 'error' => 'A responsibility type is required for the entered amount.']); exit; }
$reasonAllowsResponsibility = $operationType === 'VOID' && $reasonCategory === 'CUSTOMER_REQUEST';
if (!$reasonAllowsResponsibility
    && isset($requiredResponsibilityByReason[$reasonCategory])
    && $responsibility !== $requiredResponsibilityByReason[$reasonCategory]) {
    $expected = strtolower($requiredResponsibilityByReason[$reasonCategory] === 'NONE' ? 'no responsibility' : $requiredResponsibilityByReason[$reasonCategory] . ' responsibility');
    echo json_encode(['success' => false, 'error' => ucfirst(str_replace('_', ' ', strtolower($reasonCategory))) . ' requires ' . $expected . '.']); exit;
}
if ($responsibility === 'CASHIER' && !$responsibleUserId) { echo json_encode(['success' => false, 'error' => 'A responsible cashier is required.']); exit; }
if ($responsibility !== 'CASHIER') $responsibleUserId = null;
if ($responsibility === 'CASHIER'
    && $operationType === 'VOID'
    && $responsibilityAmount <= 0
    && $reasonCategory !== 'CASHIER_ERROR') {
    echo json_encode(['success' => false, 'error' => 'Responsibility amount must be greater than 0 for a VOID assigned to a cashier.']); exit;
}
if ($responsibility === 'CUSTOMER' && $operationType === 'REFUND' && $responsibilityAmount >= $grossRefundAmount) {
    echo json_encode(['success' => false, 'error' => 'Customer responsibility amount must be less than the refund amount.']); exit;
}
if ($responsibility === 'CASHIER' && $operationType === 'REFUND' && $responsibilityAmount <= 0) {
    $responsibilityAmount = $grossRefundAmount;
}

// Get system settings for cancellation
try {
    $settings = Database::fetch(
        "SELECT cancellation_requires_confirmation, void_requires_confirmation,
                return_requires_confirmation, cancellation_refund_processing_days
         FROM system_settings WHERE setting_id = 1"
    ) ?: [];
} catch (Throwable $e) {
    $settings = Database::fetch(
        "SELECT cancellation_requires_confirmation, cancellation_refund_processing_days
         FROM system_settings WHERE setting_id = 1"
    ) ?: [];
    $settings['void_requires_confirmation'] = $settings['cancellation_requires_confirmation'] ?? 1;
    $settings['return_requires_confirmation'] = $settings['cancellation_requires_confirmation'] ?? 1;
}

// Get ticket transaction by transaction code
$ticketTxn = Database::fetch(
    "SELECT * FROM ticket_transactions WHERE transaction_code = :code",
    ['code' => $txnCode]
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
if ($grossRefundAmount > $totalAmount) {
    echo json_encode(['success' => false, 'error' => 'Refund amount cannot exceed the original Total Amount (₱' . number_format($totalAmount, 2) . ').']); exit;
}

$ticketTxnId = (int) $ticketTxn['transaction_id'];
$passengerId = $ticketTxn['passenger_id'] ?? null;
$printFeeAmount = $operationType === 'VOID' && $reasonCategory === 'CASHIER_ERROR'
    ? CancellationService::printFeeAmountForTicket($ticketTxnId)
    : 0.0;
if ($operationType === 'VOID' && $responsibility === 'CASHIER') {
    $responsibilityAmount = round($voidFee + $voidServiceFee + $printFeeAmount, 2);
}
if ($responsibility === 'CASHIER' && $operationType === 'VOID' && $responsibilityAmount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Responsibility amount must be greater than 0 for a VOID assigned to a cashier.']); exit;
}
$refundAmount = $operationType === 'REFUND'
    ? round($grossRefundAmount - ($responsibility === 'CUSTOMER' ? $responsibilityAmount : 0), 2)
    : 0.0;

if (!CancellationService::resolveProviderId($ticketTxn)) {
    echo json_encode(['success' => false, 'error' => 'This ticket transaction has no associated provider or wallet.']); exit;
}

$targetCashier = null;
if ($responsibility === 'CASHIER') {
    $targetCashier = resolveTargetCashier($responsibleUserId, (int) $ticketTxn['branch_id']);
    if (!$targetCashier) {
        echo json_encode(['success' => false, 'error' => 'The selected cashier must be active and assigned to this transaction branch.']); exit;
    }
}

$chargeAccountId = resolveTicketChargeAccountId($ticketTxnId, $passengerId ? (int) $passengerId : null);
$chargeAmount = 0.0;
$cashRefundAmount = 0.0;
if ($operationType === 'REFUND') {
    try {
        $refundPreview = RefundService::previewPaymentAllocations(
            'TICKET_TRANSACTION',
            $ticketTxnId,
            $refundAmount
        );
        $chargeAmount = (float) ($refundPreview['charge_amount'] ?? 0);
        $cashRefundAmount = (float) ($refundPreview['cash_amount'] ?? 0);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Refund allocation failed: ' . $e->getMessage()]);
        exit;
    }
} else {
    $chargeAmount = CancellationService::computeChargePaymentsTotal($ticketTxnId);
}

$requiresConfirmation = (int) ($operationType === 'VOID'
    ? ($settings['void_requires_confirmation'] ?? $settings['cancellation_requires_confirmation'] ?? 1)
    : ($settings['return_requires_confirmation'] ?? $settings['cancellation_requires_confirmation'] ?? 1));

// Get active cashier session for this user
$cashierSession = Database::fetch(
    "SELECT session_id FROM cashier_sessions 
     WHERE cashier_user_id = :uid AND status = 'OPEN' 
     ORDER BY started_at DESC LIMIT 1",
    ['uid' => $user['user_id']]
);
$cashierSessionId = $cashierSession
    ? (int) $cashierSession['session_id']
    : (!empty($ticketTxn['cashier_session_id']) ? (int) $ticketTxn['cashier_session_id'] : null);

// Start database transaction
Database::connection()->beginTransaction();

try {
    $lockedTicketTxn = Database::fetch(
        "SELECT * FROM ticket_transactions WHERE transaction_id = :transaction_id FOR UPDATE",
        ['transaction_id' => $ticketTxnId]
    );
    if (!$lockedTicketTxn || in_array($lockedTicketTxn['status'], ['cancelled', 'refunded'], true)) {
        throw new RuntimeException('Ticket transaction is already cancelled/refunded.');
    }
    $pendingCheck = Database::fetch(
        "SELECT cancellation_id FROM ticket_cancellations
         WHERE transaction_id = :transaction_id AND status = 'pending'
         LIMIT 1",
        ['transaction_id' => $ticketTxnId]
    );
    if ($pendingCheck) {
        throw new RuntimeException('There is already a pending cancellation request for this ticket.');
    }
    $ticketTxn = $lockedTicketTxn;

    $ticketServiceFee = max(0, round((float) ($ticketTxn['service_fee'] ?? 0), 2));
    $refundableTicketAmount = max(0, $totalAmount - $ticketServiceFee);
    $cancellationType = $operationType === 'VOID'
        || $grossRefundAmount >= $refundableTicketAmount
        ? 'full'
        : 'partial';

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
                (transaction_id, transaction_code, operation_type, passenger_id, reason, reason_category,
                 responsibility, responsible_user_id, cancellation_type, refund_amount, gross_refund_amount,
                 charge_amount, cash_refund_amount, responsibility_amount, void_fee, void_service_fee,
                 lost_sales_void_fee, lost_sales_service_fee,
                 status, requested_by, cashier_session_id, responsibility_cashier_session_id, requested_at)
             VALUES (:tid, :code, :operation_type, :pid, :reason, :reason_category,
                     :responsibility, :responsible_user_id, :ctype, :ramount, :gross_amount,
                     :camount, :cramount, :responsibility_amount, :void_fee, :void_service_fee,
                     :lost_sales_void_fee, :lost_sales_service_fee, 'pending', :uid,
                     :csid, :target_csid, NOW())",
            [
                'tid' => $ticketTxnId,
                'code' => $ticketTxn['transaction_code'],
                'operation_type' => $operationType,
                'pid' => $passengerId,
                'reason' => $reason,
                'reason_category' => $reasonCategory,
                'responsibility' => $responsibility,
                'responsible_user_id' => $responsibleUserId,
                'ctype' => $cancellationType,
                'ramount' => $refundAmount,
                'gross_amount' => $grossRefundAmount,
                'camount' => $chargeAmount,
                'cramount' => $cashRefundAmount,
                'responsibility_amount' => $responsibilityAmount,
                'void_fee' => $voidFee,
                'void_service_fee' => $voidServiceFee,
                'lost_sales_void_fee' => $lostSalesVoidFee,
                'lost_sales_service_fee' => $lostSalesServiceFee,
                'uid' => $user['user_id'],
                'csid' => $cashierSessionId,
                'target_csid' => $targetCashier['session_id'] ?? null,
            ]
        );

        $cancellationId = (int) Database::lastInsertId();
        $adjustmentId = createTicketAdjustment(
            $ticketTxnId,
            $cancellationId,
            $operationType,
            $reasonCategory,
            $responsibility,
            $responsibilityAmount,
            $responsibility === 'CUSTOMER' ? $chargeAccountId : null,
            $responsibleUserId,
            $targetCashier['session_id'] ?? null,
            'PENDING',
            null,
            $reason
        );
        Database::execute(
            "UPDATE ticket_cancellations SET adjustment_id = :adjustment_id WHERE cancellation_id = :cancellation_id",
            ['adjustment_id' => $adjustmentId, 'cancellation_id' => $cancellationId]
        );

        // Track pending cash separately; finalized expected cash is updated
        // only after approval and actual refund processing.
        if ($cashierSessionId && $cashRefundAmount > 0) {
            Database::execute(
                "UPDATE cashier_sessions
                 SET pending_refunds_cash = COALESCE(pending_refunds_cash, 0) + :amount
                 WHERE session_id = :session_id",
                ['amount' => $cashRefundAmount, 'session_id' => $cashierSessionId]
            );
        }

        logActivity($user['user_id'], 'TICKET_ADJUSTMENT_REQUEST', 'POS', $ticketTxn['transaction_code'], null,
            ['cancellation_id' => $cancellationId, 'adjustment_id' => $adjustmentId, 'ticket_txn_id' => $ticketTxnId,
             'operation_type' => $operationType, 'reason_category' => $reasonCategory,
             'responsibility' => $responsibility, 'responsible_user_id' => $responsibleUserId,
             'gross_refund_amount' => $grossRefundAmount, 'refund_amount' => $refundAmount,
             'responsibility_amount' => $responsibilityAmount, 'void_fee' => $voidFee,
             'void_service_fee' => $voidServiceFee,
             'lost_sales_void_fee' => $lostSalesVoidFee, 'lost_sales_service_fee' => $lostSalesServiceFee,
             'charge_amount' => $chargeAmount, 'status' => 'pending']);

        Database::connection()->commit();

        PusherService::triggerBranch((int) $ticketTxn['branch_id'], 'refund.updated', [
            'branch_id' => (int) $ticketTxn['branch_id'],
            'cancellation_id' => (int) $cancellationId,
            'transaction_code' => $ticketTxn['transaction_code'],
            'status' => 'pending',
        ]);

        echo json_encode([
            'success' => true,
            'message' => $operationType === 'VOID'
                ? 'Void request submitted. Awaiting manager approval.'
                : 'Cancellation/refund request submitted. Awaiting manager approval.',
            'transaction_id' => $ticketTxn['transaction_id'],
            'transaction_code' => $ticketTxn['transaction_code'],
            'operation_type' => $operationType,
            'reason_category' => $reasonCategory,
            'gross_refund_amount' => $grossRefundAmount,
            'refund_amount' => $refundAmount,
            'responsibility' => $responsibility,
            'responsibility_amount' => $responsibilityAmount,
            'void_fee' => $voidFee,
            'void_service_fee' => $voidServiceFee,
            'lost_sales_void_fee' => $lostSalesVoidFee,
            'lost_sales_service_fee' => $lostSalesServiceFee,
            'charge_amount' => $chargeAmount,
            'cancellation_id' => $cancellationId,
            'adjustment_id' => $adjustmentId,
            'status' => 'pending_confirmation',
            'requires_confirmation' => true,
            'refund_source' => $operationType === 'REFUND' ? 'original_payment_sources' : 'none',
        ]);

    } else {
        // -----------------------------------------------------------------
        // IMMEDIATE CANCEL PATH — no confirmation required.
        // Financial/restorative effects are handled centrally by CancellationService.
        // -----------------------------------------------------------------

        // Record the approved cancellation request.
        Database::execute(
            "INSERT INTO ticket_cancellations
                (transaction_id, transaction_code, operation_type, passenger_id, reason, reason_category,
                 responsibility, responsible_user_id, cancellation_type, refund_amount, gross_refund_amount,
                 charge_amount, cash_refund_amount, responsibility_amount, void_fee, void_service_fee,
                 lost_sales_void_fee, lost_sales_service_fee,
                 status, requested_by, cashier_session_id, responsibility_cashier_session_id, requested_at,
                 approved_by, approved_at)
             VALUES (:tid, :code, :operation_type, :pid, :reason, :reason_category,
                     :responsibility, :responsible_user_id, :ctype, :ramount, :gross_amount,
                     :camount, :cramount, :responsibility_amount, :void_fee, :void_service_fee,
                     :lost_sales_void_fee, :lost_sales_service_fee, 'approved', :uid,
                     :csid, :target_csid, NOW(), :uid2, NOW())",
            [
                'tid' => $ticketTxnId,
                'code' => $ticketTxn['transaction_code'],
                'operation_type' => $operationType,
                'pid' => $passengerId,
                'reason' => $reason,
                'reason_category' => $reasonCategory,
                'responsibility' => $responsibility,
                'responsible_user_id' => $responsibleUserId,
                'ctype' => $cancellationType,
                'ramount' => $refundAmount,
                'gross_amount' => $grossRefundAmount,
                'camount' => $chargeAmount,
                'cramount' => $cashRefundAmount,
                'responsibility_amount' => $responsibilityAmount,
                'void_fee' => $voidFee,
                'void_service_fee' => $voidServiceFee,
                'lost_sales_void_fee' => $lostSalesVoidFee,
                'lost_sales_service_fee' => $lostSalesServiceFee,
                'uid' => $user['user_id'],
                'csid' => $cashierSessionId,
                'target_csid' => $targetCashier['session_id'] ?? null,
                'uid2' => $user['user_id'],
            ]
        );

        $cancellationId = (int) Database::lastInsertId();
        $adjustmentId = createTicketAdjustment(
            $ticketTxnId,
            $cancellationId,
            $operationType,
            $reasonCategory,
            $responsibility,
            $responsibilityAmount,
            $responsibility === 'CUSTOMER' ? $chargeAccountId : null,
            $responsibleUserId,
            $targetCashier['session_id'] ?? null,
            'APPROVED',
            (int) $user['user_id'],
            $reason
        );
        Database::execute(
            "UPDATE ticket_cancellations SET adjustment_id = :adjustment_id WHERE cancellation_id = :cancellation_id",
            ['adjustment_id' => $adjustmentId, 'cancellation_id' => $cancellationId]
        );

        $processingDays = $settings['cancellation_refund_processing_days'] ?? 0;
        $cancellationData = [
            'cancellation_id' => $cancellationId,
            'requested_by' => $user['user_id'],
            'cashier_session_id' => $cashierSessionId,
            'requested_at' => date('Y-m-d H:i:s'),
            'reason' => $reason,
            'reason_category' => $reasonCategory,
            'operation_type' => $operationType,
            'cancellation_type' => $cancellationType,
            'gross_refund_amount' => $grossRefundAmount,
            'responsibility_amount' => $responsibilityAmount,
            'void_fee' => $voidFee,
            'void_service_fee' => $voidServiceFee,
            'lost_sales_void_fee' => $lostSalesVoidFee,
            'lost_sales_service_fee' => $lostSalesServiceFee,
            'adjustment_id' => $adjustmentId,
        ];

        $effects = $operationType === 'VOID'
            ? CancellationService::processVoidEffects(
                $ticketTxn,
                $cancellationData,
                (int) $user['user_id'],
                $reason
            )
            : CancellationService::processCancellationEffects(
                $ticketTxn,
                $cancellationData,
                $refundAmount,
                $cashRefundAmount,
                $chargeAmount,
                (int) $user['user_id'],
                (int) $processingDays,
                $reason,
                null
            );

        logActivity($user['user_id'], 'TICKET_ADJUSTMENT', 'POS', $ticketTxn['transaction_code'], null,
            ['cancellation_id' => $cancellationId, 'adjustment_id' => $adjustmentId, 'ticket_txn_id' => $ticketTxnId,
             'operation_type' => $operationType, 'reason_category' => $reasonCategory,
             'responsibility' => $responsibility, 'responsible_user_id' => $responsibleUserId,
             'gross_refund_amount' => $grossRefundAmount, 'refund_amount' => $refundAmount,
             'responsibility_amount' => $responsibilityAmount,
             'void_fee' => $voidFee, 'void_service_fee' => $voidServiceFee,
             'lost_sales_void_fee' => $lostSalesVoidFee, 'lost_sales_service_fee' => $lostSalesServiceFee,
             'charge_amount' => $effects['charge_reversal_amount'],
             'wallet_balance_before' => $effects['wallet_balance_before'],
             'wallet_balance_after' => $effects['wallet_balance_after'],
             'wallet_txn_code' => $effects['wallet_txn_code'],
             'void_fee_wallet_debited' => $effects['void_fee_wallet_debited'] ?? false,
             'void_fee_wallet_debit_amount' => $effects['void_fee_wallet_debit_amount'] ?? 0,
             'void_fee_wallet_debit_void_fee' => $effects['void_fee_wallet_debit_void_fee'] ?? 0,
             'void_fee_wallet_debit_service_fee' => $effects['void_fee_wallet_debit_service_fee'] ?? 0,
             'void_fee_wallet_balance_before' => $effects['void_fee_wallet_balance_before'] ?? null,
             'void_fee_wallet_balance_after' => $effects['void_fee_wallet_balance_after'] ?? null,
             'void_fee_wallet_txn_code' => $effects['void_fee_wallet_txn_code'] ?? null,
             'stock_restored' => $effects['stock_restored'] ?? false,
             'stock_restoration_already_applied' => $effects['stock_restoration_already_applied'] ?? false,
             'stock_restoration_movement_id' => $effects['stock_restoration_movement_id'] ?? null,
             'stock_restoration_quantity' => $effects['stock_restoration_quantity'] ?? 0,
             'requires_confirmation' => false]);

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
                ? 'Ticket voided successfully. No cash or bank refund was issued.'
                : 'Ticket cancelled successfully and source refund processed.',
            'transaction_id' => $ticketTxn['transaction_id'],
            'transaction_code' => $ticketTxn['transaction_code'],
            'operation_type' => $operationType,
            'reason_category' => $reasonCategory,
            'gross_refund_amount' => $grossRefundAmount,
            'refund_amount' => $refundAmount,
            'responsibility' => $responsibility,
            'responsibility_amount' => $responsibilityAmount,
            'void_fee' => $voidFee,
            'void_service_fee' => $voidServiceFee,
            'lost_sales_void_fee' => $lostSalesVoidFee,
            'lost_sales_service_fee' => $lostSalesServiceFee,
            'charge_amount' => $effects['charge_reversal_amount'],
            'cash_refund_amount' => $effects['cash_refund_amount'],
            'bank_refund_amount' => $effects['bank_refund_amount'] ?? 0,
            'void_fee_wallet_debited' => $effects['void_fee_wallet_debited'] ?? false,
            'void_fee_wallet_debit_amount' => $effects['void_fee_wallet_debit_amount'] ?? 0,
            'void_fee_wallet_debit_void_fee' => $effects['void_fee_wallet_debit_void_fee'] ?? 0,
            'void_fee_wallet_debit_service_fee' => $effects['void_fee_wallet_debit_service_fee'] ?? 0,
            'void_fee_wallet_balance_before' => $effects['void_fee_wallet_balance_before'] ?? null,
            'void_fee_wallet_balance_after' => $effects['void_fee_wallet_balance_after'] ?? null,
            'void_fee_wallet_txn_code' => $effects['void_fee_wallet_txn_code'] ?? null,
            'stock_restored' => $effects['stock_restored'] ?? false,
            'stock_restoration_already_applied' => $effects['stock_restoration_already_applied'] ?? false,
            'stock_restoration_movement_id' => $effects['stock_restoration_movement_id'] ?? null,
            'stock_restoration_quantity' => $effects['stock_restoration_quantity'] ?? 0,
            'cancellation_id' => $cancellationId,
            'adjustment_id' => $adjustmentId,
            'refund_status' => $effects['refund_status'] ?? null,
            'is_consumed_variant' => !empty($effects['is_consumed_variant']),
            'requires_confirmation' => false,
        ]);
    }

} catch (Throwable $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Cancellation failed: ' . $e->getMessage()]);
}

