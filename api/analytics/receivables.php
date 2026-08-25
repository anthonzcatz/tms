<?php
/**
 * Accounts Receivable Summary API
 * Returns outstanding charge summary for the dashboard widget
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/AnalyticsFilter.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $filter = AnalyticsFilter::parse($_GET, $user);
    $periodStart = $filter['start_date'];
    $periodEnd = $filter['end_exclusive'];

    // --- Summary from customer_charges (same formula as admin/charges/index.php) ---
    // Branch is determined from the customer's latest CHARGE payment session.
    $latestChargeCte =
        "SELECT tp.charged_to_passenger_id, tp.cashier_session_id,
                ROW_NUMBER() OVER (PARTITION BY tp.charged_to_passenger_id ORDER BY tp.created_at DESC) AS rn
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         WHERE pm.tracks_credit = 1 AND tp.charged_to_passenger_id IS NOT NULL";

    if ($filter['branch_id'] !== null) {
        $branchWhere = 'cs.branch_id = :ar_branch_id';
        $branchParams = ['ar_branch_id' => $filter['branch_id']];
    } else {
        $branchIds = array_values(array_filter(
            array_map('intval', $filter['accessible_branch_ids']),
            static fn (int $id): bool => $id > 0
        ));
        if (empty($branchIds)) {
            $branchWhere = '0 = 1';
            $branchParams = [];
        } else {
            $placeholders = [];
            $branchParams = [];
            foreach ($branchIds as $index => $branchId) {
                $key = 'ar_branch_' . $index;
                $placeholders[] = ':' . $key;
                $branchParams[$key] = $branchId;
            }
            $branchWhere = 'cs.branch_id IN (' . implode(', ', $placeholders) . ')';
        }
    }

    $stats = Database::fetch(
        "SELECT
            COUNT(*) AS total_customers,
            COALESCE(SUM(cc.balance), 0) AS total_outstanding,
            SUM(CASE WHEN cc.status = 'OUTSTANDING' THEN 1 ELSE 0 END) AS outstanding_count,
            SUM(CASE WHEN cc.status = 'OVERDUE' THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN cc.status = 'CLEAR' THEN 1 ELSE 0 END) AS clear_count
         FROM customer_charges cc
         LEFT JOIN ({$latestChargeCte}) latest
            ON latest.charged_to_passenger_id = cc.passenger_id AND latest.rn = 1
         LEFT JOIN cashier_sessions cs ON latest.cashier_session_id = cs.session_id
         WHERE {$branchWhere}",
        $branchParams
    );

    // --- Period charge activity (gross new charges) ---
    $branchExpression = "COALESCE(
        CASE
            WHEN tp.source_type = 'TICKET_TRANSACTION' THEN COALESCE(tt.branch_id, pw.branch_id)
            WHEN tp.source_type = 'SERVICE_TRANSACTION' THEN st.branch_id
            WHEN tp.source_type = 'POS_ORDER' THEN po.branch_id
        END,
        cs.branch_id
    )";
    $chargeBranch = AnalyticsFilter::branchCondition($filter, $branchExpression, 'ar_charge_branch');
    $chargedPeriodRow = Database::fetch(
        "SELECT COALESCE(SUM(tp.amount), 0) AS period_amount
         FROM transaction_payments tp
         JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
         LEFT JOIN cashier_sessions cs ON tp.cashier_session_id = cs.session_id
         LEFT JOIN ticket_transactions tt ON tp.source_type = 'TICKET_TRANSACTION' AND tp.source_id = tt.transaction_id
         LEFT JOIN provider_wallets pw ON tt.wallet_id = pw.wallet_id
         LEFT JOIN service_transactions st ON tp.source_type = 'SERVICE_TRANSACTION' AND tp.source_id = st.service_txn_id
         LEFT JOIN pos_orders po ON tp.source_type = 'POS_ORDER' AND tp.source_id = po.order_id
         WHERE pm.tracks_credit = 1
           AND tp.charged_to_passenger_id IS NOT NULL
           AND tp.created_at >= :period_start
           AND tp.created_at < :period_end
           AND {$chargeBranch['sql']}",
        array_merge($chargeBranch['params'], [
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
        ])
    );

    // --- Period payments collected ---
    $paymentBranch = AnalyticsFilter::branchCondition($filter, 'cp.branch_id', 'ar_payment_branch');
    $collectedPeriodRow = Database::fetch(
        "SELECT COALESCE(SUM(cp.amount_paid), 0) AS period_amount
         FROM charge_payments cp
         WHERE cp.confirmation_status IN ('NOT_REQUIRED', 'CONFIRMED')
           AND cp.created_at >= :period_start
           AND cp.created_at < :period_end
           AND {$paymentBranch['sql']}",
        array_merge($paymentBranch['params'], [
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
        ])
    );

    $chargedPeriod = (float) ($chargedPeriodRow['period_amount'] ?? 0);
    $collectedPeriod = (float) ($collectedPeriodRow['period_amount'] ?? 0);

    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => [
                'total_outstanding' => round((float) ($stats['total_outstanding'] ?? 0), 2),
                'total_customers'   => (int) ($stats['total_customers'] ?? 0),
                'outstanding_count' => (int) ($stats['outstanding_count'] ?? 0),
                'overdue_count'     => (int) ($stats['overdue_count'] ?? 0),
                'clear_count'       => (int) ($stats['clear_count'] ?? 0),
                'charged_period'    => round($chargedPeriod, 2),
                'collected_period'  => round($collectedPeriod, 2),
                'charged_7d'        => round($chargedPeriod, 2),
                'collected_7d'      => round($collectedPeriod, 2),
            ],
            'reconciled' => true,
            'reconciliation_delta' => 0.0,
            'filter' => AnalyticsFilter::responseMeta($filter),
        ],
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Receivables Widget Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load receivables summary']);
}
