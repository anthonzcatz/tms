<?php
/**
 * ProviderWalletDeductionService
 *
 * Resolves provider-wallet deduction policy and calculates the amount for each
 * POS wallet movement. The policy belongs to the provider that owns the
 * resolved wallet, which also supports parent-provider wallet fallback.
 */

require_once __DIR__ . '/../../config/database.php';

final class ProviderWalletDeductionService
{
    public const MODE_ALL_CHARGES = 'all_charges';
    public const MODE_BASE_ONLY = 'base_only';
    public const MODE_NONE = 'none';

    /**
     * Load the policy from the provider that owns a wallet.
     *
     * The legacy Void Fee column is used only when the new columns are not yet
     * available, so an older deployment keeps its previous behavior while the
     * new migration is being rolled out.
     */
    public static function forWallet(?int $walletId): array
    {
        $policy = self::defaultPolicy($walletId);
        if (!$walletId || $walletId < 1) {
            return $policy;
        }

        $wallet = null;
        try {
            $wallet = Database::fetch(
                "SELECT pw.wallet_id, pw.provider_id, pw.branch_id, pw.variant_id,
                        pw.status, pw.current_balance,
                        tp.wallet_deduct_all_charges,
                        tp.wallet_deduct_base_only
                 FROM provider_wallets pw
                 LEFT JOIN ticket_providers tp ON tp.provider_id = pw.provider_id
                 WHERE pw.wallet_id = :wallet_id
                 LIMIT 1",
                ['wallet_id' => $walletId]
            );
        } catch (Throwable $e) {
            // Fall back to the pre-migration provider setting.
            try {
                $wallet = Database::fetch(
                    "SELECT pw.wallet_id, pw.provider_id, pw.branch_id, pw.variant_id,
                            pw.status, pw.current_balance,
                            tp.void_fee_wallet_enabled
                     FROM provider_wallets pw
                     LEFT JOIN ticket_providers tp ON tp.provider_id = pw.provider_id
                     WHERE pw.wallet_id = :wallet_id
                     LIMIT 1",
                    ['wallet_id' => $walletId]
                );
                if ($wallet) {
                    $wallet['wallet_deduct_all_charges'] = (int) ($wallet['void_fee_wallet_enabled'] ?? 0);
                    $wallet['wallet_deduct_base_only'] = 1;
                }
            } catch (Throwable $legacyException) {
                return $policy;
            }
        }

        if (!$wallet) {
            return $policy;
        }

        $allCharges = (int) ($wallet['wallet_deduct_all_charges'] ?? 0) === 1;
        $baseOnly = (int) ($wallet['wallet_deduct_base_only'] ?? 1) === 1;

        return [
            'wallet_id' => (int) ($wallet['wallet_id'] ?? $walletId),
            'provider_id' => (int) ($wallet['provider_id'] ?? 0),
            'branch_id' => $wallet['branch_id'] !== null ? (int) $wallet['branch_id'] : null,
            'variant_id' => !empty($wallet['variant_id']) ? (int) $wallet['variant_id'] : null,
            'wallet_status' => $wallet['status'] ?? null,
            'current_balance' => (float) ($wallet['current_balance'] ?? 0),
            'wallet_deduct_all_charges' => $allCharges ? 1 : 0,
            'wallet_deduct_base_only' => $baseOnly ? 1 : 0,
            'all_charges_enabled' => $allCharges,
            'base_only_enabled' => $baseOnly,
            'mode' => self::mode($allCharges, $baseOnly),
        ];
    }

    public static function mode(bool $allCharges, bool $baseOnly): string
    {
        if ($allCharges) {
            return self::MODE_ALL_CHARGES;
        }
        return $baseOnly ? self::MODE_BASE_ONLY : self::MODE_NONE;
    }

    /**
     * Calculate the normal POS sale movement from the stored ticket amounts.
     */
    public static function saleDebit(array $ticket, array $policy): array
    {
        $baseAmount = self::amount($ticket['base_amount'] ?? 0);
        $serviceFee = self::amount($ticket['service_fee'] ?? 0);
        $isVariant = !empty($ticket['variant_id']) || !empty($policy['variant_id']);
        $mode = $policy['mode'] ?? self::MODE_BASE_ONLY;

        $debitAmount = 0.0;
        if (!$isVariant) {
            $debitAmount = match ($mode) {
                self::MODE_ALL_CHARGES => self::amount($baseAmount + $serviceFee),
                self::MODE_BASE_ONLY => $baseAmount,
                default => 0.0,
            };
        }

        return [
            'mode' => $mode,
            'base_amount' => $baseAmount,
            'service_fee' => $serviceFee,
            'amount' => $debitAmount,
            'is_variant' => $isVariant,
        ];
    }

