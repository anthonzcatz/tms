-- Migration: Add POS settings to system_settings table
-- Date: 2026-05-18
-- Description: Add settings to control cashier session management permissions

-- Add POS-related columns to existing system_settings table
-- Note: Ignore errors if columns already exist
ALTER TABLE `system_settings` 
ADD COLUMN IF NOT EXISTS `pos_cashier_can_open_session` tinyint(1) DEFAULT 1 COMMENT 'Whether cashiers can open their own sessions',
ADD COLUMN IF NOT EXISTS `pos_cashier_can_close_session` tinyint(1) DEFAULT 1 COMMENT 'Whether cashiers can close their own sessions',
ADD COLUMN IF NOT EXISTS `pos_manager_can_open_for_cashier` tinyint(1) DEFAULT 1 COMMENT 'Whether managers can open sessions for cashiers',
ADD COLUMN IF NOT EXISTS `pos_manager_can_close_for_cashier` tinyint(1) DEFAULT 1 COMMENT 'Whether managers can close sessions for cashiers';

-- Set default values
UPDATE `system_settings` SET
  `pos_cashier_can_open_session` = 1,
  `pos_cashier_can_close_session` = 1,
  `pos_manager_can_open_for_cashier` = 1,
  `pos_manager_can_close_for_cashier` = 1
WHERE `setting_id` = 1;
