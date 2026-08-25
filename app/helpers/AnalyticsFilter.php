<?php

final class AnalyticsFilter
{
    private const MAX_RANGE_DAYS = 3660;

    public static function parse(array $query, array $user): array
    {
        $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');
        $range = strtolower(trim((string)($query['range'] ?? 'today')));
        $isCustom = strpos($range, 'custom') === 0;
        $granularity = self::resolveGranularity($range, $query['granularity'] ?? null, $isCustom);
        $today = new DateTimeImmutable('today', $timezone);

        if ($isCustom) {
            $start = self::parseDate($query['start_date'] ?? null, 'start_date', $timezone);
            $end = self::parseDate($query['end_date'] ?? null, 'end_date', $timezone);
            if ($granularity === 'monthly') {
                $start = $start->modify('first day of this month');
                $end = $end->modify('last day of this month');
            } elseif ($granularity === 'annual') {
                $start = $start->setDate((int)$start->format('Y'), 1, 1);
                $end = $end->setDate((int)$end->format('Y'), 12, 31);
            }
        } else {
            [$start, $end] = self::presetDates($range, $today);
        }

        if ($start > $end) {
            throw new InvalidArgumentException('start_date must be before or equal to end_date');
        }

        $days = $start->diff($end)->days + 1;
        if ($days > self::MAX_RANGE_DAYS) {
            throw new InvalidArgumentException('The selected date range is too large');
        }

        $accessibleBranchIds = self::accessibleBranchIds($user);
        $branchIdRaw = array_key_exists('branch_id', $query)
            ? trim((string)$query['branch_id'])
            : '';
        $branchId = null;

        if ($branchIdRaw !== '') {
            $branchId = IdEncoder::decode($branchIdRaw);
            if ($branchId === false || !in_array((int)$branchId, $accessibleBranchIds, true)) {
                throw new InvalidArgumentException('Access denied for this branch');
            }
            $branchId = (int)$branchId;
        }

        $endExclusive = $end->modify('+1 day');

        return [
            'range' => $isCustom ? 'custom' : $range,
            'requested_range' => $range,
            'granularity' => $granularity,
            'is_custom' => $isCustom,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'end_exclusive' => $endExclusive->format('Y-m-d'),
            'days' => $days,
            'branch_id' => $branchId,
            'branch_id_raw' => $branchIdRaw,
            'accessible_branch_ids' => $accessibleBranchIds,
            'is_all_branches' => $branchId === null,
        ];
    }

    public static function branchCondition(array $filter, string $column, string $prefix = 'analytics_branch'): array
    {
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '_', $prefix);
        if ($filter['branch_id'] !== null) {
            $key = $prefix . '_id';
            return [
                'sql' => $column . ' = :' . $key,
                'params' => [$key => $filter['branch_id']],
            ];
        }

        $branchIds = array_values(array_filter(array_map('intval', $filter['accessible_branch_ids']), static fn (int $id): bool => $id > 0));
        if (!$branchIds) {
            return ['sql' => '1 = 0', 'params' => []];
        }

        $placeholders = [];
        $params = [];
        foreach ($branchIds as $index => $branchId) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $branchId;
        }

        return [
            'sql' => $column . ' IN (' . implode(', ', $placeholders) . ')',
            'params' => $params,
        ];
    }

    public static function dateCondition(array $filter, string $column, string $prefix = 'analytics_date'): array
    {
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '_', $prefix);
        $startKey = $prefix . '_start';
        $endKey = $prefix . '_end';

        return [
            'sql' => $column . ' >= :' . $startKey . ' AND ' . $column . ' < :' . $endKey,
            'params' => [
                $startKey => $filter['start_date'],
                $endKey => $filter['end_exclusive'],
            ],
        ];
    }

    public static function responseMeta(array $filter): array
    {
        return [
            'range' => $filter['range'],
            'granularity' => $filter['granularity'],
            'start_date' => $filter['start_date'],
            'end_date' => $filter['end_date'],
            'branch_id' => $filter['branch_id_raw'],
            'branch_scope' => $filter['is_all_branches'] ? 'all' : 'selected',
        ];
    }

    private static function resolveGranularity(string $range, ?string $requested, bool $isCustom): string
    {
        if ($isCustom) {
            $requested = strtolower(trim((string)$requested));
            if ($requested === '') {
                if ($range === 'custom-monthly') {
                    return 'monthly';
                }
                if ($range === 'custom-annual') {
                    return 'annual';
                }
                return 'daily';
            }
            if (!in_array($requested, ['daily', 'monthly', 'annual'], true)) {
                throw new InvalidArgumentException('Invalid custom range granularity');
            }
            return $requested;
        }

        return match ($range) {
            'today' => 'hourly',
            'year' => 'monthly',
            'month', 'week', 'last30days' => 'daily',
            default => throw new InvalidArgumentException('Invalid analytics range'),
        };
    }

    private static function presetDates(string $range, DateTimeImmutable $today): array
    {
        return match ($range) {
            'today' => [$today, $today],
            'week' => [$today->modify('-6 days'), $today],
            'last30days' => [$today->modify('-29 days'), $today],
            'month' => [$today->modify('first day of this month'), $today],
            'year' => [$today->setDate((int)$today->format('Y'), 1, 1), $today],
            default => throw new InvalidArgumentException('Invalid analytics range'),
        };
    }

    private static function parseDate($value, string $field, DateTimeZone $timezone): DateTimeImmutable
    {
        $value = trim((string)$value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

        if ($value === '' || !$date || $hasErrors || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($field . ' must use YYYY-MM-DD format');
        }

        return $date;
    }

    private static function accessibleBranchIds(array $user): array
    {
        $roleCode = Auth::userRoleCode() ?? ($user['role_code'] ?? '');
        if ($roleCode === 'SUPER_ADMIN') {
            $rows = Database::fetchAll("SELECT branch_id FROM business_branches WHERE status = 'active'");
            return array_values(array_filter(array_map('intval', array_column($rows, 'branch_id')), static fn (int $id): bool => $id > 0));
        }

        $branchValue = Auth::userBranchId() ?? ($user['branch_id'] ?? null);
        $branchIds = array_map('intval', explode(',', (string) $branchValue));
        return array_values(array_unique(array_filter($branchIds, static fn (int $id): bool => $id > 0)));
    }
}
