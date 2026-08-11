<?php
/**
 * Customer Charges API
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

if ($method === 'GET') {
    $passengerId = $_GET['passenger_id'] ?? null;

    // Decode passenger_id if provided
    if ($passengerId) {
        $decodedPassengerId = IdEncoder::decode($passengerId);
        if ($decodedPassengerId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid passenger ID']);
            return;
        }
        $passengerId = $decodedPassengerId;
    }

    if (!$passengerId) { echo json_encode(['success' => false, 'error' => 'passenger_id required']); return; }

    // Charge entries (from transaction_payments where payment method tracks_credit = 1)
    $charges = Database::fetchAll(
        "SELECT tp.payment_id, tp.amount, tp.created_at, tp.source_type,
                pm.method_name, pm.method_type,
                COALESCE(st.transaction_code, tt.transaction_code) AS txn_code,
                tt.ticket_number,
                stype.name AS service_type_name,
                CASE WHEN tp.source_type = 'TICKET_TRANSACTION' THEN 'Ticket' ELSE stype.name END AS item_label,
                bb.branch_name,
                CONCAT(e.first_name, IF(e.middle_name IS NOT NULL AND e.middle_name != '', CONCAT(' ', LEFT(e.middle_name, 1), '.'), ''), ' ', e.last_name) AS cashier_name
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         LEFT JOIN cashier_sessions cs ON tp.cashier_session_id = cs.session_id
         LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
         LEFT JOIN user_accounts ua ON tp.created_by = ua.user_id
         LEFT JOIN employees e ON ua.emp_id = e.emp_id
         LEFT JOIN service_transactions st ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
         LEFT JOIN service_types stype ON st.service_type_id = stype.service_type_id
         LEFT JOIN ticket_transactions tt ON tp.source_type = 'TICKET_TRANSACTION' AND tp.source_id = tt.transaction_id
         WHERE pm.tracks_credit = 1 AND tp.charged_to_passenger_id = :pid
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

    // Cancellation-based charge reversals (show as negative charges / adjustments)
    $reversals = Database::fetchAll(
        "SELECT tc.cancellation_id, tc.charge_amount AS amount, tc.approved_at AS created_at,
                tc.transaction_code AS txn_code, tt.ticket_number, 'Ticket Cancellation' AS item_label,
                CONCAT(e.first_name, IF(e.middle_name IS NOT NULL AND e.middle_name != '', CONCAT(' ', LEFT(e.middle_name, 1), '.'), ''), ' ', e.last_name) AS cashier_name,
                'CHARGE_REVERSAL' AS entry_type
         FROM ticket_cancellations tc
         LEFT JOIN ticket_transactions tt ON tc.transaction_id = tt.transaction_id
         LEFT JOIN user_accounts ua ON tc.approved_by = ua.user_id
         LEFT JOIN employees e ON ua.emp_id = e.emp_id
         WHERE tc.passenger_id = :pid AND tc.charge_amount > 0 AND tc.status = 'approved'
         ORDER BY tc.approved_at DESC",
        ['pid' => $passengerId]
    );

    echo json_encode(['success' => true, 'data' => ['charges' => $charges, 'payments' => $payments, 'reversals' => $reversals]]);
    return;
}

if ($method === 'POST') {
    $input       = json_decode(file_get_contents('php://input'), true);
    $passengerId = $input['passenger_id'] ?? null;
    $amountPaid  = floatval($input['amount_paid'] ?? 0);
    $methodId    = $input['payment_method_id'] ?? null;
    $bankAcctId  = $input['bank_account_id'] ?? null;
    $refNum      = $input['reference_number'] ?? null;
    $notes       = $input['notes'] ?? null;
    $branchId    = $input['branch_id'] ?? $user['branch_id'] ?? null;

    if (!$passengerId || $amountPaid <= 0 || !$methodId) {
        echo json_encode(['success' => false, 'error' => 'passenger_id, amount_paid, and payment_method_id are required.']); return;
    }

    if (!$branchId) {
        echo json_encode(['success' => false, 'error' => 'branch_id is required.']); return;
    }

    $payCode = 'CP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $pm = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $methodId]);
    
    // Check system settings for bank confirmation requirement
    $settings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
    $requireConfirmation = false;
    
    // For bank/e-wallet payments, check system setting directly
    if ($bankAcctId && ($pm['method_type'] === 'BANK_TRANSFER' || $pm['method_type'] === 'E_WALLET')) {
        // System setting overrides payment method setting for bank/e-wallet charge payments
        $requireConfirmation = ($settings['bank_charge_payments_require_confirmation'] ?? 0) == 1;
    } elseif ($pm && $pm['requires_confirmation']) {
        // For non-bank methods, use payment method's requires_confirmation setting
        $requireConfirmation = true;
    }
    
    $confirmStatus = $requireConfirmation ? 'PENDING' : 'NOT_REQUIRED';

    try {
        Database::connection()->beginTransaction();

        // Re-read customer_charges inside the transaction and lock the row to prevent
        // concurrent charge payments from over-applying or corrupting the balance.
        $chargeRow = Database::fetch("SELECT * FROM customer_charges WHERE passenger_id = :pid FOR UPDATE", ['pid' => $passengerId]);
        if (!$chargeRow) {
            Database::connection()->rollBack();
            echo json_encode(['success' => false, 'error' => 'No charge record found for this customer.']);
            return;
        }
        if ($chargeRow['balance'] <= 0) {
            Database::connection()->rollBack();
            echo json_encode(['success' => false, 'error' => 'Customer has no outstanding balance.']);
            return;
        }

        $balBefore = floatval($chargeRow['balance']);
        $applied   = min($amountPaid, $balBefore);
        $balAfter  = $balBefore - $applied;

        Database::execute(
            "INSERT INTO charge_payments
                (payment_code, passenger_id, branch_id, payment_method_id, bank_account_id, amount_paid, balance_before, balance_after,
                 reference_number, confirmation_status, notes, created_by, created_at)
             VALUES (:code, :pid, :branch, :method, :bank, :amount, :before, :after, :ref, :confirm, :notes, :uid, :created_at)",
            ['code' => $payCode, 'pid' => $passengerId, 'branch' => $branchId, 'method' => $methodId,
             'bank' => $bankAcctId, 'amount' => $applied, 'before' => $balBefore, 'after' => $balAfter,
             'ref' => $refNum, 'confirm' => $confirmStatus, 'notes' => $notes, 'uid' => $user['user_id'],
             'created_at' => date('Y-m-d H:i:s')]
        );

        $chargePaymentId = Database::connection()->lastInsertId();

        // Create bank transaction if payment method is bank/e-wallet and bank_account_id is provided
        // Only create immediately if confirmation is NOT required
        if ($bankAcctId && ($pm['method_type'] === 'BANK_TRANSFER' || $pm['method_type'] === 'E_WALLET') && !$requireConfirmation) {
            $bankAccount = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id FOR UPDATE", ['id' => $bankAcctId]);
            if ($bankAccount) {
                $balBeforeBank = floatval($bankAccount['current_balance'] ?? 0);
                $balAfterBank = $balBeforeBank + $applied;
                
                $bankTxnCode = 'BANK-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -5));
                Database::execute(
                    "INSERT INTO bank_transactions
                        (bank_account_id, txn_code, txn_type, direction, amount, balance_before, balance_after,
                         reference_table, reference_id, remarks, confirmation_status, created_by, created_at)
                     VALUES (:bank_id, :code, 'RECEIPT', 'IN', :amount, :before, :after, 'charge_payments', :ref_id, :remarks, 'CONFIRMED', :uid, :created_at)",
                    [
                        'bank_id' => $bankAcctId,
                        'code' => $bankTxnCode,
                        'amount' => $applied,
                        'before' => $balBeforeBank,
                        'after' => $balAfterBank,
                        'ref_id' => $chargePaymentId,
                        'remarks' => "Payment collection from passenger {$passengerId}",
                        'uid' => $user['user_id'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                );
                
                Database::execute(
                    "UPDATE bank_accounts SET current_balance = :balance, updated_at = NOW() WHERE bank_account_id = :id",
                    ['balance' => $balAfterBank, 'id' => $bankAcctId]
                );
                
                logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankTxnCode,
                    null, ['bank_account_id' => $bankAcctId, 'amount' => $applied, 'type' => 'RECEIPT']);
            }
        }

        // Update customer_charges aggregate
        // Balance is always updated immediately for better UX
        // If confirmation is required and payment is rejected, balance will be reverted
        $newStatus = $balAfter <= 0 ? 'CLEAR' : $chargeRow['status'];
        Database::execute(
            "UPDATE customer_charges SET
                total_paid = total_paid + :paid,
                balance = :after,
                status = :status,
                last_payment_date = :last_payment_date,
                updated_at = :updated_at
             WHERE passenger_id = :pid",
            ['paid' => $applied, 'after' => $balAfter, 'status' => $newStatus, 'pid' => $passengerId,
             'last_payment_date' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        );

        Database::connection()->commit();

        logActivity($user['user_id'], 'COLLECT_CHARGE_PAYMENT', 'CUSTOMER_CHARGES', $payCode,
            ['balance' => $balBefore], ['balance' => $balAfter, 'amount_paid' => $applied]);

        echo json_encode(['success' => true, 'message' => 'Payment recorded.', 'new_balance' => $balAfter, 'payment_code' => $payCode]);
    } catch (Exception $e) {
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();
        }
        error_log('Charge Payment Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to record payment: ' . $e->getMessage()]);
    }
    return;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
