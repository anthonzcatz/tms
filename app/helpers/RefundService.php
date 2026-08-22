<?php
/**
 * RefundService
 *
 * Allocates the cashier-entered refund amount against the original payment
 * lines and records the corresponding source-specific reversal. The service
 * does not decide whether a cancellation is approved; callers invoke it only
 * after the refund record has been created inside their transaction.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/BalanceLedgerService.php';
require_once __DIR__ . '/ChargeService.php';

final class RefundService
{
    /**
     * Preview allocation in the agreed business order: CHARGE first, then the
     * remaining original payment lines in their recorded order.
     */
    public static function previewPaymentAllocations(
        string $sourceType,
        int $sourceId,
        float $refundAmount
    ): array {
        self::assertSource($sourceType, $sourceId);
        $refundAmount = self::amount($refundAmount);
        if ($refundAmount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $payments = Database::fetchAll(
            "SELECT tp.payment_id, tp.amount, tp.confirmation_status,
                    tp.bank_account_id, tp.payment_method_id,
                    tp.charged_to_passenger_id,
                    pm.method_name, pm.method_type, pm.tracks_credit
             FROM transaction_payments tp
             JOIN payment_methods pm ON pm.method_id = tp.payment_method_id
             WHERE tp.source_type = :source_type
               AND tp.source_id = :source_id
               AND tp.confirmation_status <> 'REJECTED'
             ORDER BY CASE WHEN pm.tracks_credit = 1 THEN 0 ELSE 1 END,
                      tp.payment_id ASC",
            ['source_type' => $sourceType, 'source_id' => $sourceId]
        );

        // Legacy rows may pre-date transaction_payments. They cannot be tied
        // to a method, so retain a visible CASH fallback rather than silently
        // losing the entered refund amount.
        if (!$payments) {
            return [
                'allocations' => [[
                    'source_payment_id' => null,
                    'payment_method_id' => null,
                    'payment_method_type' => 'CASH',
                    'payment_method_name' => 'Cash (legacy fallback)',
                    'confirmation_status' => 'NOT_REQUIRED',
                    'bank_account_id' => null,
                    'amount' => $refundAmount,
                    'refund_route' => 'CASH',
                    'passenger_id' => null,
                ]],
                'cash_amount' => $refundAmount,
                'charge_amount' => 0.0,
                'bank_amount' => 0.0,
                'other_amount' => 0.0,
                'total_allocated' => $refundAmount,
            ];
        }

        $remaining = $refundAmount;
        $allocations = [];
        $totals = [
            'cash_amount' => 0.0,
            'charge_amount' => 0.0,
            'bank_amount' => 0.0,
            'other_amount' => 0.0,
        ];

        foreach ($payments as $payment) {
            if ($remaining <= 0.009) {
                break;
            }

            $originalAmount = self::amount((float) $payment['amount']);
            $allocatedAmount = self::amount(min($originalAmount, $remaining));
            if ($allocatedAmount <= 0) {
                continue;
            }

            $isCharge = (int) $payment['tracks_credit'] === 1;
            $isBank = in_array($payment['method_type'], ['BANK_TRANSFER', 'E_WALLET'], true);
            $route = $isCharge
                ? 'CHARGE_REVERSAL'
                : ($isBank ? 'BANK_REFUND' : ($payment['method_type'] === 'CASH' ? 'CASH' : 'OTHER'));

            $allocations[] = [
                'source_payment_id' => (int) $payment['payment_id'],
                'payment_method_id' => (int) $payment['payment_method_id'],
                'payment_method_type' => $payment['method_type'],
                'payment_method_name' => $payment['method_name'],
                'confirmation_status' => $payment['confirmation_status'],
                'bank_account_id' => $payment['bank_account_id'] !== null ? (int) $payment['bank_account_id'] : null,
                'amount' => $allocatedAmount,
                'refund_route' => $route,
                'passenger_id' => $payment['charged_to_passenger_id'] !== null
                    ? (int) $payment['charged_to_passenger_id']
                    : null,
            ];

            if ($route === 'CHARGE_REVERSAL') {
                $totals['charge_amount'] += $allocatedAmount;
            } elseif ($route === 'CASH') {
                $totals['cash_amount'] += $allocatedAmount;
            } elseif ($route === 'BANK_REFUND') {
                $totals['bank_amount'] += $allocatedAmount;
            } else {
                $totals['other_amount'] += $allocatedAmount;
            }

            $remaining = self::amount($remaining - $allocatedAmount);
        }

        if ($remaining > 0.009) {
            throw new RuntimeException(
                'The Refund Amount exceeds the refundable amount recorded in the original payment lines.'
            );
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = self::amount($value);
        }
        $totals['allocations'] = $allocations;
        $totals['total_allocated'] = self::amount($refundAmount - $remaining);

        return $totals;
    }

    /**
     * Process every allocation exactly once. This method reuses an existing
     * caller transaction, or starts one when invoked independently.
     */
    public static function processPaymentAllocations(
        string $refundScope,
        int $refundId,
        string $sourceType,
        int $sourceId,
        float $refundAmount,
        int $processedBy,
        ?int $cashierSessionId = null,
        ?int $passengerId = null,
        ?string $remarks = null
    ): array {
        $refundScope = strtoupper(trim($refundScope));
        if (!in_array($refundScope, ['TICKET', 'SERVICE'], true)) {
            throw new InvalidArgumentException('Invalid refund scope.');
        }
        if ($refundId <= 0 || $processedBy <= 0) {
            throw new InvalidArgumentException('A valid refund and processing user are required.');
        }
        self::assertSource($sourceType, $sourceId);

        $runner = function () use (
            $refundScope,
            $refundId,
            $sourceType,
            $sourceId,
            $refundAmount,
            $processedBy,
            $cashierSessionId,
            $passengerId,
            $remarks
        ): array {
            $existingRows = Database::fetchAll(
                "SELECT allocation_id, refund_route, amount, status
                 FROM refund_allocations
                 WHERE refund_scope = :scope AND refund_id = :refund_id
                 ORDER BY allocation_id ASC",
                ['scope' => $refundScope, 'refund_id' => $refundId]
            );
            if ($existingRows) {
                return self::summarizeExisting($existingRows);
            }

            $preview = self::previewPaymentAllocations($sourceType, $sourceId, $refundAmount);
            $actual = [
                'cash_amount' => 0.0,
                'charge_amount' => 0.0,
                'bank_amount' => 0.0,
                'other_amount' => 0.0,
                'allocations' => [],
            ];

            foreach ($preview['allocations'] as $allocation) {
                $amount = self::amount((float) $allocation['amount']);
                $idempotencyKey = sprintf(
                    'refund:%s:%d:payment:%s:%s',
                    strtolower($refundScope),
                    $refundId,
                    $allocation['source_payment_id'] ?? 'legacy',
                    strtolower($allocation['refund_route'])
                );

                Database::execute(
                    "INSERT INTO refund_allocations
                        (refund_scope, refund_id, source_payment_id, payment_method_id,
                         payment_method_type, refund_route, bank_account_id, amount,
                         status, reference_table, reference_id, idempotency_key,
                         created_by, remarks, created_at)
                     VALUES (:scope, :refund_id, :source_payment_id, :payment_method_id,
                             :payment_method_type, :refund_route, :bank_account_id, :amount,
                             'PENDING', :reference_table, :reference_id, :idempotency_key,
                             :created_by, :remarks, NOW())",
                    [
                        'scope' => $refundScope,
                        'refund_id' => $refundId,
                        'source_payment_id' => $allocation['source_payment_id'],
                        'payment_method_id' => $allocation['payment_method_id'],
                        'payment_method_type' => $allocation['payment_method_type'],
                        'refund_route' => $allocation['refund_route'],
                        'bank_account_id' => $allocation['bank_account_id'],
                        'amount' => $amount,
                        'reference_table' => $sourceType === 'TICKET_TRANSACTION'
                            ? 'ticket_refunds'
                            : 'service_refunds',
                        'reference_id' => $refundId,
                        'idempotency_key' => $idempotencyKey,
                        'created_by' => $processedBy,
                        'remarks' => $remarks,
                    ]
                );
                $allocationId = (int) Database::lastInsertId();

                switch ($allocation['refund_route']) {
                    case 'CHARGE_REVERSAL':
                        $targetPassengerId = $allocation['passenger_id'] ?: $passengerId;
                        self::reverseCustomerCharge($targetPassengerId, $amount);
                        $actual['charge_amount'] += $amount;
                        break;

                    case 'CASH':
                        if ($cashierSessionId && $amount > 0) {
                            Database::execute(
                                "UPDATE cashier_sessions
                                 SET total_refunds = COALESCE(total_refunds, 0) + :amount
                                 WHERE session_id = :session_id",
                                ['amount' => $amount, 'session_id' => $cashierSessionId]
                            );
                        }
                        $actual['cash_amount'] += $amount;
                        break;

                    case 'BANK_REFUND':
                        if ($allocation['confirmation_status'] === 'PENDING') {
                            throw new RuntimeException(
                                'A bank/e-wallet payment must be confirmed before it can be refunded.'
                            );
                        }
                        if (!$allocation['bank_account_id']) {
                            throw new RuntimeException('The original bank/e-wallet payment has no bank account.');
                        }

                        $originalBankTxn = Database::fetch(
                            "SELECT bank_txn_id
                             FROM bank_transactions
                             WHERE reference_table = 'transaction_payments'
                               AND reference_id = :payment_id
                               AND direction = 'IN'
                               AND confirmation_status = 'CONFIRMED'
                             ORDER BY bank_txn_id DESC
                             LIMIT 1",
                            ['payment_id' => $allocation['source_payment_id']]
                        );

                        $bankResult = BalanceLedgerService::bankMovement(
                            (int) $allocation['bank_account_id'],
                            'REFUND',
                            'OUT',
                            $amount,
                            'refund_allocations',
                            $allocationId,
                            'Refund for ' . $sourceType . ' #' . $sourceId,
                            $processedBy,
                            $idempotencyKey,
                            $originalBankTxn ? (int) $originalBankTxn['bank_txn_id'] : null,
                            false,
                            $remarks
                        );
                        $actual['bank_amount'] += $amount;
                        $actual['bank_txn_ids'][] = $bankResult['bank_txn_id'];
                        break;

                    case 'OTHER':
                        $actual['other_amount'] += $amount;
                        break;
                }

                Database::execute(
                    "UPDATE refund_allocations
                     SET status = 'PROCESSED', processed_by = :processed_by,
                         processed_at = NOW()
                     WHERE allocation_id = :allocation_id",
                    ['processed_by' => $processedBy, 'allocation_id' => $allocationId]
                );

                $actual['allocations'][] = [
                    'allocation_id' => $allocationId,
                    'refund_route' => $allocation['refund_route'],
                    'amount' => $amount,
                    'source_payment_id' => $allocation['source_payment_id'],
                ];
            }

            self::updateRefundSummary($refundScope, $refundId, $actual);
            $actual['cash_amount'] = self::amount($actual['cash_amount']);
            $actual['charge_amount'] = self::amount($actual['charge_amount']);
            $actual['bank_amount'] = self::amount($actual['bank_amount']);
            $actual['other_amount'] = self::amount($actual['other_amount']);
            $actual['total_allocated'] = self::amount(
                $actual['cash_amount']
                + $actual['charge_amount']
                + $actual['bank_amount']
                + $actual['other_amount']
            );

            return $actual;
        };

        return self::withinTransaction($runner);
    }

    private static function reverseCustomerCharge(?int $passengerId, float $amount): void
    {
        if (!$passengerId || $amount <= 0) {
            throw new RuntimeException('The CHARGE refund has no passenger account.');
        }

        ChargeService::reverseCustomerCharge($passengerId, $amount);
    }

    private static function updateRefundSummary(string $scope, int $refundId, array $actual): void
    {
        $table = $scope === 'TICKET' ? 'ticket_refunds' : 'service_refunds';
        $idColumn = $scope === 'TICKET' ? 'refund_id' : 'service_refund_id';
        $cashColumn = 'cash_amount';
        $chargeColumn = $scope === 'TICKET' ? 'charge_reversal_amount' : 'charge_reversal_amount';

        Database::execute(
            "UPDATE {$table}
             SET {$cashColumn} = :cash_amount,
                 {$chargeColumn} = :charge_amount,
                 bank_amount = :bank_amount,
                 other_amount = :other_amount,
                 refund_method = :refund_method
             WHERE {$idColumn} = :refund_id",
            [
                'cash_amount' => self::amount($actual['cash_amount']),
                'charge_amount' => self::amount($actual['charge_amount']),
                'bank_amount' => self::amount($actual['bank_amount']),
                'other_amount' => self::amount($actual['other_amount'] ?? 0),
                'refund_method' => self::refundMethod($actual),
                'refund_id' => $refundId,
            ]
        );
    }

    private static function refundMethod(array $actual): string
    {
        $routes = 0;
        foreach (['cash_amount', 'charge_amount', 'bank_amount', 'other_amount'] as $key) {
            if (($actual[$key] ?? 0) > 0) {
                $routes++;
            }
        }
        if ($routes > 1) {
            return 'mixed';
        }
        if (($actual['bank_amount'] ?? 0) > 0) {
            return 'bank_refund';
        }
        if (($actual['charge_amount'] ?? 0) > 0) {
            return 'charge_reversal';
        }
        if (($actual['cash_amount'] ?? 0) > 0) {
            return 'cash';
        }
        return 'other';
    }

    private static function summarizeExisting(array $rows): array
    {
        $result = [
            'cash_amount' => 0.0,
            'charge_amount' => 0.0,
            'bank_amount' => 0.0,
            'other_amount' => 0.0,
            'allocations' => [],
            'already_applied' => true,
        ];

        foreach ($rows as $row) {
            $amount = self::amount((float) $row['amount']);
            $key = match ($row['refund_route']) {
                'CASH' => 'cash_amount',
                'CHARGE_REVERSAL' => 'charge_amount',
                'BANK_REFUND' => 'bank_amount',
                default => 'other_amount',
            };
            $result[$key] += $amount;
            $result['allocations'][] = [
                'allocation_id' => (int) $row['allocation_id'],
                'refund_route' => $row['refund_route'],
                'amount' => $amount,
                'status' => $row['status'],
            ];
        }

        foreach (['cash_amount', 'charge_amount', 'bank_amount', 'other_amount'] as $key) {
            $result[$key] = self::amount($result[$key]);
        }
        $result['total_allocated'] = self::amount(
            $result['cash_amount']
            + $result['charge_amount']
            + $result['bank_amount']
            + $result['other_amount']
        );
        return $result;
    }

    private static function assertSource(string $sourceType, int $sourceId): void
    {
        if (!in_array($sourceType, ['TICKET_TRANSACTION', 'SERVICE_TRANSACTION'], true) || $sourceId <= 0) {
            throw new InvalidArgumentException('Invalid refund source transaction.');
        }
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

    private static function amount(float $amount): float
    {
        return round($amount, 2);
    }
}
