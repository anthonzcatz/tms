-- Migration: add_session_termination_reason
-- Adds termination_reason to user_sessions to explain why a session was closed.
-- Run once against the TMS database.

ALTER TABLE `user_sessions`
    ADD COLUMN IF NOT EXISTS `termination_reason` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Reason why session was terminated (e.g., max_concurrent_sessions, device_blocked, admin_terminated)';
