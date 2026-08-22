-- Add setting to control whether pending refunds are shown/deducted in Close Cashier Session
ALTER TABLE `system_settings`
    ADD COLUMN IF NOT EXISTS `show_pending_refunds_in_close_session` tinyint(1) NOT NULL DEFAULT 0
        COMMENT 'When enabled, pending refunds are included in Close Cashier Session breakdown and expected cash';

UPDATE `system_settings`
    SET `show_pending_refunds_in_close_session` = 0
    WHERE `setting_id` = 1;
