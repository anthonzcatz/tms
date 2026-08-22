<?php
/**
 * Provider Wallet Summary API
 * Returns wallet balances grouped by provider for the dashboard widget
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
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
    $branchScope = AnalyticsFilter::branchCondition($filter, 'pw.branch_id', 'wallet_branch');
    $branchWhere = 'AND ' . $branchScope['sql'];
    $branchParams = $branchScope['params'];
    $isHistorical = $filter['end_date'] < date('Y-m-d');
    $walletHistoryJoin = $isHistorical
        ? "LEFT JOIN (
                SELECT wt.wallet_id,
                       SUBSTRING_INDEX(GROUP_CONCAT(CAST(wt.balance_after AS CHAR)
                           ORDER BY wt.created_at DESC, wt.wallet_txn_id DESC SEPARATOR ','), ',', 1) AS balance_as_of
                FROM wallet_transactions wt
                WHERE wt.created_at < :wallet_ledger_as_of
                GROUP BY wt.wallet_id
           ) ws ON ws.wallet_id = pw.wallet_id"
        : '';
    $balanceColumn = $isHistorical ? 'COALESCE(ws.balance_as_of, 0)' : 'pw.current_balance';
    $asOfWhere = $isHistorical ? ' AND pw.created_at < :wallet_outer_as_of' : '';
    $snapshotParams = $isHistorical ? ['wallet_ledger_as_of' => $filter['end_exclusive']] : [];
    $outerParams = $isHistorical ? ['wallet_outer_as_of' => $filter['end_exclusive']] : [];

    // Total wallet balance across all accessible wallets
    $totals = Database::fetch(
        "SELECT
            COUNT(*) AS wallet_count,
            COALESCE(SUM($balanceColumn), 0) AS total_balance,
            COUNT(CASE WHEN $balanceColumn <= 0 THEN 1 END) AS low_balance_count
         FROM provider_wallets pw
         $walletHistoryJoin
         WHERE pw.status = 'active'
         $asOfWhere
         $branchWhere",
        array_merge($branchParams, $snapshotParams, $outerParams)
    );

    // Per-provider summary
    $providers = Database::fetchAll(
        "SELECT
            tp.provider_id,
            tp.provider_name,
            COUNT(pw.wallet_id) AS branch_count,
            COALESCE(SUM($balanceColumn), 0) AS total_balance,
            MIN($balanceColumn) AS min_balance
         FROM provider_wallets pw
         $walletHistoryJoin
         LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
         WHERE pw.status = 'active'
         $asOfWhere
         $branchWhere
         GROUP BY tp.provider_id, tp.provider_name
         ORDER BY total_balance DESC
         LIMIT 8",
        array_merge($branchParams, $snapshotParams, $outerParams)
    );

    // Per-branch breakdown (for stacked bar chart — branch on X, provider as series)
    // Return top 6 branches by total balance
    $branches = Database::fetchAll(
        "SELECT
            bb.branch_id,
            bb.branch_code,
            bb.branch_name,
            tp.provider_name,
            COALESCE($balanceColumn, 0) AS balance
         FROM provider_wallets pw
         $walletHistoryJoin
         INNER JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
         INNER JOIN business_branches bb ON pw.branch_id = bb.branch_id
         WHERE pw.status = 'active'
         $asOfWhere
         $branchWhere
         ORDER BY bb.branch_name, tp.provider_name",
        array_merge($branchParams, $snapshotParams, $outerParams)
    );

    // Calculate branch totals and sort, then limit to top 6
    $branchTotals = [];
    foreach ($branches as $b) {
        $id = $b['branch_id'];
        if (!isset($branchTotals[$id])) {
            $branchTotals[$id] = ['name' => $b['branch_name'], 'total' => 0];
        }
        $branchTotals[$id]['total'] += $b['balance'];
    }
    uasort($branchTotals, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    $topBranchIds = array_slice(array_keys($branchTotals), 0, 6, true);

    // Filter branches to only include top 6
    $branches = array_filter($branches, function($b) use ($topBranchIds) {
        return in_array($b['branch_id'], $topBranchIds);
    });
    $branches = array_values($branches);

    echo json_encode([
        'success' => true,
        'data'    => [
            'total_balance'     => floatval($totals['total_balance'] ?? 0),
            'wallet_count'      => intval($totals['wallet_count'] ?? 0),
            'low_balance_count' => intval($totals['low_balance_count'] ?? 0),
            'providers'         => array_map(function ($p) {
                return [
                    'provider_name'  => $p['provider_name'],
                    'branch_count'   => intval($p['branch_count']),
                    'total_balance'  => floatval($p['total_balance']),
                    'min_balance'    => floatval($p['min_balance']),
                ];
            }, $providers),
            'branches'          => array_map(function ($b) {
                return [
                    'branch_id'     => intval($b['branch_id']),
                    'branch_code'   => $b['branch_code'],
                    'branch_name'   => $b['branch_name'],
                    'provider_name' => $b['provider_name'],
                    'balance'       => floatval($b['balance']),
                ];
            }, $branches),
            'filter'            => AnalyticsFilter::responseMeta($filter),
            'as_of'             => $filter['end_date'],
        ],
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Wallet Summary Widget Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load wallet summary']);
}
