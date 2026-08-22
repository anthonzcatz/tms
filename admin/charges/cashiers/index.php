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
$filterSource = strtoupper(trim((string) ($_GET['source'] ?? '')));
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

$validSources = ['TICKET_TRANSACTION', 'SERVICE_TRANSACTION', 'POS_ORDER'];
if (!in_array($filterSource, $validSources, true)) {
    $filterSource = '';
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

$cashierIdExpression = 'COALESCE(cs.cashier_user_id, tp.created_by)';
$cashierNameExpression = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', ce.first_name, ce.last_name)), ''), cua.username, 'Unknown Cashier')";
$branchIdExpression = 'COALESCE(cs.branch_id, tt.branch_id, st.branch_id, po.branch_id)';
$branchNameExpression = "COALESCE(bb.branch_name, 'Unassigned Branch')";
$chargeFrom = "
    FROM transaction_payments tp
    INNER JOIN payment_methods pm
        ON pm.method_id = tp.payment_method_id
       AND pm.tracks_credit = 1
    LEFT JOIN cashier_sessions cs ON cs.session_id = tp.cashier_session_id
    LEFT JOIN user_accounts cua ON cua.user_id = {$cashierIdExpression}
    LEFT JOIN employees ce ON ce.emp_id = cua.emp_id
    LEFT JOIN ticket_transactions tt
        ON tp.source_type = 'TICKET_TRANSACTION'
       AND tp.source_id = tt.transaction_id
    LEFT JOIN service_transactions st
        ON tp.source_type = 'SERVICE_TRANSACTION'
       AND tp.source_id = st.service_txn_id
    LEFT JOIN pos_orders po
        ON tp.source_type = 'POS_ORDER'
       AND tp.source_id = po.order_id
    LEFT JOIN service_types stype ON stype.service_type_id = st.service_type_id
    LEFT JOIN passenger_accounts charge_pa ON charge_pa.passenger_id = tp.charged_to_passenger_id
    LEFT JOIN passenger_accounts ticket_pa ON ticket_pa.passenger_id = tt.passenger_id
    LEFT JOIN passenger_accounts service_pa ON service_pa.passenger_id = st.passenger_id
    LEFT JOIN business_branches bb ON bb.branch_id = {$branchIdExpression}
";

$voidCashierNameExpression = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', vrc_e.first_name, vrc_e.last_name)), ''), vrc_u.username, 'Unknown Cashier')";
$voidBranchNameExpression = "COALESCE(vbb.branch_name, 'Unassigned Branch')";
$voidDateExpression = 'COALESCE(tc.approved_at, tc.requested_at)';
$voidFrom = "
    FROM ticket_cancellations tc
    INNER JOIN ticket_transactions vtt ON vtt.transaction_id = tc.transaction_id
    LEFT JOIN user_accounts vrc_u ON vrc_u.user_id = tc.responsible_user_id
    LEFT JOIN employees vrc_e ON vrc_e.emp_id = vrc_u.emp_id
    LEFT JOIN cashier_sessions vrcs ON vrcs.session_id = tc.responsibility_cashier_session_id
    LEFT JOIN passenger_accounts vpa ON vpa.passenger_id = tc.passenger_id
    LEFT JOIN business_branches vbb ON vbb.branch_id = vtt.branch_id
";

$scopeWhere = ['tp.amount > 0'];
$scopeParams = [];
if ($allowedBranchIds !== null) {
    if (empty($allowedBranchIds)) {
        $scopeWhere[] = '1 = 0';
    } else {
        $scopePlaceholders = [];
        foreach ($allowedBranchIds as $index => $branchId) {
            $placeholder = ':scope_branch_' . $index;
            $scopePlaceholders[] = $placeholder;
            $scopeParams['scope_branch_' . $index] = $branchId;
        }
        $scopeWhere[] = $branchIdExpression . ' IN (' . implode(', ', $scopePlaceholders) . ')';
    }
}
if ($filterBranch !== '') {
    $scopeWhere[] = $branchIdExpression . ' = :branch_filter';
    $scopeParams['branch_filter'] = (int) $filterBranch;
}

$where = $scopeWhere;
$params = $scopeParams;
$where[] = 'tp.created_at >= :date_from';
$where[] = 'tp.created_at < :date_to_exclusive';
$params['date_from'] = $filterDateFrom . ' 00:00:00';
$params['date_to_exclusive'] = (new DateTimeImmutable($filterDateTo))->modify('+1 day')->format('Y-m-d 00:00:00');

if ($filterCashier !== '') {
    $where[] = $cashierIdExpression . ' = :cashier_filter';
    $params['cashier_filter'] = (int) $filterCashier;
}
if ($filterSource !== '') {
    $where[] = 'tp.source_type = :source_filter';
    $params['source_filter'] = $filterSource;
}
if ($filterSessionStatus !== '') {
    $where[] = 'cs.status = :session_status_filter';
    $params['session_status_filter'] = $filterSessionStatus;
}
if ($filterSearch !== '') {
    $searchFields = [
        $cashierNameExpression . ' LIKE :search_cashier_name',
        'cua.username LIKE :search_username',
        'bb.branch_name LIKE :search_branch',
        'charge_pa.fullname LIKE :search_charge_account',
        'ticket_pa.fullname LIKE :search_ticket_passenger',
        'service_pa.fullname LIKE :search_service_passenger',
        'tt.transaction_code LIKE :search_ticket_code',
        'st.transaction_code LIKE :search_service_code',
        'po.order_code LIKE :search_order_code'
    ];
    $where[] = '(' . implode(' OR ', $searchFields) . ')';
    $searchValue = '%' . $filterSearch . '%';
    $params['search_cashier_name'] = $searchValue;
    $params['search_username'] = $searchValue;
    $params['search_branch'] = $searchValue;
    $params['search_charge_account'] = $searchValue;
    $params['search_ticket_passenger'] = $searchValue;
    $params['search_service_passenger'] = $searchValue;
    $params['search_ticket_code'] = $searchValue;
    $params['search_service_code'] = $searchValue;
    $params['search_order_code'] = $searchValue;
}

$whereSql = implode(' AND ', $where);

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
if ($filterSource !== '' && $filterSource !== 'TICKET_TRANSACTION') {
    $voidWhere[] = '1 = 0';
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
        'vpa.fullname LIKE :void_search_passenger',
        'tc.transaction_code LIKE :void_search_transaction',
        'vtt.ticket_number LIKE :void_search_ticket_number'
    ];
    $voidWhere[] = '(' . implode(' OR ', $voidSearchFields) . ')';
    $voidSearchValue = '%' . $filterSearch . '%';
    $voidParams['void_search_cashier_name'] = $voidSearchValue;
    $voidParams['void_search_username'] = $voidSearchValue;
    $voidParams['void_search_branch'] = $voidSearchValue;
    $voidParams['void_search_passenger'] = $voidSearchValue;
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

$stats = Database::fetch(
    "SELECT
        COUNT(DISTINCT {$cashierIdExpression}) AS total_cashiers,
        COUNT(DISTINCT tp.payment_id) AS total_charges,
        COUNT(DISTINCT tp.charged_to_passenger_id) AS total_customers,
        COALESCE(SUM(tp.amount), 0) AS total_amount
     {$chargeFrom}
     WHERE {$whereSql}",
    $params
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

$cashierCharges = Database::fetchAll(
    "SELECT
        {$cashierIdExpression} AS cashier_user_id,
        {$cashierNameExpression} AS cashier_name,
        cua.username AS cashier_username,
        {$branchIdExpression} AS branch_id,
        {$branchNameExpression} AS branch_name,
        COUNT(DISTINCT tp.payment_id) AS charge_count,
        COUNT(DISTINCT tp.charged_to_passenger_id) AS customer_count,
        COALESCE(SUM(tp.amount), 0) AS total_charged,
        MAX(tp.created_at) AS last_charge_at,
        COUNT(DISTINCT cs.session_id) AS session_count,
        GROUP_CONCAT(DISTINCT cs.status ORDER BY cs.status SEPARATOR ', ') AS session_statuses
     {$chargeFrom}
     WHERE {$whereSql}
     GROUP BY {$cashierIdExpression}, ce.first_name, ce.last_name, cua.username,
              {$branchIdExpression}, bb.branch_name
     ORDER BY total_charged DESC, last_charge_at DESC, cashier_name ASC",
    $params
);

$voidCashierCharges = Database::fetchAll(
    "SELECT
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierNameExpression} AS cashier_name,
        vrc_u.username AS cashier_username,
        vtt.branch_id AS branch_id,
        {$voidBranchNameExpression} AS branch_name,
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
            'customer_count' => 0,
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
            'customer_count' => 0,
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

$allCashierCharges = Database::fetchAll(
    "SELECT
        {$cashierIdExpression} AS cashier_user_id,
        {$cashierNameExpression} AS cashier_name,
        cua.username AS cashier_username,
        GROUP_CONCAT(DISTINCT {$branchNameExpression} ORDER BY {$branchNameExpression} SEPARATOR ', ') AS branch_names,
        COUNT(DISTINCT tp.payment_id) AS charge_count,
        COUNT(DISTINCT tp.charged_to_passenger_id) AS customer_count,
        COALESCE(SUM(tp.amount), 0) AS total_charged,
        MAX(tp.created_at) AS last_charge_at
     {$chargeFrom}
     WHERE " . implode(' AND ', $scopeWhere) . "
     GROUP BY {$cashierIdExpression}, ce.first_name, ce.last_name, cua.username
     ORDER BY cashier_name ASC",
    $scopeParams
);

$voidAllCashierCharges = Database::fetchAll(
    "SELECT
        tc.responsible_user_id AS cashier_user_id,
        {$voidCashierNameExpression} AS cashier_name,
        vrc_u.username AS cashier_username,
        GROUP_CONCAT(DISTINCT {$voidBranchNameExpression} ORDER BY {$voidBranchNameExpression} SEPARATOR ', ') AS branch_names,
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
            'customer_count' => 0,
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
            'customer_count' => 0,
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
$chargeEntryType = $unionText("'charge'");
$voidEntryType = $unionText("'void_responsibility'");
$refundEntryType = $unionText("'refund_responsibility'");
$chargeSourceType = $unionText('tp.source_type');
$voidSourceType = $unionText("'TICKET_TRANSACTION'");
$chargeConfirmationStatus = $unionText('tp.confirmation_status');
$chargeMethodName = $unionText('pm.method_name');
$chargeCashierName = $unionText($cashierNameExpression);
$voidCashierName = $unionText($voidCashierNameExpression);
$chargeCashierUsername = $unionText('cua.username');
$voidCashierUsername = $unionText('vrc_u.username');
$chargeBranchName = $unionText($branchNameExpression);
$voidBranchName = $unionText($voidBranchNameExpression);
$chargeAccountName = $unionText('charge_pa.fullname');
$chargeAccountMobile = $unionText('charge_pa.mobile_number');
$chargeTransactionCode = $unionText('COALESCE(tt.transaction_code, st.transaction_code, po.order_code)');
$voidTransactionCode = $unionText('tc.transaction_code');
$chargeTicketNumber = $unionText('tt.ticket_number');
$voidTicketNumber = $unionText('vtt.ticket_number');
$chargePassengerName = $unionText('COALESCE(ticket_pa.fullname, service_pa.fullname)');
$voidPassengerName = $unionText('vpa.fullname');
$chargeTransactionType = $unionText("CASE
            WHEN tp.source_type = 'TICKET_TRANSACTION' THEN 'Ticket'
            WHEN tp.source_type = 'SERVICE_TRANSACTION' THEN COALESCE(stype.name, 'Service')
            ELSE 'POS Order'
        END");
$voidTransactionType = $unionText("'VOID / CASHIER RESPONSIBILITY'");
$refundTransactionType = $unionText("'REFUND / CASHIER RESPONSIBILITY'");
$chargeSessionCode = $unionText('cs.session_code');
$voidSessionCode = $unionText('vrcs.session_code');
$chargeSessionStatus = $unionText('cs.status');
$voidSessionStatus = $unionText('vrcs.status');
$voidResponsibilityStatus = $unionText('tc.status');
$voidSettlementStatus = $unionText("(SELECT ta.settlement_status
         FROM ticket_adjustments ta
         WHERE ta.cancellation_id = tc.cancellation_id
         ORDER BY ta.created_at DESC
         LIMIT 1)");

$chargeEntrySelect = "SELECT
        tp.payment_id AS entry_id,
        {$chargeEntryType} AS entry_type,
        tp.amount,
        tp.created_at,
        {$chargeSourceType} AS source_type,
        {$chargeConfirmationStatus} AS confirmation_status,
        {$chargeMethodName} AS method_name,
        {$cashierIdExpression} AS cashier_user_id,
        {$chargeCashierName} AS cashier_name,
        {$chargeCashierUsername} AS cashier_username,
        {$chargeBranchName} AS branch_name,
        {$chargeAccountName} AS charge_account_name,
        {$chargeAccountMobile} AS charge_account_mobile,
        {$chargeTransactionCode} AS transaction_code,
        {$chargeTicketNumber} AS ticket_number,
        {$chargePassengerName} AS passenger_name,
        {$chargeTransactionType} AS transaction_type,
        {$chargeSessionCode} AS session_code,
        {$chargeSessionStatus} AS session_status,
        {$unionNull} AS responsibility_status,
        {$unionNull} AS settlement_status
     {$chargeFrom}
     WHERE {$whereSql}";

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
        {$unionNull} AS charge_account_name,
        {$unionNull} AS charge_account_mobile,
        {$voidTransactionCode} AS transaction_code,
        {$voidTicketNumber} AS ticket_number,
        {$voidPassengerName} AS passenger_name,
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
        {$unionNull} AS charge_account_name,
        {$unionNull} AS charge_account_mobile,
        {$voidTransactionCode} AS transaction_code,
        {$voidTicketNumber} AS ticket_number,
        {$voidPassengerName} AS passenger_name,
        {$refundTransactionType} AS transaction_type,
        {$voidSessionCode} AS session_code,
        {$voidSessionStatus} AS session_status,
        {$voidResponsibilityStatus} AS responsibility_status,
        {$voidSettlementStatus} AS settlement_status
     {$voidFrom}
     WHERE {$refundWhereSql}";

$chargeEntryUnionSql = $chargeEntrySelect . ' UNION ALL ' . $voidEntrySelect . ' UNION ALL ' . $refundEntrySelect;
$chargeEntryParams = array_merge($params, $voidParams, $refundParams);
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

$cashierOptions = Database::fetchAll(
    "SELECT DISTINCT
        {$cashierIdExpression} AS cashier_user_id,
        {$cashierNameExpression} AS cashier_name
     {$chargeFrom}
     WHERE " . implode(' AND ', $scopeWhere) . "
     ORDER BY cashier_name ASC",
    $scopeParams
);

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
    'source' => $filterSource,
    'session_status' => $filterSessionStatus,
    'search' => $filterSearch,
    'per_page' => $detailPerPage
];

include __DIR__ . '/views/index.php';
