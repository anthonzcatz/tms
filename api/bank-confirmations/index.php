<?php
/**
 * Bank Transfer Confirmations API
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
$userRoleCode = $user['role_code'] ?? '';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $p = Database::fetch("SELECT * FROM transaction_payments WHERE payment_id = :id", ['id' => $id]);
        if (!$p) {
            $p = Database::fetch("SELECT * FROM charge_payments WHERE charge_payment_id = :id", ['id' => $id]);
        }
        echo json_encode($p ? ['success' => true, 'data' => $p] : ['success' => false, 'error' => 'Not found']);
        return;
    }
    $status = $_GET['status'] ?? 'PENDING';
    
    // Get transaction_payments with pending confirmation
    $transactionPayments = Database::fetchAll(
        "SELECT tp.*, pm.method_name, ba.bank_name, 'transaction' AS source_type
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         LEFT JOIN bank_accounts ba ON tp.bank_account_id = ba.bank_account_id
         WHERE pm.requires_confirmation = 1 AND tp.confirmation_status = :status
         ORDER BY tp.created_at DESC",
        ['status' => $status]
    );
    
    // Get charge_payments with pending confirmation
    $chargePayments = Database::fetchAll(
        "SELECT cp.*, pm.method_name, ba.bank_name, 'charge' AS source_type
         FROM charge_payments cp
         JOIN payment_methods pm ON cp.payment_method_id = pm.method_id
         LEFT JOIN bank_accounts ba ON cp.bank_account_id = ba.bank_account_id
         WHERE cp.confirmation_status = :status AND cp.bank_account_id IS NOT NULL
         ORDER BY cp.created_at DESC",
        ['status' => $status]
    );
    
    // Merge and sort by date
    $allPayments = array_merge($transactionPayments, $chargePayments);
    usort($allPayments, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    echo json_encode(['success' => true, 'data' => $allPayments]);
    return;
}

if ($method === 'PUT') {
    if ($userRoleCode !== 'SUPER_ADMIN' && !Auth::can('VIEW_BANK_CONFIRMATIONS')) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'Permission denied.']); return;
    }

    $input  = json_decode(file_get_contents('php://input'), true);
    $payId  = $input['payment_id'] ?? null;
    $depositId = $input['deposit_id'] ?? null;
    $chargePaymentId = $input['charge_payment_id'] ?? null;
    $action = $input['action'] ?? null;
    $notes  = $input['notes'] ?? null;

    if (!$payId && !$depositId && !$chargePaymentId || !in_array($action, ['CONFIRMED', 'REJECTED'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request.']); return;
    }

    // Confirm or reject a payment/deposit
    if ($action === 'CONFIRMED' || $action === 'REJECTED') {
        try {
            Database::connection()->beginTransaction();

            if ($payId) {
                // Handle transaction_payments confirmation (existing logic)
                $existing = Database::fetch("SELECT * FROM transaction_payments WHERE payment_id = :id", ['id' => $payId]);
                if (!$existing) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Payment not found']);
                    return;
                }

                $pm = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $existing['payment_method_id']]);
                if (!$pm) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Payment method not found']);
                    return;
                }

                Database::execute(
                    "UPDATE transaction_payments SET confirmation_status = :status, confirmed_by = :uid, confirmed_at = NOW() WHERE payment_id = :id",
                    ['status' => $action, 'uid' => $user['user_id'], 'id' => $payId]
                );

                // Create bank transaction when confirming bank/e-wallet payments
                if ($action === 'CONFIRMED' && $existing['bank_account_id'] && ($pm['method_type'] === 'BANK_TRANSFER' || $pm['method_type'] === 'E_WALLET')) {
                    $bankAccount = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id", ['id' => $existing['bank_account_id']]);
                    if ($bankAccount) {
                        $balBeforeBank = floatval($bankAccount['current_balance'] ?? 0);
                        $balAfterBank = $balBeforeBank + $existing['amount'];
                        
                        $bankTxnCode = 'BANK-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -5));
                        Database::execute(
                            "INSERT INTO bank_transactions
                                (bank_account_id, txn_code, confirmation_status, txn_type, direction, amount, balance_before, balance_after,
                                 reference_table, reference_id, remarks, created_by, created_at)
                             VALUES (:bank_id, :code, 'CONFIRMED', 'RECEIPT', 'IN', :amount, :before, :after, 'transaction_payments', :ref_id, :remarks, :uid, NOW())",
                            [
                                'bank_id' => $existing['bank_account_id'],
                                'code' => $bankTxnCode,
                                'amount' => $existing['amount'],
                                'before' => $balBeforeBank,
                                'after' => $balAfterBank,
                                'ref_id' => $payId,
                                'remarks' => "Confirmed bank transfer payment",
                                'uid' => $user['user_id']
                            ]
                        );
                        
                        Database::execute(
                            "UPDATE bank_accounts SET current_balance = :balance WHERE bank_account_id = :id",
                            ['balance' => $balAfterBank, 'id' => $existing['bank_account_id']]
                        );
                        
                        logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankTxnCode,
                            null, ['bank_account_id' => $existing['bank_account_id'], 'amount' => $existing['amount'], 'type' => 'RECEIPT']);
                    }
                }

                logActivity($user['user_id'], $action . '_PAYMENT', 'Bank Confirmations', "PAY-{$payId}", null, ['status' => $action]);
            }

            if ($depositId) {
                // Handle bank_transactions confirmation (new deposit logic)
                $existing = Database::fetch("SELECT * FROM bank_transactions WHERE bank_txn_id = :id", ['id' => $depositId]);
                if (!$existing) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Deposit not found']);
                    return;
                }

                if ($existing['confirmation_status'] !== 'PENDING') {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Deposit is not pending']);
                    return;
                }

                Database::execute(
                    "UPDATE bank_transactions SET confirmation_status = :status, confirmed_by = :uid, confirmed_at = NOW() WHERE bank_txn_id = :id",
                    ['status' => $action, 'uid' => $user['user_id'], 'id' => $depositId]
                );

                // Update bank account balance when confirming a deposit
                if ($action === 'CONFIRMED') {
                    $bankAccount = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id", ['id' => $existing['bank_account_id']]);
                    if ($bankAccount) {
                        $balBeforeBank = floatval($bankAccount['current_balance'] ?? 0);
                        $balAfterBank = $balBeforeBank + $existing['amount'];
                        
                        Database::execute(
                            "UPDATE bank_accounts SET current_balance = :balance WHERE bank_account_id = :id",
                            ['balance' => $balAfterBank, 'id' => $existing['bank_account_id']]
                        );
                    }
                }

                logActivity($user['user_id'], $action . '_DEPOSIT', 'Bank Confirmations', "DEP-{$depositId}", null, ['status' => $action]);
            }

            if ($chargePaymentId) {
                // Handle charge_payments confirmation (new logic)
                $existing = Database::fetch("SELECT * FROM charge_payments WHERE charge_payment_id = :id", ['id' => $chargePaymentId]);
                if (!$existing) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Charge payment not found']);
                    return;
                }

                if ($existing['confirmation_status'] !== 'PENDING') {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Charge payment is not pending']);
                    return;
                }

                $pm = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $existing['payment_method_id']]);
                if (!$pm) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Payment method not found']);
                    return;
                }

                Database::execute(
                    "UPDATE charge_payments SET confirmation_status = :status, confirmed_by = :uid, confirmed_at = NOW() WHERE charge_payment_id = :id",
                    ['status' => $action, 'uid' => $user['user_id'], 'id' => $chargePaymentId]
                );

                // Create bank transaction when confirming bank/e-wallet charge payments
                if ($action === 'CONFIRMED' && $existing['bank_account_id'] && ($pm['method_type'] === 'BANK_TRANSFER' || $pm['method_type'] === 'E_WALLET')) {
                    $bankAccount = Database::fetch("SELECT * FROM bank_accounts WHERE bank_account_id = :id", ['id' => $existing['bank_account_id']]);
                    if ($bankAccount) {
                        $balBeforeBank = floatval($bankAccount['current_balance'] ?? 0);
                        $balAfterBank = $balBeforeBank + $existing['amount_paid'];
                        
                        $bankTxnCode = 'BANK-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -5));
                        Database::execute(
                            "INSERT INTO bank_transactions
                                (bank_account_id, txn_code, confirmation_status, txn_type, direction, amount, balance_before, balance_after,
                                 reference_table, reference_id, remarks, created_by, created_at)
                             VALUES (:bank_id, :code, 'CONFIRMED', 'RECEIPT', 'IN', :amount, :before, :after, 'charge_payments', :ref_id, :remarks, :uid, NOW())",
                            [
                                'bank_id' => $existing['bank_account_id'],
                                'code' => $bankTxnCode,
                                'amount' => $existing['amount_paid'],
                                'before' => $balBeforeBank,
                                'after' => $balAfterBank,
                                'ref_id' => $chargePaymentId,
                                'remarks' => "Confirmed charge collection payment from passenger {$existing['passenger_id']}",
                                'uid' => $user['user_id']
                            ]
                        );
                        
                        Database::execute(
                            "UPDATE bank_accounts SET current_balance = :balance WHERE bank_account_id = :id",
                            ['balance' => $balAfterBank, 'id' => $existing['bank_account_id']]
                        );
                        
                        logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankTxnCode,
                            null, ['bank_account_id' => $existing['bank_account_id'], 'amount' => $existing['amount_paid'], 'type' => 'RECEIPT']);
                    }
                }

                // Note: Customer balance is already updated when payment is made
                // On confirmation: bank balance is updated
                // On rejection: customer balance is reverted
                if ($action === 'REJECTED') {
                    Database::execute(
                        "UPDATE customer_charges SET 
                            total_paid = total_paid - :paid1,
                            balance = balance + :paid2,
                            status = 'OUTSTANDING',
                            updated_at = NOW()
                         WHERE passenger_id = :pid",
                        ['paid1' => $existing['amount_paid'], 'paid2' => $existing['amount_paid'], 'pid' => $existing['passenger_id']]
                    );
                }

                logActivity($user['user_id'], $action . '_CHARGE_PAYMENT', 'Bank Confirmations', "CP-{$chargePaymentId}", null, ['status' => $action]);
            }

            Database::connection()->commit();

            echo json_encode(['success' => true, 'message' => ucfirst(strtolower($action)) . ' successfully']);
            return;
        } catch (Exception $e) {
            Database::connection()->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to process: ' . $e->getMessage()]);
            return;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        return;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
