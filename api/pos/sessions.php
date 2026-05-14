<?php
/**
 * POS Sessions API — Open / Close cashier sessions
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

switch ($method) {
    case 'GET':  handleGet();  break;
    case 'POST': handlePost(); break;
    case 'PUT':  handlePut();  break;
    default: http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

function handleGet() {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $session = Database::fetch(
            "SELECT cs.*,
                    cs.total_sales AS total_cash_paid,
                    COALESCE(cs.total_cash, 0) AS total_cash,
                    COALESCE(cs.total_bank_transfer, 0) AS total_bank_transfer,
                    COALESCE(cs.total_e_wallet, 0) AS total_e_wallet,
                    COALESCE(cs.total_charge, 0) AS total_charge,
                    COALESCE(cs.total_other, 0) AS total_other,
                    (SELECT COUNT(*) FROM service_transactions WHERE session_id = cs.session_id) AS txn_count,
                    (cs.starting_cash + COALESCE(cs.total_cash, 0)) AS expected_cash
             FROM cashier_sessions cs
             WHERE cs.session_id = :id",
            ['id' => $id]
        );
        if (!$session) { echo json_encode(['success' => false, 'error' => 'Session not found']); return; }

        // Get payment breakdown from transaction_payments (same logic as shifts API)
        $paymentWhere = "AND tp.created_at >= :start";
        $paymentParams = [
            'uid'   => $session['cashier_user_id'],
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
             WHERE tp.created_by = :uid
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
        $session['expected_cash'] = $session['starting_cash'] + $expectedCashPayments;

        echo json_encode(['success' => true, 'data' => ['session' => $session, 'payments' => $payments]]);
        return;
    }

    // List sessions for current user (today)
    global $user;
    $sessions = Database::fetchAll(
        "SELECT cs.*, bb.branch_name
         FROM cashier_sessions cs
         LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
         WHERE cs.cashier_user_id = :uid AND DATE(cs.started_at) = CURDATE()
         ORDER BY cs.started_at DESC",
        ['uid' => $user['user_id']]
    );
    echo json_encode(['success' => true, 'data' => $sessions]);
}

function handlePost() {
    global $user;
    $input = json_decode(file_get_contents('php://input'), true);
    $branchId = $input['branch_id'] ?? null;
    $openingCash = $input['opening_cash_balance'] ?? 0;
    $notes = $input['notes'] ?? null;

    if (!$branchId) { echo json_encode(['success' => false, 'error' => 'Branch is required.']); return; }

    // Check for already open session
    $open = Database::fetch(
        "SELECT session_id FROM cashier_sessions WHERE cashier_user_id = :uid AND status = 'OPEN'",
        ['uid' => $user['user_id']]
    );
    if ($open) { echo json_encode(['success' => false, 'error' => 'You already have an open session. Close it first.']); return; }

    // Generate session code
    $sessionCode = 'SES-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    Database::execute(
        "INSERT INTO cashier_sessions (session_code, cashier_user_id, branch_id, started_at, starting_cash, status, notes)
         VALUES (:code, :uid, :branch, NOW(), :cash, 'OPEN', :notes)",
        ['code' => $sessionCode, 'uid' => $user['user_id'], 'branch' => $branchId, 'cash' => $openingCash, 'notes' => $notes]
    );
    $sessionId = Database::connection()->lastInsertId();
    logActivity($user['user_id'], 'OPEN_SESSION', 'POS', "SES-{$sessionId}", null, ['branch_id' => $branchId, 'opening_cash' => $openingCash]);
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
    if ($session['cashier_user_id'] != $user['user_id']) { echo json_encode(['success' => false, 'error' => 'Not your session.']); return; }

    if ($action === 'close') {
        if ($session['status'] !== 'OPEN') { echo json_encode(['success' => false, 'error' => 'Session is not open.']); return; }
        $closingCash = $input['closing_cash_balance'] ?? 0;
        $notes = $input['notes'] ?? null;

        // Get payment breakdown for this session to calculate expected cash based on payment method settings
        $paymentWhere = "AND tp.created_at >= :start";
        $paymentParams = [
            'uid'   => $session['cashier_user_id'],
            'start' => $session['started_at']
        ];

        $payments = Database::fetchAll(
            "SELECT pm.method_name, pm.method_type, pm.include_in_expected_cash, SUM(tp.amount) AS total_amount
             FROM transaction_payments tp
             JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
             WHERE tp.created_by = :uid
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

        // Compute expected cash using payment method settings
        $expectedCash = $session['starting_cash'] + $expectedCashPayments;
        $variance = $closingCash - $expectedCash;

        Database::execute(
            "UPDATE cashier_sessions SET
                ended_at = NOW(), actual_cash = :close, expected_cash = :expected,
                cash_variance = :variance, status = 'CLOSED', notes = :notes
             WHERE session_id = :id",
            ['close' => $closingCash, 'expected' => $expectedCash, 'variance' => $variance, 'notes' => $notes, 'id' => $sessionId]
        );
        logActivity($user['user_id'], 'CLOSE_SESSION', 'POS', "SES-{$sessionId}", null, ['closing_cash' => $closingCash, 'variance' => $variance]);
        echo json_encode(['success' => true, 'message' => 'Session closed.', 'variance' => $variance]);
        return;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
}
