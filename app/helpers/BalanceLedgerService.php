<?php
/**
 * BalanceLedgerService
 *
 * Transaction-safe, append-only balance movements for provider wallets and
 * company bank accounts. Callers may already have an open transaction; this
 * service reuses it and only commits transactions that it starts itself.
 */

require_once __DIR__ . '/../../config/database.php';

final class BalanceLedgerService
{
    private const WALLET_TYPES = ['TOPUP', 'SALE', 'REFUND', 'ADJUSTMENT'];
    private const BANK_TYPES = [
        'RECEIPT',
        'DEPOSIT',
        'DISBURSEMENT',
        'TRANSFER_IN',
        'TRANSFER_OUT',
        'ADJUSTMENT',
        'REFUND',
    ];

    /**
     * Apply a provider-wallet movement and write its ledger row atomically.
     */
    public static function walletMovement(
        int $walletId,
        string $txnType,
        string $direction,
        float $amount,
        ?string $referenceTable,
        ?int $referenceId,
        ?string $remarks,
        int $createdBy,
        ?string $idempotencyKey = null,
        ?int $reversalOfTxnId = null,
        ?bool $allowOverdraft = null
    ): array {
        $amount = self::amount($amount);
        $txnType = strtoupper(trim($txnType));
        $direction = strtoupper(trim($direction));

        if ($walletId <= 0 || $createdBy <= 0) {
            throw new InvalidArgumentException('A valid wallet and user are required.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Balance movement amount must be greater than zero.');
        }
        if (!in_array($txnType, self::WALLET_TYPES, true)) {
            throw new InvalidArgumentException('Invalid wallet transaction type.');
        }
        if (!in_array($direction, ['IN', 'OUT'], true)) {
            throw new InvalidArgumentException('Invalid wallet transaction direction.');
        }
        if (($txnType === 'TOPUP' && $direction !== 'IN')
            || ($txnType === 'SALE' && $direction !== 'OUT')
            || ($txnType === 'REFUND' && $direction !== 'IN')) {
            $expectedDirection = $txnType === 'SALE' ? 'OUT' : 'IN';
            throw new InvalidArgumentException("{$txnType} must use the {$expectedDirection} direction.");
        }

        return self::withinTransaction(function () use (
            $walletId,
            $txnType,
            $direction,
            $amount,
            $referenceTable,
            $referenceId,
            $remarks,
            $createdBy,
            $idempotencyKey,
            $reversalOfTxnId,
            $allowOverdraft
        ) {
            if ($idempotencyKey) {
                $existing = Database::fetch(
                    'SELECT wallet_txn_id, wallet_id, txn_code, txn_type, direction, amount,
                            balance_before, balance_after, reference_table, reference_id,
                            created_at
                     FROM wallet_transactions
                     WHERE idempotency_key = :idempotency_key
                     LIMIT 1',
                    ['idempotency_key' => $idempotencyKey]
                );
                if ($existing) {
                    return self::walletResult($existing, true);
                }
            }

            $wallet = Database::fetch(
                "SELECT wallet_id, current_balance, branch_id, status
                 FROM provider_wallets
                 WHERE wallet_id = :wallet_id
                   AND status = 'active'
                 FOR UPDATE",
                ['wallet_id' => $walletId]
            );

            if (!$wallet) {
                throw new RuntimeException('Wallet not found or inactive.');
            }

            $balanceBefore = self::amount((float) $wallet['current_balance']);
            $balanceAfter = $direction === 'IN'
                ? self::amount($balanceBefore + $amount)
                : self::amount($balanceBefore - $amount);

            if ($direction === 'OUT' && $allowOverdraft === null) {
                $allowOverdraft = self::walletOverdraftAllowed();
            }
            if ($direction === 'OUT' && $balanceAfter < 0 && !$allowOverdraft) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $txnCode = self::code($txnType);
            Database::execute(
                "INSERT INTO wallet_transactions
                    (wallet_id, txn_code, txn_type, direction, amount,
                     balance_before, balance_after, reference_table, reference_id,
                     remarks, created_by, idempotency_key, reversal_of_txn_id, created_at)
                 VALUES (:wallet_id, :txn_code, :txn_type, :direction, :amount,
                         :balance_before, :balance_after, :reference_table, :reference_id,
                         :remarks, :created_by, :idempotency_key, :reversal_of_txn_id, NOW())",
                [
                    'wallet_id' => $walletId,
                    'txn_code' => $txnCode,
                    'txn_type' => $txnType,
                    'direction' => $direction,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'reference_table' => $referenceTable,
                    'reference_id' => $referenceId,
                    'remarks' => $remarks,
                    'created_by' => $createdBy,
                    'idempotency_key' => $idempotencyKey,
                    'reversal_of_txn_id' => $reversalOfTxnId,
                ]
            );

            $walletTxnId = (int) Database::lastInsertId();
            Database::execute(
                'UPDATE provider_wallets
                 SET current_balance = :balance_after, updated_at = NOW()
                 WHERE wallet_id = :wallet_id',
                ['balance_after' => $balanceAfter, 'wallet_id' => $walletId]
            );

            return [
                'already_applied' => false,
                'wallet_txn_id' => $walletTxnId,
                'wallet_id' => $walletId,
                'txn_code' => $txnCode,
                'txn_type' => $txnType,
                'direction' => $direction,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'branch_id' => (int) $wallet['branch_id'],
            ];
        });
    }

