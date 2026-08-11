<?php
/**
 * Provider Wallet Summary API
 * Returns wallet balances grouped by provider for the dashboard widget
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';

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

    $userRoleCode  = $user['role_code'] ?? '';
    $userBranchId  = $user['branch_id'] ?? null;

    // Optional encoded branch filter from request
    $filterBranchIdRaw = isset($_GET['branch_id']) && $_GET['branch_id'] !== ''
        ? $_GET['branch_id']
        : null;
    $filterBranchId = null;

    if ($filterBranchIdRaw !== null) {
        $filterBranchId = IdEncoder::decode($filterBranchIdRaw);
        if ($filterBranchId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid branch ID']);
            exit;
        }
    }

    // Build branch restriction
    $branchWhere  = '';
    $branchParams = [];

    if ($filterBranchId) {
        // Check if user has access to this branch
        if ($userRoleCode === 'SUPER_ADMIN') {
            // SUPER_ADMIN can access any branch
            $branchWhere  = "AND pw.branch_id = ?";
            $branchParams = [$filterBranchId];
        } elseif ($userBranchId) {
            // Non-SUPER_ADMIN can only filter within their allowed branches
            $branchIds = array_map('trim', explode(',', $userBranchId));
            $allowedBranchIds = array_map('intval', array_filter($branchIds, function ($id) {
                return $id !== '';
            }));
            if (in_array((int)$filterBranchId, $allowedBranchIds, true)) {
                // User has access to this specific branch
                $branchWhere  = "AND pw.branch_id = ?";
                $branchParams = [$filterBranchId];
            } else {
                // User doesn't have access to this branch, use their allowed branches
                $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
                $branchWhere  = "AND pw.branch_id IN ($placeholders)";
                $branchParams = $branchIds;
            }
        }
    } elseif ($userRoleCode !== 'SUPER_ADMIN' && $userBranchId) {
        // No specific branch selected, use user's branch restriction
        $branchIds    = array_map('trim', explode(',', $userBranchId));
        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $branchWhere  = "AND pw.branch_id IN ($placeholders)";
        $branchParams = $branchIds;
    }

    // Total wallet balance across all accessible wallets
    $totals = Database::fetch(
        "SELECT
            COUNT(*)                        AS wallet_count,
            COALESCE(SUM(pw.current_balance), 0) AS total_balance,
            COUNT(CASE WHEN pw.current_balance <= 0 THEN 1 END) AS low_balance_count
         FROM provider_wallets pw
         WHERE pw.status = 'active'
         $branchWhere",
        $branchParams
    );

    // Per-provider summary
    $providers = Database::fetchAll(
        "SELECT
            tp.provider_id,
            tp.provider_name,
            COUNT(pw.wallet_id)                  AS branch_count,
            COALESCE(SUM(pw.current_balance), 0) AS total_balance,
            MIN(pw.current_balance)              AS min_balance
         FROM provider_wallets pw
         LEFT JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
         WHERE pw.status = 'active'
         $branchWhere
         GROUP BY tp.provider_id, tp.provider_name
         ORDER BY total_balance DESC
         LIMIT 8",
        $branchParams
    );

    // Per-branch breakdown (for stacked bar chart — branch on X, provider as series)
    // Return top 6 branches by total balance
    $branches = Database::fetchAll(
        "SELECT
            bb.branch_id,
            bb.branch_code,
            bb.branch_name,
            tp.provider_name,
            COALESCE(pw.current_balance, 0) AS balance
         FROM provider_wallets pw
         INNER JOIN ticket_providers tp ON pw.provider_id = tp.provider_id
         INNER JOIN business_branches bb ON pw.branch_id = bb.branch_id
         WHERE pw.status = 'active'
         $branchWhere
         ORDER BY bb.branch_name, tp.provider_name",
        $branchParams
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
        ],
    ]);

} catch (Exception $e) {
    error_log('Wallet Summary Widget Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
