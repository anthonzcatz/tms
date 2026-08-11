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

        // 1. Mark ticket as cancelled.
        Database::execute(
            "UPDATE ticket_transactions SET status = 'cancelled' WHERE transaction_id = :tid",
            ['tid' => $ticketTxnId]
        );

        // 2. Reverse the CHARGE portion from customer_charges, if applicable.
        if ($chargeAmount > 0 && $passengerId) {
            self::reverseCustomerCharge((int) $passengerId, $chargeAmount);
        }

        // 3. Resolve wallet and credit proportional base amount.
        $walletResult = self::creditWalletForRefund($ticketTxn, $refundAmount, $cancellation, $processedByUserId, $reason, $remarks);

        // 4. Restore physical branch stock for non-wallet variants (wallet-backed variants skip stock).
        if (!empty($ticketTxn['variant_id']) && empty($walletResult['is_variant_wallet'])) {
            self::restoreBranchStockForCancel($ticketTxn, 1, $processedByUserId);
        }

        // 5. Zero out order item and update pos_orders.total_refunded_amount.
        $orderItem = Database::fetch(
            "SELECT oi.item_id, oi.order_id FROM pos_order_items oi
             WHERE oi.reference_id = :tid AND oi.item_type = 'TICKET' LIMIT 1",
            ['tid' => $ticketTxnId]
        );

        if ($orderItem) {
            Database::execute(
                "UPDATE pos_order_items SET total_amount = 0 WHERE item_id = :iid",
                ['iid' => $orderItem['item_id']]
            );
            Database::execute(
                "UPDATE pos_orders SET total_refunded_amount = COALESCE(total_refunded_amount, 0) + :ramount WHERE order_id = :oid",
                ['ramount' => $refundAmount, 'oid' => $orderItem['order_id']]
            );
        }

        // 5. Insert refund record.
        $refundRecord = self::createTicketRefund(
            $ticketTxn,
            $cancellation,
            $refundAmount,
            $cashRefundAmount,
            $chargeAmount,
            $processedByUserId,
            $processingDays
        );

        return [
            'wallet_id'             => $walletResult['wallet_id'],
            'wallet_refund_amount'  => $walletResult['wallet_refund_amount'],
            'wallet_balance_before' => $walletResult['balance_before'],
            'wallet_balance_after'  => $walletResult['balance_after'],
            'wallet_txn_code'       => $walletResult['txn_code'],
            'refund_status'         => $refundRecord['status'],
            'refund_record_id'      => $refundRecord['refund_id'],
        ];
    }

    /**
     * Reverse the customer_charges balance for a passenger by the given charge amount.
     */
    public static function reverseCustomerCharge(int $passengerId, float $chargeAmount): void
    {
        if ($chargeAmount <= 0) {
            return;
        }

        $chargeRow = Database::fetch(
            "SELECT * FROM customer_charges WHERE passenger_id = :pid",
            ['pid' => $passengerId]
        );

        if (!$chargeRow) {
            return;
        }

        $newBalance = max(0, floatval($chargeRow['balance']) - $chargeAmount);
        $newCharged = max(0, floatval($chargeRow['total_charged']) - $chargeAmount);
        $newStatus  = $newBalance <= 0 ? 'CLEAR' : $chargeRow['status'];

        Database::execute(
            "UPDATE customer_charges
             SET total_charged = :charged,
                 balance       = :balance,
                 status        = :status,
                 updated_at    = NOW()
             WHERE passenger_id = :pid",
            [
                'charged' => $newCharged,
                'balance' => $newBalance,
                'status'  => $newStatus,
                'pid'     => $passengerId,
            ]
        );
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
     * Resolve the wallet for this ticket (provider + branch + variant), credit the
     * proportional base amount, and insert a wallet_transactions refund row.
     */
    private static function creditWalletForRefund(
        array $ticketTxn,
        float $refundAmount,
        array $cancellation,
        int $processedByUserId,
        string $reason,
        ?string $remarks
    ): array {
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
            "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active' FOR UPDATE",
            ['wid' => $walletId]
        );

        if (!$wallet) {
            throw new Exception('Wallet not found or inactive during refund processing.');
        }

        $ticketBaseAmount  = floatval($ticketTxn['base_amount'] ?? 0);
        $ticketTotalAmount = floatval($ticketTxn['total_amount'] ?? 0);
        $walletRefundAmount = $ticketTotalAmount > 0
            ? round($ticketBaseAmount * ($refundAmount / $ticketTotalAmount), 2)
            : 0;

        $balanceBefore = floatval($wallet['current_balance']);
        $balanceAfter  = $balanceBefore + $walletRefundAmount;

        Database::execute(
            "UPDATE provider_wallets SET current_balance = :new_balance, updated_at = NOW() WHERE wallet_id = :wid",
            ['new_balance' => $balanceAfter, 'wid' => $walletId]
        );

        $wTxnCode = 'RF-' . date('Ymd-His') . '-' . sprintf('%03d', mt_rand(0, 999));
        $wTxnRemarks = 'Refund: ' . $ticketTxn['transaction_code']
            . ' | Cancellation #' . (int) $cancellation['cancellation_id']
            . ($remarks ? ' | ' . $remarks : ($reason ? ' | ' . $reason : ''));

        Database::execute(
            "INSERT INTO wallet_transactions
                (wallet_id, txn_code, txn_type, direction, amount, balance_before, balance_after,
                 reference_table, reference_id, remarks, created_by, created_at)
             VALUES (:wid, :code, 'REFUND', 'IN', :amount, :before, :after,
                     'ticket_transactions', :ref_id, :remarks, :uid, NOW())",
            [
                'wid'     => $walletId,
                'code'    => $wTxnCode,
                'amount'  => $walletRefundAmount,
                'before'  => $balanceBefore,
                'after'   => $balanceAfter,
                'ref_id'  => (int) $ticketTxn['transaction_id'],
                'remarks' => $wTxnRemarks,
                'uid'     => $processedByUserId,
            ]
        );

        return [
            'wallet_id'         => $walletId,
            'wallet_refund_amount' => $walletRefundAmount,
            'balance_before'    => $balanceBefore,
            'balance_after'     => $balanceAfter,
            'txn_code'          => $wTxnCode,
            'is_variant_wallet' => !empty($resolvedWallet['variant_id']),
        ];
    }

    /**
     * Restore physical branch stock for a cancelled non-wallet variant ticket.
     */
    private static function restoreBranchStockForCancel(array $ticketTxn, int $qty, int $processedByUserId): array
    {
        return TicketStockHelper::restoreOnSale(
            (int) $ticketTxn['branch_id'],
            (int) $ticketTxn['provider_id'],
            (int) $ticketTxn['variant_id'],
            $qty,
            $processedByUserId,
            [
                'reference_type' => 'TICKET_TRANSACTION',
                'reference_id'   => (int) $ticketTxn['transaction_id'],
                'remarks'        => 'Stock restored from cancellation ' . $ticketTxn['transaction_code'],
            ]
        );
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
                (transaction_id, transaction_code, cancellation_id, passenger_id, refund_amount,
                 cash_amount, charge_reversal_amount,
                 refund_method, status, requested_by, cashier_session_id, requested_at, processed_by, processed_at)
             VALUES (:tid, :code, :cid, :pid, :ramount,
                     :camount, :cramount,
                     'cash', :status, :ruid, :rcsid, :rtime, :puid, NOW())",
            [
                'tid'     => (int) $ticketTxn['transaction_id'],
                'code'    => $ticketTxn['transaction_code'],
                'cid'     => (int) $cancellation['cancellation_id'],
                'pid'     => $ticketTxn['passenger_id'] ?? null,
                'ramount' => $refundAmount,
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
