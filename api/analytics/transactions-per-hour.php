<?php
/**
 * Transactions per Hour API
 * Returns hourly transaction volume for the dashboard
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/AnalyticsFilter.php';

header('Content-Type: application/json');

try {
    $user = Auth::user();
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $filter = AnalyticsFilter::parse($_GET, $user);
    $branchScope = AnalyticsFilter::branchCondition($filter, 'po.branch_id', 'hourly_branch');
    $dateScope = AnalyticsFilter::dateCondition($filter, 'po.created_at', 'hourly_date');
    $branchWhere = 'AND ' . $branchScope['sql'];
    $branchParams = $branchScope['params'];
    $dateWhere = 'AND ' . $dateScope['sql'];
    $dateParams = $dateScope['params'];

    $isHourly = $filter['granularity'] === 'hourly';
    $isAnnual = $filter['granularity'] === 'annual';
    $isMonthly = $filter['granularity'] === 'monthly' || $isAnnual;
    $periodKeys = [];
    $labels = [];
    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');

    if ($isHourly) {
        for ($hour = 0; $hour < 24; $hour++) {
            $periodKeys[] = sprintf('%02d:00', $hour);
            $labels[] = sprintf('%02d:00', $hour);
        }
    } else {
        $cursor = new DateTimeImmutable($filter['start_date'], $timezone);
        $lastDate = new DateTimeImmutable($filter['end_date'], $timezone);
        if ($isAnnual) {
            $cursor = $cursor->setDate((int)$cursor->format('Y'), 1, 1);
            $lastDate = $lastDate->setDate((int)$lastDate->format('Y'), 1, 1);
        } elseif ($isMonthly) {
            $cursor = $cursor->modify('first day of this month');
            $lastDate = $lastDate->modify('first day of this month');
        }

        while ($cursor <= $lastDate) {
            if ($isAnnual) {
                $periodKeys[] = $cursor->format('Y');
                $labels[] = $cursor->format('Y');
                $cursor = $cursor->modify('+1 year');
            } elseif ($isMonthly) {
                $periodKeys[] = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $cursor = $cursor->modify('+1 month');
            } else {
                $periodKeys[] = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('M d');
                $cursor = $cursor->modify('+1 day');
            }
        }
    }

    $params = array_merge($branchParams, $dateParams);
    $data = [];

    if ($isHourly) {
        foreach ($periodKeys as $periodKey) {
            $result = Database::fetch(
                "SELECT COUNT(DISTINCT po.order_id) as count
                 FROM pos_orders po
                 WHERE po.status = 'completed'
                   $dateWhere
                   $branchWhere
                   AND HOUR(po.created_at) = :hour",
                array_merge($params, ['hour' => (int)substr($periodKey, 0, 2)])
            );
            $data[] = (int)($result['count'] ?? 0);
        }
    } else {
        $groupExpression = $isAnnual
            ? "DATE_FORMAT(po.created_at, '%Y')"
            : ($isMonthly ? "DATE_FORMAT(po.created_at, '%Y-%m')" : "DATE(po.created_at)");
        $rows = Database::fetchAll(
            "SELECT $groupExpression as period_key,
                    COUNT(DISTINCT po.order_id) as count
             FROM pos_orders po
             WHERE po.status = 'completed'
               $dateWhere
               $branchWhere
             GROUP BY $groupExpression
             ORDER BY period_key ASC",
            $params
        );
        $dataMap = [];
        foreach ($rows as $row) {
            $dataMap[(string)$row['period_key']] = (int)$row['count'];
        }
        foreach ($periodKeys as $periodKey) {
            $data[] = $dataMap[$periodKey] ?? 0;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $labels,
            'data' => $data,
            'filter' => AnalyticsFilter::responseMeta($filter)
        ]
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Transactions per Hour API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load transaction volume']);
}
