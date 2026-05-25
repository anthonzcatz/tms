<?php
/**
 * POS Sessions API — Open / Close cashier sessions
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

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
                    COALESCE(cs.total_refunds_wallet, 0) AS total_refunds,
                    (SELECT COUNT(*) FROM service_transactions WHERE cashier_session_id = cs.session_id) +
                    (SELECT COUNT(*) FROM ticket_transactions WHERE cashier_session_id = cs.session_id) AS txn_count,
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
        $paymentWhere = "AND tp.created_at >= :start";
        $paymentParams = [
            'sid'   => $session['session_id'],
            'start' => $session['started_at']
        ];
        if ($session['ended_at']) {
            $paymentWhere .= " AND tp.created_at <= :end";
            $paymentParams['end'] = $session['ended_at'];
        }

        $payments = Database::fetchAll(
            "SELECT pm.method_name, pm.method_type, pm.include_in_expected_cash, SUM(tp.amount) AS total_amount
             FROM transaction_payments tp
             JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
             WHERE tp.cashier_session_id = :sid
               $paymentWhere
             GROUP BY pm.method_id, pm.method_name, pm.method_type, pm.include_in_expected_cash
             ORDER BY total_amount DESC",
            $paymentParams
        );

        // Map payment breakdown to session fields
        $paymentMap = [
            'CASH' => 'total_cash',
            'BANK_TRANSFER' => 'total_bank_transfer',
            'E_WALLET' => 'total_e_wallet',
            'CHARGE' => 'total_charge',
            'OTHER' => 'total_other'
        ];

        // Calculate expected cash based on payment methods with include_in_expected_cash flag
        $expectedCashPayments = 0;
        foreach ($payments as $payment) {
            $methodType = $payment['method_type'];
            if (isset($paymentMap[$methodType])) {
                $session[$paymentMap[$methodType]] = $payment['total_amount'];
            }
            // Add to expected cash if payment method is configured to be included
            if ($payment['include_in_expected_cash']) {
                $expectedCashPayments += $payment['total_amount'];
            }
        }

        // Recalculate expected cash based on payment method settings
        // Expected cash = starting cash + cash payments - refunds
        $totalRefunds = floatval($session['total_refunds'] ?? 0);
        $session['expected_cash'] = $session['starting_cash'] + $expectedCashPayments - $totalRefunds;

        echo json_encode(['success' => true, 'data' => ['session' => $session, 'payments' => $payments]]);
        return;
    }
    
    // Build query for listing sessions
    $sql = "SELECT cs.*, 
                   bb.branch_name,
                   COALESCE(cs.total_refunds_wallet, 0) AS total_refunds,
                   CONCAT(e.first_name, ' ', 
                          COALESCE(CONCAT(LEFT(e.middle_name, 1), '. '), ''), 
                          e.last_name) AS cashier_name
            FROM cashier_sessions cs
            LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
            LEFT JOIN user_accounts ua ON cs.cashier_user_id = ua.user_id
            LEFT JOIN employees e ON ua.emp_id = e.emp_id
            WHERE 1=1";
    $params = [];
    
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
    $input = json_decode(file_get_contents('php://input'), true);
    $branchId = $input['branch_id'] ?? null;
    $openingCash = $input['opening_cash_balance'] ?? $input['starting_cash'] ?? 0;
    $notes = $input['notes'] ?? null;
    $cashierUserId = $input['cashier_user_id'] ?? $user['user_id'];
    $openedByManager = $input['opened_by_manager'] ?? false;

    if (!$branchId) { echo json_encode(['success' => false, 'error' => 'Branch is required.']); return; }

    // Check for already open session for the cashier
    $open = Database::fetch(
        "SELECT session_id FROM cashier_sessions WHERE cashier_user_id = :uid AND status = 'OPEN'",
        ['uid' => $cashierUserId]
    );
    if ($open) { echo json_encode(['success' => false, 'error' => 'This cashier already has an open session. Close it first.']); return; }

    // Generate session code
    $sessionCode = 'SES-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    Database::execute(
        "INSERT INTO cashier_sessions (session_code, cashier_user_id, branch_id, started_at, starting_cash, status, notes)
         VALUES (:code, :uid, :branch, :started_at, :cash, 'OPEN', :notes)",
        ['code' => $sessionCode, 'uid' => $cashierUserId, 'branch' => $branchId, 'started_at' => date('Y-m-d H:i:s'), 'cash' => $openingCash, 'notes' => $notes]
    );
    $sessionId = Database::connection()->lastInsertId();
    
    $activityData = ['branch_id' => $branchId, 'opening_cash' => $openingCash];
    if ($openedByManager) {
        $activityData['opened_by_manager'] = $user['user_id'];
        $activityData['cashier_user_id'] = $cashierUserId;
    }
    
    logActivity($user['user_id'], 'OPEN_SESSION', 'POS', "SES-{$sessionId}", null, $activityData);
    echo json_encode(['success' => true, 'message' => 'Session opened.', 'session_id' => $sessionId]);
}

function handlePut() {
    global $user;
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;
    $action = $input['action'] ?? null;
    if (!$sessionId) { echo json_encode(['success' => false, 'error' => 'Missing session ID.']); return; }

    $session = Database::fetch("SELECT * FROM cashier_sessions WHERE session_id = :id", ['id' => $sessionId]);
    if (!$session) { echo json_encode(['success' => false, 'error' => 'Session not found.']); return; }

    // Allow managers and super admins to close any session
    $isManager = ($user['role_code'] === 'SUPER_ADMIN' || $user['role_code'] === 'MANAGER');
    if ($session['cashier_user_id'] != $user['user_id'] && !$isManager) {
        echo json_encode(['success' => false, 'error' => 'Not your session.']); return;
    }

    if ($action === 'close') {
        if ($session['status'] !== 'OPEN') { echo json_encode(['success' => false, 'error' => 'Session is not open.']); return; }
        $closingCash = $input['closing_cash_balance'] ?? 0;
        $notes = $input['notes'] ?? null;
        $cashDepositBankId = $input['cash_deposit_bank_id'] ?? null;
        $depositNow = $input['deposit_now'] ?? false;
        
        // Check system settings for deposit confirmation requirement
        $settings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
        $depositRequiresConfirmation = ($settings['bank_deposits_require_confirmation'] ?? 1) == 1;
        
        // If deposit_now is checked, bypass confirmation
        $requireDepositConfirmation = $cashDepositBankId && !$depositNow && $depositRequiresConfirmation;

        // Get payment breakdown for this session to calculate expected cash based on payment method settings
        $paymentWhere = "AND tp.created_at >= :start";
        $paymentParams = [
            'sid'   => $session['session_id'],
            'start' => $session['started_at']
        ];

        $payments = Database::fetchAll(
            "SELECT pm.method_name, pm.method_type, pm.include_in_expected_cash, SUM(tp.amount) AS total_amount
             FROM transaction_payments tp
             JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
             WHERE tp.cashier_session_id = :sid
               $paymentWhere
             GROUP BY pm.method_id, pm.method_name, pm.method_type, pm.include_in_expected_cash",
            $paymentParams
        );

        // Calculate expected cash based on payment methods with include_in_expected_cash flag
        $expectedCashPayments = 0;
        foreach ($payments as $payment) {
            if ($payment['include_in_expected_cash']) {
                $expectedCashPayments += $payment['total_amount'];
            }
        }

        // Compute expected cash: starting cash + in-cash payments - refunds
        $totalRefunds = floatval($session['total_refunds'] ?? 0);
        $expectedCash = $session['starting_cash'] + $expectedCashPayments - $totalRefunds;
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

        try {
            Database::connection()->beginTransaction();

            Database::execute(
                "UPDATE cashier_sessions SET
                    ended_at = :ended_at, actual_cash = :close, expected_cash = :expected,
                    cash_variance = :variance, status = 'CLOSED', notes = :notes,
                    cash_deposit_bank_id = :bank_id, deposit_status = :deposit_status,
                    deposited_at = :deposited_at, deposited_by = :deposited_by
                 WHERE session_id = :id",
                [
                    'ended_at' => date('Y-m-d H:i:s'),
                    'close' => $closingCash, 'expected' => $expectedCash, 'variance' => $variance,
                    'notes' => $notes, 'bank_id' => $cashDepositBankId, 'deposit_status' => $depositStatus,
                    'deposited_at' => $depositNow ? date('Y-m-d H:i:s') : null, 'deposited_by' => $depositNow ? $user['user_id'] : null,
                    'id' => $sessionId
                ]
            );

            // Create bank transaction if cash was deposited immediately
            if ($cashDepositBankId && $depositStatus === 'DEPOSITED') {
                $bankAccount = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id", ['id' => $cashDepositBankId]);
                if ($bankAccount) {
                    $balBeforeBank = floatval($bankAccount['current_balance'] ?? 0);
                    $balAfterBank = $balBeforeBank + $closingCash;
                    
                    $bankTxnCode = 'BANK-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -5));
                    Database::execute(
                        "INSERT INTO bank_transactions
                            (bank_account_id, txn_code, confirmation_status, txn_type, direction, amount, balance_before, balance_after,
                             reference_table, reference_id, remarks, created_by, created_at)
                         VALUES (:bank_id, :code, 'CONFIRMED', 'DEPOSIT', 'IN', :amount, :before, :after, 'cashier_sessions', :ref_id, :remarks, :uid, :created_at)",
                        [
                            'bank_id' => $cashDepositBankId,
                            'code' => $bankTxnCode,
                            'amount' => $closingCash,
                            'before' => $balBeforeBank,
                            'after' => $balAfterBank,
                            'ref_id' => $sessionId,
                            'remarks' => "Cash deposit from session {$session['session_code']}",
                            'uid' => $user['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]
                    );
                    
                    Database::execute(
                        "UPDATE bank_accounts SET current_balance = :balance WHERE bank_account_id = :id",
                        ['balance' => $balAfterBank, 'id' => $cashDepositBankId]
                    );
                    
                    logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankTxnCode,
                        null, ['bank_account_id' => $cashDepositBankId, 'amount' => $closingCash, 'type' => 'DEPOSIT']);
                }
            }

            Database::connection()->commit();

            logActivity($user['user_id'], 'CLOSE_SESSION', 'POS', "SES-{$sessionId}", null, ['closing_cash' => $closingCash, 'variance' => $variance]);
            echo json_encode(['success' => true, 'message' => 'Session closed.', 'variance' => $variance]);
            return;
        } catch (Exception $e) {
            Database::connection()->rollBack();
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

            // Update session deposit status
            Database::execute(
                "UPDATE cashier_sessions SET
                    cash_deposit_bank_id = :bank_id, deposit_status = 'DEPOSITED',
                    deposited_at = :deposited_at, deposited_by = :uid
                 WHERE session_id = :id",
                ['bank_id' => $bankAccountId, 'deposited_at' => date('Y-m-d H:i:s'), 'uid' => $user['user_id'], 'id' => $sessionId]
            );

            // Create bank transaction
            $bankAccount = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id", ['id' => $bankAccountId]);
            if ($bankAccount) {
                $balBeforeBank = floatval($bankAccount['current_balance'] ?? 0);
                $balAfterBank = $balBeforeBank + $depositAmount;
                
                $bankTxnCode = 'BANK-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -5));
                $confirmStatus = $depositRequiresConfirmation ? 'PENDING' : 'CONFIRMED';
                
                Database::execute(
                    "INSERT INTO bank_transactions
                        (bank_account_id, txn_code, confirmation_status, txn_type, direction, amount, balance_before, balance_after,
                         reference_table, reference_id, remarks, created_by, created_at)
                     VALUES (:bank_id, :code, :confirm, 'DEPOSIT', 'IN', :amount, :before, :after, 'cashier_sessions', :ref_id, :remarks, :uid, :created_at)",
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

            logActivity($user['user_id'], 'RECORD_DEPOSIT', 'POS', "SES-{$sessionId}", null, ['bank_account_id' => $bankAccountId, 'amount' => $depositAmount]);
            echo json_encode(['success' => true, 'message' => 'Deposit recorded successfully. Awaiting confirmation.']);
            return;
        } catch (Exception $e) {
            Database::connection()->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to record deposit: ' . $e->getMessage()]);
            return;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
}
