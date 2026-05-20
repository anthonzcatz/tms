-- Migration: Add Cashier Transportation Type Assignments
-- Description: Allow cashiers to be assigned to specific transportation types (airline, shipping, bus, etc.)
-- This enables flexible cashier assignment - e.g., cashier 1 can only sell airline tickets,
-- cashier 2 can sell airline and shipping tickets, etc.

-- Create cashier_transport_assignments table
CREATE TABLE IF NOT EXISTS cashier_transport_assignments (
    assignment_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL COMMENT 'User ID (cashier)',
    provider_id BIGINT NULL COMMENT 'Specific provider ID (NULL if using transport_type)',
    transport_type ENUM('airline', 'shipping', 'bus', 'other') NULL COMMENT 'Transportation type (NULL if using specific provider_id)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NULL COMMENT 'User who created this assignment',
    
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES ticket_providers(provider_id) ON DELETE CASCADE,
    
    -- Ensure at least one of provider_id or transport_type is specified
    CHECK (
        (provider_id IS NOT NULL AND transport_type IS NULL) OR
        (provider_id IS NULL AND transport_type IS NOT NULL)
    ),
    
    -- Prevent duplicate assignments
    UNIQUE KEY uk_user_provider (user_id, provider_id, transport_type),
    
    INDEX idx_user_id (user_id),
    INDEX idx_provider_id (provider_id),
    INDEX idx_transport_type (transport_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cashier to transportation type assignments';

-- Add column to user_accounts to indicate if cashier has restricted transport types
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'user_accounts' 
                   AND COLUMN_NAME = 'has_restricted_transport');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE user_accounts ADD COLUMN has_restricted_transport TINYINT(1) DEFAULT 0 COMMENT ''Whether cashier is restricted to specific transport types/providers'' AFTER role_id',
    'SELECT "Column has_restricted_transport already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