    /**
     * Apply a bank-account movement and write its ledger row atomically.
     */
    public static function bankMovement(
        int $bankAccountId,
        string $txnType,
        string $direction,
        float $amount,
        ?string $referenceTable,
        ?int $referenceId,
        ?string $remarks,
        int $createdBy,
        ?string $idempotencyKey = null,
        ?int $reversalOfTxnId = null,
        ?bool $allowNegative = false,
        ?string $confirmationNotes = null
    ): array {
        $amount = self::amount($amount);
        $txnType = strtoupper(trim($txnType));
        $direction = strtoupper(trim($direction));

        if ($bankAccountId <= 0 || $createdBy <= 0) {
            throw new InvalidArgumentException('A valid bank account and user are required.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Bank movement amount must be greater than zero.');
        }
        if (!in_array($txnType, self::BANK_TYPES, true)) {
            throw new InvalidArgumentException('Invalid bank transaction type.');
        }
        if (!in_array($direction, ['IN', 'OUT'], true)) {
            throw new InvalidArgumentException('Invalid bank transaction direction.');
        }

        return self::withinTransaction(function () use (
            $bankAccountId,
            $txnType,
            $direction,
            $amount,
            $referenceTable,
            $referenceId,
            $remarks,
            $createdBy,
            $idempotencyKey,
            $reversalOfTxnId,
            $allowNegative,
            $confirmationNotes
        ) {
            if ($idempotencyKey) {
                $existing = Database::fetch(
                    'SELECT bank_txn_id, bank_account_id, txn_code, confirmation_status,
                            txn_type, direction, amount, balance_before, balance_after,
                            reference_table, reference_id, created_at
                     FROM bank_transactions
                     WHERE idempotency_key = :idempotency_key
                     LIMIT 1',
                    ['idempotency_key' => $idempotencyKey]
                );
                if ($existing) {
                    return self::bankResult($existing, true);
                }
            }

            $account = Database::fetch(
                "SELECT bank_account_id, branch_id, current_balance, is_active
                 FROM bank_accounts
                 WHERE bank_account_id = :bank_account_id
                   AND is_active = 1
                 FOR UPDATE",
                ['bank_account_id' => $bankAccountId]
            );

            if (!$account) {
                throw new RuntimeException('Bank account not found or inactive.');
            }

            $balanceBefore = self::amount((float) $account['current_balance']);
            $balanceAfter = $direction === 'IN'
                ? self::amount($balanceBefore + $amount)
                : self::amount($balanceBefore - $amount);

            if ($direction === 'OUT' && $balanceAfter < 0 && !$allowNegative) {
                throw new RuntimeException('Insufficient bank-account balance.');
            }

            $txnCode = self::code('BANK');
            Database::execute(
                "INSERT INTO bank_transactions
                    (bank_account_id, txn_code, confirmation_status, txn_type, direction,
                     amount, balance_before, balance_after, reference_table, reference_id,
                     remarks, created_by, confirmed_by, confirmed_at, idempotency_key,
                     reversal_of_txn_id, confirmation_notes, created_at)
                 VALUES (:bank_account_id, :txn_code, 'CONFIRMED', :txn_type, :direction,
                         :amount, :balance_before, :balance_after, :reference_table, :reference_id,
                         :remarks, :created_by, :confirmed_by, NOW(), :idempotency_key,
                         :reversal_of_txn_id, :confirmation_notes, NOW())",
                [
                    'bank_account_id' => $bankAccountId,
                    'txn_code' => $txnCode,
                    'txn_type' => $txnType,
                    'direction' => $direction,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'reference_table' => $referenceTable,
                    'reference_id' => $referenceId,
                    'remarks' => $remarks,
                    'created_by' => $createdBy,
                    'confirmed_by' => $createdBy,
                    'idempotency_key' => $idempotencyKey,
                    'reversal_of_txn_id' => $reversalOfTxnId,
                    'confirmation_notes' => $confirmationNotes,
                ]
            );

            $bankTxnId = (int) Database::lastInsertId();
            Database::execute(
                'UPDATE bank_accounts
                 SET current_balance = :balance_after, updated_at = NOW()
                 WHERE bank_account_id = :bank_account_id',
                ['balance_after' => $balanceAfter, 'bank_account_id' => $bankAccountId]
            );

            return [
                'already_applied' => false,
                'bank_txn_id' => $bankTxnId,
                'bank_account_id' => $bankAccountId,
                'txn_code' => $txnCode,
                'txn_type' => $txnType,
                'direction' => $direction,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'branch_id' => $account['branch_id'] !== null ? (int) $account['branch_id'] : null,
            ];
        });
    }