    /**
     * Calculate regular Void fees that should be charged to a provider wallet.
     * Technical-issue Lost Sales fees are intentionally excluded for now.
     */
    public static function voidFeeDebit(array $cancellation, array $policy, string $reasonCategory = ''): float
    {
        if (($policy['mode'] ?? self::MODE_BASE_ONLY) !== self::MODE_ALL_CHARGES) {
            return 0.0;
        }

        if (in_array(strtoupper($reasonCategory), ['PRINTER_ERROR', 'SYSTEM_ERROR', 'CANCEL'], true)) {
            return 0.0;
        }

        return self::amount($cancellation['void_fee'] ?? 0)
            + self::amount($cancellation['void_service_fee'] ?? 0);
    }

    /**
     * Find the original sale movement for a ticket.
     */
    public static function originalSaleMovement(int $transactionId): ?array
    {
        if ($transactionId <= 0) {
            return null;
        }

        try {
            return Database::fetch(
                "SELECT wallet_txn_id, wallet_id, amount, balance_before, balance_after
                 FROM wallet_transactions
                 WHERE idempotency_key = :idempotency_key
                   AND txn_type = 'SALE'
                   AND direction = 'OUT'
                 LIMIT 1",
                ['idempotency_key' => 'pos-sale:' . $transactionId]
            );
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve how much of the original sale should be restored.
     *
     * Full refunds and Voids restore the exact original SALE amount. Partial
     * refunds are capped by that amount. A missing original ledger row falls
     * back to the historical base-only amount and never invents a service-fee
     * credit that was not recorded at sale time.
     */
    public static function restoration(array $ticketTxn, float $refundAmount, array $cancellation): array
    {
        $originalSale = self::originalSaleMovement((int) ($ticketTxn['transaction_id'] ?? 0));
        $walletPolicy = self::forWallet(
            !empty($ticketTxn['wallet_id']) ? (int) $ticketTxn['wallet_id'] : null
        );
        $baseFallback = ($walletPolicy['mode'] ?? self::MODE_BASE_ONLY) === self::MODE_NONE
            ? 0.0
            : self::amount($ticketTxn['base_amount'] ?? 0);
        $originalAmount = $originalSale
            ? self::amount($originalSale['amount'] ?? 0)
            : $baseFallback;
        $operationType = strtoupper((string) ($cancellation['operation_type'] ?? 'REFUND'));
        $cancellationType = strtolower((string) ($cancellation['cancellation_type'] ?? ''));
        $ticketTotal = self::amount($ticketTxn['total_amount'] ?? 0);
        $ticketServiceFee = self::amount($ticketTxn['service_fee'] ?? 0);
        $refundableTicketAmount = max(0, $ticketTotal - $ticketServiceFee);
        $grossRefundAmount = self::amount($cancellation['gross_refund_amount'] ?? $refundAmount);
        $isFull = $operationType === 'VOID'
            || $cancellationType === 'full'
            || ($refundableTicketAmount > 0 && $grossRefundAmount >= $refundableTicketAmount);
        $requestedAmount = self::amount($refundAmount);
        $restoreAmount = $isFull
            ? $originalAmount
            : min($requestedAmount, $originalAmount);

        return [
            'amount' => self::amount($restoreAmount),
            'original_amount' => $originalAmount,
            'wallet_txn_id' => $originalSale ? (int) ($originalSale['wallet_txn_id'] ?? 0) : null,
            'used_legacy_fallback' => !$originalSale,
        ];
    }

    private static function defaultPolicy(?int $walletId): array
    {
        return [
            'wallet_id' => $walletId ? (int) $walletId : null,
            'provider_id' => 0,
            'branch_id' => null,
            'variant_id' => null,
            'wallet_status' => null,
            'current_balance' => 0.0,
            'wallet_deduct_all_charges' => 0,
            'wallet_deduct_base_only' => 1,
            'all_charges_enabled' => false,
            'base_only_enabled' => true,
            'mode' => self::MODE_BASE_ONLY,
        ];
    }

    private static function amount($value): float
    {
        return round(max(0, (float) $value), 2);
    }
}
