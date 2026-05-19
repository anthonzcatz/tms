-- Migration: Add pos_orders and pos_order_items tables
-- Purpose: Group 1 ticket + multiple services under one order for comprehensive reporting.
-- Each confirmed POS checkout creates one pos_orders row.
-- Each item in the cart (1 ticket or N services) becomes a pos_order_items row.
-- The existing ticket_transactions and service_transactions retain their rows;
-- pos_order_items.reference_id points back to them.
--
-- Run once in phpMyAdmin or MySQL CLI.

CREATE TABLE IF NOT EXISTS `pos_orders` (
  `order_id`           bigint(20)    NOT NULL AUTO_INCREMENT,
  `order_code`         varchar(60)   NOT NULL COMMENT 'e.g. ORD-20260519-093012-123-45',
  `branch_id`          bigint(20)    DEFAULT NULL,
  `cashier_session_id` bigint(20)    DEFAULT NULL,
  `created_by`         bigint(20)    NOT NULL,
  `subtotal`           decimal(12,2) DEFAULT 0.00 COMMENT 'Pre-discount sum of all items',
  `discount_total`     decimal(12,2) DEFAULT 0.00,
  `grand_total`        decimal(12,2) DEFAULT 0.00 COMMENT 'Amount due after discounts',
  `amount_paid`        decimal(12,2) DEFAULT 0.00,
  `change_amount`      decimal(12,2) DEFAULT 0.00,
  `status`             enum('completed','cancelled','refunded') DEFAULT 'completed',
  `remarks`            text          DEFAULT NULL,
  `created_at`         timestamp     NOT NULL DEFAULT current_timestamp(),
  `updated_at`         timestamp     NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uq_order_code` (`order_code`),
  KEY `idx_branch_id`          (`branch_id`),
  KEY `idx_cashier_session_id` (`cashier_session_id`),
  KEY `idx_created_by`         (`created_by`),
  KEY `idx_created_at`         (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_order_items` (
  `item_id`           bigint(20)    NOT NULL AUTO_INCREMENT,
  `order_id`          bigint(20)    NOT NULL,
  `item_type`         enum('TICKET','SERVICE') NOT NULL,
  `reference_id`      bigint(20)    DEFAULT NULL COMMENT 'ticket_transactions.transaction_id or service_transactions.service_txn_id',
  `transaction_code`  varchar(60)   DEFAULT NULL COMMENT 'Denormalized for quick lookup (TKT-... or SVC-...)',
  `total_amount`      decimal(12,2) NOT NULL COMMENT 'Item total for quick order sum calculations',
  `created_at`        timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `idx_order_id`     (`order_id`),
  KEY `idx_reference_id` (`reference_id`),
  KEY `idx_item_type`    (`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foreign keys (only added if not already present)
-- Check and add fk_pos_orders_branch
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_orders'
    AND constraint_name = 'fk_pos_orders_branch'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `pos_orders` ADD CONSTRAINT `fk_pos_orders_branch` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE SET NULL',
    'SELECT "fk_pos_orders_branch already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add fk_pos_orders_session
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_orders'
    AND constraint_name = 'fk_pos_orders_session'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `pos_orders` ADD CONSTRAINT `fk_pos_orders_session` FOREIGN KEY (`cashier_session_id`) REFERENCES `cashier_sessions` (`session_id`) ON DELETE SET NULL',
    'SELECT "fk_pos_orders_session already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add fk_pos_order_items_order
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_order_items'
    AND constraint_name = 'fk_pos_order_items_order'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `pos_order_items` ADD CONSTRAINT `fk_pos_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `pos_orders` (`order_id`) ON DELETE CASCADE',
    'SELECT "fk_pos_order_items_order already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
