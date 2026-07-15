<?php
/**
 * WalletResolver
 * Resolves the correct wallet for a provider/branch combination.
 * If the provider has its own wallet, use it. Otherwise walk up the parent chain.
 */
class WalletResolver {

    /**
     * Resolve the active wallet for a provider and branch.
     * Returns the provider_wallets row, or false if none found.
     *
     * @param int $providerId Operating provider ID
     * @param int $branchId Branch ID
     * @return array|false
     */
    public static function resolve($providerId, $branchId) {
        $providerId = (int) $providerId;
        $branchId = (int) $branchId;

        if ($providerId <= 0 || $branchId <= 0) {
            return false;
        }

        return self::resolveRecursive($providerId, $branchId, []);
    }

    /**
     * Resolve with cycle protection.
     */
    private static function resolveRecursive($providerId, $branchId, array $visited) {
        if (isset($visited[$providerId])) {
            return false;
        }
        $visited[$providerId] = true;

        $wallet = self::getWallet($providerId, $branchId);
        if ($wallet) {
            return $wallet;
        }

        $parentId = self::getParentProviderId($providerId);
        if ($parentId) {
            return self::resolveRecursive($parentId, $branchId, $visited);
        }

        return false;
    }

    /**
     * Get active wallet for provider + branch.
     */
    private static function getWallet($providerId, $branchId) {
        return Database::fetch(
            "SELECT * FROM provider_wallets
             WHERE provider_id = :provider_id
               AND branch_id = :branch_id
               AND status = 'active'
             LIMIT 1",
            [
                'provider_id' => (int) $providerId,
                'branch_id' => (int) $branchId,
            ]
        ) ?: false;
    }

    /**
     * Get parent provider ID for a provider.
     */
    private static function getParentProviderId($providerId) {
        $provider = Database::fetch(
            "SELECT parent_provider_id FROM ticket_providers WHERE provider_id = :provider_id LIMIT 1",
            ['provider_id' => (int) $providerId]
        );

        return $provider && !empty($provider['parent_provider_id']) ? (int) $provider['parent_provider_id'] : false;
    }
}