    /**
     * Confirm a bank transaction that was created in PENDING state (for
     * delayed cash deposits). The existing pending row becomes the receipt;
     * no duplicate bank ledger row is created.
     */
    public static function confirmPendingBankTransaction(
        int $bankTxnId,
        int $confirmedBy,
        ?string $confirmationNotes = null
    ): array {
        if ($bankTxnId <= 0 || $confirmedBy <= 0) {
            throw new InvalidArgumentException('A valid pending bank transaction and user are required.');
        }

        return self::withinTransaction(function () use ($bankTxnId, $confirmedBy, $confirmationNotes) {
            $pending = Database::fetch(
                'SELECT * FROM bank_transactions WHERE bank_txn_id = :bank_txn_id FOR UPDATE',
                ['bank_txn_id' => $bankTxnId]
            );
            if (!$pending) {
                throw new RuntimeException('Pending bank transaction not found.');
            }
            if ($pending['confirmation_status'] === 'CONFIRMED') {
                return self::bankResult($pending, true);
            }
            if ($pending['confirmation_status'] !== 'PENDING') {
                throw new RuntimeException('Bank transaction is no longer pending.');
            }

            $account = Database::fetch(
                'SELECT bank_account_id, branch_id, current_balance
                 FROM bank_accounts
                 WHERE bank_account_id = :bank_account_id
                   AND is_active = 1
                 FOR UPDATE',
                ['bank_account_id' => (int) $pending['bank_account_id']]
            );
            if (!$account) {
                throw new RuntimeException('Bank account not found or inactive.');
            }

            $before = self::amount((float) $account['current_balance']);
            $after = $pending['direction'] === 'OUT'
                ? self::amount($before - (float) $pending['amount'])
                : self::amount($before + (float) $pending['amount']);
            if ($after < 0) {
                throw new RuntimeException('Insufficient bank-account balance.');
            }

            Database::execute(
                "UPDATE bank_transactions
                 SET confirmation_status = 'CONFIRMED',
                     balance_before = :balance_before,
                     balance_after = :balance_after,
                     confirmed_by = :confirmed_by,
                     confirmed_at = NOW(),
                     confirmation_notes = :confirmation_notes
                 WHERE bank_txn_id = :bank_txn_id",
                [
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'confirmed_by' => $confirmedBy,
                    'confirmation_notes' => $confirmationNotes,
                    'bank_txn_id' => $bankTxnId,
                ]
            );
            Database::execute(
                'UPDATE bank_accounts
                 SET current_balance = :balance_after, updated_at = NOW()
                 WHERE bank_account_id = :bank_account_id',
                ['balance_after' => $after, 'bank_account_id' => (int) $pending['bank_account_id']]
            );

            $pending['confirmation_status'] = 'CONFIRMED';
            $pending['balance_before'] = $before;
            $pending['balance_after'] = $after;
            $pending['confirmed_by'] = $confirmedBy;
            return self::bankResult($pending, false);
        });
    }

