<?php
/**
 * Bank Transfer Confirmations API
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BalanceLedgerService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PaymentSettlementService.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/NotificationService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';

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
$userRoleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');

function requireBankConfirmationBranchAccess(?int $branchId): void
{
    global $user;
    try {
        PosAccess::assertBranchAccess($user, (int) $branchId);
    } catch (Throwable $e) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    
    // Decode ID if it's encrypted (not numeric)
    if ($id && !is_numeric($id)) {
        $decodedId = IdEncoder::decode($id);
        if ($decodedId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid payment ID']);
            return;
        }
        $id = $decodedId;
    }
    
    if ($id) {
        $p = Database::fetch("SELECT * FROM transaction_payments WHERE payment_id = :id", ['id' => $id]);
        if ($p) {
            $paymentBranch = Database::fetch(
                "SELECT COALESCE(tt.branch_id, st.branch_id, cs.branch_id) AS branch_id
                 FROM transaction_payments tp
                 LEFT JOIN ticket_transactions tt
                   ON tp.source_type = 'TICKET_TRANSACTION' AND tp.source_id = tt.transaction_id
                 LEFT JOIN service_transactions st
                   ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
                 LEFT JOIN cashier_sessions cs ON tp.cashier_session_id = cs.session_id
                 WHERE tp.payment_id = :id",
                ['id' => $id]
            );
            requireBankConfirmationBranchAccess(isset($paymentBranch['branch_id']) ? (int) $paymentBranch['branch_id'] : null);
        } else {
            $p = Database::fetch("SELECT * FROM charge_payments WHERE charge_payment_id = :id", ['id' => $id]);
            if ($p) {
                requireBankConfirmationBranchAccess(isset($p['branch_id']) ? (int) $p['branch_id'] : null);
            }
        }
        echo json_encode($p ? ['success' => true, 'data' => $p] : ['success' => false, 'error' => 'Not found']);
        return;
    }
    $status = $_GET['status'] ?? 'PENDING';
    
    // Get transaction_payments with pending confirmation
    $transactionWhere = ['pm.requires_confirmation = 1', 'tp.confirmation_status = :transaction_status'];
    $transactionParams = ['transaction_status' => $status];
    PosAccess::applyBranchScope(
        $transactionWhere,
        $transactionParams,
        'COALESCE(tt.branch_id, st.branch_id)',
        $user,
        'bank_confirmation_transaction_branch'
    );
    $transactionPayments = Database::fetchAll(
        "SELECT tp.*, pm.method_name, pm.method_type,
                ba.bank_name, ba.account_name, ba.account_number,
                COALESCE(tt.transaction_code, st.transaction_code) AS transaction_code,
                tt.ticket_number, tt.status AS ticket_status,
                COALESCE(po.order_code, '') AS order_code,
                COALESCE(tt.branch_id, st.branch_id) AS branch_id,
                COALESCE(tt.passenger_id, st.passenger_id) AS passenger_id,
                'transaction' AS source_type
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         LEFT JOIN bank_accounts ba ON tp.bank_account_id = ba.bank_account_id
         LEFT JOIN ticket_transactions tt
            ON tp.source_type = 'TICKET_TRANSACTION' AND tp.source_id = tt.transaction_id
         LEFT JOIN service_transactions st
            ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
         LEFT JOIN pos_order_items oi
            ON ((tp.source_type = 'TICKET_TRANSACTION' AND oi.item_type = 'TICKET' AND oi.reference_id = tt.transaction_id)
             OR (tp.source_type = 'SERVICE_TRANSACTION' AND oi.item_type = 'SERVICE' AND oi.reference_id = st.service_txn_id))
         LEFT JOIN pos_orders po ON oi.order_id = po.order_id
         WHERE " . implode(' AND ', $transactionWhere) . "
         ORDER BY tp.created_at DESC",
        $transactionParams
    );
    
    // Get charge_payments with pending confirmation
    $chargeWhere = ['cp.confirmation_status = :charge_status', 'cp.bank_account_id IS NOT NULL'];
    $chargeParams = ['charge_status' => $status];
    PosAccess::applyBranchScope($chargeWhere, $chargeParams, 'cp.branch_id', $user, 'bank_confirmation_charge_branch');
    $chargePayments = Database::fetchAll(
        "SELECT cp.*, pm.method_name, ba.bank_name, 'charge' AS source_type
         FROM charge_payments cp
         JOIN payment_methods pm ON cp.payment_method_id = pm.method_id
         LEFT JOIN bank_accounts ba ON cp.bank_account_id = ba.bank_account_id
         WHERE " . implode(' AND ', $chargeWhere) . "
         ORDER BY cp.created_at DESC",
        $chargeParams
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
    $realtimeBranchIds = [];
    $stockUpdates = [];

    if (!$payId && !$depositId && !$chargePaymentId || !in_array($action, ['CONFIRMED', 'REJECTED'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request.']); return;
    }

    // Confirm or reject a payment/deposit
    if ($action === 'CONFIRMED' || $action === 'REJECTED') {
        try {
            Database::connection()->beginTransaction();

            if ($payId) {
                // Handle transaction_payments confirmation (existing logic)
                $existing = Database::fetch("SELECT * FROM transaction_payments WHERE payment_id = :id FOR UPDATE", ['id' => $payId]);
                if (!$existing) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Payment not found']);
                    return;
                }

                $paymentBranch = Database::fetch(
                    "SELECT COALESCE(tt.branch_id, st.branch_id, cs.branch_id) AS branch_id,
                            tt.provider_id, tt.variant_id
                     FROM transaction_payments tp
                     LEFT JOIN ticket_transactions tt ON tp.source_type = 'TICKET_TRANSACTION' AND tp.source_id = tt.transaction_id
                     LEFT JOIN service_transactions st ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
                     LEFT JOIN cashier_sessions cs ON tp.cashier_session_id = cs.session_id
                     WHERE tp.payment_id = :id",
                    ['id' => $payId]
                );
                requireBankConfirmationBranchAccess(
                    isset($paymentBranch['branch_id']) ? (int) $paymentBranch['branch_id'] : null
                );
                if (!empty($paymentBranch['branch_id'])) {
                    $realtimeBranchIds[] = (int) $paymentBranch['branch_id'];
                }

                $pm = Database::fetch("SELECT * FROM payment_methods WHERE method_id = :id", ['id' => $existing['payment_method_id']]);
                if (!$pm) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Payment method not found']);
                    return;
                }

                if (($existing['confirmation_status'] ?? null) !== 'PENDING') {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Payment is no longer pending.']);
                    return;
                }

                Database::execute(
                    "UPDATE transaction_payments
                     SET confirmation_status = :status,
                         confirmed_by = :uid,
                         confirmed_at = :confirmed_at,
                         confirmation_notes = :notes
                     WHERE payment_id = :id AND confirmation_status = 'PENDING'",
                    [
                        'status' => $action,
                        'uid' => $user['user_id'],
                        'confirmed_at' => date('Y-m-d H:i:s'),
                        'notes' => $notes,
                        'id' => $payId,
                    ]
                );

                if ($action === 'CONFIRMED'
                    && $existing['bank_account_id']
                    && in_array($pm['method_type'], ['BANK_TRANSFER', 'E_WALLET'], true)) {
                    $bankMovement = BalanceLedgerService::bankMovement(
                        (int) $existing['bank_account_id'],
                        'RECEIPT',
                        'IN',
                        (float) $existing['amount'],
                        'transaction_payments',
                        (int) $payId,
                        'Confirmed ' . strtolower((string) $pm['method_type']) . ' payment',
                        (int) $user['user_id'],
                        'bank-receipt:payment:' . (int) $payId,
                        null,
                        false,
                        $notes
                    );
                    logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankMovement['txn_code'],
                        null, ['bank_account_id' => $existing['bank_account_id'], 'amount' => $existing['amount'], 'type' => 'RECEIPT']);
                }

                if ($action === 'REJECTED') {
                    $settlement = PaymentSettlementService::handleRejectedTransactionPayment(
                        $existing,
                        $pm,
                        (int) $user['user_id'],
                        $notes
                    );
                    logActivity($user['user_id'], 'REJECTED_PAYMENT_SETTLEMENT', 'POS', "PAY-{$payId}",
                        null, $settlement);
                    if (!empty($settlement['voided'])
                        && ($existing['source_type'] ?? '') === 'TICKET_TRANSACTION'
                        && !empty($paymentBranch['branch_id'])
                        && !empty($paymentBranch['provider_id'])
                        && !empty($paymentBranch['variant_id'])) {
                        $stockUpdates[] = [
                            'branch_id' => (int) $paymentBranch['branch_id'],
                            'provider_id' => (int) $paymentBranch['provider_id'],
                            'variant_id' => (int) $paymentBranch['variant_id'],
                        ];
                    }
                }

                logActivity($user['user_id'], $action . '_PAYMENT', 'Bank Confirmations', "PAY-{$payId}", null,
                    ['status' => $action, 'notes' => $notes]);

                // Payment notification triggers
                if ($action === 'CONFIRMED') {
                    // Notify user who made the payment
                    if ($existing['created_by']) {
                        NotificationService::createFromTemplate('payment_confirmed', $existing['created_by'], [
                            'amount' => number_format($existing['amount'], 2),
                            'reference' => "PAY-{$payId}"
                        ]);
                    }
                } else if ($action === 'REJECTED') {
                    // Notify user and SUPER_ADMIN about failed payment
                    if ($existing['created_by']) {
                        NotificationService::createFromTemplate('payment_failed', $existing['created_by'], [
                            'amount' => number_format($existing['amount'], 2),
                            'reference' => "PAY-{$payId}"
                        ]);
                    }

                    // Also notify SUPER_ADMIN
                    $superAdmins = Database::fetchAll(
                        "SELECT ua.user_id FROM user_accounts ua
                         JOIN user_roles r ON ua.role_id = r.role_id
                         WHERE r.role_code = 'SUPER_ADMIN' AND ua.status = 'active'"
                    );

                    foreach ($superAdmins as $superAdmin) {
                        NotificationService::createFromTemplate('payment_failed', $superAdmin['user_id'], [
                            'amount' => number_format($existing['amount'], 2),
                            'reference' => "PAY-{$payId}"
                        ]);
                    }
                }
            }

            if ($depositId) {
                // Handle bank_transactions confirmation (new deposit logic)
                $existing = Database::fetch("SELECT * FROM bank_transactions WHERE bank_txn_id = :id FOR UPDATE", ['id' => $depositId]);
                if (!$existing) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Deposit not found']);
                    return;
                }

                $depositBranch = Database::fetch(
                    "SELECT branch_id FROM bank_accounts WHERE bank_account_id = :bank_account_id",
                    ['bank_account_id' => $existing['bank_account_id']]
                );
                requireBankConfirmationBranchAccess(
                    isset($depositBranch['branch_id']) ? (int) $depositBranch['branch_id'] : null
                );
                if (!empty($depositBranch['branch_id'])) {
                    $realtimeBranchIds[] = (int) $depositBranch['branch_id'];
                }

                if ($existing['confirmation_status'] !== 'PENDING') {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Deposit is not pending']);
                    return;
                }

                $depositResult = $action === 'CONFIRMED'
                    ? BalanceLedgerService::confirmPendingBankTransaction(
                        (int) $depositId,
                        (int) $user['user_id'],
                        $notes
                    )
                    : BalanceLedgerService::rejectPendingBankTransaction(
                        (int) $depositId,
                        (int) $user['user_id'],
                        $notes
                    );

                if ($existing['reference_table'] === 'cashier_sessions' && $existing['reference_id']) {
                    if ($action === 'CONFIRMED') {
                        Database::execute(
                            "UPDATE cashier_sessions
                             SET deposit_status = 'DEPOSITED',
                                 deposited_at = NOW(),
                                 deposited_by = :user_id
                             WHERE session_id = :session_id",
                            ['user_id' => $user['user_id'], 'session_id' => (int) $existing['reference_id']]
                        );
                    } else {
                        Database::execute(
                            "UPDATE cashier_sessions
                             SET deposit_status = 'PENDING',
                                 deposited_at = NULL,
                                 deposited_by = NULL
                             WHERE session_id = :session_id",
                            ['session_id' => (int) $existing['reference_id']]
                        );
                    }
                }

                logActivity($user['user_id'], $action . '_DEPOSIT', 'Bank Confirmations', "DEP-{$depositId}", null,
                    ['status' => $action, 'bank_txn_code' => $depositResult['txn_code'], 'notes' => $notes]);
            }

            if ($chargePaymentId) {
                // Handle charge_payments confirmation (new logic)
                $existing = Database::fetch("SELECT * FROM charge_payments WHERE charge_payment_id = :id FOR UPDATE", ['id' => $chargePaymentId]);
                if (!$existing) {
                    Database::connection()->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Charge payment not found']);
                    return;
                }

                requireBankConfirmationBranchAccess(
                    isset($existing['branch_id']) ? (int) $existing['branch_id'] : null
                );
                if (!empty($existing['branch_id'])) {
                    $realtimeBranchIds[] = (int) $existing['branch_id'];
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
                    "UPDATE charge_payments SET confirmation_status = :status, confirmed_by = :uid, confirmed_at = :confirmed_at WHERE charge_payment_id = :id",
                    ['status' => $action, 'uid' => $user['user_id'], 'confirmed_at' => date('Y-m-d H:i:s'), 'id' => $chargePaymentId]
                );

                // Create the bank receipt through the shared bank ledger.
                if ($action === 'CONFIRMED'
                    && $existing['bank_account_id']
                    && in_array($pm['method_type'], ['BANK_TRANSFER', 'E_WALLET'], true)) {
                    $bankMovement = BalanceLedgerService::bankMovement(
                        (int) $existing['bank_account_id'],
                        'RECEIPT',
                        'IN',
                        (float) $existing['amount_paid'],
                        'charge_payments',
                        (int) $chargePaymentId,
                        "Confirmed charge collection from passenger {$existing['passenger_id']}",
                        (int) $user['user_id'],
                        'bank-receipt:charge:' . (int) $chargePaymentId,
                        null,
                        false,
                        $notes
                    );
                    logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankMovement['txn_code'],
                        null, ['bank_account_id' => $existing['bank_account_id'], 'amount' => $existing['amount_paid'], 'type' => 'RECEIPT']);
                }

                // Note: Customer balance is already updated when payment is made
                // On confirmation: bank balance is updated
                // On rejection: customer balance is reverted
                if ($action === 'REJECTED' && $existing['passenger_id']) {
                    $chargeRow = Database::fetch(
                        "SELECT * FROM customer_charges WHERE passenger_id = :pid FOR UPDATE",
                        ['pid' => $existing['passenger_id']]
                    );
                    if ($chargeRow) {
                        $newBalance = floatval($chargeRow['balance']) + floatval($existing['amount_paid']);
                        $newPaid    = max(0, floatval($chargeRow['total_paid']) - floatval($existing['amount_paid']));
                        $newStatus  = $newBalance > 0 ? 'OUTSTANDING' : 'CLEAR';
                        Database::execute(
                            "UPDATE customer_charges SET 
                                total_paid = :paid,
                                balance = :balance,
                                status = :status,
                                updated_at = :updated_at
                             WHERE passenger_id = :pid",
                            ['paid' => $newPaid, 'balance' => $newBalance, 'status' => $newStatus, 'pid' => $existing['passenger_id'], 'updated_at' => date('Y-m-d H:i:s')]
                        );
                    }
                }

                logActivity($user['user_id'], $action . '_CHARGE_PAYMENT', 'Bank Confirmations', "CP-{$chargePaymentId}", null, ['status' => $action]);
            }

            Database::connection()->commit();

            $realtimeItemType = $depositId ? 'DEPOSIT' : ($chargePaymentId ? 'CHARGE' : 'PAYMENT');
            $realtimeItemId = (int) ($depositId ?: ($chargePaymentId ?: $payId));
            foreach (array_values(array_unique($realtimeBranchIds)) as $realtimeBranchId) {
                PusherService::triggerBranch($realtimeBranchId, 'bank.confirmation.updated', [
                    'branch_id' => $realtimeBranchId,
                    'item_type' => $realtimeItemType,
                    'item_id' => $realtimeItemId,
                    'action' => $action,
                    'confirmation_status' => $action,
                ]);
            }
            foreach ($stockUpdates as $stockUpdate) {
                PusherService::triggerBranch($stockUpdate['branch_id'], 'ticket_stock.updated', array_merge(
                    $stockUpdate,
                    ['source' => 'rejected_payment']
                ));
            }

            echo json_encode(['success' => true, 'message' => ucfirst(strtolower($action)) . ' successfully']);
            return;
        } catch (Throwable $e) {
            if (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }
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
