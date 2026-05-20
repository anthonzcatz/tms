-- Add bank transaction confirmation settings to system_settings
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'system_settings' 
                   AND COLUMN_NAME = 'bank_pos_payments_require_confirmation');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE system_settings ADD COLUMN bank_pos_payments_require_confirmation TINYINT(1) DEFAULT 1 AFTER pos_manager_can_close_for_cashier',
    'SELECT "Column bank_pos_payments_require_confirmation already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'system_settings' 
                   AND COLUMN_NAME = 'bank_charge_payments_require_confirmation');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE system_settings ADD COLUMN bank_charge_payments_require_confirmation TINYINT(1) DEFAULT 0 AFTER bank_pos_payments_require_confirmation',
    'SELECT "Column bank_charge_payments_require_confirmation already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'system_settings' 
                   AND COLUMN_NAME = 'bank_deposits_require_confirmation');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE system_settings ADD COLUMN bank_deposits_require_confirmation TINYINT(1) DEFAULT 1 AFTER bank_charge_payments_require_confirmation',
    'SELECT "Column bank_deposits_require_confirmation already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
