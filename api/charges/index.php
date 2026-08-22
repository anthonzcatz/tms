<?php
/**
 * Customer Charges API
 */
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/BalanceLedgerService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/ChargeService.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';
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
    try {
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

        $branch    = trim($_GET['branch'] ?? '');
        $entryType = trim($_GET['type'] ?? '');
        $page      = (int)($_GET['page'] ?? 1);
        $perPage   = (int)($_GET['per_page'] ?? 10);
        if ($page < 1) $page = 1;
        if ($perPage < 1) $perPage = 10;
        if ($perPage > 100) $perPage = 100;
        $offset = ($page - 1) * $perPage;

        $empNameConcat = "CONCAT(e.first_name, IF(e.middle_name IS NOT NULL AND e.middle_name != '', CONCAT(' ', LEFT(e.middle_name, 1), '.'), ''), ' ', e.last_name)";
        $empNameConfirmed = "CONCAT(ec.first_name, IF(ec.middle_name IS NOT NULL AND ec.middle_name != '', CONCAT(' ', LEFT(ec.middle_name, 1), '.'), ''), ' ', ec.last_name)";

        // Charge entries (from transaction_payments where payment method tracks_credit = 1)
        $chargeSql =
            "SELECT tp.payment_id AS entry_id,
                    'charge' AS entry_type,
                    tp.amount,
                    tp.created_at,
                    COALESCE(st.transaction_code, tt.transaction_code) AS txn_code,
                    tt.ticket_number,
                    CASE
                        WHEN tp.source_type = 'TICKET_TRANSACTION' THEN ticket_passenger.fullname
                        WHEN tp.source_type = 'SERVICE_TRANSACTION' THEN service_passenger.fullname
                        ELSE NULL
                    END AS transaction_passenger_name,
                    CASE WHEN tp.source_type = 'TICKET_TRANSACTION' THEN 'Ticket' ELSE stype.name END AS item_label,
                    pm.method_name,
                    bb.branch_name,
                    {$empNameConcat} AS cashier_name,
                    NULL AS reference_number,
                    NULL AS confirmation_status,
                    NULL AS confirmed_by,
                    NULL AS notes,
                    NULL AS balance_before,
                    NULL AS balance_after,
                    tp.source_type,
                    NULL AS operation_type
             FROM transaction_payments tp
             JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
             LEFT JOIN cashier_sessions cs ON tp.cashier_session_id = cs.session_id
             LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
             LEFT JOIN user_accounts ua ON tp.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             LEFT JOIN service_transactions st ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
             LEFT JOIN service_types stype ON st.service_type_id = stype.service_type_id
             LEFT JOIN ticket_transactions tt ON tp.source_type = 'TICKET_TRANSACTION' AND tp.source_id = tt.transaction_id
             LEFT JOIN passenger_accounts ticket_passenger ON tt.passenger_id = ticket_passenger.passenger_id
             LEFT JOIN passenger_accounts service_passenger ON st.passenger_id = service_passenger.passenger_id
             WHERE pm.tracks_credit = 1 AND tp.charged_to_passenger_id = ?";

        // Payments received
        $paymentSql =
            "SELECT cp.charge_payment_id AS entry_id,
                    'payment' AS entry_type,
                    cp.amount_paid AS amount,
                    cp.created_at,
                    cp.payment_code AS txn_code,
                    NULL AS ticket_number,
                    NULL AS transaction_passenger_name,
                    'Payment Collection' AS item_label,
                    pm.method_name,
                    bb.branch_name,
                    {$empNameConcat} AS cashier_name,
                    cp.reference_number,
                    cp.confirmation_status,
                    {$empNameConfirmed} AS confirmed_by,
                    cp.notes,
                    cp.balance_before,
                    cp.balance_after,
                    NULL AS source_type,
                    NULL AS operation_type
             FROM charge_payments cp
             LEFT JOIN payment_methods pm ON cp.payment_method_id = pm.method_id
             LEFT JOIN business_branches bb ON cp.branch_id = bb.branch_id
             LEFT JOIN user_accounts ua ON cp.created_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             LEFT JOIN user_accounts uac ON cp.confirmed_by = uac.user_id
             LEFT JOIN employees ec ON uac.emp_id = ec.emp_id
             WHERE cp.passenger_id = ?";

        $refundTables = Database::fetch(
            "SELECT COUNT(*) AS table_count
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name IN ('ticket_refunds', 'refund_allocations')"
        );
        $hasRefundAllocationTables = (int) ($refundTables['table_count'] ?? 0) === 2;

        $ticketReversalSourceParts = [
            "SELECT tc_source.cancellation_id,
                    original_charge.charged_to_passenger_id,
                    SUM(original_charge.amount) AS reversal_amount
             FROM ticket_cancellations tc_source
             JOIN transaction_payments original_charge
               ON original_charge.source_type = 'TICKET_TRANSACTION'
              AND original_charge.source_id = tc_source.transaction_id
             JOIN payment_methods original_method
               ON original_method.method_id = original_charge.payment_method_id
              AND original_method.tracks_credit = 1
             WHERE tc_source.operation_type = 'VOID'
               AND original_charge.amount > 0
               AND original_charge.charged_to_passenger_id IS NOT NULL
               AND original_charge.confirmation_status <> 'REJECTED'
             GROUP BY tc_source.cancellation_id, original_charge.charged_to_passenger_id"
        ];

        $legacyRefundAllocationExclusion = '';
        if ($hasRefundAllocationTables) {
            $ticketReversalSourceParts[] =
                "SELECT tr.cancellation_id,
                        original_charge.charged_to_passenger_id,
                        SUM(ra.amount) AS reversal_amount
                 FROM ticket_refunds tr
                 JOIN refund_allocations ra
                   ON ra.refund_scope = 'TICKET'
                  AND ra.refund_id = tr.refund_id
                  AND ra.refund_route = 'CHARGE_REVERSAL'
                  AND ra.status = 'PROCESSED'
                 JOIN transaction_payments original_charge
                   ON original_charge.payment_id = ra.source_payment_id
                 JOIN payment_methods original_method
                   ON original_method.method_id = original_charge.payment_method_id
                  AND original_method.tracks_credit = 1
                 WHERE original_charge.source_type = 'TICKET_TRANSACTION'
                   AND original_charge.amount > 0
                   AND original_charge.charged_to_passenger_id IS NOT NULL
                   AND original_charge.confirmation_status <> 'REJECTED'
                 GROUP BY tr.cancellation_id, original_charge.charged_to_passenger_id";

            $legacyRefundAllocationExclusion =
                "AND NOT EXISTS (
                    SELECT 1
                    FROM ticket_refunds tr_existing
                    JOIN refund_allocations ra_existing
                      ON ra_existing.refund_scope = 'TICKET'
                     AND ra_existing.refund_id = tr_existing.refund_id
                     AND ra_existing.refund_route = 'CHARGE_REVERSAL'
                     AND ra_existing.status = 'PROCESSED'
                    WHERE tr_existing.cancellation_id = tc_source.cancellation_id
                )";
        }

        $ticketReversalSourceParts[] =
            "SELECT tc_source.cancellation_id,
                    original_charge.charged_to_passenger_id,
                    ROUND(
                        tc_source.charge_amount * SUM(original_charge.amount)
                        / NULLIF(total_charge.total_amount, 0),
                        2
                    ) AS reversal_amount
             FROM ticket_cancellations tc_source
             JOIN transaction_payments original_charge
               ON original_charge.source_type = 'TICKET_TRANSACTION'
              AND original_charge.source_id = tc_source.transaction_id
             JOIN payment_methods original_method
               ON original_method.method_id = original_charge.payment_method_id
              AND original_method.tracks_credit = 1
             JOIN (
                 SELECT total_payment.source_id,
                        SUM(total_payment.amount) AS total_amount
                 FROM transaction_payments total_payment
                 JOIN payment_methods total_method
                   ON total_method.method_id = total_payment.payment_method_id
                  AND total_method.tracks_credit = 1
                 WHERE total_payment.source_type = 'TICKET_TRANSACTION'
                   AND total_payment.amount > 0
                   AND total_payment.charged_to_passenger_id IS NOT NULL
                   AND total_payment.confirmation_status <> 'REJECTED'
                 GROUP BY total_payment.source_id
             ) total_charge ON total_charge.source_id = tc_source.transaction_id
             WHERE COALESCE(tc_source.operation_type, 'REFUND') <> 'VOID'
               AND tc_source.charge_amount > 0
               AND original_charge.amount > 0
               AND original_charge.charged_to_passenger_id IS NOT NULL
               AND original_charge.confirmation_status <> 'REJECTED'
               {$legacyRefundAllocationExclusion}
             GROUP BY tc_source.cancellation_id,
                      tc_source.charge_amount,
                      original_charge.charged_to_passenger_id,
                      total_charge.total_amount";

        $ticketReversalSourceSql = implode(' UNION ALL ', $ticketReversalSourceParts);

        // Cancellation-based charge reversals (show as negative charges / adjustments)
        $reversalSql =
            "SELECT tc.cancellation_id AS entry_id,
                    'reversal' AS entry_type,
                    reversal_source.reversal_amount AS amount,
                    tc.approved_at AS created_at,
                    tc.transaction_code AS txn_code,
                    tt.ticket_number,
                    ticket_passenger.fullname AS transaction_passenger_name,
                    CASE
                        WHEN COALESCE(tc.operation_type, 'REFUND') = 'VOID' THEN 'Ticket Void'
                        ELSE 'Ticket Cancellation'
                    END AS item_label,
                    NULL AS method_name,
                    bb.branch_name,
                    {$empNameConcat} AS cashier_name,
                    NULL AS reference_number,
                    NULL AS confirmation_status,
                    NULL AS confirmed_by,
                    tc.remarks AS notes,
                    NULL AS balance_before,
                    NULL AS balance_after,
                    NULL AS source_type,
                    COALESCE(tc.operation_type, 'REFUND') AS operation_type
             FROM ticket_cancellations tc
             JOIN ({$ticketReversalSourceSql}) AS reversal_source
               ON reversal_source.cancellation_id = tc.cancellation_id
             LEFT JOIN ticket_transactions tt ON tc.transaction_id = tt.transaction_id
             LEFT JOIN passenger_accounts ticket_passenger ON tt.passenger_id = ticket_passenger.passenger_id
             LEFT JOIN cashier_sessions cs ON tc.cashier_session_id = cs.session_id
             LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
             LEFT JOIN user_accounts ua ON tc.approved_by = ua.user_id
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             WHERE reversal_source.charged_to_passenger_id = ?
               AND reversal_source.reversal_amount > 0
               AND tc.charge_amount > 0
               AND tc.status IN ('approved', 'completed')";

        $unionParts = [$chargeSql, $paymentSql, $reversalSql];

        // Service cancellations may not exist in all environments; guard against missing table.
        $serviceTable = Database::fetch(
            "SELECT COUNT(*) AS table_exists
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'service_cancellations'"
        );
        if ((int)($serviceTable['table_exists'] ?? 0) > 0) {
            $serviceReversalSql =
                "SELECT sc.service_cancellation_id AS entry_id,
                        'reversal' AS entry_type,
                        sc.charge_amount AS amount,
                        sc.approved_at AS created_at,
                        sc.transaction_code AS txn_code,
                        NULL AS ticket_number,
                        NULL AS transaction_passenger_name,
                        'Service Cancellation' AS item_label,
                        NULL AS method_name,
                        bb.branch_name,
                        {$empNameConcat} AS cashier_name,
                        NULL AS reference_number,
                        NULL AS confirmation_status,
                        NULL AS confirmed_by,
                        sc.remarks AS notes,
                        NULL AS balance_before,
                        NULL AS balance_after,
                        NULL AS source_type,
                        NULL AS operation_type
                 FROM service_cancellations sc
                 LEFT JOIN cashier_sessions cs ON sc.cashier_session_id = cs.session_id
                 LEFT JOIN business_branches bb ON cs.branch_id = bb.branch_id
                 LEFT JOIN user_accounts ua ON sc.approved_by = ua.user_id
                 LEFT JOIN employees e ON ua.emp_id = e.emp_id
                 WHERE sc.passenger_id = ?
                   AND sc.charge_amount > 0
                   AND sc.status IN ('approved', 'completed')";
            $unionParts[] = $serviceReversalSql;
        }

        $wrappedUnion = '(' . implode(') UNION ALL (', $unionParts) . ')';
        $unionParamCount = count($unionParts);

        // Build filter SQL (outer WHERE)
        $outerWhere = [];
        if ($branch !== '') {
            $outerWhere[] = 'h.branch_name = ?';
        }
        if ($entryType !== '' && in_array($entryType, ['charge', 'payment', 'reversal'], true)) {
            $outerWhere[] = 'h.entry_type = ?';
        }
        $whereSql = $outerWhere ? 'WHERE ' . implode(' AND ', $outerWhere) : '';

        // Count total rows matching filters
        $countParams = array_pad([], $unionParamCount, $passengerId);
        if ($branch !== '') $countParams[] = $branch;
        if ($entryType !== '') $countParams[] = $entryType;
        $countSql = "SELECT COUNT(*) AS total FROM ({$wrappedUnion}) AS h {$whereSql}";
        $countRow = Database::fetch($countSql, $countParams);
        $total = (int)($countRow['total'] ?? 0);

        // Fetch page of entries
        $dataParams = $countParams;
        $dataSql = "SELECT * FROM ({$wrappedUnion}) AS h {$whereSql} ORDER BY h.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $entries = Database::fetchAll($dataSql, $dataParams);

        // Distinct branches for the filter dropdown
        $branchParams = array_pad([], $unionParamCount, $passengerId);
        $branchesSql = "SELECT DISTINCT h.branch_name FROM ({$wrappedUnion}) AS h WHERE h.branch_name IS NOT NULL AND h.branch_name != '' ORDER BY h.branch_name";
        $branchRows = Database::fetchAll($branchesSql, $branchParams);
        $branches = array_column($branchRows, 'branch_name');

        $totalPages = (int) ceil($total / $perPage);

        echo json_encode([
            'success' => true,
            'data' => [
                'entries' => $entries,
                'branches' => $branches,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages
                ]
            ]
        ]);
        return;
    } catch (Throwable $e) {
        error_log('Charges API GET error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        return;
    }
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

        // Apply collection with base/fee allocation
        $newBalances = ChargeService::applyCollection($passengerId, $applied);
        $balAfter = floatval($newBalances['new_balance']);

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

        // Create the confirmed bank receipt through the shared ledger when
        // confirmation is not required.
        if ($bankAcctId
            && in_array($pm['method_type'], ['BANK_TRANSFER', 'E_WALLET'], true)
            && !$requireConfirmation) {
            $bankMovement = BalanceLedgerService::bankMovement(
                (int) $bankAcctId,
                'RECEIPT',
                'IN',
                (float) $applied,
                'charge_payments',
                (int) $chargePaymentId,
                "Payment collection from passenger {$passengerId}",
                (int) $user['user_id'],
                'bank-receipt:charge:' . (int) $chargePaymentId,
                null,
                false,
                $notes
            );
            logActivity($user['user_id'], 'CREATE_BANK_TRANSACTION', 'BANK_TRANSACTIONS', $bankMovement['txn_code'],
                null, ['bank_account_id' => $bankAcctId, 'amount' => $applied, 'type' => 'RECEIPT']);
        }

        Database::connection()->commit();

        PusherService::triggerBranch((int) $branchId, 'charge.updated', [
            'branch_id' => (int) $branchId,
            'charge_payment_id' => (int) $chargePaymentId,
            'payment_code' => $payCode,
            'confirmation_status' => $confirmStatus,
        ]);

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

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $passengerId = $input['passenger_id'] ?? null;
    $mode = strtoupper($input['service_fee_mode'] ?? 'CUSTOMER');
    $companyPassengerId = !empty($input['company_passenger_id']) ? (int) $input['company_passenger_id'] : null;

    if (!$passengerId) {
        echo json_encode(['success' => false, 'error' => 'passenger_id is required.']);
        return;
    }

    $allowedModes = ['CUSTOMER', 'WAIVED', 'COMPANY'];
    if (!in_array($mode, $allowedModes, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid service_fee_mode.']);
        return;
    }

    if ($mode === 'COMPANY' && !$companyPassengerId) {
        echo json_encode(['success' => false, 'error' => 'company_passenger_id is required for COMPANY mode.']);
        return;
    }

    try {
        Database::connection()->beginTransaction();

        $chargeRow = Database::fetch("SELECT * FROM customer_charges WHERE passenger_id = :pid FOR UPDATE", ['pid' => $passengerId]);
        if (!$chargeRow) {
            // Create a charge record so the mode can be stored even if no balance yet
            Database::execute(
                "INSERT INTO customer_charges (passenger_id, total_charged, total_paid, balance, status, last_charge_date)
                 VALUES (:pid, 0, 0, 0, 'CLEAR', :now)",
                ['pid' => $passengerId, 'now' => date('Y-m-d H:i:s')]
            );
            $chargeRow = Database::fetch("SELECT * FROM customer_charges WHERE passenger_id = :pid FOR UPDATE", ['pid' => $passengerId]);
        }

        $effectiveDate = $mode === 'CUSTOMER' ? null : date('Y-m-d H:i:s');

        // When switching to COMPANY or WAIVED, reallocate any remaining customer fee balance
        // so the customer's outstanding balance reflects only what they actually owe.
        if (in_array($mode, ['COMPANY', 'WAIVED'], true) && $chargeRow) {
            $baseCharged = (float) ($chargeRow['base_charged'] ?? 0);
            $basePaid    = (float) ($chargeRow['base_paid'] ?? 0);
            $feeCharged  = (float) ($chargeRow['fee_charged'] ?? 0);
            $feePaid     = (float) ($chargeRow['fee_paid'] ?? 0);
            $totalPaid   = (float) ($chargeRow['total_paid'] ?? 0);
            $feeBalance  = round($feeCharged - $feePaid, 2);

            if ($feeBalance > 0) {
                if ($mode === 'COMPANY' && $companyPassengerId) {
                    // Move unpaid fee to the company/CEO account
                    ChargeService::addToCustomerCharge($companyPassengerId, $feeBalance, 0, $feeBalance);
                }
                // WAIVED: the remaining fee is simply removed from the customer record

                $newFeeCharged   = $feePaid;
                $newFeeBalance   = 0.00;
                $newTotalCharged = $baseCharged + $newFeeCharged;
                $newBalance      = $newTotalCharged - $totalPaid;
                $newBaseBalance  = $baseCharged - $basePaid;
                $newStatus       = $newBalance > 0 ? 'OUTSTANDING' : 'CLEAR';

                Database::execute(
                    "UPDATE customer_charges
                     SET fee_charged = :fee_charged,
                         fee_paid = :fee_paid,
                         fee_balance = :fee_balance,
                         base_balance = :base_balance,
                         total_charged = :total_charged,
                         balance = :balance,
                         status = :status,
                         updated_at = :updated_at
                     WHERE passenger_id = :pid",
                    [
                        'pid' => $passengerId,
                        'fee_charged' => $newFeeCharged,
                        'fee_paid' => $feePaid,
                        'fee_balance' => $newFeeBalance,
                        'base_balance' => $newBaseBalance,
                        'total_charged' => $newTotalCharged,
                        'balance' => $newBalance,
                        'status' => $newStatus,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );
            }
        }

        Database::execute(
            "UPDATE customer_charges
             SET service_fee_mode = :mode,
                 company_passenger_id = :company_pid,
                 exempt_effective_date = :eff_date,
                 updated_at = :updated_at
             WHERE passenger_id = :pid",
            [
                'pid' => $passengerId,
                'mode' => $mode,
                'company_pid' => $companyPassengerId,
                'eff_date' => $effectiveDate,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        );

        Database::connection()->commit();

        logActivity($user['user_id'], 'UPDATE_SERVICE_FEE_MODE', 'CUSTOMER_CHARGES', null,
            ['passenger_id' => $passengerId, 'old_mode' => $chargeRow['service_fee_mode'] ?? 'CUSTOMER'],
            ['passenger_id' => $passengerId, 'new_mode' => $mode, 'company_passenger_id' => $companyPassengerId, 'exempt_effective_date' => $effectiveDate]
        );

        echo json_encode(['success' => true, 'message' => 'Service fee mode updated.', 'service_fee_mode' => $mode]);
    } catch (Exception $e) {
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();
        }
        error_log('Update service fee mode error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to update service fee mode: ' . $e->getMessage()]);
    }
    return;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
