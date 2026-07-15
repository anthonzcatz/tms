-- Migration: Add provider wallet hierarchy support
-- Purpose:
--   1. Add parent_provider_id to ticket_providers for parent/child relationship.
--   2. Add provider_id to ticket_transactions to store the operating provider.
--   3. Add provider_id to pos_order_items to store the operating provider.
--   4. Backfill existing ticket transactions and order items from wallet/provider.
--
-- Run once in phpMyAdmin or MySQL CLI.

-- =========================================================
-- ticket_providers: parent_provider_id
-- =========================================================
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'ticket_providers'
    AND column_name = 'parent_provider_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ticket_providers` ADD COLUMN `parent_provider_id` BIGINT NULL AFTER `provider_id`',
    'SELECT "parent_provider_id column already exists in ticket_providers" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index on parent_provider_id
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
    AND table_name = 'ticket_providers'
    AND index_name = 'idx_parent_provider_id'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `ticket_providers` ADD INDEX `idx_parent_provider_id` (`parent_provider_id`)',
    'SELECT "idx_parent_provider_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Self-referencing foreign key
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
    AND table_name = 'ticket_providers'
    AND constraint_name = 'fk_ticket_providers_parent'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `ticket_providers` ADD CONSTRAINT `fk_ticket_providers_parent` FOREIGN KEY (`parent_provider_id`) REFERENCES `ticket_providers` (`provider_id`) ON DELETE SET NULL',
    'SELECT "fk_ticket_providers_parent already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- ticket_transactions: provider_id (operating provider)
-- =========================================================
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'ticket_transactions'
    AND column_name = 'provider_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `ticket_transactions` ADD COLUMN `provider_id` BIGINT NULL AFTER `wallet_id`',
    'SELECT "provider_id column already exists in ticket_transactions" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill ticket_transactions provider_id from the wallet provider
UPDATE `ticket_transactions` tt
SET tt.provider_id = (
    SELECT pw.provider_id
    FROM `provider_wallets` pw
    WHERE pw.wallet_id = tt.wallet_id
)
WHERE tt.provider_id IS NULL;

-- Make provider_id NOT NULL after backfill
SET @col_nullable = (
    SELECT is_nullable
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'ticket_transactions'
    AND column_name = 'provider_id'
);

SET @sql = IF(@col_nullable = 'YES',
    'ALTER TABLE `ticket_transactions` MODIFY COLUMN `provider_id` BIGINT NOT NULL',
    'SELECT "provider_id already NOT NULL in ticket_transactions" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index on ticket_transactions provider_id
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
    AND table_name = 'ticket_transactions'
    AND index_name = 'idx_ticket_transactions_provider_id'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `ticket_transactions` ADD INDEX `idx_ticket_transactions_provider_id` (`provider_id`)',
    'SELECT "idx_ticket_transactions_provider_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Foreign key to ticket_providers
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
    AND table_name = 'ticket_transactions'
    AND constraint_name = 'fk_ticket_transactions_provider_id'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `ticket_transactions` ADD CONSTRAINT `fk_ticket_transactions_provider_id` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`) ON DELETE RESTRICT',
    'SELECT "fk_ticket_transactions_provider_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- pos_order_items: provider_id (operating provider, nullable for services)
-- =========================================================
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_order_items'
    AND column_name = 'provider_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `pos_order_items` ADD COLUMN `provider_id` BIGINT NULL AFTER `discount_id`',
    'SELECT "provider_id column already exists in pos_order_items" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill pos_order_items provider_id from ticket_transactions for ticket items
UPDATE `pos_order_items` oi
LEFT JOIN `ticket_transactions` tt ON oi.reference_id = tt.transaction_id AND oi.item_type = 'TICKET'
SET oi.provider_id = tt.provider_id
WHERE oi.item_type = 'TICKET'
AND oi.provider_id IS NULL;

-- Index on pos_order_items provider_id
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_order_items'
    AND index_name = 'idx_pos_order_items_provider_id'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `pos_order_items` ADD INDEX `idx_pos_order_items_provider_id` (`provider_id`)',
    'SELECT "idx_pos_order_items_provider_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Foreign key to ticket_providers (nullable for service items)
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_order_items'
    AND constraint_name = 'fk_pos_order_items_provider_id'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `pos_order_items` ADD CONSTRAINT `fk_pos_order_items_provider_id` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`) ON DELETE SET NULL',
    'SELECT "fk_pos_order_items_provider_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
