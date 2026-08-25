<?php
/**
 * Cashier Charges Report Controller
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

Auth::requireLogin();
$user = Auth::user();
$userRoleCode = $user['role_code'] ?? '';

if ($userRoleCode !== 'SUPER_ADMIN'
    && !Auth::canAccessModule('admin/charges/')
    && !Auth::canAccessModule('admin/charges/cashiers/')) {
    $message = 'You do not have permission to access Cashier Charges.';
    $defaultDashboard = BASE_URL . '/admin/dashboard';
    include dirname(dirname(dirname(__DIR__))) . '/admin/includes/access-denied.php';
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$isSuperAdmin = $userRoleCode === 'SUPER_ADMIN';
$userBranchValue = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
$allowedBranchIds = $isSuperAdmin
    ? null
    : array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) $userBranchValue)),
        static fn (int $branchId): bool => $branchId > 0
    )));

$parseDate = static function ($value, string $fallback): string {
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return $fallback;
    }

    return $date->format('Y-m-d');
};

$today = date('Y-m-d');
$filterDateFrom = $parseDate($_GET['date_from'] ?? '', $today);
$filterDateTo = $parseDate($_GET['date_to'] ?? '', $today);
$dateFilterNotice = '';
if ($filterDateFrom > $filterDateTo) {
    [$filterDateFrom, $filterDateTo] = [$filterDateTo, $filterDateFrom];
    $dateFilterNotice = 'Date From and Date To were swapped because Date From was later.';
}

$filterSearch = trim((string) ($_GET['search'] ?? ''));
$filterSessionStatus = strtoupper(trim((string) ($_GET['session_status'] ?? '')));
$filterCashier = trim((string) ($_GET['cashier'] ?? ''));
$filterCashier = ctype_digit($filterCashier) && (int) $filterCashier > 0 ? (int) $filterCashier : '';
$filterBranch = trim((string) ($_GET['branch'] ?? ''));
$filterBranch = ctype_digit($filterBranch) && (int) $filterBranch > 0 ? (int) $filterBranch : '';
$detailPage = max(1, (int) ($_GET['page'] ?? 1));
$detailPerPage = (int) ($_GET['per_page'] ?? 25);
if (!in_array($detailPerPage, [25, 50, 100], true)) {
    $detailPerPage = 25;
}

$validSessionStatuses = ['OPEN', 'CLOSED', 'RECONCILED'];
if (!in_array($filterSessionStatus, $validSessionStatuses, true)) {
    $filterSessionStatus = '';
}

$branchListWhere = ["status = 'active'"];
$branchListParams = [];
if ($allowedBranchIds !== null) {
    if (empty($allowedBranchIds)) {
        $branchListWhere[] = '1 = 0';
    } else {
        $branchPlaceholders = [];
        foreach ($allowedBranchIds as $index => $branchId) {
            $placeholder = ':branch_list_' . $index;
            $branchPlaceholders[] = $placeholder;
            $branchListParams['branch_list_' . $index] = $branchId;
        }
        $branchListWhere[] = 'branch_id IN (' . implode(', ', $branchPlaceholders) . ')';
    }
}
$branches = Database::fetchAll(
    'SELECT branch_id, branch_name FROM business_branches WHERE '
    . implode(' AND ', $branchListWhere)
    . ' ORDER BY branch_name ASC',
    $branchListParams
);
$availableBranchIds = array_map(static fn (array $branch): int => (int) $branch['branch_id'], $branches);
if ($filterBranch !== '' && !in_array((int) $filterBranch, $availableBranchIds, true)) {
    $filterBranch = '';
}

$voidCashierNameExpression = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', vrc_e.first_name, vrc_e.last_name)), ''), vrc_u.username, 'Unknown Cashier')";
$voidBranchNameExpression = "COALESCE(vbb.branch_name, 'Unassigned Branch')";
$voidDateExpression = 'COALESCE(tc.approved_at, tc.requested_at)';
$voidFrom = "
    FROM ticket_cancellations tc
    INNER JOIN ticket_transactions vtt ON vtt.transaction_id = tc.transaction_id
    LEFT JOIN user_accounts vrc_u ON vrc_u.user_id = tc.responsible_user_id
    LEFT JOIN employees vrc_e ON vrc_e.emp_id = vrc_u.emp_id
    LEFT JOIN cashier_sessions vrcs ON vrcs.session_id = tc.responsibility_cashier_session_id
    LEFT JOIN passenger_accounts vpa ON vpa.passenger_id = COALESCE(tc.passenger_id, vtt.passenger_id)
    LEFT JOIN ticket_providers v_operating_provider ON v_operating_provider.provider_id = vtt.provider_id
    LEFT JOIN ticket_providers v_main_provider
        ON v_main_provider.provider_id = COALESCE(v_operating_provider.parent_provider_id, v_operating_provider.provider_id)
    LEFT JOIN provider_ticket_variants v_ticket_variant ON v_ticket_variant.variant_id = vtt.variant_id
    LEFT JOIN business_branches vbb ON vbb.branch_id = vtt.branch_id
";

$voidScopeWhere = [
    "tc.operation_type = 'VOID'",
    "tc.responsibility = 'CASHIER'",
    'tc.responsible_user_id IS NOT NULL',
    "tc.status IN ('pending', 'approved', 'completed')",
    'COALESCE(tc.responsibility_amount, 0) > 0'
];
$voidScopeParams = [];
if ($allowedBranchIds !== null) {
    if (empty($allowedBranchIds)) {
        $voidScopeWhere[] = '1 = 0';
    } else {
        $voidScopePlaceholders = [];
        foreach ($allowedBranchIds as $index => $branchId) {
            $placeholder = ':void_scope_branch_' . $index;
            $voidScopePlaceholders[] = $placeholder;
            $voidScopeParams['void_scope_branch_' . $index] = $branchId;
        }
        $voidScopeWhere[] = 'vtt.branch_id IN (' . implode(', ', $voidScopePlaceholders) . ')';
    }
}
if ($filterBranch !== '') {
    $voidScopeWhere[] = 'vtt.branch_id = :void_branch_filter';
    $voidScopeParams['void_branch_filter'] = (int) $filterBranch;
}

$voidWhere = $voidScopeWhere;
$voidParams = $voidScopeParams;
$voidWhere[] = $voidDateExpression . ' >= :void_date_from';
$voidWhere[] = $voidDateExpression . ' < :void_date_to_exclusive';
$voidParams['void_date_from'] = $filterDateFrom . ' 00:00:00';
$voidParams['void_date_to_exclusive'] = (new DateTimeImmutable($filterDateTo))->modify('+1 day')->format('Y-m-d 00:00:00');

if ($filterCashier !== '') {
    $voidWhere[] = 'tc.responsible_user_id = :void_cashier_filter';
    $voidParams['void_cashier_filter'] = (int) $filterCashier;
}
if ($filterSessionStatus !== '') {
    $voidWhere[] = 'vrcs.status = :void_session_status_filter';
    $voidParams['void_session_status_filter'] = $filterSessionStatus;
}
if ($filterSearch !== '') {
    $voidSearchFields = [
        $voidCashierNameExpression . ' LIKE :void_search_cashier_name',
        'vrc_u.username LIKE :void_search_username',
        'vbb.branch_name LIKE :void_search_branch',
        'tc.transaction_code LIKE :void_search_transaction',
        'vtt.ticket_number LIKE :void_search_ticket_number'
    ];
    $voidWhere[] = '(' . implode(' OR ', $voidSearchFields) . ')';
    $voidSearchValue = '%' . $filterSearch . '%';
    $voidParams['void_search_cashier_name'] = $voidSearchValue;
    $voidParams['void_search_username'] = $voidSearchValue;
    $voidParams['void_search_branch'] = $voidSearchValue;
    $voidParams['void_search_transaction'] = $voidSearchValue;
    $voidParams['void_search_ticket_number'] = $voidSearchValue;
}

$voidWhereSql = implode(' AND ', $voidWhere);
$voidScopeWhereSql = implode(' AND ', $voidScopeWhere);
$refundWhere = array_map(
    static fn (string $condition): string => str_replace(':void_', ':refund_', $condition),
    $voidWhere
);
$refundWhere[0] = "tc.operation_type = 'REFUND'";
$refundWhereSql = implode(' AND ', $refundWhere);
$refundParams = [];
foreach ($voidParams as $key => $value) {
    $refundParams[str_replace('void_', 'refund_', $key)] = $value;
}
$refundScopeWhere = array_map(
    static fn (string $condition): string => str_replace(':void_', ':refund_', $condition),
    $voidScopeWhere
);
$refundScopeWhere[0] = "tc.operation_type = 'REFUND'";
$refundScopeWhereSql = implode(' AND ', $refundScopeWhere);
$refundScopeParams = [];
foreach ($voidScopeParams as $key => $value) {
    $refundScopeParams[str_replace('void_', 'refund_', $key)] = $value;
}

$cashierStatsParams = array_merge($voidParams, $refundParams);
$stats = Database::fetch(
    "SELECT
        COUNT(DISTINCT cashier_user_id) AS total_cashiers,
        COUNT(*) AS total_charges,
        COALESCE(SUM(amount), 0) AS total_amount
     FROM (
        SELECT tc.responsible_user_id AS cashier_user_id,
               COALESCE(tc.responsibility_amount, 0) AS amount
        {$voidFrom}
        WHERE {$voidWhereSql}
        UNION ALL
        SELECT tc.responsible_user_id AS cashier_user_id,
               COALESCE(tc.responsibility_amount, 0) AS amount
        {$voidFrom}
        WHERE {$refundWhereSql}
     ) cashier_responsibilities",
    $cashierStatsParams
) ?: [];

$voidStats = Database::fetch(
    "SELECT
        COUNT(DISTINCT tc.cancellation_id) AS void_count,
        COUNT(DISTINCT tc.responsible_user_id) AS void_cashiers,
        COALESCE(SUM(COALESCE(tc.void_fee, 0) + COALESCE(tc.void_service_fee, 0)), 0) AS void_amount
     {$voidFrom}
     WHERE {$voidWhereSql}",
    $voidParams
) ?: [];
$stats['void_count'] = (int) ($voidStats['void_count'] ?? 0);
$stats['void_cashiers'] = (int) ($voidStats['void_cashiers'] ?? 0);
$stats['void_amount'] = (float) ($voidStats['void_amount'] ?? 0);

$cashierCharges = [];

$voidCashierCharges = Database::fetchAll(
    "SELECT
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierNameExpression} AS cashier_name,
        vrc_u.username AS cashier_username,
        vtt.branch_id AS branch_id,
        {$voidBranchNameExpression} AS branch_name,
        COUNT(DISTINCT tc.cancellation_id) AS charge_count,
        COUNT(DISTINCT tc.cancellation_id) AS void_count,
        COALESCE(SUM(tc.void_fee), 0) AS void_amount,
        COALESCE(SUM(tc.void_service_fee), 0) AS service_amount,
        COALESCE(SUM(COALESCE(tc.void_fee, 0) + COALESCE(tc.void_service_fee, 0)), 0) AS void_total,
        MAX({$voidDateExpression}) AS last_void_at,
        COUNT(DISTINCT vrcs.session_id) AS session_count,
        GROUP_CONCAT(DISTINCT vrcs.status ORDER BY vrcs.status SEPARATOR ', ') AS session_statuses
     {$voidFrom}
     WHERE {$voidWhereSql}
     GROUP BY tc.responsible_user_id, vrc_e.first_name, vrc_e.last_name, vrc_u.username,
              vtt.branch_id, vbb.branch_name
     ORDER BY void_total DESC, last_void_at DESC, cashier_name ASC",
    $voidParams
);

$refundCashierCharges = Database::fetchAll(
    "SELECT
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierNameExpression} AS cashier_name,
        vrc_u.username AS cashier_username,
        vtt.branch_id AS branch_id,
        {$voidBranchNameExpression} AS branch_name,
        COUNT(DISTINCT tc.cancellation_id) AS charge_count,
        COALESCE(SUM(tc.responsibility_amount), 0) AS charge_amount,
        MAX({$voidDateExpression}) AS last_refund_at,
        COUNT(DISTINCT vrcs.session_id) AS session_count,
        GROUP_CONCAT(DISTINCT vrcs.status ORDER BY vrcs.status SEPARATOR ', ') AS session_statuses
     {$voidFrom}
     WHERE {$refundWhereSql}
     GROUP BY tc.responsible_user_id, vrc_e.first_name, vrc_e.last_name, vrc_u.username,
              vtt.branch_id, vbb.branch_name
     ORDER BY charge_amount DESC, last_refund_at DESC, cashier_name ASC",
    $refundParams
);

foreach ($cashierCharges as &$cashierCharge) {
    $cashierCharge['charge_amount'] = 0.0;
    $cashierCharge['void_count'] = 0;
    $cashierCharge['void_amount'] = 0.0;
    $cashierCharge['service_amount'] = 0.0;
    $cashierCharge['void_total'] = 0.0;
}
unset($cashierCharge);
$cashierChargeMap = [];
foreach ($cashierCharges as $cashierCharge) {
    $key = (int) $cashierCharge['cashier_user_id'] . ':' . (int) $cashierCharge['branch_id'];
    $cashierChargeMap[$key] = $cashierCharge;
}
foreach ($voidCashierCharges as $voidCashierCharge) {
    $key = (int) $voidCashierCharge['cashier_user_id'] . ':' . (int) $voidCashierCharge['branch_id'];
    if (!isset($cashierChargeMap[$key])) {
        $cashierChargeMap[$key] = [
            'cashier_user_id' => $voidCashierCharge['cashier_user_id'],
            'cashier_name' => $voidCashierCharge['cashier_name'],
            'cashier_username' => $voidCashierCharge['cashier_username'],
            'branch_id' => $voidCashierCharge['branch_id'],
            'branch_name' => $voidCashierCharge['branch_name'],
            'charge_count' => 0,
            'total_charged' => 0.0,
            'last_charge_at' => null,
            'session_count' => 0,
            'session_statuses' => '',
            'charge_amount' => 0.0,
            'void_count' => 0,
            'void_amount' => 0.0,
            'service_amount' => 0.0,
            'void_total' => 0.0,
        ];
    }
    $activity = &$cashierChargeMap[$key];
    $activity['charge_count'] += (int) $voidCashierCharge['charge_count'];
    $activity['void_count'] += (int) $voidCashierCharge['void_count'];
    $activity['void_amount'] += (float) $voidCashierCharge['void_amount'];
    $activity['service_amount'] += (float) $voidCashierCharge['service_amount'];
    $activity['void_total'] += (float) $voidCashierCharge['void_total'];
    $activity['session_count'] += (int) $voidCashierCharge['session_count'];
    $activityDates = array_filter([$activity['last_charge_at'] ?? null, $voidCashierCharge['last_void_at'] ?? null]);
    if ($activityDates) {
        $activity['last_charge_at'] = max($activityDates);
    }
    $activityStatuses = array_values(array_unique(array_filter(array_merge(
        array_map('trim', explode(',', (string) ($activity['session_statuses'] ?? ''))),
        array_map('trim', explode(',', (string) ($voidCashierCharge['session_statuses'] ?? '')))
    ))));
    $activity['session_statuses'] = implode(', ', $activityStatuses);
    unset($activity);
}
foreach ($refundCashierCharges as $refundCashierCharge) {
    $key = (int) $refundCashierCharge['cashier_user_id'] . ':' . (int) $refundCashierCharge['branch_id'];
    if (!isset($cashierChargeMap[$key])) {
        $cashierChargeMap[$key] = [
            'cashier_user_id' => $refundCashierCharge['cashier_user_id'],
            'cashier_name' => $refundCashierCharge['cashier_name'],
            'cashier_username' => $refundCashierCharge['cashier_username'],
            'branch_id' => $refundCashierCharge['branch_id'],
            'branch_name' => $refundCashierCharge['branch_name'],
            'charge_count' => 0,
            'total_charged' => 0.0,
            'last_charge_at' => null,
            'session_count' => 0,
            'session_statuses' => '',
            'charge_amount' => 0.0,
            'void_count' => 0,
            'void_amount' => 0.0,
            'service_amount' => 0.0,
            'void_total' => 0.0,
        ];
    }
    $activity = &$cashierChargeMap[$key];
    $activity['charge_count'] += (int) $refundCashierCharge['charge_count'];
    $activity['charge_amount'] += (float) $refundCashierCharge['charge_amount'];
    $activity['session_count'] += (int) $refundCashierCharge['session_count'];
    $activityDates = array_filter([$activity['last_charge_at'] ?? null, $refundCashierCharge['last_refund_at'] ?? null]);
    if ($activityDates) {
        $activity['last_charge_at'] = max($activityDates);
    }
    $activityStatuses = array_values(array_unique(array_filter(array_merge(
        array_map('trim', explode(',', (string) ($activity['session_statuses'] ?? ''))),
        array_map('trim', explode(',', (string) ($refundCashierCharge['session_statuses'] ?? '')))
    ))));
    $activity['session_statuses'] = implode(', ', $activityStatuses);
    unset($activity);
}
$cashierCharges = array_values($cashierChargeMap);
usort($cashierCharges, static function (array $left, array $right): int {
    $leftTotal = (float) $left['total_charged']
        + (float) ($left['charge_amount'] ?? 0)
        + (float) $left['void_total'];
    $rightTotal = (float) $right['total_charged']
        + (float) ($right['charge_amount'] ?? 0)
        + (float) $right['void_total'];
    return $rightTotal <=> $leftTotal
        ?: strcmp((string) ($right['last_charge_at'] ?? ''), (string) ($left['last_charge_at'] ?? ''))
        ?: strcmp((string) $left['cashier_name'], (string) $right['cashier_name']);
});

$allCashierCharges = [];

$voidAllCashierCharges = Database::fetchAll(
    "SELECT
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierNameExpression} AS cashier_name,
        vrc_u.username AS cashier_username,
        GROUP_CONCAT(DISTINCT {$voidBranchNameExpression} ORDER BY {$voidBranchNameExpression} SEPARATOR ', ') AS branch_names,
        COUNT(DISTINCT tc.cancellation_id) AS charge_count,
        COUNT(DISTINCT tc.cancellation_id) AS void_count,
        COALESCE(SUM(tc.void_fee), 0) AS void_amount,
        COALESCE(SUM(tc.void_service_fee), 0) AS service_amount,
        COALESCE(SUM(COALESCE(tc.void_fee, 0) + COALESCE(tc.void_service_fee, 0)), 0) AS void_total,
        MAX({$voidDateExpression}) AS last_void_at
     {$voidFrom}
     WHERE {$voidScopeWhereSql}
     GROUP BY tc.responsible_user_id, vrc_e.first_name, vrc_e.last_name, vrc_u.username
     ORDER BY cashier_name ASC",
    $voidScopeParams
);

$refundAllCashierCharges = Database::fetchAll(
    "SELECT
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierNameExpression} AS cashier_name,
        vrc_u.username AS cashier_username,
        GROUP_CONCAT(DISTINCT {$voidBranchNameExpression} ORDER BY {$voidBranchNameExpression} SEPARATOR ', ') AS branch_names,
        COUNT(DISTINCT tc.cancellation_id) AS charge_count,
        COALESCE(SUM(tc.responsibility_amount), 0) AS charge_amount,
        MAX({$voidDateExpression}) AS last_refund_at
     {$voidFrom}
     WHERE {$refundScopeWhereSql}
     GROUP BY tc.responsible_user_id, vrc_e.first_name, vrc_e.last_name, vrc_u.username
     ORDER BY cashier_name ASC",
    $refundScopeParams
);

foreach ($allCashierCharges as &$allCashierCharge) {
    $allCashierCharge['charge_amount'] = 0.0;
    $allCashierCharge['void_count'] = 0;
    $allCashierCharge['void_amount'] = 0.0;
    $allCashierCharge['service_amount'] = 0.0;
    $allCashierCharge['void_total'] = 0.0;
}
unset($allCashierCharge);
$allCashierChargeMap = [];
foreach ($allCashierCharges as $allCashierCharge) {
    $allCashierChargeMap[(int) $allCashierCharge['cashier_user_id']] = $allCashierCharge;
}
foreach ($voidAllCashierCharges as $voidAllCashierCharge) {
    $cashierUserId = (int) $voidAllCashierCharge['cashier_user_id'];
    if (!isset($allCashierChargeMap[$cashierUserId])) {
        $allCashierChargeMap[$cashierUserId] = [
            'cashier_user_id' => $voidAllCashierCharge['cashier_user_id'],
            'cashier_name' => $voidAllCashierCharge['cashier_name'],
            'cashier_username' => $voidAllCashierCharge['cashier_username'],
            'branch_names' => '',
            'charge_count' => 0,
            'total_charged' => 0.0,
            'last_charge_at' => null,
            'charge_amount' => 0.0,
            'void_count' => 0,
            'void_amount' => 0.0,
            'service_amount' => 0.0,
            'void_total' => 0.0,
        ];
    }
    $activity = &$allCashierChargeMap[$cashierUserId];
    $activity['charge_count'] += (int) $voidAllCashierCharge['charge_count'];
    $activity['void_count'] += (int) $voidAllCashierCharge['void_count'];
    $activity['void_amount'] += (float) $voidAllCashierCharge['void_amount'];
    $activity['service_amount'] += (float) $voidAllCashierCharge['service_amount'];
    $activity['void_total'] += (float) $voidAllCashierCharge['void_total'];
    $activityBranches = array_values(array_unique(array_filter(array_merge(
        array_map('trim', explode(',', (string) ($activity['branch_names'] ?? ''))),
        array_map('trim', explode(',', (string) ($voidAllCashierCharge['branch_names'] ?? '')))
    ))));
    $activity['branch_names'] = implode(', ', $activityBranches);
    $activityDates = array_filter([$activity['last_charge_at'] ?? null, $voidAllCashierCharge['last_void_at'] ?? null]);
    if ($activityDates) {
        $activity['last_charge_at'] = max($activityDates);
    }
    unset($activity);
}
foreach ($refundAllCashierCharges as $refundAllCashierCharge) {
    $cashierUserId = (int) $refundAllCashierCharge['cashier_user_id'];
    if (!isset($allCashierChargeMap[$cashierUserId])) {
        $allCashierChargeMap[$cashierUserId] = [
            'cashier_user_id' => $refundAllCashierCharge['cashier_user_id'],
            'cashier_name' => $refundAllCashierCharge['cashier_name'],
            'cashier_username' => $refundAllCashierCharge['cashier_username'],
            'branch_names' => '',
            'charge_count' => 0,
            'total_charged' => 0.0,
            'last_charge_at' => null,
            'charge_amount' => 0.0,
            'void_count' => 0,
            'void_amount' => 0.0,
            'service_amount' => 0.0,
            'void_total' => 0.0,
        ];
    }
    $activity = &$allCashierChargeMap[$cashierUserId];
    $activity['charge_count'] += (int) $refundAllCashierCharge['charge_count'];
    $activity['charge_amount'] += (float) $refundAllCashierCharge['charge_amount'];
    $activityBranches = array_values(array_unique(array_filter(array_merge(
        array_map('trim', explode(',', (string) ($activity['branch_names'] ?? ''))),
        array_map('trim', explode(',', (string) ($refundAllCashierCharge['branch_names'] ?? '')))
    ))));
    $activity['branch_names'] = implode(', ', $activityBranches);
    $activityDates = array_filter([$activity['last_charge_at'] ?? null, $refundAllCashierCharge['last_refund_at'] ?? null]);
    if ($activityDates) {
        $activity['last_charge_at'] = max($activityDates);
    }
    unset($activity);
}
$allCashierCharges = array_values($allCashierChargeMap);
usort($allCashierCharges, static fn (array $left, array $right): int => strcmp((string) $left['cashier_name'], (string) $right['cashier_name']));

$unionText = static fn (string $expression): string =>
    "CONVERT(({$expression}) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
$unionNull = 'CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci';
$voidEntryType = $unionText("'void_responsibility'");
$refundEntryType = $unionText("'refund_responsibility'");
$voidSourceType = $unionText("'TICKET_TRANSACTION'");
$voidCashierName = $unionText($voidCashierNameExpression);
$voidCashierUsername = $unionText('vrc_u.username');
$voidBranchName = $unionText($voidBranchNameExpression);
$voidTransactionCode = $unionText('tc.transaction_code');
$voidTicketNumber = $unionText('vtt.ticket_number');
$voidCustomerName = $unionText('vpa.fullname');
$voidMainProviderName = $unionText('COALESCE(v_main_provider.provider_name, v_operating_provider.provider_name)');
$voidTicketVariantName = $unionText('v_ticket_variant.variant_name');
$voidTransactionType = $unionText("'VOID / CASHIER RESPONSIBILITY'");
$refundTransactionType = $unionText("'REFUND / CASHIER RESPONSIBILITY'");
$voidSessionCode = $unionText('vrcs.session_code');
$voidSessionStatus = $unionText('vrcs.status');
$voidResponsibilityStatus = $unionText('tc.status');
$voidSettlementStatus = $unionText("(SELECT ta.settlement_status
         FROM ticket_adjustments ta
         WHERE ta.cancellation_id = tc.cancellation_id
         ORDER BY ta.created_at DESC
         LIMIT 1)");

$voidEntrySelect = "SELECT
        tc.cancellation_id AS entry_id,
        {$voidEntryType} AS entry_type,
        tc.responsibility_amount AS amount,
        {$voidDateExpression} AS created_at,
        {$voidSourceType} AS source_type,
        {$unionNull} AS confirmation_status,
        {$unionNull} AS method_name,
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierName} AS cashier_name,
        {$voidCashierUsername} AS cashier_username,
        {$voidBranchName} AS branch_name,
        {$voidTransactionCode} AS transaction_code,
        {$voidTicketNumber} AS ticket_number,
        {$voidCustomerName} AS customer_name,
        {$voidMainProviderName} AS main_provider_name,
        {$voidTicketVariantName} AS ticket_variant_name,
        {$voidTransactionType} AS transaction_type,
        {$voidSessionCode} AS session_code,
        {$voidSessionStatus} AS session_status,
        {$voidResponsibilityStatus} AS responsibility_status,
        {$voidSettlementStatus} AS settlement_status
     {$voidFrom}
     WHERE {$voidWhereSql}";

$refundEntrySelect = "SELECT
        tc.cancellation_id AS entry_id,
        {$refundEntryType} AS entry_type,
        tc.responsibility_amount AS amount,
        {$voidDateExpression} AS created_at,
        {$voidSourceType} AS source_type,
        {$unionNull} AS confirmation_status,
        {$unionNull} AS method_name,
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierName} AS cashier_name,
        {$voidCashierUsername} AS cashier_username,
        {$voidBranchName} AS branch_name,
        {$voidTransactionCode} AS transaction_code,
        {$voidTicketNumber} AS ticket_number,
        {$voidCustomerName} AS customer_name,
        {$voidMainProviderName} AS main_provider_name,
        {$voidTicketVariantName} AS ticket_variant_name,
        {$refundTransactionType} AS transaction_type,
        {$voidSessionCode} AS session_code,
        {$voidSessionStatus} AS session_status,
        {$voidResponsibilityStatus} AS responsibility_status,
        {$voidSettlementStatus} AS settlement_status
     {$voidFrom}
     WHERE {$refundWhereSql}";

$chargeEntryUnionSql = $voidEntrySelect . ' UNION ALL ' . $refundEntrySelect;
$chargeEntryParams = array_merge($voidParams, $refundParams);
$detailCountRow = Database::fetch(
    "SELECT COUNT(*) AS total FROM ({$chargeEntryUnionSql}) AS cashier_charge_entries",
    $chargeEntryParams
) ?: [];
$detailCount = (int) ($detailCountRow['total'] ?? 0);
$detailTotalPages = max(1, (int) ceil($detailCount / $detailPerPage));
if ($detailPage > $detailTotalPages) {
    $detailPage = $detailTotalPages;
}
$detailOffset = ($detailPage - 1) * $detailPerPage;

$chargeEntries = Database::fetchAll(
    "SELECT *
     FROM ({$chargeEntryUnionSql}) AS cashier_charge_entries
     ORDER BY created_at DESC, entry_id DESC
     LIMIT {$detailPerPage} OFFSET {$detailOffset}",
    $chargeEntryParams
);

$cashierOptions = [];

$voidCashierOptions = Database::fetchAll(
    "SELECT DISTINCT
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierNameExpression} AS cashier_name
     {$voidFrom}
     WHERE {$voidScopeWhereSql}
     ORDER BY cashier_name ASC",
    $voidScopeParams
);

$refundCashierOptions = Database::fetchAll(
    "SELECT DISTINCT
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierNameExpression} AS cashier_name
     {$voidFrom}
     WHERE {$refundScopeWhereSql}
     ORDER BY cashier_name ASC",
    $refundScopeParams
);
$cashierOptionMap = [];
foreach (array_merge($cashierOptions, $voidCashierOptions, $refundCashierOptions) as $cashierOption) {
    $cashierOptionMap[(int) $cashierOption['cashier_user_id']] = $cashierOption;
}
$cashierOptions = array_values($cashierOptionMap);
usort($cashierOptions, static fn (array $left, array $right): int => strcmp((string) $left['cashier_name'], (string) $right['cashier_name']));

$filterValues = [
    'date_from' => $filterDateFrom,
    'date_to' => $filterDateTo,
    'branch' => $filterBranch,
    'cashier' => $filterCashier,
    'session_status' => $filterSessionStatus,
    'search' => $filterSearch,
    'per_page' => $detailPerPage
];

include __DIR__ . '/views/index.php';
