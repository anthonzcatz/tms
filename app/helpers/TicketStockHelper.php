<?php
/**
 * TicketStockHelper
 *
 * Core helper for ticket variant inventory operations:
 * - Branch stock balance updates (on_hand_qty / reserved_qty)
 * - Atomic stock movement ledger creation
 * - Availability validation respecting allow_negative_ticket_stock
 * - Status transition rules, default approver fallback, and discrepancy handling
 *
 * All mutating methods run inside a transaction. If a caller already started a
 * transaction, the helper re-uses it (no nested transaction errors).
 */

require_once __DIR__ . '/../../config/database.php';

final class TicketStockHelper
{
    /**
     * Allowed request status transitions.
     */
    private const STATUS_TRANSITIONS = [
        'DRAFT'              => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED'          => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED'           => ['DISPATCHED', 'CANCELLED'],
        'DISPATCHED'         => ['RECEIVED', 'PARTIALLY_RECEIVED', 'DISPUTED', 'CANCELLED'],
        'RECEIVED'           => ['CLOSED', 'CANCELLED'],
        'PARTIALLY_RECEIVED' => ['CLOSED', 'CANCELLED', 'DISPUTED'],
        'DISPUTED'           => ['CLOSED', 'CANCELLED'],
        'REJECTED'           => ['CANCELLED'],
        'CLOSED'             => [],
        'CANCELLED'          => [],
    ];

    /**
     * Columns that can be passed to createMovement().
     */
    private const MOVEMENT_COLUMNS = [
        'branch_id',
        'provider_id',
        'variant_id',
        'movement_type',
        'quantity_delta',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'source_branch_id',
        'destination_branch_id',
        'ticket_number_from',
        'ticket_number_to',
        'remarks',
        'performed_by',
        'performed_at',
        'approved_by',
    ];

    /**
     * Columns that can be updated when transitioning a stock request status.
     */
    private const REQUEST_STATUS_FIELDS = [
        'SUBMITTED'          => ['requested_at'],
        'APPROVED'           => ['approved_by', 'approved_at'],
        'REJECTED'           => ['approved_by', 'approved_at', 'rejection_reason'],
        'DISPATCHED'         => ['dispatched_by', 'dispatched_at'],
        'RECEIVED'           => ['received_by', 'received_at'],
        'PARTIALLY_RECEIVED' => ['received_by', 'received_at'],
        'DISPUTED'           => ['received_by', 'received_at'],
        'CLOSED'             => ['closed_by', 'closed_at'],
        'CANCELLED'          => ['closed_by', 'closed_at', 'cancellation_reason'],
    ];

    /** @var bool|null */
    private static ?bool $allowNegativeStock = null;

    /* ============================================================
       TRANSACTION HANDLING
       ============================================================ */

