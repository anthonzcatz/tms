-- Migration: Financial source refunds and append-only ledger consistency
-- Date: 2026-08-12
-- Purpose:
--   1. Link wallet/bank reversals to the original ledger movement.
--   2. Store source-specific refund allocations for ticket/service refunds.
--   3. Keep pending cash refunds separate from finalized cashier cash.
--   4. Align bank deposit/refund transaction types with the application flows.
--
-- Run after the wallet, flexible-payment, ticket-cancellation, ticket-refund,
-- and bank transaction migrations. This migration is ticket-only; service
-- cancellation tables are intentionally managed by their separate migration.
-- All statements are intended to be safe to re-run.

-- =========================================================
-- 1. IDEMPOTENT LEDGER REFERENCES
-- =========================================================
ALTER TABLE `wallet_transactions`
    ADD COLUMN IF NOT EXISTS `idempotency_key` varchar(150) DEFAULT NULL
        COMMENT 'Stable key used to prevent duplicate sale/refund/reversal rows',
    ADD COLUMN IF NOT EXISTS `reversal_of_txn_id` bigint(20) DEFAULT NULL
        COMMENT 'Original wallet transaction reversed by this movement';

CREATE UNIQUE INDEX IF NOT EXISTS `uq_wallet_transactions_idempotency`
    ON `wallet_transactions` (`idempotency_key`);

CREATE INDEX IF NOT EXISTS `idx_wallet_transactions_reference`
    ON `wallet_transactions` (`reference_table`, `reference_id`);

-- Older installations stored POS sale rows as an empty enum value because
-- SALE was missing from the original wallet transaction definition.
ALTER TABLE `wallet_transactions`
    MODIFY COLUMN `txn_type` enum('TOPUP','SALE','REFUND','ADJUSTMENT') DEFAULT NULL;

UPDATE `wallet_transactions`
SET `txn_type` = 'SALE'
WHERE (`txn_type` IS NULL OR `txn_type` = '')
  AND `direction` = 'OUT'
  AND `reference_table` = 'ticket_transactions';

ALTER TABLE `bank_transactions`
    ADD COLUMN IF NOT EXISTS `idempotency_key` varchar(150) DEFAULT NULL
        COMMENT 'Stable key used to prevent duplicate bank receipts/refunds',
    ADD COLUMN IF NOT EXISTS `reversal_of_txn_id` bigint(20) DEFAULT NULL
        COMMENT 'Original bank transaction reversed by this movement',
    ADD COLUMN IF NOT EXISTS `confirmation_notes` text DEFAULT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS `uq_bank_transactions_idempotency`
    ON `bank_transactions` (`idempotency_key`);

CREATE INDEX IF NOT EXISTS `idx_bank_transactions_reference`
    ON `bank_transactions` (`reference_table`, `reference_id`);

-- The application records delayed cash deposits as DEPOSIT and refunds as
-- outbound REFUND movements. Keep the enum aligned with those workflows.
ALTER TABLE `bank_transactions`
    MODIFY COLUMN `txn_type` enum(
        'RECEIPT',
        'DEPOSIT',
        'DISBURSEMENT',
        'TRANSFER_IN',
        'TRANSFER_OUT',
        'ADJUSTMENT',
        'REFUND'
    ) DEFAULT NULL;

