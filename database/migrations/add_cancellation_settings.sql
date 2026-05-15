-- Migration: Add cancellation settings to system_settings table
-- Date: 2026-05-15

-- Add cancellation-related columns to existing system_settings table
-- Note: Ignore errors if columns already exist
ALTER TABLE `system_settings` ADD COLUMN IF NOT EXISTS `cancellation_requires_confirmation` tinyint(1) DEFAULT 1 COMMENT 'Whether ticket cancellation requires confirmation before processing';
ALTER TABLE `system_settings` ADD COLUMN IF NOT EXISTS `cancellation_auto_approve` tinyint(1) DEFAULT 0 COMMENT 'Whether cancellations are automatically approved';
ALTER TABLE `system_settings` ADD COLUMN IF NOT EXISTS `cancellation_refund_to_wallet` tinyint(1) DEFAULT 1 COMMENT 'Whether cancelled tickets are automatically refunded to wallet';
ALTER TABLE `system_settings` ADD COLUMN IF NOT EXISTS `cancellation_refund_processing_days` int(11) DEFAULT 3 COMMENT 'Number of days to process refunds';
ALTER TABLE `system_settings` ADD COLUMN IF NOT EXISTS `cancellation_allow_partial` tinyint(1) DEFAULT 0 COMMENT 'Whether partial cancellation is allowed';

-- Set default values
UPDATE `system_settings` SET
  `cancellation_requires_confirmation` = 1,
  `cancellation_auto_approve` = 0,
  `cancellation_refund_to_wallet` = 1,
  `cancellation_refund_processing_days` = 3,
  `cancellation_allow_partial` = 0
WHERE `setting_id` = 1;
