-- Migration: Add original_grand_total and total_refunded_amount columns to pos_orders
-- Purpose: Track the original amount before cancellations and total refunded amount to display correctly in transaction history
-- Run this in phpMyAdmin or MySQL CLI

-- Check if original_grand_total column already exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_orders'
    AND column_name = 'original_grand_total'
);

-- Only add original_grand_total column if it doesn't exist
SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `pos_orders` ADD COLUMN `original_grand_total` decimal(12,2) DEFAULT 0.00 COMMENT ''Original amount before cancellations'' AFTER `grand_total`',
    'SELECT "original_grand_total column already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if total_refunded_amount column already exists
SET @column_exists2 = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_orders'
    AND column_name = 'total_refunded_amount'
);

-- Only add total_refunded_amount column if it doesn't exist
SET @sql2 = IF(@column_exists2 = 0,
    'ALTER TABLE `pos_orders` ADD COLUMN `total_refunded_amount` decimal(12,2) DEFAULT 0.00 COMMENT ''Total amount refunded from cancellations'' AFTER `original_grand_total`',
    'SELECT "total_refunded_amount column already exists" AS message'
);

PREPARE stmt FROM @sql2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records: set original_grand_total to current grand_total for records that don't have it set
UPDATE pos_orders 
SET original_grand_total = grand_total 
WHERE original_grand_total = 0 OR original_grand_total IS NULL;

-- Calculate total_refunded_amount for existing orders based on cancelled items
UPDATE pos_orders o
SET total_refunded_amount = (
    SELECT COALESCE(SUM(tc.refund_amount), 0)
    FROM pos_order_items oi
    LEFT JOIN ticket_transactions tt ON oi.reference_id = tt.transaction_id AND oi.item_type = 'TICKET'
    LEFT JOIN ticket_cancellations tc ON tt.transaction_id = tc.transaction_id
    WHERE oi.order_id = o.order_id AND tc.status IN ('approved', 'completed')
)
WHERE total_refunded_amount = 0 OR total_refunded_amount IS NULL;

SELECT "Migration completed successfully" AS message;
