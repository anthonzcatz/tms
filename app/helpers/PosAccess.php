<?php
/**
 * POS authorization helpers for branch and cashier-session scoping.
 */
require_once __DIR__ . '/../../config/database.php';

final class PosAccess
{
    public static function allowedBranchIds(array $user): ?array
    {
        if (($user['role_code'] ?? '') === 'SUPER_ADMIN') {
            return null;
        }

        $branchValue = Auth::userBranchId();
        if ($branchValue === null || trim((string) $branchValue) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) $branchValue)),
            static fn (int $branchId): bool => $branchId > 0
        )));
    }

    public static function assertBranchAccess(array $user, int $branchId): void
    {
        if ($branchId <= 0) {
            throw new InvalidArgumentException('A valid branch is required.');
        }

        $allowedBranchIds = self::allowedBranchIds($user);
        if ($allowedBranchIds !== null && !in_array($branchId, $allowedBranchIds, true)) {
            throw new RuntimeException('You are not authorized to use this branch.');
        }

        $branch = Database::fetch(
            "SELECT branch_id FROM business_branches WHERE branch_id = :branch_id AND status = 'active'",
            ['branch_id' => $branchId]
        );
        if (!$branch) {
            throw new InvalidArgumentException('Invalid or inactive branch selected.');
        }
    }

    public static function sessionForUser(int $sessionId, array $user, bool $forUpdate = false): ?array
    {
        $sql = "SELECT * FROM cashier_sessions WHERE session_id = :session_id";
        $params = ['session_id' => $sessionId];
        $isManager = in_array($user['role_code'] ?? '', ['SUPER_ADMIN', 'MANAGER'], true);

        if (!$isManager) {
            $sql .= " AND cashier_user_id = :user_id";
            $params['user_id'] = (int) $user['user_id'];
        }

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $session = Database::fetch($sql, $params);
        if ($session) {
            self::assertBranchAccess($user, (int) $session['branch_id']);
        }

        return $session;
    }

    public static function assertSessionForTransaction(int $sessionId, int $branchId, array $user): array
    {
        self::assertBranchAccess($user, $branchId);

        $session = Database::fetch(
            "SELECT * FROM cashier_sessions
             WHERE session_id = :session_id
               AND status = 'OPEN'
               AND cashier_user_id = :user_id
               AND branch_id = :branch_id",
            ['session_id' => $sessionId, 'user_id' => (int) $user['user_id'], 'branch_id' => $branchId]
        );

        if (!$session) {
            throw new RuntimeException('No active cashier session found for this branch.');
        }

        return $session;
    }

    /**
     * Total cash change given to customers during the session.
     * Only counts change for orders that include at least one CASH payment,
     * because change is physically disbursed from the cash drawer.
     */
    public static function sessionTotalCashChange(int $sessionId, string $startedAt, ?string $endedAt = null): float
    {
        $where = "o.cashier_session_id = :sid AND o.change_amount > 0 AND o.created_at >= :start";
        $params = ['sid' => $sessionId, 'start' => $startedAt];
        if ($endedAt) {
            $where .= " AND o.created_at <= :end";
            $params['end'] = $endedAt;
        }

        $row = Database::fetch(
            "SELECT COALESCE(SUM(o.change_amount), 0) AS total_change
             FROM pos_orders o
             WHERE $where
               AND EXISTS (
                   SELECT 1
                   FROM pos_order_items oi
                   JOIN transaction_payments tp ON tp.source_id = oi.reference_id
                   JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
                   WHERE oi.order_id = o.order_id
                     AND (
                         (oi.item_type = 'TICKET' AND tp.source_type = 'TICKET_TRANSACTION')
                         OR (oi.item_type = 'SERVICE' AND tp.source_type = 'SERVICE_TRANSACTION')
                     )
                     AND pm.method_type = 'CASH'
               )",
            $params
        );
        return floatval($row['total_change'] ?? 0);
    }
}
