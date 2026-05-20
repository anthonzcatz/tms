-- Add current_balance to bank_accounts table (if not exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'bank_accounts' 
                   AND COLUMN_NAME = 'current_balance');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE bank_accounts ADD COLUMN current_balance DECIMAL(12,2) DEFAULT 0.00 AFTER account_type',
    'SELECT "Column current_balance already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add bank_account_id to cashier_sessions to track where cash was deposited
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'cashier_sessions' 
                   AND COLUMN_NAME = 'cash_deposit_bank_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE cashier_sessions ADD COLUMN cash_deposit_bank_id BIGINT NULL AFTER notes',
    'SELECT "Column cash_deposit_bank_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint (if not exists)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'cashier_sessions' 
                  AND COLUMN_NAME = 'cash_deposit_bank_id' 
                  AND REFERENCED_TABLE_NAME = 'bank_accounts');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE cashier_sessions ADD CONSTRAINT fk_cash_deposit_bank FOREIGN KEY (cash_deposit_bank_id) REFERENCES bank_accounts(bank_account_id) ON DELETE SET NULL',
    'SELECT "Foreign key already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add deposit status to track if cash has been deposited
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'cashier_sessions' 
                   AND COLUMN_NAME = 'deposit_status');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE cashier_sessions ADD COLUMN deposit_status ENUM("PENDING", "DEPOSITED", "NOT_APPLICABLE") DEFAULT "PENDING" AFTER cash_deposit_bank_id',
    'SELECT "Column deposit_status already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add deposited_at timestamp
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'cashier_sessions' 
                   AND COLUMN_NAME = 'deposited_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE cashier_sessions ADD COLUMN deposited_at TIMESTAMP NULL AFTER deposit_status',
    'SELECT "Column deposited_at already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add deposited_by
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'cashier_sessions' 
                   AND COLUMN_NAME = 'deposited_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE cashier_sessions ADD COLUMN deposited_by BIGINT NULL AFTER deposited_at',
    'SELECT "Column deposited_by already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add confirmation_status to bank_transactions for deposit confirmation workflow
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'bank_transactions' 
                   AND COLUMN_NAME = 'confirmation_status');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE bank_transactions ADD COLUMN confirmation_status ENUM("PENDING", "CONFIRMED", "REJECTED") DEFAULT "CONFIRMED" AFTER txn_code',
    'SELECT "Column confirmation_status already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add confirmed_by and confirmed_at to bank_transactions
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'bank_transactions' 
                   AND COLUMN_NAME = 'confirmed_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE bank_transactions ADD COLUMN confirmed_by BIGINT NULL AFTER created_by',
    'SELECT "Column confirmed_by already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'bank_transactions' 
                   AND COLUMN_NAME = 'confirmed_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE bank_transactions ADD COLUMN confirmed_at TIMESTAMP NULL AFTER confirmed_by',
    'SELECT "Column confirmed_at already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
