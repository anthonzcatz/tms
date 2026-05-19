-- Migration: Ensure activity_logs has log_id primary key auto-increment
-- This fixes activity tracking for updates and changes

-- Check if log_id exists, if not add it
SET @dbname = DATABASE();
SET @tablename = 'activity_logs';
SET @columnname = 'log_id';

SET @sql = IF(
    NOT EXISTS(
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = @dbname 
        AND table_name = @tablename 
        AND column_name = @columnname
    ),
    'ALTER TABLE activity_logs ADD COLUMN log_id BIGINT AUTO_INCREMENT PRIMARY KEY FIRST;',
    'SELECT "log_id column already exists" AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- If log_id exists but is not primary key, fix it
ALTER TABLE activity_logs 
MODIFY COLUMN log_id BIGINT AUTO_INCREMENT PRIMARY KEY;

-- Ensure all other columns exist and are properly typed
ALTER TABLE activity_logs 
MODIFY COLUMN user_id BIGINT NULL,
MODIFY COLUMN device_id BIGINT NULL,
MODIFY COLUMN action VARCHAR(100),
MODIFY COLUMN module_name VARCHAR(100),
MODIFY COLUMN reference_code VARCHAR(100),
MODIFY COLUMN ip_address VARCHAR(100),
MODIFY COLUMN old_value LONGTEXT,
MODIFY COLUMN new_value LONGTEXT,
MODIFY COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add index on user_id for faster lookups
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);

-- Add index on created_at for sorting
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Add index on module_name and action for filtering
CREATE INDEX idx_activity_logs_module_action ON activity_logs(module_name, action);
