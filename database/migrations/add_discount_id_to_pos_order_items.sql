-- Migration: Add discount_id to pos_order_items
-- Purpose: Store discount type per ticket item for display in transaction list/detail

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_order_items'
    AND column_name = 'discount_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `pos_order_items` ADD COLUMN `discount_id` bigint(20) DEFAULT NULL AFTER `accommodation_id`',
    'SELECT "discount_id column already exists in pos_order_items" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_order_items'
    AND constraint_name = 'fk_pos_order_items_discount'
);

SET @sql2 = IF(@fk_exists = 0,
    'ALTER TABLE `pos_order_items` ADD CONSTRAINT `fk_pos_order_items_discount` FOREIGN KEY (`discount_id`) REFERENCES `discount_types` (`discount_id`) ON DELETE SET NULL',
    'SELECT "fk_pos_order_items_discount already exists" AS message'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