-- =========================================================
-- 2. SOURCE-SPECIFIC REFUND ALLOCATIONS
-- =========================================================
CREATE TABLE IF NOT EXISTS `refund_allocations` (
    `allocation_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `refund_scope` enum('TICKET','SERVICE') NOT NULL,
    `refund_id` bigint(20) NOT NULL COMMENT 'ticket_refunds.refund_id',
    `source_payment_id` bigint(20) DEFAULT NULL COMMENT 'Original transaction_payments.payment_id when applicable',
    `payment_method_id` bigint(20) DEFAULT NULL,
    `payment_method_type` varchar(50) DEFAULT NULL,
    `refund_route` enum('CHARGE_REVERSAL','CASH','BANK_REFUND','OTHER') NOT NULL,
    `bank_account_id` bigint(20) DEFAULT NULL,
    `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
    `status` enum('PENDING','PROCESSED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `reference_table` varchar(100) DEFAULT NULL,
    `reference_id` bigint(20) DEFAULT NULL,
    `idempotency_key` varchar(150) NOT NULL,
    `created_by` bigint(20) DEFAULT NULL,
    `processed_by` bigint(20) DEFAULT NULL,
    `processed_at` timestamp NULL DEFAULT NULL,
    `remarks` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`allocation_id`),
    UNIQUE KEY `uq_refund_allocations_idempotency` (`idempotency_key`),
    KEY `idx_refund_allocations_refund` (`refund_scope`, `refund_id`),
    KEY `idx_refund_allocations_source_payment` (`source_payment_id`),
    KEY `idx_refund_allocations_bank_account` (`bank_account_id`),
    KEY `idx_refund_allocations_status` (`status`),
    CONSTRAINT `fk_refund_allocations_payment_method`
        FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`method_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_refund_allocations_bank_account`
        FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`bank_account_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_refund_allocations_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_refund_allocations_processed_by`
        FOREIGN KEY (`processed_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Source-specific refund allocation and reversal audit ledger';

ALTER TABLE `refund_allocations`
    MODIFY COLUMN `refund_id` bigint(20) NOT NULL COMMENT 'ticket_refunds.refund_id';

-- Ticket cancellations only. Service cancellations are outside the POS
-- cancellation scope and are managed, if enabled, by their separate migration.
ALTER TABLE `ticket_refunds`
    ADD COLUMN IF NOT EXISTS `bank_amount` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Amount refunded through bank/e-wallet account',
    ADD COLUMN IF NOT EXISTS `other_amount` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Amount requiring an external/non-bank refund route',
    ADD COLUMN IF NOT EXISTS `refund_method` varchar(50) DEFAULT NULL;

-- =========================================================
-- 3. CASHIER REFUND RESERVATION
-- =========================================================
ALTER TABLE `cashier_sessions`
    ADD COLUMN IF NOT EXISTS `pending_refunds_cash` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Cash refunds pending approval; excluded from finalized expected cash',
    ADD COLUMN IF NOT EXISTS `total_refunds` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Finalized cash refunds paid from the cashier drawer';

-- Existing installations used total_refunds_wallet for pending cash. Do not
-- silently rewrite historical values; new application code uses the dedicated
-- pending_refunds_cash and total_refunds fields.

-- =========================================================
-- 4. STOCK MOVEMENT COMPATIBILITY
-- =========================================================
-- POS_CANCEL was used by the helper before the enum was updated. Keep the
-- explicit value for existing audit rows while the helper now writes the
-- canonical POS_SALE_REVERSAL value.
ALTER TABLE `ticket_stock_movements`
    MODIFY COLUMN `movement_type` enum(
        'OPENING_BALANCE',
        'POS_SALE',
        'POS_SALE_REVERSAL',
        'POS_CANCEL',
        'RETURN_TO_SOURCE',
        'DAMAGE_OR_VOID',
        'STOCK_IN',
        'STOCK_OUT',
        'DISPATCH',
        'RECEIPT',
        'ADJUSTMENT',
        'REPLACEMENT',
        'NEGATIVE_BALANCE'
    ) NOT NULL;

-- =========================================================
-- 5. PAYMENT CONFIRMATION LOOKUPS
-- =========================================================
CREATE INDEX IF NOT EXISTS `idx_transaction_payments_confirmation_source`
    ON `transaction_payments` (`confirmation_status`, `source_type`, `source_id`);

CREATE INDEX IF NOT EXISTS `idx_charge_payments_confirmation_source`
    ON `charge_payments` (`confirmation_status`, `passenger_id`, `bank_account_id`);
