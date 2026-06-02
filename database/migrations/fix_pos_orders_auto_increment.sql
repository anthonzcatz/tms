-- Fix pos_orders table to add AUTO_INCREMENT to order_id
-- This fixes the foreign key constraint error where lastInsertId() returns 0

-- Check if AUTO_INCREMENT is already set
SET @auto_increment_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'pos_orders'
    AND column_name = 'order_id'
    AND extra LIKE '%auto_increment%'
);

-- Only add AUTO_INCREMENT if it doesn't exist
SET @sql = IF(@auto_increment_exists = 0,
    'ALTER TABLE `pos_orders` MODIFY COLUMN `order_id` bigint(20) NOT NULL AUTO_INCREMENT',
    'SELECT "AUTO_INCREMENT already exists on pos_orders.order_id" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure the AUTO_INCREMENT value is set correctly based on existing data
SET @max_id = (SELECT MAX(order_id) FROM pos_orders);
SET @sql = IF(@max_id IS NOT NULL,
    CONCAT('ALTER TABLE `pos_orders` AUTO_INCREMENT = ', @max_id + 1),
    'SELECT "No existing data, AUTO_INCREMENT will start at 1" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
