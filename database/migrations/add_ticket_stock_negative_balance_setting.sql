-- Migration: Add ticket stock negative balance setting to system_settings table
-- Date: 2026-07-16

-- When enabled, the system can allow branch/provider/variant stock to go negative
-- during POS sale, dispatch, or manual adjustment. Default is disabled to prevent overselling.
ALTER TABLE `system_settings`
  ADD COLUMN IF NOT EXISTS `allow_negative_ticket_stock` TINYINT(1) DEFAULT 0
  COMMENT 'When enabled, ticket stock can go negative for providers/variants; otherwise sales/transfers are blocked when available quantity is insufficient.';

-- Set default value for existing installation
UPDATE `system_settings` SET `allow_negative_ticket_stock` = 0 WHERE `setting_id` = 1;
