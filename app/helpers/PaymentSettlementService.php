<?php
/**
 * PaymentSettlementService
 *
 * Applies the rollback needed when a required POS payment is rejected after
 * checkout. A rejected payment is not a customer refund: it either leaves a
 * sufficiently funded mixed-payment transaction intact or voids the sale and
 * reverses the exact provider wallet/physical-stock effects.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/BalanceLedgerService.php';
require_once __DIR__ . '/TicketStockHelper.php';

final class PaymentSettlementService
{
    public static function handleRejectedTransactionPayment(
        array $payment,
        array $paymentMethod,
        int $processedBy,
        ?string $notes = null
    ): array {
        $sourceType = $payment['source_type'] ?? null;
        $sourceId = (int) ($payment['source_id'] ?? 0);
        if (!in_array($sourceType, ['TICKET_TRANSACTION', 'SERVICE_TRANSACTION'], true) || $sourceId <= 0) {
            throw new InvalidArgumentException('Invalid rejected payment source.');
        }

        return self::withinTransaction(function () use ($payment, $paymentMethod, $processedBy, $notes, $sourceType, $sourceId) {
            $paymentAmount = round((float) $payment['amount'], 2);
            self::reverseSessionPaymentTotal($payment, $paymentMethod);

            $transaction = self::sourceTransaction($sourceType, $sourceId);
            $validPaid = Database::fetch(
                "SELECT COALESCE(SUM(amount), 0) AS total
                 FROM transaction_payments
                 WHERE source_type = :source_type
                   AND source_id = :source_id
                   AND confirmation_status IN ('NOT_REQUIRED', 'CONFIRMED')",
                ['source_type' => $sourceType, 'source_id' => $sourceId]
            );
            $fundedAmount = round((float) ($validPaid['total'] ?? 0), 2);
            $transactionTotal = round((float) $transaction['total_amount'], 2);

            if ($fundedAmount + 0.009 >= $transactionTotal) {
                return [
                    'voided' => false,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'rejected_amount' => $paymentAmount,
                    'funded_amount' => $fundedAmount,
                ];
            }

            if ($sourceType === 'TICKET_TRANSACTION') {
                self::voidTicketSale($transaction, (int) $payment['payment_id'], $processedBy, $notes);
            } else {
                self::voidServiceSale($transaction, (int) $payment['payment_id']);
            }

            return [
                'voided' => true,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'rejected_amount' => $paymentAmount,
                'funded_amount' => $fundedAmount,
            ];
        });
    }

    private static function voidTicketSale(
        array $ticket,
        int $paymentId,
        int $processedBy,
        ?string $notes
    ): void {
        $ticketId = (int) $ticket['transaction_id'];
        if (in_array($ticket['status'], ['cancelled', 'refunded'], true)) {
            return;
        }

        Database::execute(
            "UPDATE ticket_transactions SET status = 'cancelled' WHERE transaction_id = :transaction_id",
            ['transaction_id' => $ticketId]
        );

        $wallet = Database::fetch(
            "SELECT wallet_id, variant_id
             FROM provider_wallets
             WHERE wallet_id = :wallet_id
             LIMIT 1",
            ['wallet_id' => (int) $ticket['wallet_id']]
        );
        if (!$wallet) {
            throw new RuntimeException('The rejected ticket payment has no original wallet.');
        }

        $originalSale = Database::fetch(
            "SELECT wallet_txn_id
             FROM wallet_transactions
             WHERE idempotency_key = :idempotency_key
             LIMIT 1",
            ['idempotency_key' => 'pos-sale:' . $ticketId]
        );
        $baseAmount = round((float) ($ticket['base_amount'] ?? 0), 2);
        // POS variant tickets use the separate ticket-stock ledger and do not
        // create a monetary wallet debit, so there is no wallet amount to restore.
        if (empty($ticket['variant_id']) && $baseAmount > 0) {
            BalanceLedgerService::walletMovement(
                (int) $wallet['wallet_id'],
                'REFUND',
                'IN',
                $baseAmount,
                'transaction_payments',
                $paymentId,
                'Provider cost restored after bank/e-wallet payment rejection'
                    . ($notes ? ' | ' . $notes : ''),
                $processedBy,
                'bank-reject:ticket:' . $ticketId . ':wallet',
                $originalSale ? (int) $originalSale['wallet_txn_id'] : null
            );
        }

        // A rejected payment means the POS sale did not complete. Restore the
        // physical stock consumed by a stock-controlled variant, regardless of
        // whether a variant-specific wallet identity was selected.
        $variant = !empty($ticket['variant_id'])
            ? TicketStockHelper::getVariant((int) $ticket['variant_id'])
            : null;
        if ($variant && (bool) $variant['stock_controlled']) {
            $providerId = (int) ($ticket['provider_id'] ?? 0);
            if (!$providerId) {
                $providerIdRow = Database::fetch(
                    'SELECT provider_id FROM provider_wallets WHERE wallet_id = :wallet_id',
                    ['wallet_id' => (int) $wallet['wallet_id']]
                );
                $providerId = (int) ($providerIdRow['provider_id'] ?? 0);
            }
            if (!$providerId || empty($ticket['branch_id'])) {
                throw new RuntimeException('The rejected ticket payment has incomplete stock reference data.');
            }
            TicketStockHelper::restoreOnSale(
                (int) $ticket['branch_id'],
                $providerId,
                (int) $ticket['variant_id'],
                1,
                $processedBy,
                [
                    'reference_type' => 'TRANSACTION_PAYMENT',
                    'reference_id' => $paymentId,
                    'remarks' => 'Stock restored after rejected bank/e-wallet payment',
                ]
            );
        }

        self::voidOrderItem('TICKET', $ticketId);
    }

    private static function voidServiceSale(array $service, int $paymentId): void
    {
        $serviceId = (int) $service['service_txn_id'];
        if (in_array($service['status'], ['cancelled', 'refunded'], true)) {
            return;
        }

        Database::execute(
            "UPDATE service_transactions SET status = 'cancelled' WHERE service_txn_id = :service_txn_id",
            ['service_txn_id' => $serviceId]
        );
        self::voidOrderItem('SERVICE', $serviceId);
    }

    private static function voidOrderItem(string $itemType, int $referenceId): void
    {
        $item = Database::fetch(
            "SELECT oi.item_id, oi.order_id, oi.total_amount,
                    po.cashier_session_id, po.grand_total
             FROM pos_order_items oi
             LEFT JOIN pos_orders po ON po.order_id = oi.order_id
             WHERE oi.item_type = :item_type AND oi.reference_id = :reference_id
             LIMIT 1",
            ['item_type' => $itemType, 'reference_id' => $referenceId]
        );
        if (!$item) {
            return;
        }

        Database::execute(
            'UPDATE pos_order_items SET total_amount = 0 WHERE item_id = :item_id',
            ['item_id' => (int) $item['item_id']]
        );

        $active = Database::fetch(
            "SELECT COUNT(*) AS active_count
             FROM pos_order_items oi
             LEFT JOIN ticket_transactions tt
                ON oi.item_type = 'TICKET' AND oi.reference_id = tt.transaction_id
             LEFT JOIN service_transactions st
                ON oi.item_type = 'SERVICE' AND oi.reference_id = st.service_txn_id
             WHERE oi.order_id = :order_id
               AND (
                    (oi.item_type = 'TICKET' AND tt.status NOT IN ('cancelled', 'refunded'))
                    OR (oi.item_type = 'SERVICE' AND st.status NOT IN ('cancelled', 'refunded'))
               )",
            ['order_id' => (int) $item['order_id']]
        );
        if ((int) ($active['active_count'] ?? 0) === 0) {
            Database::execute(
                "UPDATE pos_orders SET status = 'cancelled' WHERE order_id = :order_id",
                ['order_id' => (int) $item['order_id']]
            );
            if (!empty($item['cashier_session_id']) && (float) ($item['grand_total'] ?? 0) > 0) {
                Database::execute(
                    "UPDATE cashier_sessions
                     SET total_sales = GREATEST(0, COALESCE(total_sales, 0) - :amount)
                     WHERE session_id = :session_id",
                    [
                        'amount' => (float) $item['grand_total'],
                        'session_id' => (int) $item['cashier_session_id'],
                    ]
                );
            }
        }
    }

    private static function reverseSessionPaymentTotal(array $payment, array $paymentMethod): void
    {
        $sessionId = (int) ($payment['cashier_session_id'] ?? 0);
        if (!$sessionId) {
            return;
        }

        $column = match ($paymentMethod['method_type'] ?? '') {
            'CASH' => 'total_cash',
            'BANK_TRANSFER' => 'total_bank_transfer',
            'E_WALLET' => 'total_e_wallet',
            'CHARGE' => 'total_charge',
            default => 'total_other',
        };
        Database::execute(
            "UPDATE cashier_sessions
             SET {$column} = GREATEST(0, COALESCE({$column}, 0) - :amount)
             WHERE session_id = :session_id",
            [
                'amount' => round((float) $payment['amount'], 2),
                'session_id' => $sessionId,
            ]
        );
    }

    private static function sourceTransaction(string $sourceType, int $sourceId): array
    {
        if ($sourceType === 'TICKET_TRANSACTION') {
            $row = Database::fetch(
                'SELECT * FROM ticket_transactions WHERE transaction_id = :source_id FOR UPDATE',
                ['source_id' => $sourceId]
            );
        } else {
            $row = Database::fetch(
                'SELECT * FROM service_transactions WHERE service_txn_id = :source_id FOR UPDATE',
                ['source_id' => $sourceId]
            );
        }
        if (!$row) {
            throw new RuntimeException('The payment source transaction was not found.');
        }
        return $row;
    }

    private static function withinTransaction(callable $callback): array
    {
        $pdo = Database::connection();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback();
            if ($started) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