    /**
     * Run a callback inside a transaction. If a transaction already exists,
     * re-use it. Otherwise start, commit, or roll back on error.
     */
    private static function runInTransaction(callable $callback)
    {
        $pdo = Database::connection();
        $started = !$pdo->inTransaction();

        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if ($started) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if ($started) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /* ============================================================
       SYSTEM SETTINGS & VARIANT LOOKUP
       ============================================================ */

    /**
     * Whether negative ticket stock balances are currently allowed.
     */
    public static function allowNegativeStock(): bool
    {
        if (self::$allowNegativeStock === null) {
            $row = Database::fetch(
                "SELECT `allow_negative_ticket_stock` FROM `system_settings` WHERE `setting_id` = 1"
            );
            self::$allowNegativeStock = (int) ($row['allow_negative_ticket_stock'] ?? 0) === 1;
        }

        return self::$allowNegativeStock;
    }

    /**
     * Reset the cached allow-negative-stock flag (useful in tests or long-running CLI).
     */
    public static function resetAllowNegativeStockCache(): void
    {
        self::$allowNegativeStock = null;
    }

    /**
     * Fetch a ticket variant by ID.
     */
    public static function getVariant(int $variantId): ?array
    {
        return Database::fetch(
            "SELECT `variant_id`, `provider_id`, `variant_code`, `variant_name`,
                    `stock_controlled`, `requires_ticket_number`, `is_active`
             FROM `provider_ticket_variants`
             WHERE `variant_id` = :variant_id",
            ['variant_id' => $variantId]
        );
    }

    /**
     * Fetch the branch stock row. Optionally lock it with FOR UPDATE.
     */
    public static function getBranchStock(int $branchId, int $providerId, int $variantId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT `stock_id`, `branch_id`, `provider_id`, `variant_id`,
                       `on_hand_qty`, `reserved_qty`,
                       (`on_hand_qty` - `reserved_qty`) AS `available_qty`
                FROM `branch_ticket_stocks`
                WHERE `branch_id` = :branch_id
                  AND `provider_id` = :provider_id
                  AND `variant_id` = :variant_id";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        return Database::fetch($sql, [
            'branch_id'   => $branchId,
            'provider_id' => $providerId,
            'variant_id'  => $variantId,
        ]);
    }

    /**
     * Quick read of available quantity.
     */
    public static function availableQty(int $branchId, int $providerId, int $variantId): int
    {
        $row = self::getBranchStock($branchId, $providerId, $variantId);
        return (int) ($row['available_qty'] ?? 0);
    }

    /* ============================================================
       AVAILABILITY VALIDATION
       ============================================================ */

    /**
     * Validate that the requested quantity can be taken from branch stock.
     *
     * Throws Exception when insufficient and negative stock is disabled.
     * Returns true otherwise.
     */
    public static function validateAvailability(
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        ?string $context = null
    ): bool {
        if ($qty <= 0) {
            return true;
        }

        $variant = self::getVariant($variantId);
        if (!$variant) {
            throw new Exception('Ticket variant not found.');
        }

        if (!(bool) $variant['stock_controlled']) {
            return true;
        }

        if (!$variant['is_active']) {
            throw new Exception('Ticket variant is inactive.');
        }

        $available = self::availableQty($branchId, $providerId, $variantId);

        if ($qty > $available && !self::allowNegativeStock()) {
            $ctx = $context ? " [{$context}]" : '';
            throw new Exception(
                "Insufficient ticket stock{$ctx}: requested {$qty}, available {$available}."
            );
        }

        return true;
    }

    /* ============================================================
       STOCK BALANCE UPDATES
       ============================================================ */

    /**
     * Generic atomic upsert of one quantity column in branch_ticket_stocks.
     * $column must be 'on_hand_qty' or 'reserved_qty'.
     */
    private static function upsertBranchStock(
        PDO $pdo,
        int $branchId,
        int $providerId,
        int $variantId,
        string $column,
        int $delta
    ): void {
        if (!in_array($column, ['on_hand_qty', 'reserved_qty'], true)) {
            throw new Exception('Invalid stock column: ' . $column);
        }

        $otherColumn = $column === 'on_hand_qty' ? 'reserved_qty' : 'on_hand_qty';

        $stmt = $pdo->prepare(
            "INSERT INTO `branch_ticket_stocks`
                (`branch_id`, `provider_id`, `variant_id`, `{$column}`, `{$otherColumn}`, `updated_at`)
             VALUES (:branch_id, :provider_id, :variant_id, :delta, 0, NOW())
             ON DUPLICATE KEY UPDATE
                `{$column}` = `{$column}` + VALUES(`{$column}`),
                `{$otherColumn}` = `{$otherColumn}` + VALUES(`{$otherColumn}`),
                `updated_at` = NOW()"
        );

        $stmt->execute([
            ':branch_id'   => $branchId,
            ':provider_id' => $providerId,
            ':variant_id'  => $variantId,
            ':delta'       => $delta,
        ]);
    }

    /**
     * Adjust on_hand_qty and record a movement.
     */
    public static function adjustOnHand(
        int $branchId,
        int $providerId,
        int $variantId,
        int $delta,
        string $movementType,
        array $movementData,
        int $userId
    ): array {
        return self::runInTransaction(function (PDO $pdo) use (
            $branchId, $providerId, $variantId, $delta, $movementType, $movementData, $userId
        ) {
            return self::_adjustOnHand($pdo, $branchId, $providerId, $variantId, $delta, $movementType, $movementData, $userId);
        });
    }

    private static function _adjustOnHand(
        PDO $pdo,
        int $branchId,
        int $providerId,
        int $variantId,
        int $delta,
        string $movementType,
        array $movementData,
        int $userId
    ): array {
        if ($delta === 0) {
            $row = self::getBranchStock($branchId, $providerId, $variantId);
            return [
                'movement_id'    => null,
                'on_hand_before' => (int) ($row['on_hand_qty'] ?? 0),
                'on_hand_after'  => (int) ($row['on_hand_qty'] ?? 0),
            ];
        }

        // Validate all stock decreases before the locked read.
        if ($delta < 0) {
            self::validateAvailability($branchId, $providerId, $variantId, -$delta, $movementType);
        }

        $variant = self::getVariant($variantId);
        if (!$variant) {
            throw new Exception('Ticket variant not found.');
        }

        $row = self::getBranchStock($branchId, $providerId, $variantId, true);
        $balanceBefore = (int) ($row['on_hand_qty'] ?? 0);
        $balanceAfter  = $balanceBefore + $delta;

        // Final guard against negative on-hand when the system does not allow it.
        if ($delta < 0 && $balanceAfter < 0 && !self::allowNegativeStock()) {
            throw new Exception('Insufficient on-hand stock.');
        }

        self::upsertBranchStock($pdo, $branchId, $providerId, $variantId, 'on_hand_qty', $delta);

        // Negative balance note when forced by system setting.
        if ($balanceAfter < 0 && self::allowNegativeStock()) {
            $movementData['remarks'] = ($movementData['remarks'] ?? '') . ' [NEGATIVE_BALANCE forced by allow_negative_ticket_stock]';
        }

        $movementId = self::_createMovement($pdo, array_merge([
            'branch_id'      => $branchId,
            'provider_id'    => $providerId,
            'variant_id'     => $variantId,
            'movement_type'  => $movementType,
            'quantity_delta' => $delta,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'performed_by'   => $userId,
            'performed_at'   => date('Y-m-d H:i:s'),
        ], $movementData));

        return [
            'movement_id'    => $movementId,
            'on_hand_before' => $balanceBefore,
            'on_hand_after'  => $balanceAfter,
        ];
    }

    /**
     * Adjust reserved_qty. Used by POS reservations and releases.
     */
    public static function adjustReserved(
        int $branchId,
        int $providerId,
        int $variantId,
        int $delta,
        int $userId
    ): array {
        return self::runInTransaction(function (PDO $pdo) use (
            $branchId, $providerId, $variantId, $delta, $userId
        ) {
            return self::_adjustReserved($pdo, $branchId, $providerId, $variantId, $delta);
        });
    }

    private static function _adjustReserved(
        PDO $pdo,
        int $branchId,
        int $providerId,
        int $variantId,
        int $delta
    ): array {
        $row = self::getBranchStock($branchId, $providerId, $variantId, true);
        $reservedBefore = (int) ($row['reserved_qty'] ?? 0);
        $reservedAfter  = $reservedBefore + $delta;
        $onHand         = (int) ($row['on_hand_qty'] ?? 0);

        if ($reservedAfter < 0) {
            throw new Exception('Reserved quantity cannot go negative.');
        }

        if ($delta > 0 && $reservedAfter > $onHand) {
            throw new Exception('Reservation cannot exceed on-hand quantity.');
        }

        if ($delta !== 0) {
            self::upsertBranchStock($pdo, $branchId, $providerId, $variantId, 'reserved_qty', $delta);
        }

        return [
            'reserved_before' => $reservedBefore,
            'reserved_after'  => $reservedAfter,
        ];
    }

    /* ============================================================
       RESERVATIONS (POS CONCURRENCY)
       ============================================================ */

    /**
     * Reserve stock for an in-progress POS cart/session.
     */
    public static function reserveStock(
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        int $userId,
        array $extra = []
    ): array {
        if ($qty <= 0) {
            throw new Exception('Reservation quantity must be positive.');
        }

        self::validateAvailability($branchId, $providerId, $variantId, $qty, 'RESERVATION');

        return self::runInTransaction(function (PDO $pdo) use (
            $branchId, $providerId, $variantId, $qty, $userId, $extra
        ) {
            $reserved = self::_adjustReserved($pdo, $branchId, $providerId, $variantId, $qty);

            if (!empty($extra['session_id']) || !empty($extra['pos_order_id'])) {
                self::_insertReservation($pdo, $branchId, $providerId, $variantId, $qty, $extra);
            }

            return $reserved;
        });
    }

    /**
     * Release a prior reservation.
     */
    public static function releaseReservation(
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        int $userId,
        array $extra = []
    ): array {
        if ($qty <= 0) {
            throw new Exception('Release quantity must be positive.');
        }

        return self::runInTransaction(function (PDO $pdo) use (
            $branchId, $providerId, $variantId, $qty, $userId, $extra
        ) {
            $reserved = self::_adjustReserved($pdo, $branchId, $providerId, $variantId, -$qty);

            if (!empty($extra['session_id']) || !empty($extra['pos_order_id'])) {
                self::_expireReservation($pdo, $branchId, $providerId, $variantId, $qty, $extra);
            }

            return $reserved;
        });
    }

    /**
     * Deduct stock for a completed POS sale. Optionally release a reservation first.
     */
    public static function deductForSale(
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        int $userId,
        array $extra = []
    ): array {
        if ($qty <= 0) {
            throw new Exception('Sale quantity must be positive.');
        }

        $releaseReserved = (int) ($extra['release_reserved'] ?? 0);

        return self::runInTransaction(function (PDO $pdo) use (
            $branchId, $providerId, $variantId, $qty, $userId, $releaseReserved, $extra
        ) {
            if ($releaseReserved > 0) {
                $reservation = Database::fetch(
                    "SELECT reservation_id, reserved_qty
                     FROM ticket_stock_reservations
                     WHERE branch_id = :branch_id
                       AND provider_id = :provider_id
                       AND variant_id = :variant_id
                       AND reserved_qty >= :qty
                       AND (session_id = :session_id OR pos_order_id = :pos_order_id)
                     ORDER BY reservation_id ASC
                     LIMIT 1",
                    [
                        'branch_id' => $branchId,
                        'provider_id' => $providerId,
                        'variant_id' => $variantId,
                        'qty' => $releaseReserved,
                        'session_id' => $extra['session_id'] ?? null,
                        'pos_order_id' => $extra['pos_order_id'] ?? null,
                    ]
                );
                if ($reservation) {
                    self::_adjustReserved($pdo, $branchId, $providerId, $variantId, -$releaseReserved);
                    self::_expireReservation($pdo, $branchId, $providerId, $variantId, $releaseReserved, $extra);
                }
            }

            $movementData = [
                'reference_type'     => $extra['reference_type'] ?? 'POS_ORDER',
                'reference_id'       => $extra['reference_id'] ?? null,
                'ticket_number_from' => $extra['ticket_number_from'] ?? null,
                'ticket_number_to'   => $extra['ticket_number_to'] ?? null,
                'remarks'            => $extra['remarks'] ?? 'POS sale',
                'approved_by'        => $extra['approved_by'] ?? null,
            ];

            return self::_adjustOnHand($pdo, $branchId, $providerId, $variantId, -$qty, 'POS_SALE', $movementData, $userId);
        });
    }

    /**
     * Restore stock after a POS sale is cancelled. Used only for non-wallet variants.
     */
    public static function restoreOnSale(
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        int $userId,
        array $extra = []
    ): array {
        if ($qty <= 0) {
            throw new Exception('Restore quantity must be positive.');
        }

        $movementData = [
            'reference_type'     => $extra['reference_type'] ?? 'TICKET_TRANSACTION',
            'reference_id'       => $extra['reference_id'] ?? null,
            'ticket_number_from' => $extra['ticket_number_from'] ?? null,
            'ticket_number_to'   => $extra['ticket_number_to'] ?? null,
            'remarks'            => $extra['remarks'] ?? 'POS cancellation',
            'approved_by'        => $extra['approved_by'] ?? null,
        ];

        return self::adjustOnHand(
            $branchId,
            $providerId,
            $variantId,
            $qty,
            'POS_SALE_REVERSAL',
            $movementData,
            $userId
        );
    }

    /* ============================================================
       RECEIVE / DISPATCH / TRANSFER
       ============================================================ */

    /**
     * Receive stock into a destination branch (increase on_hand).
     */
    public static function receiveStock(
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        int $userId,
        array $extra = []
    ): array {
        if ($qty <= 0) {
            throw new Exception('Received quantity must be positive.');
        }

        $movementData = [
            'reference_type'     => $extra['reference_type'] ?? 'STOCK_REQUEST',
            'reference_id'       => $extra['reference_id'] ?? null,
            'source_branch_id'   => $extra['source_branch_id'] ?? null,
            'ticket_number_from' => $extra['ticket_number_from'] ?? null,
            'ticket_number_to'   => $extra['ticket_number_to'] ?? null,
            'remarks'            => $extra['remarks'] ?? 'Stock receipt',
            'approved_by'        => $extra['approved_by'] ?? null,
        ];

        return self::adjustOnHand($branchId, $providerId, $variantId, $qty, 'RECEIPT', $movementData, $userId);
    }

    /**
     * Dispatch stock out of a source branch (decrease on_hand).
     */
    public static function dispatchStock(
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        int $userId,
        array $extra = []
    ): array {
        if ($qty <= 0) {
            throw new Exception('Dispatch quantity must be positive.');
        }

        $movementData = [
            'reference_type'       => $extra['reference_type'] ?? 'STOCK_REQUEST',
            'reference_id'         => $extra['reference_id'] ?? null,
            'destination_branch_id' => $extra['destination_branch_id'] ?? null,
            'ticket_number_from'   => $extra['ticket_number_from'] ?? null,
            'ticket_number_to'     => $extra['ticket_number_to'] ?? null,
            'remarks'              => $extra['remarks'] ?? 'Stock dispatch',
            'approved_by'          => $extra['approved_by'] ?? null,
        ];

        return self::adjustOnHand($branchId, $providerId, $variantId, -$qty, 'DISPATCH', $movementData, $userId);
    }

    /**
     * Perform a full source -> destination transfer in one transaction.
     */
    public static function transferStock(
        int $sourceBranchId,
        int $destinationBranchId,
        int $providerId,
        int $variantId,
        int $qty,
        int $userId,
        array $extra = []
    ): array {
        if ($qty <= 0) {
            throw new Exception('Transfer quantity must be positive.');
        }

        return self::runInTransaction(function (PDO $pdo) use (
            $sourceBranchId, $destinationBranchId, $providerId, $variantId, $qty, $userId, $extra
        ) {
            $referenceId = $extra['reference_id'] ?? null;

            $dispatch = self::_adjustOnHand(
                $pdo,
                $sourceBranchId,
                $providerId,
                $variantId,
                -$qty,
                'DISPATCH',
                [
                    'reference_type'       => $extra['reference_type'] ?? 'STOCK_REQUEST',
                    'reference_id'         => $referenceId,
                    'destination_branch_id' => $destinationBranchId,
                    'ticket_number_from'   => $extra['ticket_number_from'] ?? null,
                    'ticket_number_to'     => $extra['ticket_number_to'] ?? null,
                    'remarks'              => $extra['remarks'] ?? 'Transfer dispatch',
                    'approved_by'          => $extra['approved_by'] ?? null,
                ],
                $userId
            );

            $receipt = self::_adjustOnHand(
                $pdo,
                $destinationBranchId,
                $providerId,
                $variantId,
                $qty,
                'RECEIPT',
                [
                    'reference_type'     => $extra['reference_type'] ?? 'STOCK_REQUEST',
                    'reference_id'       => $referenceId,
                    'source_branch_id'   => $sourceBranchId,
                    'ticket_number_from' => $extra['ticket_number_from'] ?? null,
                    'ticket_number_to'   => $extra['ticket_number_to'] ?? null,
                    'remarks'            => $extra['remarks'] ?? 'Transfer receipt',
                    'approved_by'        => $extra['approved_by'] ?? null,
                ],
                $userId
            );

            return [
                'dispatch_movement_id' => $dispatch['movement_id'],
                'receipt_movement_id'  => $receipt['movement_id'],
                'source_on_hand'       => $dispatch['on_hand_after'],
                'destination_on_hand'  => $receipt['on_hand_after'],
            ];
        });
    }

    /**
     * Manual adjustment of on_hand stock.
     */
    public static function adjustStock(
        int $branchId,
        int $providerId,
        int $variantId,
        int $delta,
        int $userId,
        int $approvedBy,
        ?string $reason = null
    ): array {
        return self::adjustOnHand(
            $branchId,
            $providerId,
            $variantId,
            $delta,
            'ADJUSTMENT',
            [
                'approved_by' => $approvedBy,
                'remarks'     => $reason ?? 'Manual adjustment',
            ],
            $userId
        );
    }

    /* ============================================================
       STOCK MOVEMENT LEDGER
       ============================================================ */

    /**
     * Insert a movement row. The caller is responsible for transaction context.
     */
    public static function createMovement(array $data): int
    {
        return self::runInTransaction(function (PDO $pdo) use ($data) {
            return self::_createMovement($pdo, $data);
        });
    }

    private static function _createMovement(PDO $pdo, array $data): int
    {
        $columns = [];
        $params  = [];

        foreach (self::MOVEMENT_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $columns[] = $col;
                $params[]  = $data[$col] ?? null;
            }
        }

        if (empty($columns)) {
            throw new Exception('No movement data provided.');
        }

        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO `ticket_stock_movements` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $pdo->lastInsertId();
    }

    /* ============================================================
       STOCK REQUEST WORKFLOW
       ============================================================ */

    /**
     * Check whether a status transition is allowed.
     */
    public static function isValidStatusTransition(string $fromStatus, string $toStatus): bool
    {
        return isset(self::STATUS_TRANSITIONS[$fromStatus])
            && in_array($toStatus, self::STATUS_TRANSITIONS[$fromStatus], true);
    }

    /**
     * Transition a stock request to a new status, setting the appropriate
     * audit columns (approved_by, dispatched_by, received_by, etc.).
     */
    public static function transitionRequestStatus(
        int $requestId,
        string $newStatus,
        int $userId,
        ?string $reason = null
    ): bool {
        return self::runInTransaction(function (PDO $pdo) use ($requestId, $newStatus, $userId, $reason) {
            $request = Database::fetch(
                "SELECT `stock_request_id`, `status` FROM `ticket_stock_requests` WHERE `stock_request_id` = :id",
                ['id' => $requestId]
            );

            if (!$request) {
                throw new Exception('Stock request not found.');
            }

            $current = $request['status'];

            if ($current === $newStatus) {
                return true;
            }

            if (!self::isValidStatusTransition($current, $newStatus)) {
                throw new Exception("Invalid status transition from {$current} to {$newStatus}.");
            }

            $fields = self::REQUEST_STATUS_FIELDS[$newStatus] ?? [];
            $setParts = ["`status` = :new_status"];
            $params   = [
                ':new_status' => $newStatus,
                ':id'        => $requestId,
            ];

            foreach ($fields as $field) {
                if ($field === 'requested_at' || str_ends_with($field, '_at')) {
                    $setParts[] = "`{$field}` = NOW()";
                } elseif ($field === 'rejection_reason' || $field === 'cancellation_reason') {
                    $setParts[] = "`{$field}` = :reason";
                    $params[':reason'] = $reason ?? '';
                } else {
                    $setParts[] = "`{$field}` = :user_id";
                }
            }

            // Store the user_id in all audit columns we update (except the reason/ timestamp columns)
            foreach ($fields as $field) {
                if (!in_array($field, ['requested_at', 'approved_at', 'dispatched_at', 'received_at', 'closed_at', 'rejection_reason', 'cancellation_reason'], true)) {
                    $params[':user_id'] = $userId;
                }
            }

            $sql = "UPDATE `ticket_stock_requests` SET " . implode(', ', $setParts) . " WHERE `stock_request_id` = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return true;
        });
    }

    /**
     * Default approver fallback chain for branch stock requests:
     * 1. Active MANAGER at destination branch.
     * 2. Active ADMIN (head-office or any).
     * 3. Active SUPER_ADMIN.
     * Returns user_id or null.
     */
    public static function getDefaultApprover(int $destinationBranchId): ?int
    {
        $user = Database::fetch(
            "SELECT u.`user_id`
             FROM `user_accounts` u
             JOIN `user_roles` r ON r.`role_id` = u.`role_id`
             WHERE u.`status` = 'active'
               AND (
                   (r.`role_code` = 'MANAGER' AND u.`branch_id` = :branch_id)
                   OR r.`role_code` IN ('ADMIN', 'SUPER_ADMIN')
               )
             ORDER BY FIELD(r.`role_code`, 'MANAGER', 'ADMIN', 'SUPER_ADMIN'), u.`user_id` ASC
             LIMIT 1",
            ['branch_id' => $destinationBranchId]
        );

        return $user ? (int) $user['user_id'] : null;
    }

    /* ============================================================
       DISCREPANCIES & RETURNED TICKETS
       ============================================================ */

    /**
     * Record a receiving discrepancy.
     */
    public static function recordDiscrepancy(
        int $stockRequestId,
        ?int $requestItemId,
        string $type,
        int $expectedQty,
        int $actualQty,
        int $reportedBy,
        ?string $notes = null
    ): int {
        $allowedTypes = ['SHORTAGE', 'EXCESS', 'DAMAGED', 'WRONG_VARIANT', 'SERIAL_MISMATCH', 'OTHER'];

        if (!in_array($type, $allowedTypes, true)) {
            throw new Exception('Invalid discrepancy type.');
        }

        return self::runInTransaction(function (PDO $pdo) use (
            $stockRequestId, $requestItemId, $type, $expectedQty, $actualQty, $reportedBy, $notes
        ) {
            $stmt = $pdo->prepare(
                "INSERT INTO `ticket_stock_discrepancies`
                    (`stock_request_id`, `request_item_id`, `discrepancy_type`, `expected_qty`, `actual_qty`, `status`, `resolution_notes`, `reported_by`, `reported_at`)
                 VALUES (:stock_request_id, :request_item_id, :type, :expected_qty, :actual_qty, 'OPEN', :notes, :reported_by, NOW())"
            );

            $stmt->execute([
                ':stock_request_id'  => $stockRequestId,
                ':request_item_id'   => $requestItemId,
                ':type'              => $type,
                ':expected_qty'      => $expectedQty,
                ':actual_qty'        => $actualQty,
                ':notes'             => $notes ?? '',
                ':reported_by'       => $reportedBy,
            ]);

            return (int) $pdo->lastInsertId();
        });
    }

    /**
     * Verify that a returned ticket number falls within the original issued range.
     * Supports numeric-only and prefixed series (e.g. PAL-123456).
     */
    public static function isTicketNumberInRange(
        string $ticketNumber,
        ?string $rangeFrom,
        ?string $rangeTo
    ): bool {
        if (empty($rangeFrom) || empty($rangeTo)) {
            // No range configured; cannot validate.
            return false;
        }

        $extractNumber = function (string $value): ?int {
            if (preg_match('/\D*(\d+)/', $value, $matches)) {
                return (int) $matches[1];
            }
            return null;
        };

        $num  = $extractNumber($ticketNumber);
        $from = $extractNumber($rangeFrom);
        $to   = $extractNumber($rangeTo);

        if ($num === null || $from === null || $to === null) {
            return false;
        }

        if ($from <= $to) {
            return $num >= $from && $num <= $to;
        }

        // Range may be stored high -> low
        return $num >= $to && $num <= $from;
    }

    /* ============================================================
       INTERNAL RESERVATION TABLE HELPERS
       ============================================================ */

    private static function _insertReservation(
        PDO $pdo,
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        array $extra
    ): void {
        $stmt = $pdo->prepare(
            "INSERT INTO `ticket_stock_reservations`
                (`branch_id`, `provider_id`, `variant_id`, `reserved_qty`, `pos_order_id`, `session_id`, `expires_at`)
             VALUES (:branch_id, :provider_id, :variant_id, :qty, :pos_order_id, :session_id, :expires_at)
             ON DUPLICATE KEY UPDATE
                `reserved_qty` = `reserved_qty` + VALUES(`reserved_qty`),
                `expires_at` = VALUES(`expires_at`)"
        );

        $stmt->execute([
            ':branch_id'    => $branchId,
            ':provider_id'  => $providerId,
            ':variant_id'   => $variantId,
            ':qty'          => $qty,
            ':pos_order_id' => $extra['pos_order_id'] ?? null,
            ':session_id'   => $extra['session_id'] ?? null,
            ':expires_at'   => $extra['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+10 minutes')),
        ]);
    }

    private static function _expireReservation(
        PDO $pdo,
        int $branchId,
        int $providerId,
        int $variantId,
        int $qty,
        array $extra
    ): void {
        $reservation = Database::fetch(
            "SELECT reservation_id, reserved_qty
             FROM `ticket_stock_reservations`
             WHERE `branch_id` = :branch_id
               AND `provider_id` = :provider_id
               AND `variant_id` = :variant_id
               AND (
                   `session_id` = :session_id
                   OR `pos_order_id` = :pos_order_id
               )
             ORDER BY `reservation_id` ASC
             LIMIT 1
             FOR UPDATE",
            [
                'branch_id' => $branchId,
                'provider_id' => $providerId,
                'variant_id' => $variantId,
                'session_id' => $extra['session_id'] ?? null,
                'pos_order_id' => $extra['pos_order_id'] ?? null,
            ]
        );
        if (!$reservation) {
            return;
        }

        $reservedQty = (int) $reservation['reserved_qty'];
        if ($reservedQty <= $qty) {
            Database::execute(
                'DELETE FROM ticket_stock_reservations WHERE reservation_id = :reservation_id',
                ['reservation_id' => (int) $reservation['reservation_id']]
            );
            return;
        }

        Database::execute(
            'UPDATE ticket_stock_reservations
             SET reserved_qty = reserved_qty - :qty
             WHERE reservation_id = :reservation_id',
            [
                'qty' => $qty,
                'reservation_id' => (int) $reservation['reservation_id'],
            ]
        );
    }
}
