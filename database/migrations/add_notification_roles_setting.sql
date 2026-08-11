-- Add notification_roles column to system_settings table
-- Stores comma-separated list of role codes that can view notifications
ALTER TABLE `system_settings`
    ADD COLUMN IF NOT EXISTS `notification_roles` VARCHAR(255) DEFAULT NULL
    COMMENT 'Comma-separated role codes that can view notifications. Leave empty to use VIEW_NOTIFICATIONS permission.'
    AFTER `max_concurrent_sessions`;
