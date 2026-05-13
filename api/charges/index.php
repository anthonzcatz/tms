<?php
/**
 * Customer Charges API
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

if ($method === 'GET') {
    $passengerId = $_GET['passenger_id'] ?? null;
    if (!$passengerId) { echo json_encode(['success' => false, 'error' => 'passenger_id required']); return; }

    // Charge entries (from transaction_payments where method_type = CHARGE)
    $charges = Database::fetchAll(
        "SELECT tp.payment_id, tp.amount, tp.created_at,
                st.transaction_code AS txn_code,
                stype.name AS service_type_name
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         LEFT JOIN service_transactions st ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
         LEFT JOIN service_types stype ON st.service_type_id = stype.service_type_id
         WHERE pm.method_type = 'CHARGE' AND tp.charged_to_passenger_id = :pid
         ORDER BY tp.created_at DESC",
        ['pid' => $passengerId]
    );

    // Payments received
    $payments = Database::fetchAll(
        "SELECT cp.*, pm.method_name
         FROM charge_payments cp
         LEFT JOIN payment_methods pm ON cp.payment_method_id = pm.method_id
         WHERE cp.passenger_id = :pid
         ORDER BY cp.created_at DESC",
        ['pid' => $passengerId]
    );

    echo json_encode(['success' => true, 'data' => ['charges' => $charges, 'payments' => $payments]]);
    return;
}

if ($method === 'POST') {
    $input       = json_decode(file_get_contents('php://input'), true);
    $passengerId = $input['passenger_id'] ?? null;
    $amountPaid  = floatval($input['amount_paid'] ?? 0);
    $methodId    = $input['payment_method_id'] ?? null;
    $refNum      = $input['reference_number'] ?? null;
    $notes       = $input['notes'] ?? null;
    $branchId    = $user['branch_id'] ?? null;

    if (!$passengerId || $amountPaid <= 0 || !$methodId) {
        echo json_encode(['success' => false, 'error' => 'passenger_id, amount_paid, and payment_method_id are required.']); return;
    }

    // Get current balance
    $chargeRow = Database::fetch("SELECT * FROM customer_charges WHERE passenger_id = :pid", ['pid' => $passengerId]);
    if (!$chargeRow) { echo json_encode(['success' => false, 'error' => 'No charge record found for this customer.']); return; }
    if ($chargeRow['balance'] <= 0) { echo json_encode(['success' => false, 'error' => 'Customer has no outstanding balance.']); return; }

    $balBefore = floatval($chargeRow['balance']);
    $applied   = min($amountPaid, $balBefore);
    $balAfter  = $balBefore - $applied;

    $payCode = 'CP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $pm = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $methodId]);
    $confirmStatus = ($pm && $pm['requires_confirmation']) ? 'PENDING' : 'NOT_REQUIRED';

    Database::execute(
        "INSERT INTO charge_payments
            (payment_code, passenger_id, branch_id, payment_method_id, amount_paid, balance_before, balance_after,
             reference_number, confirmation_status, notes, created_by, created_at)
         VALUES (:code, :pid, :branch, :method, :amount, :before, :after, :ref, :confirm, :notes, :uid, NOW())",
        ['code' => $payCode, 'pid' => $passengerId, 'branch' => $branchId, 'method' => $methodId,
         'amount' => $applied, 'before' => $balBefore, 'after' => $balAfter,
         'ref' => $refNum, 'confirm' => $confirmStatus, 'notes' => $notes, 'uid' => $user['user_id']]
    );

    // Update customer_charges aggregate
    $newStatus = $balAfter <= 0 ? 'CLEAR' : $chargeRow['status'];
    Database::execute(
        "UPDATE customer_charges SET
            total_paid = total_paid + :paid,
            balance = :after,
            status = :status,
            last_payment_date = NOW(),
            updated_at = NOW()
         WHERE passenger_id = :pid",
        ['paid' => $applied, 'after' => $balAfter, 'status' => $newStatus, 'pid' => $passengerId]
    );

    logActivity($user['user_id'], 'COLLECT_CHARGE_PAYMENT', 'CUSTOMER_CHARGES', $payCode,
        ['balance' => $balBefore], ['balance' => $balAfter, 'amount_paid' => $applied]);

    echo json_encode(['success' => true, 'message' => 'Payment recorded.', 'new_balance' => $balAfter, 'payment_code' => $payCode]);
    return;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
