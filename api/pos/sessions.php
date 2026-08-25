<?php
/**
 * POS Sessions API — Open / Close cashier sessions
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/NotificationService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BalanceLedgerService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';

function logActivity($userId, $action, $module, $ref = null, $old = null, $new = null) {
    $now = date('Y-m-d H:i:s');
    Database::execute(
        "INSERT INTO activity_logs (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES (:uid, NULL, :action, :mod, :ref, :ip, :old, :new, :created_at)",
        ['uid' => $userId, 'action' => $action, 'mod' => $module, 'ref' => $ref,
         'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
         'old' => $old ? json_encode($old) : null, 'new' => $new ? json_encode($new) : null,
         'created_at' => $now]
    );
}

function getSessionVoidSummary(int $sessionId): array {
    return Database::fetch(
        "SELECT
                (SELECT COUNT(*)
                 FROM ticket_cancellations tc
                 WHERE tc.cashier_session_id = :sid_completed_count
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'completed') AS voided_ticket_count,
                (SELECT COALESCE(SUM(tt.total_amount), 0)
                 FROM ticket_cancellations tc
                 JOIN ticket_transactions tt ON tt.transaction_id = tc.transaction_id
                 WHERE tc.cashier_session_id = :sid_completed_amount
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'completed') AS voided_ticket_amount,
                (SELECT COUNT(*)
                 FROM ticket_cancellations tc
                 WHERE tc.cashier_session_id = :sid_completed_technical_count
                   AND tc.operation_type = 'VOID'
                   AND tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                   AND tc.status = 'completed') AS technical_void_count,
                (SELECT COALESCE(SUM(tt.total_amount), 0)
                 FROM ticket_cancellations tc
                 JOIN ticket_transactions tt ON tt.transaction_id = tc.transaction_id
                 WHERE tc.cashier_session_id = :sid_completed_technical_amount
                   AND tc.operation_type = 'VOID'
                   AND tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                   AND tc.status = 'completed') AS technical_void_amount,
                (SELECT COALESCE(SUM(tp.amount), 0)
                 FROM ticket_cancellations tc
                 JOIN transaction_payments tp
                   ON tp.source_type = 'TICKET_TRANSACTION'
                  AND tp.source_id = tc.transaction_id
                  AND tp.cashier_session_id = :sid_void_cash_payment
                 JOIN payment_methods pm ON pm.method_id = tp.payment_method_id
                 WHERE tc.cashier_session_id = :sid_void_cash_filter
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'completed'
                   AND pm.include_in_expected_cash = 1
                   AND tp.confirmation_status <> 'REJECTED') AS voided_cash_amount,
                (SELECT COALESCE(SUM(
                            CASE
                                WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR') THEN 0
                                ELSE COALESCE(tc.void_fee, 0)
                            END
                        ), 0)
                 FROM ticket_cancellations tc
                 WHERE tc.cashier_session_id = :sid_completed_void_fee
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'completed') AS void_fee,
                (SELECT COALESCE(SUM(
                            CASE
                                WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR') THEN 0
                                ELSE COALESCE(tc.void_service_fee, 0)
                            END
                        ), 0)
                 FROM ticket_cancellations tc
                 WHERE tc.cashier_session_id = :sid_completed_void_service_fee
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'completed') AS void_service_fee,
                (SELECT COALESCE(SUM(
                            CASE WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                                 THEN COALESCE(NULLIF(tc.lost_sales_void_fee, 0), tc.void_fee, 0)
                                 ELSE 0 END
                        ), 0)
                 FROM ticket_cancellations tc
                 WHERE tc.cashier_session_id = :sid_completed_lost_sales_void_fee
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'completed') AS lost_sales_void_fee,
                (SELECT COALESCE(SUM(
                            CASE WHEN tc.reason_category IN ('PRINTER_ERROR', 'SYSTEM_ERROR')
                                 THEN COALESCE(tc.lost_sales_service_fee, 0) + COALESCE(tc.void_service_fee, 0)
                                 ELSE 0 END
                        ), 0)
                 FROM ticket_cancellations tc
                 WHERE tc.cashier_session_id = :sid_completed_lost_sales_service_fee
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'completed') AS lost_sales_service_fee,
                (SELECT COUNT(*)
                 FROM ticket_cancellations tc
                 WHERE tc.cashier_session_id = :sid_pending_count
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'pending') AS pending_void_count,
                (SELECT COALESCE(SUM(tt.total_amount), 0)
                 FROM ticket_cancellations tc
                 JOIN ticket_transactions tt ON tt.transaction_id = tc.transaction_id
                 WHERE tc.cashier_session_id = :sid_pending_amount
                   AND tc.operation_type = 'VOID'
                   AND tc.status = 'pending') AS pending_void_amount",
        [
            'sid_completed_count' => $sessionId,
            'sid_completed_amount' => $sessionId,
            'sid_completed_technical_count' => $sessionId,
            'sid_completed_technical_amount' => $sessionId,
            'sid_void_cash_payment' => $sessionId,
            'sid_void_cash_filter' => $sessionId,
            'sid_completed_void_fee' => $sessionId,
            'sid_completed_void_service_fee' => $sessionId,
            'sid_completed_lost_sales_void_fee' => $sessionId,
            'sid_completed_lost_sales_service_fee' => $sessionId,
            'sid_pending_count' => $sessionId,
            'sid_pending_amount' => $sessionId,
        ]
    ) ?: [];
}

function getSessionRefundSummary(int $sessionId): array {
    return Database::fetch(
        "SELECT
                COALESCE(SUM(COALESCE(NULLIF(tc.refund_amount, 0), tc.gross_refund_amount, 0)), 0) AS refunded_sales_amount,
                COALESCE(SUM(COALESCE(tc.cash_refund_amount, 0)), 0) AS refunded_cash_amount
         FROM ticket_cancellations tc
         JOIN ticket_transactions tt ON tt.transaction_id = tc.transaction_id
         WHERE tt.cashier_session_id = :session_id
           AND tc.operation_type = 'REFUND'
           AND tc.status = 'completed'",
        ['session_id' => $sessionId]
    ) ?: [];
}

Auth::requireLogin();
$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':  handleGet();  break;
    case 'POST': handlePost(); break;
    case 'PUT':  handlePut();  break;
    default: http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGet() {
    global $user;
    $id = $_GET['id'] ?? null;
    
    // Decode ID if it's encrypted (not numeric)
    if ($id && !is_numeric($id)) {
        $decodedId = IdEncoder::decode($id);
        if ($decodedId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid session ID']);
            return;
        }
        $id = $decodedId;
    }
    
    $status = $_GET['status'] ?? null;
    $cashierId = $_GET['cashier_id'] ?? null;
    
    // Allow managers and super admins to view other cashiers' sessions
    $isManager = ($user['role_code'] === 'SUPER_ADMIN' || $user['role_code'] === 'MANAGER');
    
    if ($id) {
        $session = Database::fetch(
            "SELECT cs.*,
                    cs.total_sales AS total_cash_paid,
                    COALESCE(cs.total_cash, 0) AS total_cash,
                    COALESCE(cs.total_bank_transfer, 0) AS total_bank_transfer,
                    COALESCE(cs.total_e_wallet, 0) AS total_e_wallet,
                    COALESCE(cs.total_charge, 0) AS total_charge,
                    COALESCE(cs.total_other, 0) AS total_other,
                    COALESCE(cs.total_refunds, 0) AS total_refunds,
                    (SELECT COUNT(*) FROM pos_orders WHERE cashier_session_id = cs.session_id) AS txn_count,
                    (cs.starting_cash + COALESCE(cs.total_cash, 0)) AS expected_cash,
                    bb.branch_name,
                    -- Cashier name from employees table (format: First M. Last)
                    CONCAT(e.first_name, ' ', 
                           COALESCE(CONCAT(LEFT(e.middle_name, 1), '. '), ''), 
                           e.last_name) AS cashier_name
             FROM cashier_sessions cs
             LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
             LEFT JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             WHERE cs.session_id = :id",
            ['id' => $id]
        );
        if (!$session) { echo json_encode(['success' => false, 'error' => 'Session not found']); return; }
        
        // Check permission - only owner, manager, or super admin can view
        if ($session['cashier_user_id'] != $user['user_id'] && !$isManager) {
            echo json_encode(['success' => false, 'error' => 'Not authorized to view this session']);
            return;
        }

        // Get payment breakdown from transaction_payments (filter by session ID, not cashier ID)
        $paymentWhere = "AND tp.created_at >= :start
                          AND NOT EXISTS (
                              SELECT 1
                              FROM ticket_cancellations tc_void
                              WHERE tp.source_type = 'TICKET_TRANSACTION'
                                AND tc_void.transaction_id = tp.source_id
                                AND tc_void.operation_type = 'VOID'
                                AND tc_void.status = 'completed'
                          )";
        $paymentParams = [
            'sid'   => $session['session_id'],
            'start' => $session['started_at']
        ];
        if ($session['ended_at']) {
            $paymentWhere .= " AND tp.created_at <= :end";
            $paymentParams['end'] = $session['ended_at'];
        }

        $payments = Database::fetchAll(
            "SELECT pm.method_name, pm.method_type, pm.include_in_expected_cash, pm.is_active, pm.sort_order,
                    COALESCE(SUM(tp.amount), 0) AS gross_amount,
                    COALESCE(SUM(LEAST(tp.amount, COALESCE(refunds.refunded_amount, 0))), 0) AS refunded_amount,
                    COALESCE(SUM(GREATEST(0, tp.amount - COALESCE(refunds.refunded_amount, 0))), 0) AS active_amount,
                    COALESCE(SUM(tp.amount), 0) AS total_amount
             FROM payment_methods pm
             LEFT JOIN transaction_payments tp
               ON tp.payment_method_id = pm.method_id
              AND tp.cashier_session_id = :sid
              $paymentWhere
             LEFT JOIN (
                 SELECT source_payment_id, SUM(amount) AS refunded_amount
                 FROM refund_allocations
                 WHERE refund_scope IN ('TICKET', 'SERVICE')
                   AND status = 'PROCESSED'
                 GROUP BY source_payment_id
             ) refunds ON refunds.source_payment_id = tp.payment_id
             WHERE (pm.is_active = 1 OR tp.payment_id IS NOT NULL)
             GROUP BY pm.method_id, pm.method_name, pm.method_type, pm.include_in_expected_cash, pm.is_active, pm.sort_order
             ORDER BY total_amount DESC, pm.sort_order ASC, pm.method_name ASC",
            $paymentParams
        );

        $voidSummary = getSessionVoidSummary((int) $session['session_id']);
        $session['voided_ticket_count'] = (int) ($voidSummary['voided_ticket_count'] ?? 0);
        $session['voided_ticket_amount'] = (float) ($voidSummary['voided_ticket_amount'] ?? 0);
        $session['technical_void_count'] = (int) ($voidSummary['technical_void_count'] ?? 0);
        $session['technical_void_amount'] = (float) ($voidSummary['technical_void_amount'] ?? 0);
        $session['voided_cash_amount'] = (float) ($voidSummary['voided_cash_amount'] ?? 0);
        $session['void_fee'] = (float) ($voidSummary['void_fee'] ?? 0);
        $session['void_service_fee'] = (float) ($voidSummary['void_service_fee'] ?? 0);
        $session['lost_sales_void_fee'] = (float) ($voidSummary['lost_sales_void_fee'] ?? 0);
        $session['lost_sales_service_fee'] = (float) ($voidSummary['lost_sales_service_fee'] ?? 0);
        $session['void_income'] = round($session['void_fee'] + $session['void_service_fee'], 2);
        $session['technical_lost_sales_amount'] = round(
            $session['lost_sales_void_fee'] + $session['lost_sales_service_fee'],
            2
        );
        $refundSummary = getSessionRefundSummary((int) $session['session_id']);
        $session['refunded_sales_amount'] = (float) ($refundSummary['refunded_sales_amount'] ?? 0);
        $session['refunded_cash_amount'] = (float) ($session['total_refunds'] ?? 0);
        if ($session['refunded_cash_amount'] <= 0 && (float) ($refundSummary['refunded_cash_amount'] ?? 0) > 0) {
            $session['refunded_cash_amount'] = (float) $refundSummary['refunded_cash_amount'];
            $session['total_refunds'] = $session['refunded_cash_amount'];
        }
        $regularVoidedTicketAmount = max(0, $session['voided_ticket_amount'] - $session['technical_void_amount']);
        $session['regular_voided_ticket_amount'] = round($regularVoidedTicketAmount, 2);
        $session['voided_sales_amount'] = round(
            $session['voided_ticket_amount'] + $session['technical_lost_sales_amount'],
            2
        );
        $session['pending_void_count'] = (int) ($voidSummary['pending_void_count'] ?? 0);
        $session['pending_void_amount'] = (float) ($voidSummary['pending_void_amount'] ?? 0);

        // Map payment breakdown to session fields
        $paymentMap = [
            'CASH' => 'total_cash',
            'BANK_TRANSFER' => 'total_bank_transfer',
            'E_WALLET' => 'total_e_wallet',
            'CHARGE' => 'total_charge',
            'OTHER' => 'total_other'
        ];

        // Calculate expected cash based on payment methods with include_in_expected_cash flag
        $expectedCashPayments = 0.0;
        $paymentRefundsAmount = 0.0;
        foreach ($payments as $payment) {
            $methodType = $payment['method_type'];
            if (isset($paymentMap[$methodType])) {
                $session[$paymentMap[$methodType]] = $payment['total_amount'];
            }
            $paymentRefundsAmount += (float) ($payment['refunded_amount'] ?? 0);
            // Add to expected cash if payment method is configured to be included
            if ((int) $payment['include_in_expected_cash'] === 1) {
                $expectedCashPayments += (float) ($payment['total_amount'] ?? 0);
            }
        }
        $completedRefundsAmount = max(
            (float) ($session['refunded_sales_amount'] ?? 0),
            $paymentRefundsAmount,
            (float) ($session['total_refunds'] ?? 0)
        );
        $session['approved_refunds_amount'] = round($completedRefundsAmount, 2);

        $session['net_sales'] = round(max(
            0,
            (float) ($session['total_sales'] ?? 0)
                - $session['refunded_sales_amount']
                - $session['voided_sales_amount']
                + $session['void_income']
        ), 2);

        // Total cash change disbursed from the drawer during the session
        $totalCashChange = PosAccess::sessionTotalCashChange(
            (int) $session['session_id'],
            $session['started_at'],
            $session['ended_at'] ?: null
        );

        // Load Close Cashier Session refund display setting
        $settings = Database::fetch("SELECT show_pending_refunds_in_close_session FROM system_settings WHERE setting_id = 1");
        $showPendingRefunds = (int) ($settings['show_pending_refunds_in_close_session'] ?? 0);

        // Recalculate expected cash based on payment method settings
        // Expected cash = starting cash + cash payments - cash change - refunds - responsibility deductions
        $pendingRefundsCash = $showPendingRefunds ? floatval($session['pending_refunds_cash'] ?? 0) : 0.0;
        $totalCashAdjustments = floatval($session['total_cash_adjustments'] ?? 0);
        $voidedCashAmount = floatval($session['voided_cash_amount'] ?? 0);
        $session['expected_cash'] = $session['starting_cash'] + $expectedCashPayments
            + $session['void_income'] - $session['technical_lost_sales_amount']
            - $totalCashChange - $completedRefundsAmount - $pendingRefundsCash - $totalCashAdjustments;
        $session['total_cash_adjustments'] = $totalCashAdjustments;
        $session['voided_cash_amount'] = $voidedCashAmount;
        $session['total_cash_change'] = $totalCashChange;
        $session['pending_refunds_cash'] = floatval($session['pending_refunds_cash'] ?? 0);
        $session['show_pending_refunds_in_close_session'] = $showPendingRefunds;

        echo json_encode(['success' => true, 'data' => ['session' => $session, 'payments' => $payments]]);
        return;
    }
    
    // Build query for listing sessions
    $sql = "SELECT cs.*, 
                   bb.branch_name,
                   COALESCE(cs.total_refunds, 0) AS total_refunds,
                   CONCAT(e.first_name, ' ', 
                          COALESCE(CONCAT(LEFT(e.middle_name, 1), '. '), ''), 
                          e.last_name) AS cashier_name
            FROM cashier_sessions cs
            LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
            LEFT JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
            LEFT JOIN employees e ON ua.emp_id = e.emp_id
            WHERE 1=1";
    $params = [];

    $allowedBranchIds = PosAccess::allowedBranchIds($user);
    if ($allowedBranchIds !== null) {
        if (empty($allowedBranchIds)) {
            $sql .= ' AND 1 = 0';
        } else {
            $branchPlaceholders = [];
            foreach ($allowedBranchIds as $index => $allowedBranchId) {
                $placeholder = ':session_branch_' . $index;
                $branchPlaceholders[] = $placeholder;
                $params['session_branch_' . $index] = $allowedBranchId;
            }
            $sql .= ' AND cs.branch_id IN (' . implode(',', $branchPlaceholders) . ')';
        }
    }
    
    // Filter by status if provided
    if ($status) {
        $sql .= " AND cs.status = :status";
        $params['status'] = strtoupper($status);
    }
    
    // Filter by cashier if provided (managers only)
    if ($cashierId) {
        if (!$isManager && $cashierId != $user['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Not authorized to view other cashier sessions']);
            return;
        }
        $sql .= " AND cs.cashier_user_id = :cashier_id";
        $params['cashier_id'] = (int)$cashierId;
    } else {
        // For non-managers, only show own sessions
        if (!$isManager) {
            $sql .= " AND cs.cashier_user_id = :uid";
            $params['uid'] = $user['user_id'];
        }
    }
    
    $sql .= " ORDER BY cs.started_at DESC";
    
    $sessions = Database::fetchAll($sql, $params);
    echo json_encode(['success' => true, 'data' => $sessions]);
}

function handlePost() {
    global $user;
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $branchId = (int) ($input['branch_id'] ?? 0);
    $openingCashInput = $input['opening_cash_balance'] ?? $input['starting_cash'] ?? null;
    if ($openingCashInput === null || $openingCashInput === '') {
        throw new InvalidArgumentException('Opening cash balance is required.');
    }
    $openingCash = max(0, (float) $openingCashInput);
    $notes = $input['notes'] ?? null;
    $isManager = in_array($user['role_code'] ?? '', ['SUPER_ADMIN', 'MANAGER'], true);
    $cashierUserId = $isManager && !empty($input['cashier_user_id'])
        ? (int) $input['cashier_user_id']
        : (int) $user['user_id'];
    $openedByManager = $isManager && $cashierUserId !== (int) $user['user_id'];

    try {
        PosAccess::assertBranchAccess($user, $branchId);
        if ($cashierUserId <= 0) {
            throw new InvalidArgumentException('A valid cashier is required.');
        }

        $pdo = Database::connection();
        $lockName = 'tms:pos:open-session:' . $cashierUserId;
        $lock = Database::fetch(
            'SELECT GET_LOCK(:lock_name, 5) AS acquired',
            ['lock_name' => $lockName]
        );
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Another session request is being processed. Please try again.');
        }

        try {
            $pdo->beginTransaction();
            $open = Database::fetch(
                "SELECT session_id FROM cashier_sessions
                 WHERE cashier_user_id = :uid AND status = 'OPEN'
                 LIMIT 1 FOR UPDATE",
                ['uid' => $cashierUserId]
            );
            if ($open) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'This cashier already has an open session. Close it first.']);
                return;
            }

            $sessionCode = 'SES-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            Database::execute(
                "INSERT INTO cashier_sessions (session_code, cashier_user_id, branch_id, started_at, starting_cash, status, notes)
                 VALUES (:code, :uid, :branch, :started_at, :cash, 'OPEN', :notes)",
                ['code' => $sessionCode, 'uid' => $cashierUserId, 'branch' => $branchId, 'started_at' => date('Y-m-d H:i:s'), 'cash' => $openingCash, 'notes' => $notes]
            );
            $sessionId = Database::connection()->lastInsertId();
            $pdo->commit();
        } finally {
            Database::execute('SELECT RELEASE_LOCK(:lock_name)', ['lock_name' => $lockName]);
        }

        $activityData = ['branch_id' => $branchId, 'opening_cash' => $openingCash];
        if ($openedByManager) {
            $activityData['opened_by_manager'] = $user['user_id'];
            $activityData['cashier_user_id'] = $cashierUserId;
        }

        logActivity($user['user_id'], 'OPEN_SESSION', 'POS', "SES-{$sessionId}", null, $activityData);
        echo json_encode(['success' => true, 'message' => 'Session opened.', 'session_id' => $sessionId]);
    } catch (Throwable $e) {
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();
        }
        http_response_code($e instanceof RuntimeException ? 409 : 400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handlePut() {
    global $user;
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;
    $action = $input['action'] ?? null;
    if (!$sessionId) { echo json_encode(['success' => false, 'error' => 'Missing session ID.']); return; }

    try {
        $session = PosAccess::sessionForUser((int) $sessionId, $user);
    } catch (Throwable $e) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        return;
    }
    if (!$session) { echo json_encode(['success' => false, 'error' => 'Session not found or not accessible.']); return; }

    if ($action === 'close') {
        if ($session['status'] !== 'OPEN') { echo json_encode(['success' => false, 'error' => 'Session is not open.']); return; }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();
            $lockedSession = PosAccess::sessionForUser((int) $sessionId, $user, true);
            if (!$lockedSession || $lockedSession['status'] !== 'OPEN') {
                throw new RuntimeException('Session was already closed by another request.');
            }
            $session = $lockedSession;
            $closingCashInput = $input['closing_cash_balance'] ?? null;
            if ($closingCashInput === null || $closingCashInput === '') {
                throw new InvalidArgumentException('Actual closing cash is required.');
            }
            $closingCash = (float) $closingCashInput;
            $notes = $input['notes'] ?? null;
            $cashDepositBankId = $input['cash_deposit_bank_id'] ?? null;
            $depositNow = $input['deposit_now'] ?? false;
        
        // Check system settings for deposit confirmation requirement
        $settings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
        $depositRequiresConfirmation = ($settings['bank_deposits_require_confirmation'] ?? 1) == 1;
        
        // If deposit_now is checked, bypass confirmation
        $requireDepositConfirmation = $cashDepositBankId && !$depositNow && $depositRequiresConfirmation;

        // Get payment breakdown for this session to calculate expected cash based on payment method settings
        $paymentWhere = "AND tp.created_at >= :start
                          AND NOT EXISTS (
                              SELECT 1
                              FROM ticket_cancellations tc_void
                              WHERE tp.source_type = 'TICKET_TRANSACTION'
                                AND tc_void.transaction_id = tp.source_id
                                AND tc_void.operation_type = 'VOID'
                                AND tc_void.status = 'completed'
                          )";
        $paymentParams = [
            'sid'   => $session['session_id'],
            'start' => $session['started_at']
        ];

        $payments = Database::fetchAll(
            "SELECT pm.method_name, pm.method_type, pm.include_in_expected_cash, pm.is_active, pm.sort_order,
                    COALESCE(SUM(tp.amount), 0) AS gross_amount,
                    COALESCE(SUM(LEAST(tp.amount, COALESCE(refunds.refunded_amount, 0))), 0) AS refunded_amount,
                    COALESCE(SUM(GREATEST(0, tp.amount - COALESCE(refunds.refunded_amount, 0))), 0) AS active_amount,
                    COALESCE(SUM(tp.amount), 0) AS total_amount
             FROM payment_methods pm
             LEFT JOIN transaction_payments tp
               ON tp.payment_method_id = pm.method_id
              AND tp.cashier_session_id = :sid
              $paymentWhere
             LEFT JOIN (
                 SELECT source_payment_id, SUM(amount) AS refunded_amount
                 FROM refund_allocations
                 WHERE refund_scope IN ('TICKET', 'SERVICE')
                   AND status = 'PROCESSED'
                 GROUP BY source_payment_id
             ) refunds ON refunds.source_payment_id = tp.payment_id
             WHERE (pm.is_active = 1 OR tp.payment_id IS NOT NULL)
             GROUP BY pm.method_id, pm.method_name, pm.method_type, pm.include_in_expected_cash, pm.is_active, pm.sort_order
             ORDER BY pm.sort_order ASC, pm.method_name ASC",
            $paymentParams
        );

        // Calculate expected cash based on payment methods with include_in_expected_cash flag
        $expectedCashPayments = 0.0;
        $paymentRefundsAmount = 0.0;
        foreach ($payments as $payment) {
            $paymentRefundsAmount += (float) ($payment['refunded_amount'] ?? 0);
            if ((int) $payment['include_in_expected_cash'] === 1) {
                $expectedCashPayments += (float) ($payment['total_amount'] ?? 0);
            }
        }
        $refundSummary = getSessionRefundSummary((int) $session['session_id']);
        $completedRefundsAmount = max(
            (float) ($refundSummary['refunded_sales_amount'] ?? 0),
            $paymentRefundsAmount,
            (float) ($session['total_refunds'] ?? 0)
        );

        // Total cash change disbursed from the drawer during the session
        $totalCashChange = PosAccess::sessionTotalCashChange(
            (int) $session['session_id'],
            $session['started_at']
        );

        // Compute expected cash: starting cash + in-cash payments - cash change - refunds - responsibility deductions
        // If configured, also reserve pending refund cash so the cashier accounts for it.
        $showPendingRefunds = ($settings['show_pending_refunds_in_close_session'] ?? 0) == 1;
        $pendingRefundsCash = $showPendingRefunds ? floatval($session['pending_refunds_cash'] ?? 0) : 0.0;
        $totalCashAdjustments = floatval($session['total_cash_adjustments'] ?? 0);
        $voidSummary = getSessionVoidSummary((int) $session['session_id']);
        $voidIncome = round(
            (float) ($voidSummary['void_fee'] ?? 0) + (float) ($voidSummary['void_service_fee'] ?? 0),
            2
        );
        $voidLostSalesAmount = round(
            (float) ($voidSummary['lost_sales_void_fee'] ?? 0) + (float) ($voidSummary['lost_sales_service_fee'] ?? 0),
            2
        );
        $expectedCash = $session['starting_cash'] + $expectedCashPayments
            + $voidIncome - $voidLostSalesAmount
            - $totalCashChange - $completedRefundsAmount - $pendingRefundsCash - $totalCashAdjustments;
        $variance = $closingCash - $expectedCash;

        // Determine deposit status based on system settings

        $depositStatus = 'NOT_APPLICABLE';
        if ($cashDepositBankId) {
            if ($depositNow) {
                $depositStatus = 'DEPOSITED';
            } elseif ($requireDepositConfirmation) {
                $depositStatus = 'PENDING';
            } else {
                $depositStatus = 'DEPOSITED';
            }
        }

            Database::execute(
                "UPDATE cashier_sessions SET
                    ended_at = :ended_at, actual_cash = :close, expected_cash = :expected,
                    cash_variance = :variance, status = 'CLOSED', notes = :notes,
                    cash_deposit_bank_id = :bank_id, deposit_status = :deposit_status,
                    deposited_at = :deposited_at, deposited_by = :deposited_by
                 WHERE session_id = :id AND status = 'OPEN'",
                [
                    'ended_at' => date('Y-m-d H:i:s'),
                    'close' => $closingCash, 'expected' => $expectedCash, 'variance' => $variance,
                    'notes' => $notes, 'bank_id' => $cashDepositBankId, 'deposit_status' => $depositStatus,
                    'deposited_at' => $depositNow ? date('Y-m-d H:i:s') : null, 'deposited_by' => $depositNow ? $user['user_id'] : null,
                    'id' => $sessionId
                ]
            );

            // Immediate deposits use the same bank ledger as confirmations.
            if ($cashDepositBankId && $depositStatus === 'DEPOSITED') {
                $bankMovement = BalanceLedgerService::bankMovement(
                    (int) $cashDepositBankId,
                    'DEPOSIT',
                    'IN',
                    (float) $closingCash,
                    'cashier_sessions',
                    (int) $sessionId,
                    "Cash deposit from session {$session['session_code']}",
                    (int) $user['user_id'],
                    'bank-deposit:session:' . (int) $sessionId,
                    null,
                    false,
                    $notes
                );
                logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankMovement['txn_code'],
                    null, ['bank_account_id' => $cashDepositBankId, 'amount' => $closingCash, 'type' => 'DEPOSIT']);
            }

            Database::connection()->commit();

            logActivity($user['user_id'], 'CLOSE_SESSION', 'POS', "SES-{$sessionId}", null, ['closing_cash' => $closingCash, 'variance' => $variance]);

            // Notification trigger for cash discrepancy
            // Get configurable threshold from system settings
            $thresholdSetting = Database::fetch(
                "SELECT setting_value FROM system_notification_settings WHERE setting_key = 'pos_session_variance_threshold'"
            );
            $discrepancyThreshold = $thresholdSetting ? (float)$thresholdSetting['setting_value'] : 100;
            if (abs($variance) >= $discrepancyThreshold) {
                $status = ($variance > 0) ? 'overage' : 'shortage';
                $adminUsers = Database::fetchAll(
                    "SELECT ua.user_id FROM user_accounts ua
                     JOIN user_roles r ON ua.role_id = r.role_id
                     WHERE r.role_code IN ('SUPER_ADMIN', 'ADMIN', 'MANAGER') AND ua.status = 'active'"
                );

                foreach ($adminUsers as $admin) {
                    NotificationService::createFromTemplate('pos_session', $admin['user_id'], [
                        'session_id' => $session['session_code'],
                        'status' => $status . ' of ' . number_format(abs($variance), 2)
                    ]);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Session closed.', 'variance' => $variance]);
            return;
        } catch (Throwable $e) {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }
            echo json_encode(['success' => false, 'error' => 'Failed to close session: ' . $e->getMessage()]);
            return;
        }
    }

    // Record deposit for a closed session
    if ($action === 'record_deposit') {
        if ($session['status'] !== 'CLOSED') { echo json_encode(['success' => false, 'error' => 'Session must be closed first.']); return; }
        if ($session['deposit_status'] === 'DEPOSITED') { echo json_encode(['success' => false, 'error' => 'Already deposited.']); return; }
        
        $bankAccountId = $input['bank_account_id'] ?? null;
        if (!$bankAccountId) { echo json_encode(['success' => false, 'error' => 'Bank account is required.']); return; }

        $depositAmount = $input['deposit_amount'] ?? $session['actual_cash'];
        
        // Check system settings for deposit confirmation requirement
        $settings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
        $depositRequiresConfirmation = ($settings['bank_deposits_require_confirmation'] ?? 1) == 1;
        
        try {
            Database::connection()->beginTransaction();
            $lockedDepositSession = PosAccess::sessionForUser((int) $sessionId, $user, true);
            if (!$lockedDepositSession || $lockedDepositSession['status'] !== 'CLOSED') {
                throw new RuntimeException('Session must be closed before recording a deposit.');
            }
            if ($lockedDepositSession['deposit_status'] === 'DEPOSITED') {
                throw new RuntimeException('Already deposited.');
            }
            $session = $lockedDepositSession;
            $depositAmount = $input['deposit_amount'] ?? $session['actual_cash'];

            $depositStatus = $depositRequiresConfirmation ? 'PENDING' : 'DEPOSITED';

            // A pending deposit is not yet deposited from the bank's point of
            // view. Bank Confirmations will finalize the session after review.
            Database::execute(
                "UPDATE cashier_sessions SET
                    cash_deposit_bank_id = :bank_id,
                    deposit_status = :deposit_status,
                    deposited_at = :deposited_at,
                    deposited_by = :deposited_by
                 WHERE session_id = :id",
                [
                    'bank_id' => $bankAccountId,
                    'deposit_status' => $depositStatus,
                    'deposited_at' => $depositStatus === 'DEPOSITED' ? date('Y-m-d H:i:s') : null,
                    'deposited_by' => $depositStatus === 'DEPOSITED' ? $user['user_id'] : null,
                    'id' => $sessionId,
                ]
            );

            // Create bank transaction. A rejected deposit can be recorded
            // again, so each attempt receives a distinct idempotency key.
            $bankAccount = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id FOR UPDATE", ['id' => $bankAccountId]);
            if ($bankAccount) {
                $balBeforeBank = floatval($bankAccount['current_balance'] ?? 0);
                $balAfterBank = $balBeforeBank + $depositAmount;
                $bankTxnCode = 'BANK-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -5));
                $depositIdempotencyKey = 'bank-deposit:session:' . (int) $sessionId . ':' . strtoupper(bin2hex(random_bytes(3)));
                $confirmStatus = $depositRequiresConfirmation ? 'PENDING' : 'CONFIRMED';
                
                Database::execute(
                    "INSERT INTO bank_transactions
                        (bank_account_id, txn_code, confirmation_status, txn_type, direction, amount, balance_before, balance_after,
                         reference_table, reference_id, remarks, created_by, idempotency_key, created_at)
                     VALUES (:bank_id, :code, :confirm, 'DEPOSIT', 'IN', :amount, :before, :after, 'cashier_sessions', :ref_id, :remarks, :uid, :idempotency_key, :created_at)",
                    [
                        'bank_id' => $bankAccountId,
                        'code' => $bankTxnCode,
                        'confirm' => $confirmStatus,
                        'amount' => $depositAmount,
                        'before' => $balBeforeBank,
                        'after' => $balAfterBank,
                        'ref_id' => $sessionId,
                        'remarks' => "Cash deposit from session {$session['session_code']}" . ($depositRequiresConfirmation ? ' (pending confirmation)' : ''),
                        'uid' => $user['user_id'],
                        'idempotency_key' => $depositIdempotencyKey,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                );
                
                // Only update bank balance if confirmation is not required
                if (!$depositRequiresConfirmation) {
                    Database::execute(
                        "UPDATE bank_accounts SET current_balance = :balance WHERE bank_account_id = :id",
                        ['balance' => $balAfterBank, 'id' => $bankAccountId]
                    );
                    
                    logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankTxnCode,
                        null, ['bank_account_id' => $bankAccountId, 'amount' => $depositAmount, 'type' => 'DEPOSIT']);
                }
            }

            Database::connection()->commit();

            logActivity($user['user_id'], 'RECORD_DEPOSIT', 'POS', "SES-{$sessionId}", null,
                ['bank_account_id' => $bankAccountId, 'amount' => $depositAmount, 'status' => $depositStatus]);
            echo json_encode([
                'success' => true,
                'message' => $depositStatus === 'PENDING'
                    ? 'Deposit recorded successfully. Awaiting confirmation.'
                    : 'Deposit recorded successfully.',
                'deposit_status' => $depositStatus,
            ]);
            return;
        } catch (Throwable $e) {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }
            echo json_encode(['success' => false, 'error' => 'Failed to record deposit: ' . $e->getMessage()]);
            return;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
}