    /**
     * Reject a pending bank transaction without touching the account balance.
     */
    public static function rejectPendingBankTransaction(
        int $bankTxnId,
        int $rejectedBy,
        ?string $confirmationNotes = null
    ): array {
        if ($bankTxnId <= 0 || $rejectedBy <= 0) {
            throw new InvalidArgumentException('A valid pending bank transaction and user are required.');
        }

        return self::withinTransaction(function () use ($bankTxnId, $rejectedBy, $confirmationNotes) {
            $pending = Database::fetch(
                'SELECT * FROM bank_transactions WHERE bank_txn_id = :bank_txn_id FOR UPDATE',
                ['bank_txn_id' => $bankTxnId]
            );
            if (!$pending) {
                throw new RuntimeException('Bank transaction not found.');
            }
            if ($pending['confirmation_status'] === 'REJECTED') {
                return self::bankResult($pending, true);
            }
            if ($pending['confirmation_status'] !== 'PENDING') {
                throw new RuntimeException('Bank transaction is no longer pending.');
            }

            Database::execute(
                "UPDATE bank_transactions
                 SET confirmation_status = 'REJECTED',
                     confirmed_by = :confirmed_by,
                     confirmed_at = NOW(),
                     confirmation_notes = :confirmation_notes
                 WHERE bank_txn_id = :bank_txn_id",
                [
                    'confirmed_by' => $rejectedBy,
                    'confirmation_notes' => $confirmationNotes,
                    'bank_txn_id' => $bankTxnId,
                ]
            );
            $pending['confirmation_status'] = 'REJECTED';
            $pending['confirmed_by'] = $rejectedBy;
            return self::bankResult($pending, false);
        });
    }

    private static function walletOverdraftAllowed(): bool
    {
        try {
            $settings = Database::fetch(
                'SELECT pos_allow_insufficient_wallet
                 FROM system_settings
                 WHERE setting_id = 1'
            );
            return !empty($settings['pos_allow_insufficient_wallet']);
        } catch (Throwable $e) {
            return false;
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

    private static function code(string $prefix): string
    {
        return strtoupper($prefix) . '-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    private static function walletResult(array $row, bool $alreadyApplied): array
    {
        return [
            'already_applied' => $alreadyApplied,
            'wallet_txn_id' => (int) $row['wallet_txn_id'],
            'wallet_id' => (int) $row['wallet_id'],
            'txn_code' => $row['txn_code'],
            'txn_type' => $row['txn_type'],
            'direction' => $row['direction'],
            'amount' => self::amount((float) $row['amount']),
            'balance_before' => self::amount((float) $row['balance_before']),
            'balance_after' => self::amount((float) $row['balance_after']),
            'branch_id' => null,
        ];
    }

    private static function bankResult(array $row, bool $alreadyApplied): array
    {
        return [
            'already_applied' => $alreadyApplied,
            'bank_txn_id' => (int) $row['bank_txn_id'],
            'bank_account_id' => (int) $row['bank_account_id'],
            'txn_code' => $row['txn_code'],
            'txn_type' => $row['txn_type'],
            'direction' => $row['direction'],
            'amount' => self::amount((float) $row['amount']),
            'balance_before' => self::amount((float) $row['balance_before']),
            'balance_after' => self::amount((float) $row['balance_after']),
            'branch_id' => null,
        ];
    }
}
