-- Add settings column to notification_preferences for per-user threshold configuration
ALTER TABLE notification_preferences ADD COLUMN settings JSON NULL COMMENT 'Per-user notification settings (thresholds, etc.)';

-- Create system_notification_settings table for global notification thresholds
CREATE TABLE IF NOT EXISTS system_notification_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Setting key (e.g., wallet_transaction_threshold, pos_session_variance_threshold)',
    setting_value DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Threshold value',
    description VARCHAR(255) NULL COMMENT 'Description of the setting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system notification settings
INSERT INTO system_notification_settings (setting_key, setting_value, description) VALUES
('wallet_transaction_threshold', 10000.00, 'Minimum transaction amount to trigger large transaction alert'),
('pos_session_variance_threshold', 100.00, 'Minimum cash variance to trigger POS session discrepancy alert'),
('wallet_low_balance_threshold', 1000.00, 'Minimum wallet balance to trigger low balance alert')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), description=VALUES(description);
