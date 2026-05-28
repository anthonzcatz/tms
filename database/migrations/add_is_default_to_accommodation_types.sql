-- Migration: Add is_default column to accommodation_types
-- Purpose: Mark one accommodation as default for dropdown selection

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = 'accommodation_types'
    AND column_name = 'is_default'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `accommodation_types` ADD COLUMN `is_default` tinyint(1) DEFAULT 0 COMMENT ''Mark as default accommodation for dropdown selection'' AFTER `name`',
    'SELECT "is_default column already exists in accommodation_types" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Set ECONOMY as default accommodation
UPDATE accommodation_types SET is_default = 1 WHERE code = 'ECONOMY';
