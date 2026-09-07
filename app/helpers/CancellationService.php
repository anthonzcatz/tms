<?php
/**
 * CancellationService
 *
 * Shared helper for applying the financial/restorative effects of a ticket cancellation.
 * This service centralizes the duplicated logic that previously existed in both
 * api/pos/ticket-cancel.php (immediate cancellation) and api/pos/cancellation-approval.php
 * (manager approval of a pending cancellation).
 *
 * Responsibilities:
 * - Mark the ticket transaction as cancelled.
 * - Reverse the CHARGE portion from customer_charges.
 * - Resolve the correct wallet and credit the proportional base amount.
 * - Record a wallet_transactions ledger row for the refund.
 * - Zero the pos_order_items row and update pos_orders.total_refunded_amount.
 * - Insert a ticket_refunds record.
 *
 * This service does NOT manage:
 * - Creating/updating the ticket_cancellations request row.
 * - Cashier session total_refunds_wallet adjustments.
 * - Final JSON response or activity logging (it returns data the caller can log).
 *
 * All operations run inside the caller's existing database transaction.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/BalanceLedgerService.php';
require_once __DIR__ . '/ProviderWalletDeductionService.php';
require_once __DIR__ . '/TicketStockHelper.php';
require_once __DIR__ . '/ChargeService.php';
require_once __DIR__ . '/RefundService.php';
require_once __DIR__ . '/WalletResolver.php';

final class CancellationService
{
    /**
     * Apply the financial/restorative effects of an approved ticket cancellation.
     *
     * @param array   $ticketTxn          Full ticket_transactions row.
     * @param array   $cancellation        Full ticket_cancellations row (already created/updated by caller).
     * @param float   $refundAmount        Total refund amount requested/approved.
     * @param float   $cashRefundAmount    Cash portion of the refund (refundAmount - chargeAmount).
     * @param float   $chargeAmount        Charge/debt portion to reverse from customer_charges.
     * @param int     $processedByUserId   User ID processing/approving the cancellation.
     * @param int     $processingDays      Number of days before refund status becomes completed (0 = completed immediately).
     * @param string  $reason              Cancellation reason from the original request.
     * @param string|null $remarks         Optional approver/manager remarks.
     * @return array  Data about the financial effects, suitable for response and logging.
     * @throws Exception if any required wallet/order data is missing.
     */
    public static function processCancellationEffects(
        array $ticketTxn,
        array $cancellation,
        float $refundAmount,
        float $cashRefundAmount,
        float $chargeAmount,
        int $processedByUserId,
        int $processingDays,
        string $reason,
        ?string $remarks
    ): array {
        $ticketTxnId = (int) $ticketTxn['transaction_id'];
        $passengerId = $ticketTxn['passenger_id'] ?? null;
        $refundPreview = RefundService::previewPaymentAllocations(
            'TICKET_TRANSACTION',
            $ticketTxnId,
            $refundAmount
        );
        $effectiveCashRefundAmount = (float) ($refundPreview['cash_amount'] ?? 0);
        $effectiveChargeAmount = (float) ($refundPreview['charge_amount'] ?? 0);

        // 1. Mark ticket as cancelled. The caller has already locked and
        // approved the cancellation request inside the same transaction.
        Database::execute(
            "UPDATE ticket_transactions SET status = 'cancelled' WHERE transaction_id = :tid",
            ['tid' => $ticketTxnId]
        );

        // 2. Credit the exact original provider wallet. The wallet amount is
        // capped at the original provider cost because service fees are not a
        // provider-wallet charge.
        $walletResult = self::creditWalletForRefund(
            $ticketTxn,
            $refundAmount,
            $cancellation,
            $processedByUserId,
            $reason,
            $remarks
        );

        // Variant tickets are consumed at sale time and are never returned to available stock.
        // Provider-wallet restoration is also skipped for variants by creditWalletForRefund().

        // 4. Reduce the order item by the entered refund instead of always
        // zeroing it; this preserves partial-refund reporting.
        $orderItem = Database::fetch(
            "SELECT oi.item_id, oi.order_id, oi.total_amount
             FROM pos_order_items oi
             WHERE oi.reference_id = :tid AND oi.item_type = 'TICKET'
             LIMIT 1",
            ['tid' => $ticketTxnId]
        );

        if ($orderItem) {
            Database::execute(
                "UPDATE pos_order_items
                 SET total_amount = GREATEST(0, total_amount - :ramount)
                 WHERE item_id = :iid",
                ['ramount' => $refundAmount, 'iid' => $orderItem['item_id']]
            );
            Database::execute(
                "UPDATE pos_orders
                 SET total_refunded_amount = COALESCE(total_refunded_amount, 0) + :ramount
                 WHERE order_id = :oid",
                ['ramount' => $refundAmount, 'oid' => $orderItem['order_id']]
            );
        }

        // 5. Insert the refund record, then apply the exact source allocation
        // (cash, CHARGE, bank/e-wallet, or other) against that record.
        $refundRecord = self::createTicketRefund(
            $ticketTxn,
            $cancellation,
            $refundAmount,
            $effectiveCashRefundAmount,
            $effectiveChargeAmount,
            $processedByUserId,
            $processingDays
        );

        $allocationResult = RefundService::processPaymentAllocations(
            'TICKET',
            $refundRecord['refund_id'],
            'TICKET_TRANSACTION',
            $ticketTxnId,
            $refundAmount,
            $processedByUserId,
            !empty($cancellation['cashier_session_id']) ? (int) $cancellation['cashier_session_id'] : null,
            $passengerId ? (int) $passengerId : null,
            $remarks ?: $reason
        );

        Database::execute(
            "UPDATE ticket_cancellations
             SET charge_amount = :charge_amount,
                 cash_refund_amount = :cash_refund_amount,
                 status = 'completed',
                 processed_at = NOW()
             WHERE cancellation_id = :cancellation_id",
            [
                'charge_amount' => $allocationResult['charge_amount'],
                'cash_refund_amount' => $allocationResult['cash_amount'],
                'cancellation_id' => (int) $cancellation['cancellation_id'],
            ]
        );

        return [
            'wallet_id'             => $walletResult['wallet_id'],
            'wallet_refund_amount'  => $walletResult['wallet_refund_amount'],
            'wallet_balance_before' => $walletResult['balance_before'],
            'wallet_balance_after'  => $walletResult['balance_after'],
            'wallet_txn_code'       => $walletResult['txn_code'],
            'wallet_original_sale_amount' => $walletResult['original_sale_amount'] ?? $walletResult['wallet_refund_amount'],
            'wallet_used_legacy_fallback' => $walletResult['used_legacy_fallback'] ?? false,
            'refund_status'         => $refundRecord['status'],
            'refund_record_id'      => $refundRecord['refund_id'],
            'cash_refund_amount'    => $allocationResult['cash_amount'],
            'charge_reversal_amount' => $allocationResult['charge_amount'],
            'bank_refund_amount'    => $allocationResult['bank_amount'],
            'payment_allocations'   => $allocationResult['allocations'],
            'is_consumed_variant'   => !empty($ticketTxn['variant_id']) || !empty($walletResult['is_consumed_variant']),
        ];
    }

    /**
     * Reverse the customer_charges balance for a passenger by the given charge amount,
     * maintaining the base and service fee split.
     */
    public static function reverseCustomerCharge(int $passengerId, float $chargeAmount): void
    {
        if ($chargeAmount <= 0) {
            return;
        }

        ChargeService::reverseCustomerCharge($passengerId, $chargeAmount);
    }

    /**
     * Compute the total CHARGE payment amount for a ticket transaction.
     */
    public static function computeChargePaymentsTotal(int $ticketTxnId): float
    {
        return floatval(Database::fetch(
            "SELECT COALESCE(SUM(tp.amount), 0) AS total
             FROM transaction_payments tp
             JOIN payment_methods pm ON tp.payment_method_id = pm.method_id
             WHERE tp.source_type = 'TICKET_TRANSACTION'
               AND tp.source_id   = :tid
               AND pm.tracks_credit = 1",
            ['tid' => $ticketTxnId]
        )['total'] ?? 0);
    }

    public static function printFeeAmountForTicket(int $ticketTxnId): float
    {
        if ($ticketTxnId <= 0) {
            return 0.0;
        }

        $row = Database::fetch(
            "SELECT COALESCE(SUM(COALESCE(st.total_amount, oi_service.total_amount, 0)), 0) AS amount
             FROM pos_order_items oi_ticket
             JOIN pos_order_items oi_service
               ON oi_service.order_id = oi_ticket.order_id
             LEFT JOIN service_transactions st
               ON st.service_txn_id = oi_service.reference_id
             LEFT JOIN service_types st_type
               ON st_type.service_type_id = COALESCE(oi_service.service_type_id, st.service_type_id)
             WHERE oi_ticket.reference_id = :transaction_id
               AND oi_ticket.item_type = 'TICKET'
               AND oi_service.item_type = 'SERVICE'
               AND (st_type.code = 'PRINT_FEE'
                    OR TRIM(UPPER(st_type.name)) = 'PRINT FEE')
               AND COALESCE(st.status, '') NOT IN ('cancelled', 'refunded')",
            ['transaction_id' => $ticketTxnId]
        );

        return round(max(0, (float) ($row['amount'] ?? 0)), 2);
    }

    /**
     * Derive the operating provider_id from a ticket transaction, with a legacy fallback
     * to the recorded wallet if provider_id is missing.
     */
    public static function resolveProviderId(array $ticketTxn): ?int
    {
        $providerId = $ticketTxn['provider_id'] ?? null;
        $walletId   = $ticketTxn['wallet_id'] ?? null;

        if (!$providerId && $walletId) {
            $walletProvider = Database::fetch(
                "SELECT provider_id FROM provider_wallets WHERE wallet_id = :wid",
                ['wid' => $walletId]
            );
            if ($walletProvider) {
                $providerId = $walletProvider['provider_id'];
            }
        }

        return $providerId ? (int) $providerId : null;
    }

    /**
     * Apply a Void operation without returning cash or bank funds.
     *
     * Variant tickets are consumed and therefore receive no provider wallet or
     * physical-stock restoration. Original CHARGE debt is still reversed.
     */
    public static function processVoidEffects(
        array $ticketTxn,
        array $cancellation,
        int $processedByUserId,
        ?string $remarks = null
    ): array {
        $ticketTxnId = (int) $ticketTxn['transaction_id'];
        $ticketTotal = (float) ($ticketTxn['total_amount'] ?? 0);
        $reason = (string) ($cancellation['reason'] ?? 'Void');
        $reasonCategory = strtoupper((string) ($cancellation['reason_category'] ?? 'OTHER'));
        $isCancelVoid = $reasonCategory === 'CANCEL';

        Database::execute(
            "UPDATE ticket_transactions SET status = 'cancelled' WHERE transaction_id = :tid",
            ['tid' => $ticketTxnId]
        );

        $orderItem = Database::fetch(
            "SELECT oi.item_id, oi.order_id
             FROM pos_order_items oi
             WHERE oi.reference_id = :tid AND oi.item_type = 'TICKET'
             LIMIT 1",
            ['tid' => $ticketTxnId]
        );
        if ($orderItem) {
            Database::execute(
                "UPDATE pos_order_items SET total_amount = 0 WHERE item_id = :iid",
                ['iid' => (int) $orderItem['item_id']]
            );

            $activeItems = Database::fetch(
                "SELECT COUNT(*) AS total
                 FROM pos_order_items oi
                 LEFT JOIN ticket_transactions tt
                   ON oi.item_type = 'TICKET' AND tt.transaction_id = oi.reference_id
                 LEFT JOIN service_transactions st
                   ON oi.item_type = 'SERVICE' AND st.service_txn_id = oi.reference_id
                 WHERE oi.order_id = :order_id
                   AND oi.total_amount > 0
                   AND (
                       (oi.item_type = 'TICKET' AND COALESCE(tt.status, '') NOT IN ('cancelled', 'refunded'))
                       OR (oi.item_type = 'SERVICE' AND COALESCE(st.status, '') NOT IN ('cancelled', 'refunded'))
                   )",
                ['order_id' => (int) $orderItem['order_id']]
            );
            if ((int) ($activeItems['total'] ?? 0) === 0) {
                Database::execute(
                    "UPDATE pos_orders SET status = 'cancelled' WHERE order_id = :order_id",
                    ['order_id' => (int) $orderItem['order_id']]
                );
            }
        }

        $chargeReversalAmount = self::reverseOriginalChargePayments($ticketTxnId);
        $walletResult = self::creditWalletForRefund(
            $ticketTxn,
            $ticketTotal,
            $cancellation,
            $processedByUserId,
            $reason,
            $remarks,
            'VOID'
        );
        $voidFeeWalletResult = self::debitVoidFeeFromProviderWallet(
            $ticketTxn,
            $cancellation,
            $walletResult,
            $processedByUserId,
            $reason,
            $remarks,
            $reasonCategory
        );
        $stockResult = $isCancelVoid
            ? self::restoreBranchStockForCancel($ticketTxn, $processedByUserId)
            : self::emptyStockRestoreResult();

        Database::execute(
            "UPDATE ticket_cancellations
             SET charge_amount = :charge_amount,
                 cash_refund_amount = 0,
                 status = 'completed',
                 processed_at = NOW()
             WHERE cancellation_id = :cancellation_id",
            [
                'charge_amount' => $chargeReversalAmount,
                'cancellation_id' => (int) $cancellation['cancellation_id'],
            ]
        );

        return [
            'wallet_id' => $walletResult['wallet_id'],
            'wallet_refund_amount' => $walletResult['wallet_refund_amount'],
            'wallet_balance_before' => $walletResult['balance_before'],
            'wallet_balance_after' => $voidFeeWalletResult['balance_after'] ?? $walletResult['balance_after'],
            'wallet_txn_code' => $walletResult['txn_code'],
            'wallet_original_sale_amount' => $walletResult['original_sale_amount'] ?? $walletResult['wallet_refund_amount'],
            'wallet_used_legacy_fallback' => $walletResult['used_legacy_fallback'] ?? false,
            'void_fee_wallet_debited' => $voidFeeWalletResult['applied'],
            'void_fee_wallet_debit_amount' => $voidFeeWalletResult['amount'],
            'void_fee_wallet_debit_void_fee' => $voidFeeWalletResult['void_fee_amount'],
            'void_fee_wallet_debit_service_fee' => $voidFeeWalletResult['void_service_fee_amount'],
            'void_fee_wallet_balance_before' => $voidFeeWalletResult['balance_before'],
            'void_fee_wallet_balance_after' => $voidFeeWalletResult['balance_after'],
            'void_fee_wallet_txn_code' => $voidFeeWalletResult['txn_code'],
            'charge_reversal_amount' => $chargeReversalAmount,
            'cash_refund_amount' => 0.0,
            'is_consumed_variant' => !empty($ticketTxn['variant_id']),
            'stock_restored' => $stockResult['restored'],
            'stock_restoration_already_applied' => $stockResult['already_applied'],
            'stock_restoration_movement_id' => $stockResult['movement_id'],
            'stock_restoration_quantity' => $stockResult['quantity'],
        ];
    }

    private static function debitVoidFeeFromProviderWallet(
        array $ticketTxn,
        array $cancellation,
        array $walletResult,
        int $processedByUserId,
        string $reason,
        ?string $remarks,
        string $reasonCategory
    ): array {
        $result = [
            'applied' => false,
            'amount' => 0.0,
            'void_fee_amount' => 0.0,
            'void_service_fee_amount' => 0.0,
            'balance_before' => null,
            'balance_after' => null,
            'txn_code' => null,
        ];
        if (strtoupper($reasonCategory) === 'CANCEL') {
            return $result;
        }

        $walletId = (int) ($walletResult['wallet_id'] ?? 0);

        if ($walletId <= 0) {
            return $result;
        }

        $walletPolicy = ProviderWalletDeductionService::forWallet($walletId);
        if (($walletPolicy['wallet_status'] ?? null) !== 'active'
            || !empty($walletPolicy['variant_id'])) {
            return $result;
        }

        $voidFee = max(0, round((float) ($cancellation['void_fee'] ?? 0), 2));
        $voidServiceFee = max(0, round((float) ($cancellation['void_service_fee'] ?? 0), 2));
        $feeAmount = ProviderWalletDeductionService::voidFeeDebit(
            $cancellation,
            $walletPolicy,
            $reasonCategory
        );

        if ($feeAmount <= 0) {
            return $result;
        }

        $walletMovement = BalanceLedgerService::walletMovement(
            $walletId,
            'ADJUSTMENT',
            'OUT',
            $feeAmount,
            'ticket_transactions',
            (int) $ticketTxn['transaction_id'],
            'Void Fee(s): ' . $ticketTxn['transaction_code']
                . ' | Void Fee: ' . number_format($voidFee, 2, '.', '')
                . ' | Void Service Fee: ' . number_format($voidServiceFee, 2, '.', '')
                . ' | Cancellation #' . (int) $cancellation['cancellation_id']
                . ($remarks ? ' | ' . $remarks : ($reason ? ' | ' . $reason : '')),
            $processedByUserId,
            'ticket-void-fee:' . (int) $cancellation['cancellation_id']
        );

        return [
            'applied' => true,
            'amount' => (float) ($walletMovement['amount'] ?? $feeAmount),
            'void_fee_amount' => $voidFee,
            'void_service_fee_amount' => $voidServiceFee,
            'balance_before' => $walletMovement['balance_before'],
            'balance_after' => $walletMovement['balance_after'],
            'txn_code' => $walletMovement['txn_code'],
        ];
    }

    private static function reverseOriginalChargePayments(int $ticketTxnId): float
    {
        $payments = Database::fetchAll(
            "SELECT tp.charged_to_passenger_id, SUM(tp.amount) AS amount
             FROM transaction_payments tp
             JOIN payment_methods pm ON pm.method_id = tp.payment_method_id
             WHERE tp.source_type = 'TICKET_TRANSACTION'
               AND tp.source_id = :transaction_id
               AND pm.tracks_credit = 1
               AND tp.charged_to_passenger_id IS NOT NULL
               AND tp.confirmation_status <> 'REJECTED'
             GROUP BY tp.charged_to_passenger_id",
            ['transaction_id' => $ticketTxnId]
        );

        $total = 0.0;
        foreach ($payments as $payment) {
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($amount <= 0) continue;
            ChargeService::reverseCustomerCharge((int) $payment['charged_to_passenger_id'], $amount);
            $total += $amount;
        }

        return round($total, 2);
    }

    /**
     * Resolve the wallet for a non-variant ticket, credit the proportional base
     * amount, and insert a linked wallet_transactions reversal.
     * Consumed variants return a zero-effect result before wallet resolution.
     */
    private static function creditWalletForRefund(
        array $ticketTxn,
        float $refundAmount,
        array $cancellation,
        int $processedByUserId,
        string $reason,
        ?string $remarks,
        string $operationType = 'REFUND'
    ): array {
        if (!empty($ticketTxn['variant_id'])) {
            return [
                'wallet_id' => null,
                'wallet_refund_amount' => 0.0,
                'balance_before' => null,
                'balance_after' => null,
                'txn_code' => null,
                'is_variant_wallet' => true,
                'is_consumed_variant' => true,
            ];
        }

        $storedWalletId = !empty($ticketTxn['wallet_id']) ? (int) $ticketTxn['wallet_id'] : null;
        $resolvedWallet = null;

        if ($storedWalletId) {
            $resolvedWallet = Database::fetch(
                "SELECT * FROM provider_wallets
                 WHERE wallet_id = :wid
                   AND status = 'active'",
                ['wid' => $storedWalletId]
            );

            if (!$resolvedWallet) {
                throw new Exception('The wallet used for this ticket is missing or inactive. Reactivate it before processing the refund.');
            }
        } else {
            $providerId = self::resolveProviderId($ticketTxn);
            if (!$providerId) {
                throw new Exception('This ticket transaction has no associated provider or wallet.');
            }

            $variantId = !empty($ticketTxn['variant_id']) ? (int) $ticketTxn['variant_id'] : null;
            $resolvedWallet = WalletResolver::resolve(
                $providerId,
                (int) $ticketTxn['branch_id'],
                $variantId
            );

            if (!$resolvedWallet) {
                throw new Exception('No active wallet found for the provider, branch and variant to process refund.');
            }
        }

        $walletId = (int) $resolvedWallet['wallet_id'];

        $wallet = Database::fetch(
            "SELECT wallet_id, variant_id, status
             FROM provider_wallets
             WHERE wallet_id = :wid
             LIMIT 1",
            ['wid' => $walletId]
        );

        if (!$wallet || $wallet['status'] !== 'active') {
            throw new Exception('Wallet not found or inactive during refund processing.');
        }

        // Variant-specific wallets represent actual physical ticket stock.
        // Once a variant ticket has been sold (and typically printed), it is no
        // longer re-sellable, so the provider wallet should NOT be credited back
        // when the ticket is cancelled. The customer may still receive a cash
        // refund from the drawer; only the provider wallet restoration is skipped.
        if (!empty($resolvedWallet['variant_id'])) {
            $currentBalance = (float) ($resolvedWallet['current_balance'] ?? 0);
            return [
                'wallet_id' => $walletId,
                'wallet_refund_amount' => 0.0,
                'balance_before' => $currentBalance,
                'balance_after' => $currentBalance,
                'txn_code' => null,
                'is_variant_wallet' => true,
            ];
        }

        $restoreCancellation = $cancellation;
        $restoreCancellation['operation_type'] = strtoupper($operationType);
        $restoration = ProviderWalletDeductionService::restoration(
            $ticketTxn,
            $refundAmount,
            $restoreCancellation
        );
        $walletRefundAmount = $restoration['amount'];
        $wTxnRemarks = 'Refund: ' . $ticketTxn['transaction_code']
            . ' | Restored sale debit: ' . number_format($walletRefundAmount, 2, '.', '')
            . ' | Cancellation #' . (int) $cancellation['cancellation_id']
            . ($remarks ? ' | ' . $remarks : ($reason ? ' | ' . $reason : ''));

        $walletMovement = null;
        if ($walletRefundAmount > 0) {
            $walletMovement = BalanceLedgerService::walletMovement(
                $walletId,
                'REFUND',
                'IN',
                $walletRefundAmount,
                'ticket_transactions',
                (int) $ticketTxn['transaction_id'],
                $wTxnRemarks,
                $processedByUserId,
                'ticket-' . strtolower($operationType) . ':' . (int) $cancellation['cancellation_id'],
                $restoration['wallet_txn_id'] ?: null
            );
        }

        return [
            'wallet_id' => $walletId,
            'wallet_refund_amount' => $walletRefundAmount,
            'balance_before' => $walletMovement['balance_before'] ?? (float) ($resolvedWallet['current_balance'] ?? 0),
            'balance_after' => $walletMovement['balance_after'] ?? (float) ($resolvedWallet['current_balance'] ?? 0),
            'txn_code' => $walletMovement['txn_code'] ?? null,
            'original_sale_amount' => $restoration['original_amount'],
            'used_legacy_fallback' => $restoration['used_legacy_fallback'],
            'is_variant_wallet' => !empty($resolvedWallet['variant_id']),
        ];
    }

    private static function emptyStockRestoreResult(): array
    {
        return [
            'restored' => false,
            'already_applied' => false,
            'movement_id' => null,
            'quantity' => 0,
        ];
    }

    /**
     * Restore physical branch stock for a Cancel Void variant ticket.
     *
     * Only reverse a recorded POS_SALE movement. This prevents a legacy or
     * partially-created ticket from receiving stock that was never deducted.
     */
    private static function restoreBranchStockForCancel(array $ticketTxn, int $processedByUserId): array
    {
        $variantId = (int) ($ticketTxn['variant_id'] ?? 0);
        if ($variantId <= 0) {
            return self::emptyStockRestoreResult();
        }

        $variant = TicketStockHelper::getVariant($variantId);
        if (!$variant || !(bool) ($variant['stock_controlled'] ?? false)) {
            return self::emptyStockRestoreResult();
        }

        $providerId = self::resolveProviderId($ticketTxn);
        $branchId = (int) ($ticketTxn['branch_id'] ?? 0);
        $transactionId = (int) ($ticketTxn['transaction_id'] ?? 0);
        if (!$providerId || $branchId <= 0 || $transactionId <= 0) {
            throw new RuntimeException('The cancelled ticket has incomplete stock reference data.');
        }

        $existingReversal = Database::fetch(
            "SELECT movement_id, quantity_delta
             FROM ticket_stock_movements
             WHERE reference_type = 'TICKET_TRANSACTION'
               AND reference_id = :transaction_id
               AND movement_type = 'POS_SALE_REVERSAL'
             ORDER BY movement_id DESC
             LIMIT 1",
            ['transaction_id' => $transactionId]
        );
        if ($existingReversal) {
            return [
                'restored' => true,
                'already_applied' => true,
                'movement_id' => (int) $existingReversal['movement_id'],
                'quantity' => abs((int) ($existingReversal['quantity_delta'] ?? 0)),
            ];
        }

        $saleMovement = Database::fetch(
            "SELECT movement_id, quantity_delta
             FROM ticket_stock_movements
             WHERE reference_type = 'TICKET_TRANSACTION'
               AND reference_id = :transaction_id
               AND movement_type = 'POS_SALE'
               AND quantity_delta < 0
             ORDER BY movement_id DESC
             LIMIT 1",
            ['transaction_id' => $transactionId]
        );
        if (!$saleMovement) {
            return self::emptyStockRestoreResult();
        }

        $movement = TicketStockHelper::restoreOnSale(
            $branchId,
            (int) $providerId,
            $variantId,
            abs((int) ($saleMovement['quantity_delta'] ?? 1)),
            $processedByUserId,
            [
                'reference_type' => 'TICKET_TRANSACTION',
                'reference_id'   => $transactionId,
                'ticket_number_from' => $ticketTxn['ticket_number'] ?? null,
                'ticket_number_to' => $ticketTxn['ticket_number'] ?? null,
                'remarks'        => 'Stock restored from Cancel Void ' . ($ticketTxn['transaction_code'] ?? ''),
            ]
        );

        return [
            'restored' => true,
            'already_applied' => false,
            'movement_id' => (int) ($movement['movement_id'] ?? 0),
            'quantity' => abs((int) ($saleMovement['quantity_delta'] ?? 1)),
        ];
    }

    /**
     * Insert a ticket_refunds record.
     */
    private static function createTicketRefund(
        array $ticketTxn,
        array $cancellation,
        float $refundAmount,
        float $cashRefundAmount,
        float $chargeAmount,
        int $processedByUserId,
        int $processingDays
    ): array {
        $status = $processingDays > 0 ? 'processing' : 'completed';

        Database::execute(
            "INSERT INTO ticket_refunds
                (transaction_id, transaction_code, cancellation_id, adjustment_id, passenger_id, refund_amount,
                 gross_refund_amount, responsibility_amount, cash_amount, charge_reversal_amount,
                 refund_method, status, requested_by, cashier_session_id, requested_at, processed_by, processed_at)
             VALUES (:tid, :code, :cid, :aid, :pid, :ramount,
                     :gross_amount, :responsibility_amount, :camount, :cramount,
                     'cash', :status, :ruid, :rcsid, :rtime, :puid, NOW())",
            [
                'tid'     => (int) $ticketTxn['transaction_id'],
                'code'    => $ticketTxn['transaction_code'],
                'cid'     => (int) $cancellation['cancellation_id'],
                'aid'     => !empty($cancellation['adjustment_id']) ? (int) $cancellation['adjustment_id'] : null,
                'pid'     => $ticketTxn['passenger_id'] ?? null,
                'ramount' => $refundAmount,
                'gross_amount' => (float) ($cancellation['gross_refund_amount'] ?? $refundAmount),
                'responsibility_amount' => (float) ($cancellation['responsibility_amount'] ?? 0),
                'camount' => $cashRefundAmount,
                'cramount'=> $chargeAmount,
                'status'  => $status,
                'ruid'    => (int) $cancellation['requested_by'],
                'rcsid'   => $cancellation['cashier_session_id'] ?? null,
                'rtime'   => $cancellation['requested_at'],
                'puid'    => $processedByUserId,
            ]
        );

        return [
            'refund_id' => (int) Database::lastInsertId(),
            'status'    => $status,
        ];
    }
}
