<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/PosAccess.php';

final class TicketStockAccess
{
    public const APPROVER = 'APPROVER';
    public const RECEIVER = 'RECEIVER';

    public static function canManage(array $user): bool
    {
        return ($user['role_code'] ?? '') === 'SUPER_ADMIN'
            || Auth::can('MANAGE_TICKET_STOCK_ACCESS');
    }

    public static function canManageAllBranches(array $user): bool
    {
        return ($user['role_code'] ?? '') === 'SUPER_ADMIN'
            || Auth::can('VIEW_ALL_TICKET_STOCK');
    }

    public static function assertManagedBranch(array $user, int $branchId): void
    {
        if ($branchId <= 0) {
            throw new InvalidArgumentException('A valid branch is required.');
        }

        if (self::canManageAllBranches($user)) {
            return;
        }

        $allowedBranchIds = PosAccess::allowedBranchIds($user);
        if ($allowedBranchIds === null || in_array($branchId, $allowedBranchIds, true)) {
            return;
        }

        throw new RuntimeException('You are not authorized to manage assignments for this branch.');
    }

    public static function normalizeAccessType($value): string
    {
        $accessType = strtoupper(trim((string) $value));
        if (!in_array($accessType, [self::APPROVER, self::RECEIVER], true)) {
            throw new InvalidArgumentException('Access type must be APPROVER or RECEIVER.');
        }
        return $accessType;
    }

    public static function assignedBranchIds(array $user): array
    {
        $userId = (int) ($user['user_id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        try {
            $rows = Database::fetchAll(
                "SELECT DISTINCT a.branch_id
                 FROM ticket_stock_access_assignments a
                 JOIN business_branches b ON b.branch_id = a.branch_id AND b.status = 'active'
                 WHERE a.user_id = :user_id
                   AND a.is_active = 1",
                ['user_id' => $userId]
            );
        } catch (Throwable $e) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (array $row): int => (int) $row['branch_id'], $rows),
            static fn (int $branchId): bool => $branchId > 0
        )));
    }

    public static function has(array $user, int $branchId, string $accessType): bool
    {
        if (($user['role_code'] ?? '') === 'SUPER_ADMIN') {
            return true;
        }

        $accessType = self::normalizeAccessType($accessType);
        $permission = $accessType === self::APPROVER
            ? 'APPROVE_TICKET_STOCK_REQUEST'
            : 'RECEIVE_TICKET_STOCK';
        if (!Auth::can($permission)) {
            return false;
        }

        try {
            $row = Database::fetch(
                "SELECT a.assignment_id
                 FROM ticket_stock_access_assignments a
                 JOIN user_accounts u ON u.user_id = a.user_id AND u.status = 'active'
                 JOIN business_branches b ON b.branch_id = a.branch_id AND b.status = 'active'
                 WHERE a.user_id = :user_id
                   AND a.branch_id = :branch_id
                   AND a.access_type = :access_type
                   AND a.is_active = 1
                 LIMIT 1",
                [
                    'user_id' => (int) ($user['user_id'] ?? 0),
                    'branch_id' => $branchId,
                    'access_type' => $accessType,
                ]
            );
        } catch (Throwable $e) {
            return false;
        }

        return (bool) $row;
    }

    public static function assertCanApprove(array $user, int $branchId): void
    {
        if (($user['role_code'] ?? '') !== 'SUPER_ADMIN' && !Auth::can('APPROVE_TICKET_STOCK_REQUEST')) {
            throw new RuntimeException('Permission denied. You do not have approval permission.');
        }
        if (!self::has($user, $branchId, self::APPROVER)) {
            throw new RuntimeException('You are not assigned to approve ticket stock requests for this branch.');
        }
    }

    public static function assertCanReceive(array $user, int $branchId): void
    {
        if (($user['role_code'] ?? '') !== 'SUPER_ADMIN' && !Auth::can('RECEIVE_TICKET_STOCK')) {
            throw new RuntimeException('Permission denied. You do not have receiving permission.');
        }
        if (!self::has($user, $branchId, self::RECEIVER)) {
            throw new RuntimeException('You are not assigned to receive ticket stock for this branch.');
        }
    }

    public static function requestCapabilities(array $user, array $request): array
    {
        $branchId = (int) ($request['destination_branch_id'] ?? 0);
        $status = strtoupper((string) ($request['status'] ?? ''));

        return [
            'can_approve' => $status === 'SUBMITTED' && self::has($user, $branchId, self::APPROVER),
            'can_receive' => in_array($status, ['APPROVED', 'DISPATCHED', 'PARTIALLY_RECEIVED'], true)
                && self::has($user, $branchId, self::RECEIVER),
        ];
    }
}
