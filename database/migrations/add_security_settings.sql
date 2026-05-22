-- Migration: add_security_settings
-- Adds session and device security columns to system_settings.
-- Run once against the TMS database.

ALTER TABLE `system_settings`
    ADD COLUMN IF NOT EXISTS `session_lifetime_minutes`   INT            NOT NULL DEFAULT 120
        COMMENT 'How long a session stays alive in minutes (default 120 = 2 hours)',
    ADD COLUMN IF NOT EXISTS `device_approval_required`   TINYINT(1)     NOT NULL DEFAULT 0
        COMMENT '1 = new devices must be manually approved before login is allowed',
    ADD COLUMN IF NOT EXISTS `max_concurrent_sessions`    INT            NOT NULL DEFAULT 1
        COMMENT 'Maximum number of concurrent active sessions per user (1 = single-session)';

-- Backfill existing row with sensible defaults
UPDATE `system_settings`
SET
    `session_lifetime_minutes` = 120,
    `device_approval_required` = 0,
    `max_concurrent_sessions`  = 1
WHERE `setting_id` = 1
  AND `session_lifetime_minutes` IS NULL;
