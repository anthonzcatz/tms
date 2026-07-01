-- =========================================================
-- NOTIFICATIONS TABLE MIGRATION
-- Flexible, future-proof notification system
-- =========================================================

-- Add default_link column to notification_templates if it doesn't exist
ALTER TABLE notification_templates 
ADD COLUMN IF NOT EXISTS default_link VARCHAR(500) NULL COMMENT 'Default redirect URL for this notification type' 
AFTER default_channels;

-- Main notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'system' COMMENT 'support, wallet, pos, payment, system, custom',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL COMMENT 'Flexible custom fields for future extensibility',
    icon VARCHAR(100) NULL COMMENT 'FontAwesome icon class',
    color VARCHAR(20) NULL DEFAULT 'info' COMMENT 'info, warning, danger, success, secondary',
    link VARCHAR(500) NULL COMMENT 'Redirect URL',
    is_read BOOLEAN DEFAULT FALSE,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    real_time BOOLEAN DEFAULT FALSE COMMENT 'true for SSE, false for polling',
    channels JSON NULL COMMENT 'email, sms, push, in-app',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at),
    INDEX idx_user_read (user_id, is_read, created_at),
    
    CONSTRAINT fk_notifications_user_id FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification templates table
CREATE TABLE IF NOT EXISTS notification_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL UNIQUE COMMENT 'notification type',
    title_template VARCHAR(255) NOT NULL COMMENT 'with placeholders like {user}, {amount}',
    message_template TEXT NOT NULL COMMENT 'with placeholders',
    default_icon VARCHAR(100) NULL,
    default_color VARCHAR(20) NULL DEFAULT 'info',
    default_priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    default_real_time BOOLEAN DEFAULT FALSE,
    default_channels JSON NULL,
    default_link VARCHAR(500) NULL COMMENT 'Default redirect URL for this notification type',
    enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences table
CREATE TABLE IF NOT EXISTS notification_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'notification type or "all"',
    channels JSON NULL COMMENT 'user preferred channels',
    enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_user_type (user_id, type),
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    
    CONSTRAINT fk_notification_preferences_user_id FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification channels table
CREATE TABLE IF NOT EXISTS notification_channels (
    channel_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE COMMENT 'email, sms, push, in-app',
    enabled BOOLEAN DEFAULT TRUE,
    config JSON NULL COMMENT 'API keys, settings',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default channels
INSERT INTO notification_channels (name, enabled, config) VALUES
('in-app', TRUE, '{"enabled": true}'),
('email', FALSE, '{"enabled": false, "smtp_host": "", "smtp_port": 587, "smtp_user": "", "smtp_pass": ""}'),
('sms', FALSE, '{"enabled": false, "api_provider": "", "api_key": ""}'),
('push', FALSE, '{"enabled": false, "service_worker": ""}')
ON DUPLICATE KEY UPDATE enabled=VALUES(enabled);

-- Insert default notification templates
INSERT INTO notification_templates (type, title_template, message_template, default_icon, default_color, default_priority, default_real_time, default_channels, default_link) VALUES
('support', 'New Support Request', '{user} submitted a support request: {subject}', 'fa-life-ring', 'info', 'high', TRUE, '["in-app"]', '/admin/support'),
('wallet_low', 'Low Balance Alert', 'Wallet {wallet_id} is below minimum threshold: {balance}', 'fa-wallet', 'warning', 'medium', FALSE, '["in-app"]', '/admin/wallets'),
('wallet_transaction', 'Wallet Transaction', '{transaction_type} of {amount} in wallet {wallet_id}', 'fa-wallet', 'success', 'low', FALSE, '["in-app"]', '/admin/wallet-transactions'),
('pos_session', 'POS Session Closed', 'Session {session_id} closed with {status}', 'fa-cash-register', 'danger', 'high', TRUE, '["in-app"]', '/admin/shifts'),
('payment_failed', 'Payment Failed', 'Payment of {amount} failed for {reference}', 'fa-times-circle', 'danger', 'high', TRUE, '["in-app"]', '/admin/payments'),
('payment_confirmed', 'Payment Confirmed', 'Payment of {amount} confirmed for {reference}', 'fa-check-circle', 'success', 'medium', FALSE, '["in-app"]', '/admin/payments'),
('system_maintenance', 'System Maintenance', 'Maintenance mode has been {status}', 'fa-cog', 'secondary', 'medium', TRUE, '["in-app"]', '/admin/system-settings'),
('system_security', 'Security Alert', '{alert_type} detected: {details}', 'fa-shield-alt', 'danger', 'high', TRUE, '["in-app"]', '/admin/system-settings')
ON DUPLICATE KEY UPDATE title_template=VALUES(title_template), default_link=VALUES(default_link);
