<?php
/**
 * Server-authoritative cashier transportation/provider access rules.
 */
require_once __DIR__ . '/../../config/database.php';

final class CashierTransportAccess
{
    private static array $contextCache = [];
    private static ?array $supportedTransportTypes = null;

    /**
     * Return the transportation/provider types that may be assigned to cashiers.
     * Driven by the active provider_types lookup table so new types are
     * automatically supported without code changes.
     */
    public static function getSupportedTransportTypes(): array
    {
        if (self::$supportedTransportTypes === null) {
            self::$supportedTransportTypes = array_column(Database::getProviderTypes(), 'type_code');
        }
        return self::$supportedTransportTypes;
    }

    public static function context(array $user): array
    {
        $userId = (int) ($user['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['restricted' => true, 'provider_ids' => [], 'transport_types' => []];
        }
        if (isset(self::$contextCache[$userId])) {
            return self::$contextCache[$userId];
        }

        $account = Database::fetch(
            "SELECT ua.has_restricted_transport, ur.role_code
             FROM user_accounts ua
             LEFT JOIN user_roles ur ON ur.role_id = ua.role_id
             WHERE ua.user_id = :user_id
             LIMIT 1",
            ['user_id' => $userId]
        );
        $restricted = ($account['role_code'] ?? $user['role_code'] ?? '') === 'CASHIER'
            && (int) ($account['has_restricted_transport'] ?? 0) === 1;

        $assignments = $restricted
            ? Database::fetchAll(
                "SELECT provider_id, transport_type
                 FROM cashier_transport_assignments
                 WHERE user_id = :user_id",
                ['user_id' => $userId]
            )
            : [];
        $providerMap = $restricted ? self::providerMap() : [];

        $context = [
            'restricted' => $restricted,
            'provider_ids' => array_values(array_unique(array_filter(
                array_map(static fn(array $row): int => (int) ($row['provider_id'] ?? 0), $assignments),
                static function (int $providerId) use ($providerMap): bool {
                    return $providerId > 0
                        && isset($providerMap[$providerId])
                        && ($providerMap[$providerId]['status'] ?? '') === 'active'
                        && in_array($providerMap[$providerId]['provider_type'] ?? '', self::getSupportedTransportTypes(), true);
                }
            ))),
            'transport_types' => array_values(array_unique(array_filter(
                array_map(static fn(array $row): string => (string) ($row['transport_type'] ?? ''), $assignments),
                static fn(string $type): bool => in_array($type, self::getSupportedTransportTypes(), true)
            ))),
        ];

        self::$contextCache[$userId] = $context;
        return $context;
    }

    public static function isAllowed(array $user, int $providerId): bool
    {
        if ($providerId <= 0) {
            return false;
        }

        $providers = self::providerMap();
        if (!isset($providers[$providerId])
            || ($providers[$providerId]['status'] ?? '') !== 'active'
            || !in_array($providers[$providerId]['provider_type'] ?? '', self::getSupportedTransportTypes(), true)) {
            return false;
        }

        $context = self::context($user);
        if (!$context['restricted']) {
            return true;
        }

        if (!$context['provider_ids']
            && in_array($providers[$providerId]['provider_type'], $context['transport_types'], true)) {
            return true;
        }

        $currentId = $providerId;
        $visited = [];
        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            if (in_array($currentId, $context['provider_ids'], true)
                && ($providers[$currentId]['status'] ?? '') === 'active') {
                return true;
            }
            $currentId = (int) ($providers[$currentId]['parent_provider_id'] ?? 0);
        }

        return false;
    }

    public static function assertAllowed(array $user, int $providerId): void
    {
        if (!self::isAllowed($user, $providerId)) {
            throw new RuntimeException('You are not authorized to sell tickets for this provider.');
        }
    }

