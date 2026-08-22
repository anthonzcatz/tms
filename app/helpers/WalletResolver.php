<?php
/**
 * WalletResolver
 * Resolves the correct active wallet for a provider/branch combination.
 * If the provider has no wallet, it falls back to the parent (main) provider wallet.
 */
require_once __DIR__ . '/../../config/database.php';

class WalletResolver {

    /**
     * Log a wallet fallback to the activity log so silent fallbacks are visible.
     */
    private static function logFallback(int $requestedProviderId, int $resolvedProviderId, int $branchId, ?int $variantId): void {
        try {
            Database::execute(
                "INSERT INTO activity_logs
                    (user_id, device_id, action, module_name, reference_code, ip_address, old_value, new_value, created_at)
                 VALUES (:user_id, NULL, 'WALLET_RESOLVER_FALLBACK', 'WALLET_RESOLVER', NULL, :ip, :old, :new, NOW())",
                [
                    'user_id' => $_SESSION['user']['user_id'] ?? null,
                    'ip'  => $_SERVER['REMOTE_ADDR'] ?? 'cli',
                    'old' => json_encode([
                        'requested_provider_id' => $requestedProviderId,
                        'branch_id'             => $branchId,
                        'variant_id'            => $variantId,
                    ]),
                    'new' => json_encode([
                        'resolved_provider_id' => $resolvedProviderId,
                    ]),
                ]
            );
        } catch (Exception $e) {
            // Never block wallet resolution because of logging failure.
            error_log('WalletResolver fallback logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the active wallet for a provider and branch.
     * Returns the provider_wallets row, or false if none found.
     * Falls back to the parent provider wallet when the operating provider has no wallet.
     *
     * @param int $providerId Operating provider ID
     * @param int $branchId Branch ID
     * @param int|null $variantId Optional ticket variant ID
     * @return array|false
     */
    public static function resolve($providerId, $branchId, $variantId = null) {
        $providerId = (int) $providerId;
        $branchId = (int) $branchId;
        $variantId = $variantId ? (int) $variantId : null;

        if ($providerId <= 0 || $branchId <= 0) {
            return false;
        }

        return self::getWallet($providerId, $branchId, $variantId);
    }

    /**
     * Get active wallet for provider + branch + optional variant.
     * If a variant wallet is requested and not found, fall back to the provider-level wallet.
     * If the provider has no wallet at all, fall back to the parent (main) provider wallet.
     */
    private static function getWallet($providerId, $branchId, $variantId = null, ?int $originalProviderId = null) {
        $originalProviderId = $originalProviderId ?? $providerId;

        if ($variantId) {
            $wallet = Database::fetch(
                "SELECT * FROM provider_wallets
                 WHERE provider_id = :provider_id
                   AND branch_id = :branch_id
                   AND variant_id = :variant_id
                   AND status = 'active'
                 LIMIT 1",
                [
                    'provider_id' => (int) $providerId,
                    'branch_id' => (int) $branchId,
                    'variant_id' => (int) $variantId,
                ]
            );
            if ($wallet) {
                return $wallet;
            }
        }

        $wallet = Database::fetch(
            "SELECT * FROM provider_wallets
             WHERE provider_id = :provider_id
               AND branch_id = :branch_id
               AND variant_id IS NULL
               AND status = 'active'
             LIMIT 1",
            [
                'provider_id' => (int) $providerId,
                'branch_id' => (int) $branchId,
            ]
        );
        if ($wallet) {
            // Variant wallet was requested but resolved to provider-level wallet.
            if ($variantId && (int) $providerId === $originalProviderId) {
                self::logFallback($originalProviderId, (int) $wallet['provider_id'], $branchId, $variantId);
            }
            return $wallet;
        }

        // If no wallet found and this provider has a parent, recurse to the parent provider
        $parent = Database::fetch(
            "SELECT parent_provider_id FROM ticket_providers WHERE provider_id = :provider_id",
            ['provider_id' => (int) $providerId]
        );
        if ($parent && !empty($parent['parent_provider_id'])) {
            $resolved = self::getWallet((int) $parent['parent_provider_id'], $branchId, $variantId, $originalProviderId);
            if ($resolved) {
                self::logFallback($originalProviderId, (int) $resolved['provider_id'], $branchId, $variantId);
            }
            return $resolved;
        }

        return false;
    }
}
