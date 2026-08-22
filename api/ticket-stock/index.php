<?php
/**
 * Ticket Stock API
 *
 * Branch inventory, stock request/transfer, movement, reservation, and
 * discrepancy operations. All mutations delegate quantity changes to
 * TicketStockHelper so branch_ticket_stocks and ticket_stock_movements stay
 * synchronized.
 */

header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PosAccess.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/TicketStockHelper.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/PusherService.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();
$user = Auth::user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handleGet();
    } elseif (in_array($method, ['POST', 'PUT'], true)) {
        validateCsrf();
        handleMutation();
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    if (Database::connection()->inTransaction()) {
        Database::connection()->rollBack();
    }
    error_log('Ticket Stock API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleGet(): void
{
    $action = $_GET['action'] ?? 'balances';
    switch ($action) {
        case 'balances':
            requirePermission('VIEW_TICKET_STOCK_BALANCES');
            $balances = getBalances();
            echo json_encode([
                'success' => true,
                'data' => $balances,
                'version' => balanceVersionFromRows($balances),
            ]);
            return;
        case 'balances_version':
            requirePermission('VIEW_TICKET_STOCK_BALANCES');
            echo json_encode(['success' => true, 'data' => getBalancesVersion()]);
            return;
        case 'variants':
            requirePermission('VIEW_TICKET_STOCK_BALANCES');
            echo json_encode(['success' => true, 'data' => getVariants()]);
            return;
        case 'movements':
            requirePermission('VIEW_TICKET_STOCK_MOVEMENTS');
            echo json_encode(['success' => true, 'data' => getMovements()]);
            return;
        case 'requests':
            requirePermission('VIEW_TICKET_STOCK_REQUESTS');
            echo json_encode(['success' => true, 'data' => getRequests()]);
            return;
        case 'request':
            requirePermission('VIEW_TICKET_STOCK_REQUESTS');
            echo json_encode(['success' => true, 'data' => getRequest((int) ($_GET['id'] ?? 0))]);
            return;
        case 'discrepancies':
            requirePermission('VIEW_TICKET_STOCK_DISCREPANCIES');
            echo json_encode(['success' => true, 'data' => getDiscrepancies()]);
            return;
        default:
            throw new InvalidArgumentException('Unknown ticket-stock action.');
    }
}

function handleMutation(): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? $_GET['action'] ?? null;
    if (!$action) {
        throw new InvalidArgumentException('Ticket-stock action is required.');
    }

    switch ($action) {
        case 'adjust':
            requirePermission('ADJUST_TICKET_STOCK');
            $adjustBranchId = requirePositiveInt($input, 'branch_id');
            $adjustProviderId = requirePositiveInt($input, 'provider_id');
            $adjustVariantId = requirePositiveInt($input, 'variant_id');
            $adjustDelta = requireInt($input, 'delta');
            $adjustReason = trim((string) ($input['reason'] ?? 'Manual stock adjustment'));
            $adjustUserId = currentUserId();
            $result = TicketStockHelper::adjustStock(
                $adjustBranchId,
                $adjustProviderId,
                $adjustVariantId,
                $adjustDelta,
                $adjustUserId,
                $adjustUserId,
                $adjustReason
            );
            logActivity(
                $adjustUserId,
                'ADJUST_TICKET_STOCK',
                'TICKET_STOCK',
                "B{$adjustBranchId}P{$adjustProviderId}V{$adjustVariantId}",
                ['on_hand_qty' => $result['on_hand_before']],
                ['on_hand_qty' => $result['on_hand_after'], 'delta' => $adjustDelta, 'reason' => $adjustReason]
            );
            if ($adjustDelta !== 0) {
                broadcastTicketStockUpdate([$adjustBranchId], [
                    'provider_id' => $adjustProviderId,
                    'variant_id' => $adjustVariantId,
                    'source' => 'adjustment',
                ]);
            }
            respondMutation('Stock adjusted successfully.', $result);
            return;

        case 'receive':
            requirePermission('RECEIVE_TICKET_STOCK');
            $receiveBranchId = requirePositiveInt($input, 'branch_id');
            $receiveProviderId = requirePositiveInt($input, 'provider_id');
            $receiveVariantId = requirePositiveInt($input, 'variant_id');
            $result = TicketStockHelper::receiveStock(
                $receiveBranchId,
                $receiveProviderId,
                $receiveVariantId,
                requirePositiveInt($input, 'qty'),
                currentUserId(),
                [
                    'reference_type' => $input['reference_type'] ?? 'MANUAL_STOCK_RECEIPT',
                    'reference_id' => nullableInt($input['reference_id'] ?? null),
                    'source_branch_id' => nullableInt($input['source_branch_id'] ?? null),
                    'ticket_number_from' => $input['ticket_number_from'] ?? null,
                    'ticket_number_to' => $input['ticket_number_to'] ?? null,
                    'remarks' => trim((string) ($input['remarks'] ?? 'Stock receipt')),
                ]
            );
            broadcastTicketStockUpdate([$receiveBranchId], [
                'provider_id' => $receiveProviderId,
                'variant_id' => $receiveVariantId,
                'source' => 'receipt',
            ]);
            respondMutation('Stock received successfully.', $result);
            return;

        case 'dispatch':
            requirePermission('DISPATCH_TICKET_STOCK');
            $dispatchBranchId = requirePositiveInt($input, 'branch_id');
            $dispatchProviderId = requirePositiveInt($input, 'provider_id');
            $dispatchVariantId = requirePositiveInt($input, 'variant_id');
            $result = TicketStockHelper::dispatchStock(
                $dispatchBranchId,
                $dispatchProviderId,
                $dispatchVariantId,
                requirePositiveInt($input, 'qty'),
                currentUserId(),
                [
                    'reference_type' => $input['reference_type'] ?? 'MANUAL_STOCK_DISPATCH',
                    'reference_id' => nullableInt($input['reference_id'] ?? null),
                    'destination_branch_id' => nullableInt($input['destination_branch_id'] ?? null),
                    'ticket_number_from' => $input['ticket_number_from'] ?? null,
                    'ticket_number_to' => $input['ticket_number_to'] ?? null,
                    'remarks' => trim((string) ($input['remarks'] ?? 'Stock dispatch')),
                ]
            );
            broadcastTicketStockUpdate([$dispatchBranchId], [
                'provider_id' => $dispatchProviderId,
                'variant_id' => $dispatchVariantId,
                'source' => 'dispatch',
            ]);
            respondMutation('Stock dispatched successfully.', $result);
            return;

        case 'transfer':
            requirePermission('DISPATCH_TICKET_STOCK');
            requirePermission('RECEIVE_TICKET_STOCK');
            $transferSourceBranchId = requirePositiveInt($input, 'source_branch_id');
            $transferDestinationBranchId = requirePositiveInt($input, 'destination_branch_id');
            $transferProviderId = requirePositiveInt($input, 'provider_id');
            $transferVariantId = requirePositiveInt($input, 'variant_id');
            $result = TicketStockHelper::transferStock(
                $transferSourceBranchId,
                $transferDestinationBranchId,
                $transferProviderId,
                $transferVariantId,
                requirePositiveInt($input, 'qty'),
                currentUserId(),
                [
                    'reference_type' => $input['reference_type'] ?? 'MANUAL_STOCK_TRANSFER',
                    'reference_id' => nullableInt($input['reference_id'] ?? null),
                    'ticket_number_from' => $input['ticket_number_from'] ?? null,
                    'ticket_number_to' => $input['ticket_number_to'] ?? null,
                    'remarks' => trim((string) ($input['remarks'] ?? 'Stock transfer')),
                ]
            );
            broadcastTicketStockUpdate([$transferSourceBranchId, $transferDestinationBranchId], [
                'provider_id' => $transferProviderId,
                'variant_id' => $transferVariantId,
                'source' => 'transfer',
            ]);
            respondMutation('Stock transferred successfully.', $result);
            return;

        case 'request_create':
            requirePermission('CREATE_TICKET_STOCK_REQUEST');
            respondMutation('Stock request created successfully.', createRequest($input));
            return;

        case 'request_transition':
            $newStatus = strtoupper(trim((string) ($input['status'] ?? '')));
            if ($newStatus === 'APPROVED') {
                requirePermission('APPROVE_TICKET_STOCK_REQUEST');
                respondMutation('Stock request approved.', approveRequest($input));
                return;
            }
            if ($newStatus === 'DISPATCHED') {
                requirePermission('DISPATCH_TICKET_STOCK');
            } elseif (in_array($newStatus, ['RECEIVED', 'PARTIALLY_RECEIVED'], true)) {
                requirePermission('RECEIVE_TICKET_STOCK');
            } else {
                requirePermission('APPROVE_TICKET_STOCK_REQUEST');
            }
            TicketStockHelper::transitionRequestStatus(
                requirePositiveInt($input, 'stock_request_id'),
                $newStatus,
                currentUserId(),
                trim((string) ($input['reason'] ?? '')) ?: null
            );
            respondMutation('Stock request status updated.', ['status' => $newStatus]);
            return;

        case 'request_dispatch':
            requirePermission('DISPATCH_TICKET_STOCK');
            $dispatchRequestId = requirePositiveInt($input, 'stock_request_id');
            $dispatchRequestData = getRequest($dispatchRequestId);
            $result = dispatchRequest($input);
            if (!empty($result['items'])
                && !empty($dispatchRequestData['source_branch_id'])
                && (int) $dispatchRequestData['source_branch_id'] !== (int) $dispatchRequestData['destination_branch_id']) {
                broadcastTicketStockUpdate([(int) $dispatchRequestData['source_branch_id']], [
                    'provider_id' => (int) $dispatchRequestData['provider_id'],
                    'source' => 'request_dispatch',
                ]);
            }
            respondMutation('Stock request dispatched successfully.', $result);
            return;

        case 'request_receive':
            requirePermission('RECEIVE_TICKET_STOCK');
            $receiveRequestId = requirePositiveInt($input, 'stock_request_id');
            $receiveRequestData = getRequest($receiveRequestId);
            $result = receiveRequest($input);
            if (!empty($result['items'])) {
                broadcastTicketStockUpdate([(int) $receiveRequestData['destination_branch_id']], [
                    'provider_id' => (int) $receiveRequestData['provider_id'],
                    'source' => 'request_receive',
                ]);
            }
            respondMutation('Stock request received successfully.', $result);
            return;

        case 'reserve':
            requirePermission('VIEW_TICKET_STOCK_BALANCES');
            $reserveBranchId = requirePositiveInt($input, 'branch_id');
            $reserveProviderId = requirePositiveInt($input, 'provider_id');
            $reserveVariantId = requirePositiveInt($input, 'variant_id');
            $result = TicketStockHelper::reserveStock(
                $reserveBranchId,
                $reserveProviderId,
                $reserveVariantId,
                requirePositiveInt($input, 'qty'),
                currentUserId(),
                reservationExtra($input)
            );
            broadcastTicketStockUpdate([$reserveBranchId], [
                'provider_id' => $reserveProviderId,
                'variant_id' => $reserveVariantId,
                'source' => 'reservation',
            ]);
            respondMutation('Stock reserved successfully.', $result);
            return;

        case 'release':
            requirePermission('VIEW_TICKET_STOCK_BALANCES');
            $releaseBranchId = requirePositiveInt($input, 'branch_id');
            $releaseProviderId = requirePositiveInt($input, 'provider_id');
            $releaseVariantId = requirePositiveInt($input, 'variant_id');
            $result = TicketStockHelper::releaseReservation(
                $releaseBranchId,
                $releaseProviderId,
                $releaseVariantId,
                requirePositiveInt($input, 'qty'),
                currentUserId(),
                reservationExtra($input)
            );
            broadcastTicketStockUpdate([$releaseBranchId], [
                'provider_id' => $releaseProviderId,
                'variant_id' => $releaseVariantId,
                'source' => 'reservation_release',
            ]);
            respondMutation('Stock reservation released successfully.', $result);
            return;

        case 'expire_reservations':
            requirePermission('ADJUST_TICKET_STOCK');
            $expiredReservations = expireReservations();
            broadcastTicketStockUpdate($expiredReservations['branch_ids'] ?? [], [
                'source' => 'reservation_expiry',
            ]);
            unset($expiredReservations['branch_ids']);
            respondMutation('Expired reservations released.', $expiredReservations);
            return;

        case 'discrepancy_create':
            requirePermission('VIEW_TICKET_STOCK_DISCREPANCIES');
            $id = TicketStockHelper::recordDiscrepancy(
                requirePositiveInt($input, 'stock_request_id'),
                nullableInt($input['request_item_id'] ?? null),
                strtoupper((string) ($input['discrepancy_type'] ?? 'OTHER')),
                requireInt($input, 'expected_qty'),
                requireInt($input, 'actual_qty'),
                currentUserId(),
                trim((string) ($input['notes'] ?? '')) ?: null
            );
            respondMutation('Discrepancy recorded successfully.', ['discrepancy_id' => $id]);
            return;

        case 'discrepancy_resolve':
            requirePermission('RESOLVE_TICKET_STOCK_DISCREPANCY');
            resolveDiscrepancy($input);
            respondMutation('Discrepancy resolved successfully.', []);
            return;

        default:
            throw new InvalidArgumentException('Unknown ticket-stock mutation.');
    }
}

function getBalanceFilters(): array
{
    $where = ['1=1'];
    $params = [];
    applyBranchScope($where, $params, 's.branch_id');
    applyOptionalInt($where, $params, 's.branch_id', 'branch_id');
    applyOptionalInt($where, $params, 's.provider_id', 'provider_id');
    applyOptionalInt($where, $params, 's.variant_id', 'variant_id');

    return [$where, $params];
}

function getBalances(): array
{
    [$where, $params] = getBalanceFilters();

    return Database::fetchAll(
        "SELECT s.stock_id, s.branch_id, b.branch_name,
                s.provider_id, p.provider_code, p.provider_name,
                s.variant_id, v.variant_code, v.variant_name,
                s.on_hand_qty, s.reserved_qty,
                (s.on_hand_qty - s.reserved_qty) AS available_qty,
                s.reorder_level, s.created_at, s.updated_at
         FROM branch_ticket_stocks s
         JOIN business_branches b ON b.branch_id = s.branch_id
         JOIN ticket_providers p ON p.provider_id = s.provider_id
         JOIN provider_ticket_variants v ON v.variant_id = s.variant_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY b.branch_name, p.provider_name, v.variant_name",
        $params
    );
}

function getBalancesVersion(): array
{
    [$where, $params] = getBalanceFilters();
    $stats = Database::fetch(
        "SELECT COUNT(*) AS row_count,
                COALESCE(MAX(s.updated_at), '') AS last_updated_at,
                COALESCE(MAX(s.stock_id), 0) AS max_stock_id,
                COALESCE(SUM(COALESCE(s.on_hand_qty, 0)), 0) AS on_hand_total,
                COALESCE(SUM(COALESCE(s.reserved_qty, 0)), 0) AS reserved_total,
                COALESCE(SUM(COALESCE(s.reorder_level, 0)), 0) AS reorder_total,
                COALESCE(SUM(s.stock_id * COALESCE(s.on_hand_qty, 0)), 0) AS on_hand_checksum,
                COALESCE(SUM(s.stock_id * COALESCE(s.reserved_qty, 0)), 0) AS reserved_checksum,
                COALESCE(SUM(s.stock_id * COALESCE(s.reorder_level, 0)), 0) AS reorder_checksum
         FROM branch_ticket_stocks s
         WHERE " . implode(' AND ', $where),
        $params
    ) ?: [];

    return [
        'version' => balanceVersionFromStats($stats),
        'updated_at' => $stats['last_updated_at'] ?: null,
        'row_count' => (int) ($stats['row_count'] ?? 0),
    ];
}

function balanceVersionFromRows(array $rows): string
{
    $stats = [
        'row_count' => count($rows),
        'last_updated_at' => '',
        'max_stock_id' => 0,
        'on_hand_total' => 0,
        'reserved_total' => 0,
        'reorder_total' => 0,
        'on_hand_checksum' => 0,
        'reserved_checksum' => 0,
        'reorder_checksum' => 0,
    ];

    foreach ($rows as $row) {
        $stockId = (int) ($row['stock_id'] ?? 0);
        $onHand = (int) ($row['on_hand_qty'] ?? 0);
        $reserved = (int) ($row['reserved_qty'] ?? 0);
        $reorder = (int) ($row['reorder_level'] ?? 0);
        $updatedAt = (string) ($row['updated_at'] ?? '');

        $stats['last_updated_at'] = max($stats['last_updated_at'], $updatedAt);
        $stats['max_stock_id'] = max($stats['max_stock_id'], $stockId);
        $stats['on_hand_total'] += $onHand;
        $stats['reserved_total'] += $reserved;
        $stats['reorder_total'] += $reorder;
        $stats['on_hand_checksum'] += $stockId * $onHand;
        $stats['reserved_checksum'] += $stockId * $reserved;
        $stats['reorder_checksum'] += $stockId * $reorder;
    }

    return balanceVersionFromStats($stats);
}

function balanceVersionFromStats(array $stats): string
{
    return hash('sha256', implode('|', [
        (int) ($stats['row_count'] ?? 0),
        (string) ($stats['last_updated_at'] ?? ''),
        (string) ($stats['max_stock_id'] ?? 0),
        (string) ($stats['on_hand_total'] ?? 0),
        (string) ($stats['reserved_total'] ?? 0),
        (string) ($stats['reorder_total'] ?? 0),
        (string) ($stats['on_hand_checksum'] ?? 0),
        (string) ($stats['reserved_checksum'] ?? 0),
        (string) ($stats['reorder_checksum'] ?? 0),
    ]));
}

function getVariants(): array
{
    $where = ['v.deleted_at IS NULL', 'v.is_active = 1'];
    $params = [];
    $branchId = optionalInt($_GET['branch_id'] ?? null);
    if ($branchId) {
        assertBranchAccess($branchId);
        $params['branch_id'] = $branchId;
    }

    $branchJoin = $branchId ? 'AND s.branch_id = :branch_id' : '';
    global $user;
    if (!$branchId
        && ($user['role_code'] ?? '') !== 'SUPER_ADMIN'
        && !Auth::can('VIEW_ALL_TICKET_STOCK')) {
        $allowed = allowedBranchIds();
        if (!$allowed) {
            $branchJoin .= ' AND 1 = 0';
        } else {
            $placeholders = [];
            foreach (array_values($allowed) as $index => $allowedBranch) {
                $key = 'variant_branch_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $allowedBranch;
            }
            $branchJoin .= ' AND s.branch_id IN (' . implode(',', $placeholders) . ')';
        }
    }

    return Database::fetchAll(
        "SELECT v.variant_id, v.provider_id, p.provider_code, p.provider_name,
                v.variant_code, v.variant_name, v.stock_controlled,
                COALESCE(s.on_hand_qty, 0) AS on_hand_qty,
                COALESCE(s.reserved_qty, 0) AS reserved_qty,
                COALESCE(s.on_hand_qty - s.reserved_qty, 0) AS available_qty,
                s.branch_id
         FROM provider_ticket_variants v
         JOIN ticket_providers p ON p.provider_id = v.provider_id
         LEFT JOIN branch_ticket_stocks s
            ON s.variant_id = v.variant_id {$branchJoin}
         WHERE " . implode(' AND ', $where) . "
         ORDER BY p.provider_name, v.variant_name",
        $params
    );
}

function getMovements(): array
{
    $where = ['1=1'];
    $params = [];
    applyBranchScope($where, $params, 'm.branch_id');
    applyOptionalInt($where, $params, 'm.branch_id', 'branch_id');
    applyOptionalInt($where, $params, 'm.provider_id', 'provider_id');
    applyOptionalInt($where, $params, 'm.variant_id', 'variant_id');

    if (!empty($_GET['movement_type'])) {
        $where[] = 'm.movement_type = :movement_type';
        $params['movement_type'] = strtoupper(trim((string) $_GET['movement_type']));
    }
    if (!empty($_GET['date_from'])) {
        $where[] = 'DATE(m.created_at) >= :date_from';
        $params['date_from'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where[] = 'DATE(m.created_at) <= :date_to';
        $params['date_to'] = $_GET['date_to'];
    }

    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $params['limit'] = $limit;
    $params['offset'] = ($page - 1) * $limit;

    $rows = Database::fetchAll(
        "SELECT m.*, b.branch_name, p.provider_code, p.provider_name,
                v.variant_code, v.variant_name,
                u.username AS performed_by_username,
                au.username AS approved_by_username
         FROM ticket_stock_movements m
         JOIN business_branches b ON b.branch_id = m.branch_id
         JOIN ticket_providers p ON p.provider_id = m.provider_id
         JOIN provider_ticket_variants v ON v.variant_id = m.variant_id
         LEFT JOIN user_accounts u ON u.user_id = m.performed_by
         LEFT JOIN user_accounts au ON au.user_id = m.approved_by
         WHERE " . implode(' AND ', $where) . "
         ORDER BY m.created_at DESC, m.movement_id DESC
         LIMIT :limit OFFSET :offset",
        $params
    );

    return $rows;
}

function getRequests(): array
{
    $where = ['1=1'];
    $params = [];
    global $user;
    if (($user['role_code'] ?? '') !== 'SUPER_ADMIN' && !Auth::can('VIEW_ALL_TICKET_STOCK')) {
        $allowed = allowedBranchIds();
        if (!$allowed) {
            $where[] = '1 = 0';
        } else {
            $sourcePlaceholders = [];
            $destinationPlaceholders = [];
            foreach (array_values($allowed) as $index => $allowedBranch) {
                $sourceKey = 'request_source_branch_' . $index;
                $destinationKey = 'request_destination_branch_' . $index;
                $sourcePlaceholders[] = ':' . $sourceKey;
                $destinationPlaceholders[] = ':' . $destinationKey;
                $params[$sourceKey] = $allowedBranch;
                $params[$destinationKey] = $allowedBranch;
            }
            $sourceList = implode(',', $sourcePlaceholders);
            $destinationList = implode(',', $destinationPlaceholders);
            $where[] = "(r.source_branch_id IN ({$sourceList}) OR r.destination_branch_id IN ({$destinationList}))";
        }
    }
    if (!empty($_GET['status'])) {
        $where[] = 'r.status = :status';
        $params['status'] = strtoupper(trim((string) $_GET['status']));
    }
    if (!empty($_GET['branch_id'])) {
        $branchId = optionalInt($_GET['branch_id']);
        assertBranchAccess($branchId);
        $where[] = '(r.source_branch_id = :filter_branch OR r.destination_branch_id = :filter_branch)';
        $params['filter_branch'] = $branchId;
    }

    return Database::fetchAll(
        "SELECT r.stock_request_id, r.request_code, r.source_branch_id,
                sb.branch_name AS source_branch_name,
                r.destination_branch_id, db.branch_name AS destination_branch_name,
                r.provider_id, p.provider_name, r.wallet_id,
                CASE
                    WHEN pv.variant_name IS NOT NULL THEN CONCAT(tp.provider_name, ' - ', pv.variant_name, ' - ', wb.branch_name)
                    WHEN parent.provider_name IS NOT NULL THEN CONCAT(parent.provider_name, ' - ', wb.branch_name)
                    ELSE CONCAT(tp.provider_name, ' - ', wb.branch_name)
                END AS wallet_name,
                r.status, r.request_reason,
                r.requested_by, ru.username AS requested_by_username,
                r.requested_at, r.approved_at, r.dispatched_at, r.received_at,
                (SELECT COUNT(*) FROM ticket_stock_request_items ri
                 WHERE ri.stock_request_id = r.stock_request_id) AS item_count,
                (SELECT COALESCE(SUM(ri.requested_qty), 0)
                 FROM ticket_stock_request_items ri
                 WHERE ri.stock_request_id = r.stock_request_id) AS requested_qty,
                (SELECT COALESCE(SUM(ri.received_qty), 0)
                 FROM ticket_stock_request_items ri
                 WHERE ri.stock_request_id = r.stock_request_id) AS received_qty
         FROM ticket_stock_requests r
         LEFT JOIN business_branches sb ON sb.branch_id = r.source_branch_id
         JOIN business_branches db ON db.branch_id = r.destination_branch_id
         JOIN ticket_providers p ON p.provider_id = r.provider_id
         LEFT JOIN provider_wallets rw ON rw.wallet_id = r.wallet_id
         LEFT JOIN ticket_providers tp ON tp.provider_id = rw.provider_id
         LEFT JOIN ticket_providers parent ON parent.provider_id = tp.parent_provider_id
         LEFT JOIN provider_ticket_variants pv ON pv.variant_id = rw.variant_id
         LEFT JOIN business_branches wb ON wb.branch_id = rw.branch_id
         LEFT JOIN user_accounts ru ON ru.user_id = r.requested_by
         WHERE " . implode(' AND ', $where) . "
         ORDER BY r.created_at DESC",
        $params
    );
}

function getRequest(int $requestId): array
{
    if ($requestId <= 0) {
        throw new InvalidArgumentException('A valid stock request ID is required.');
    }
    $request = Database::fetch(
        "SELECT r.*, sb.branch_name AS source_branch_name,
                db.branch_name AS destination_branch_name,
                p.provider_code, p.provider_name,
                CASE
                    WHEN pv.variant_name IS NOT NULL THEN CONCAT(tp.provider_name, ' - ', pv.variant_name, ' - ', wb.branch_name)
                    WHEN parent.provider_name IS NOT NULL THEN CONCAT(parent.provider_name, ' - ', wb.branch_name)
                    ELSE CONCAT(tp.provider_name, ' - ', wb.branch_name)
                END AS wallet_name
         FROM ticket_stock_requests r
         LEFT JOIN business_branches sb ON sb.branch_id = r.source_branch_id
         JOIN business_branches db ON db.branch_id = r.destination_branch_id
         JOIN ticket_providers p ON p.provider_id = r.provider_id
         LEFT JOIN provider_wallets rw ON rw.wallet_id = r.wallet_id
         LEFT JOIN ticket_providers tp ON tp.provider_id = rw.provider_id
         LEFT JOIN ticket_providers parent ON parent.provider_id = tp.parent_provider_id
         LEFT JOIN provider_ticket_variants pv ON pv.variant_id = rw.variant_id
         LEFT JOIN business_branches wb ON wb.branch_id = rw.branch_id
         WHERE r.stock_request_id = :request_id",
        ['request_id' => $requestId]
    );
    if (!$request) {
        throw new RuntimeException('Stock request not found.');
    }
    assertRequestAccess($request);
    $request['items'] = Database::fetchAll(
        "SELECT ri.*, v.variant_code, v.variant_name
         FROM ticket_stock_request_items ri
         JOIN provider_ticket_variants v ON v.variant_id = ri.variant_id
         WHERE ri.stock_request_id = :request_id
         ORDER BY v.variant_name",
        ['request_id' => $requestId]
    );
    return $request;
}

function getDiscrepancies(): array
{
    $where = ['1=1'];
    $params = [];
    applyBranchScope($where, $params, 'r.destination_branch_id');
    if (!empty($_GET['status'])) {
        $where[] = 'd.status = :status';
        $params['status'] = strtoupper(trim((string) $_GET['status']));
    }

    return Database::fetchAll(
        "SELECT d.*, r.request_code, r.destination_branch_id,
                b.branch_name AS destination_branch_name,
                v.variant_code, v.variant_name,
                u.username AS reported_by_username,
                ru.username AS resolved_by_username
         FROM ticket_stock_discrepancies d
         JOIN ticket_stock_requests r ON r.stock_request_id = d.stock_request_id
         LEFT JOIN business_branches b ON b.branch_id = r.destination_branch_id
         LEFT JOIN ticket_stock_request_items ri ON ri.request_item_id = d.request_item_id
         LEFT JOIN provider_ticket_variants v ON v.variant_id = ri.variant_id
         LEFT JOIN user_accounts u ON u.user_id = d.reported_by
         LEFT JOIN user_accounts ru ON ru.user_id = d.resolved_by
         WHERE " . implode(' AND ', $where) . "
         ORDER BY d.created_at DESC",
        $params
    );
}

function createRequest(array $input): array
{
    $destinationBranchId = requirePositiveInt($input, 'destination_branch_id');
    assertBranchAccess($destinationBranchId);
    $sourceBranchId = nullableInt($input['source_branch_id'] ?? null);
    $providerId = optionalInt($input['provider_id'] ?? null);
    $walletId = nullableInt($input['wallet_id'] ?? null);

    if ($walletId) {
        $wallet = Database::fetch(
            "SELECT wallet_id, provider_id, branch_id, variant_id, status
             FROM provider_wallets
             WHERE wallet_id = :wallet_id
             LIMIT 1",
            ['wallet_id' => $walletId]
        );
        if (!$wallet || $wallet['status'] !== 'active') {
            throw new InvalidArgumentException('The selected source wallet is not active.');
        }
        assertBranchAccess((int) $wallet['branch_id']);
        if ($providerId && $providerId !== (int) $wallet['provider_id']) {
            throw new InvalidArgumentException('The selected wallet and provider do not match.');
        }
        if ($sourceBranchId && $sourceBranchId !== (int) $wallet['branch_id']) {
            throw new InvalidArgumentException('The selected wallet and source branch do not match.');
        }
        $providerId = (int) $wallet['provider_id'];
        $sourceBranchId = (int) $wallet['branch_id'];
    }

    if (!$providerId) {
        throw new InvalidArgumentException('A provider or source wallet is required.');
    }
    if ($sourceBranchId) {
        assertBranchAccess($sourceBranchId);
    }
    $items = is_array($input['items'] ?? null) ? $input['items'] : [];
    if (!$items) {
        throw new InvalidArgumentException('At least one stock request item is required.');
    }

    $pdo = Database::connection();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $requestCode = 'TSR-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
        Database::execute(
            "INSERT INTO ticket_stock_requests
                (request_code, source_branch_id, destination_branch_id, provider_id, wallet_id,
                 status, request_reason, requested_by, requested_at, remarks, created_at)
             VALUES (:request_code, :source_branch_id, :destination_branch_id, :provider_id, :wallet_id,
                     'DRAFT', :request_reason, :requested_by, NULL, :remarks, NOW())",
            [
                'request_code' => $requestCode,
                'source_branch_id' => $sourceBranchId,
                'destination_branch_id' => $destinationBranchId,
                'provider_id' => $providerId,
                'wallet_id' => $walletId,
                'request_reason' => strtoupper((string) ($input['request_reason'] ?? 'OTHER')),
                'requested_by' => currentUserId(),
                'remarks' => trim((string) ($input['remarks'] ?? '')) ?: null,
            ]
        );
        $requestId = (int) Database::lastInsertId();
        $seenVariantIds = [];

        foreach ($items as $item) {
            $variantId = requirePositiveInt($item, 'variant_id');
            $qty = requirePositiveInt($item, 'requested_qty');
            if (isset($seenVariantIds[$variantId])) {
                throw new InvalidArgumentException('Each stock request item variant may only be added once.');
            }
            $seenVariantIds[$variantId] = true;
            $variant = TicketStockHelper::getVariant($variantId);
            if (!$variant || (int) $variant['provider_id'] !== $providerId || !(int) $variant['is_active']) {
                throw new InvalidArgumentException('A request item has an invalid provider variant.');
            }
            Database::execute(
                "INSERT INTO ticket_stock_request_items
                    (stock_request_id, variant_id, requested_qty, remarks, created_at)
                 VALUES (:request_id, :variant_id, :requested_qty, :remarks, NOW())",
                [
                    'request_id' => $requestId,
                    'variant_id' => $variantId,
                    'requested_qty' => $qty,
                    'remarks' => trim((string) ($item['remarks'] ?? '')) ?: null,
                ]
            );
        }

        if (!empty($input['submit'])) {
            TicketStockHelper::transitionRequestStatus($requestId, 'SUBMITTED', currentUserId());
        }
        if ($started) {
            $pdo->commit();
        }
        return ['stock_request_id' => $requestId, 'request_code' => $requestCode];
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function approveRequest(array $input): array
{
    $requestId = requirePositiveInt($input, 'stock_request_id');
    $request = getRequest($requestId);
    if ($request['status'] !== 'SUBMITTED') {
        throw new RuntimeException('Only submitted stock requests can be approved.');
    }

    $approvedMap = [];
    foreach ((array) ($input['items'] ?? []) as $item) {
        $approvedMap[(int) ($item['request_item_id'] ?? 0)] = max(0, (int) ($item['approved_qty'] ?? 0));
    }

    $pdo = Database::connection();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        TicketStockHelper::transitionRequestStatus($requestId, 'APPROVED', currentUserId());
        foreach ($request['items'] as $item) {
            $approvedQty = array_key_exists((int) $item['request_item_id'], $approvedMap)
                ? $approvedMap[(int) $item['request_item_id']]
                : (int) $item['requested_qty'];
            if ($approvedQty > (int) $item['requested_qty']) {
                throw new InvalidArgumentException('Approved quantity cannot exceed requested quantity.');
            }
            Database::execute(
                "UPDATE ticket_stock_request_items
                 SET approved_qty = :approved_qty, updated_at = NOW()
                 WHERE request_item_id = :request_item_id",
                [
                    'approved_qty' => $approvedQty,
                    'request_item_id' => (int) $item['request_item_id'],
                ]
            );
        }
        if ($started) {
            $pdo->commit();
        }
        return ['stock_request_id' => $requestId, 'status' => 'APPROVED'];
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function dispatchRequest(array $input): array
{
    $requestId = requirePositiveInt($input, 'stock_request_id');
    $request = getRequest($requestId);
    if (!in_array($request['status'], ['APPROVED', 'DISPATCHED'], true)) {
        throw new RuntimeException('Only approved stock requests can be dispatched.');
    }

    $sourceBranchId = $request['source_branch_id'] ? (int) $request['source_branch_id'] : null;
    $destinationBranchId = (int) $request['destination_branch_id'];
    // External (no source branch) and same-branch replenishment requests do not
    // deduct from an internal source branch; the receiving step adds the stock.
    $requiresSourceDecrement = $sourceBranchId && $sourceBranchId !== $destinationBranchId;

    $pdo = Database::connection();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $results = [];
        foreach ($request['items'] as $item) {
            $remaining = max(0, (int) $item['approved_qty'] - (int) $item['dispatched_qty']);
            if ($remaining <= 0) {
                continue;
            }
            if ($requiresSourceDecrement) {
                $result = TicketStockHelper::dispatchStock(
                    $sourceBranchId,
                    (int) $request['provider_id'],
                    (int) $item['variant_id'],
                    $remaining,
                    currentUserId(),
                    [
                        'reference_type' => 'STOCK_REQUEST',
                        'reference_id' => $requestId,
                        'destination_branch_id' => $destinationBranchId,
                        'ticket_number_from' => $item['ticket_series_from'],
                        'ticket_number_to' => $item['ticket_series_to'],
                        'remarks' => 'Dispatch for ' . $request['request_code'],
                    ]
                );
                $results[] = $result;
            }
            Database::execute(
                "UPDATE ticket_stock_request_items
                 SET dispatched_qty = dispatched_qty + :qty, updated_at = NOW()
                 WHERE request_item_id = :item_id",
                ['qty' => $remaining, 'item_id' => (int) $item['request_item_id']]
            );
        }
        TicketStockHelper::transitionRequestStatus($requestId, 'DISPATCHED', currentUserId());
        if ($started) {
            $pdo->commit();
        }
        return ['request_id' => $requestId, 'items' => $results];
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function receiveRequest(array $input): array
{
    $requestId = requirePositiveInt($input, 'stock_request_id');
    $request = getRequest($requestId);
    if (!in_array($request['status'], ['DISPATCHED', 'PARTIALLY_RECEIVED'], true)) {
        throw new RuntimeException('Only dispatched stock requests can be received.');
    }

    $receivedItems = is_array($input['items'] ?? null) ? $input['items'] : [];
    $receivedMap = [];
    foreach ($receivedItems as $item) {
        $receivedMap[(int) ($item['request_item_id'] ?? 0)] = max(0, (int) ($item['received_qty'] ?? 0));
    }

    $pdo = Database::connection();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }
    try {
        $results = [];
        $allReceived = true;
        foreach ($request['items'] as $item) {
            $remaining = max(0, (int) $item['dispatched_qty'] - (int) $item['received_qty']);
            $qty = array_key_exists((int) $item['request_item_id'], $receivedMap)
                ? min($remaining, $receivedMap[(int) $item['request_item_id']])
                : $remaining;
            if ($qty > 0) {
                $results[] = TicketStockHelper::receiveStock(
                    (int) $request['destination_branch_id'],
                    (int) $request['provider_id'],
                    (int) $item['variant_id'],
                    $qty,
                    currentUserId(),
                    [
                        'reference_type' => 'STOCK_REQUEST',
                        'reference_id' => $requestId,
                        'source_branch_id' => (int) $request['source_branch_id'],
                        'ticket_number_from' => $item['ticket_series_from'],
                        'ticket_number_to' => $item['ticket_series_to'],
                        'remarks' => 'Receipt for ' . $request['request_code'],
                    ]
                );
                Database::execute(
                    "UPDATE ticket_stock_request_items
                     SET received_qty = received_qty + :qty, updated_at = NOW()
                     WHERE request_item_id = :item_id",
                    ['qty' => $qty, 'item_id' => (int) $item['request_item_id']]
                );
            }
            $after = (int) $item['received_qty'] + $qty;
            if ($after < (int) $item['dispatched_qty']) {
                $allReceived = false;
            }
        }

        TicketStockHelper::transitionRequestStatus(
            $requestId,
            $allReceived ? 'RECEIVED' : 'PARTIALLY_RECEIVED',
            currentUserId()
        );
        if ($started) {
            $pdo->commit();
        }
        return ['request_id' => $requestId, 'items' => $results, 'complete' => $allReceived];
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function resolveDiscrepancy(array $input): void
{
    $id = requirePositiveInt($input, 'discrepancy_id');
    $status = strtoupper(trim((string) ($input['status'] ?? 'RESOLVED')));
    if (!in_array($status, ['OPEN', 'INVESTIGATING', 'RESOLVED', 'WRITTEN_OFF'], true)) {
        throw new InvalidArgumentException('Invalid discrepancy status.');
    }
    $row = Database::fetch(
        "SELECT d.discrepancy_id, r.destination_branch_id
         FROM ticket_stock_discrepancies d
         JOIN ticket_stock_requests r ON r.stock_request_id = d.stock_request_id
         WHERE d.discrepancy_id = :discrepancy_id",
        ['discrepancy_id' => $id]
    );
    if (!$row) {
        throw new RuntimeException('Discrepancy not found.');
    }
    assertBranchAccess((int) $row['destination_branch_id']);
    Database::execute(
        "UPDATE ticket_stock_discrepancies
         SET status = :status,
             resolution_notes = :notes,
             resolved_by = :resolved_by,
             resolved_at = CASE WHEN :status2 IN ('RESOLVED', 'WRITTEN_OFF') THEN NOW() ELSE NULL END,
             updated_at = NOW()
         WHERE discrepancy_id = :discrepancy_id",
        [
            'status' => $status,
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
            'resolved_by' => currentUserId(),
            'status2' => $status,
            'discrepancy_id' => $id,
        ]
    );
}

function expireReservations(): array
{
    $where = ['expires_at <= NOW()'];
    $params = [];
    applyBranchScope($where, $params, 'r.branch_id');
    $rows = Database::fetchAll(
        "SELECT reservation_id, branch_id, provider_id, variant_id,
                reserved_qty, pos_order_id, session_id
         FROM ticket_stock_reservations r
         WHERE " . implode(' AND ', $where),
        $params
    );
    $released = 0;
    $branchIds = [];
    foreach ($rows as $row) {
        TicketStockHelper::releaseReservation(
            (int) $row['branch_id'],
            (int) $row['provider_id'],
            (int) $row['variant_id'],
            (int) $row['reserved_qty'],
            currentUserId(),
            [
                'pos_order_id' => $row['pos_order_id'],
                'session_id' => $row['session_id'],
            ]
        );
        $branchIds[] = (int) $row['branch_id'];
        $released++;
    }
    return ['released' => $released, 'branch_ids' => array_values(array_unique($branchIds))];
}

function reservationExtra(array $input): array
{
    return [
        'pos_order_id' => nullableInt($input['pos_order_id'] ?? null),
        'session_id' => !empty($input['session_id']) ? (string) $input['session_id'] : null,
        'expires_at' => $input['expires_at'] ?? null,
    ];
}

function broadcastTicketStockUpdate(array $branchIds, array $payload = []): void
{
    $branchIds = array_values(array_unique(array_filter(
        array_map('intval', $branchIds),
        static fn (int $branchId): bool => $branchId > 0
    )));
    if (!$branchIds) {
        return;
    }

    $eventPayload = array_merge([
        'branch_ids' => $branchIds,
        'changed_at' => date(DATE_ATOM),
    ], $payload);
    foreach ($branchIds as $branchId) {
        PusherService::triggerBranch($branchId, 'ticket_stock.updated', array_merge(
            ['branch_id' => $branchId],
            $eventPayload
        ));
    }
}

function requirePermission(string $permission): void
{
    global $user;
    if (($user['role_code'] ?? '') === 'SUPER_ADMIN' || Auth::can($permission)) {
        return;
    }
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied. Required permission: ' . $permission]);
    exit;
}

function validateCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? $_GET['_token'] ?? null;
    if (!SecurityHelper::validateCSRFToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

function currentUserId(): int
{
    global $user;
    return (int) ($user['user_id'] ?? 0);
}

function applyBranchScope(array &$where, array &$params, string $column): void
{
    global $user;
    if (($user['role_code'] ?? '') === 'SUPER_ADMIN' || Auth::can('VIEW_ALL_TICKET_STOCK')) {
        return;
    }
    $branchIds = allowedBranchIds();
    if (!$branchIds) {
        $where[] = '1 = 0';
        return;
    }
    $placeholders = [];
    foreach (array_values($branchIds) as $index => $branchId) {
        $key = 'scope_branch_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $branchId;
    }
    $where[] = "{$column} IN (" . implode(',', $placeholders) . ')';
}

function applyOptionalInt(array &$where, array &$params, string $column, string $queryKey): void
{
    if (!isset($_GET[$queryKey]) || $_GET[$queryKey] === '') {
        return;
    }
    $value = optionalInt($_GET[$queryKey]);
    if (!$value) {
        throw new InvalidArgumentException("Invalid {$queryKey}.");
    }
    assertBranchAccess($queryKey === 'branch_id' ? $value : null);
    $where[] = "{$column} = :{$queryKey}";
    $params[$queryKey] = $value;
}

function allowedBranchIds(): array
{
    global $user;
    $ids = [];
    $userBranchId = Auth::userBranchId();
    if (!empty($userBranchId)) {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $userBranchId))));
    }
    if (($user['role_code'] ?? '') === 'CASHIER' && !empty($user['user_id'])) {
        $session = Database::fetch(
            "SELECT branch_id FROM cashier_sessions
             WHERE cashier_user_id = :user_id AND status = 'OPEN'
             ORDER BY started_at DESC LIMIT 1",
            ['user_id' => (int) $user['user_id']]
        );
        if (!empty($session['branch_id'])) {
            $ids[] = (int) $session['branch_id'];
        }
    }
    return array_values(array_unique(array_filter($ids)));
}

function assertBranchAccess(?int $branchId): void
{
    if (!$branchId) {
        return;
    }
    global $user;
    if (($user['role_code'] ?? '') === 'SUPER_ADMIN' || Auth::can('VIEW_ALL_TICKET_STOCK')) {
        return;
    }
    if (!in_array($branchId, allowedBranchIds(), true)) {
        throw new RuntimeException('Access denied for the selected branch.');
    }
}

function assertRequestAccess(array $request): void
{
    assertBranchAccess($request['source_branch_id'] ? (int) $request['source_branch_id'] : null);
    assertBranchAccess((int) $request['destination_branch_id']);
}

function requirePositiveInt(array $input, string $key): int
{
    $value = (int) ($input[$key] ?? 0);
    if ($value <= 0) {
        throw new InvalidArgumentException("{$key} must be a positive integer.");
    }
    if ($key === 'branch_id' || str_ends_with($key, '_branch_id')) {
        assertBranchAccess($value);
    }
    return $value;
}

function requireInt(array $input, string $key): int
{
    if (!isset($input[$key]) || !is_numeric($input[$key])) {
        throw new InvalidArgumentException("{$key} must be an integer.");
    }
    return (int) $input[$key];
}

function optionalInt($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Expected a numeric identifier.');
    }
    return (int) $value;
}

function nullableInt($value): ?int
{
    return optionalInt($value);
}

function respondMutation(string $message, array $data): void
{
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
}

function logActivity(?int $userId, string $action, string $moduleName, ?string $referenceCode = null, $oldValue = null, $newValue = null): void
{
    Database::execute(
        "INSERT INTO activity_logs
            (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
         VALUES
            (:user_id, NULL, :action, :module_name, :reference_code, :ip_address, :old_value, :new_value, :now)",
        [
            'user_id' => $userId,
            'now' => date('Y-m-d H:i:s'),
            'action' => $action,
            'module_name' => $moduleName,
            'reference_code' => $referenceCode,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'old_value' => $oldValue === null ? null : json_encode($oldValue),
            'new_value' => $newValue === null ? null : json_encode($newValue),
        ]
    );
}