    public static function allowedWalletProviderIds(array $user): ?array
    {
        $context = self::context($user);
        if (!$context['restricted']) {
            return null;
        }

        $providers = self::providerMap();
        $allowedRoots = [];
        foreach ($providers as $providerId => $provider) {
            if (($provider['status'] ?? '') !== 'active'
                || !in_array($provider['provider_type'] ?? '', self::getSupportedTransportTypes(), true)) {
                continue;
            }
            if ($context['provider_ids']
                ? !self::chainContainsAny($providerId, $context['provider_ids'], $providers)
                : !in_array($provider['provider_type'] ?? '', $context['transport_types'], true)) {
                continue;
            }

            $rootId = $providerId;
            $visited = [];
            while ($rootId > 0 && !isset($visited[$rootId])) {
                $visited[$rootId] = true;
                $parentId = (int) ($providers[$rootId]['parent_provider_id'] ?? 0);
                if ($parentId <= 0) {
                    break;
                }
                $rootId = $parentId;
            }
            $allowedRoots[] = $rootId;
        }

        return array_values(array_unique(array_filter($allowedRoots)));
    }

    public static function filterProviders(array $providers, array $user): array
    {
        $context = self::context($user);
        if (!$context['restricted']) {
            return array_values(array_filter(
                $providers,
                static fn(array $provider): bool => ($provider['status'] ?? 'active') === 'active'
                    && in_array($provider['provider_type'] ?? '', self::getSupportedTransportTypes(), true)
            ));
        }

        $providerMap = [];
        foreach ($providers as $provider) {
            $providerMap[(int) ($provider['provider_id'] ?? 0)] = $provider;
        }

        return array_values(array_filter(
            $providers,
            function (array $provider) use ($context, $providerMap): bool {
                $providerId = (int) ($provider['provider_id'] ?? 0);
                if ($providerId <= 0
                    || ($provider['status'] ?? '') !== 'active'
                    || !in_array($provider['provider_type'] ?? '', self::getSupportedTransportTypes(), true)) {
                    return false;
                }
                if (!$context['provider_ids']
                    && in_array($provider['provider_type'] ?? '', $context['transport_types'], true)) {
                    return true;
                }

                $currentId = $providerId;
                $visited = [];
                while ($currentId > 0 && !isset($visited[$currentId])) {
                    $visited[$currentId] = true;
                    if (in_array($currentId, $context['provider_ids'], true)
                        && ($providerMap[$currentId]['status'] ?? '') === 'active') {
                        return true;
                    }
                    $currentId = (int) ($providerMap[$currentId]['parent_provider_id'] ?? 0);
                }
                return false;
            }
        ));
    }

    public static function invalidate(int $userId): void
    {
        unset(self::$contextCache[$userId]);
    }

    private static function chainContainsAny(int $providerId, array $allowedProviderIds, array $providers): bool
    {
        $currentId = $providerId;
        $visited = [];
        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            if (in_array($currentId, $allowedProviderIds, true)
                && ($providers[$currentId]['status'] ?? '') === 'active') {
                return true;
            }
            $currentId = (int) ($providers[$currentId]['parent_provider_id'] ?? 0);
        }
        return false;
    }

    private static function isActiveProvider(int $providerId): bool
    {
        $typePlaceholders = [];
        $typeParams = [];
        foreach (self::getSupportedTransportTypes() as $index => $type) {
            $key = 'provider_type_' . $index;
            $typePlaceholders[] = ':' . $key;
            $typeParams[$key] = $type;
        }

        $provider = Database::fetch(
            "SELECT provider_id FROM ticket_providers
             WHERE provider_id = :provider_id
               AND status = 'active'
               AND provider_type IN (" . implode(',', $typePlaceholders) . ")
             LIMIT 1",
            array_merge(['provider_id' => $providerId], $typeParams)
        );
        return (bool) $provider;
    }

    private static function providerMap(): array
    {
        static $providers = null;
        if ($providers !== null) {
            return $providers;
        }

        $rows = Database::fetchAll(
            "SELECT provider_id, parent_provider_id, provider_type, status
             FROM ticket_providers"
        );
        $providers = [];
        foreach ($rows as $provider) {
            $providers[(int) $provider['provider_id']] = $provider;
        }
        return $providers;
    }
}
