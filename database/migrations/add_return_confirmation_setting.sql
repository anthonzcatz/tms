ALTER TABLE `system_settings`
    ADD COLUMN IF NOT EXISTS `return_requires_confirmation` tinyint(1) NOT NULL DEFAULT 1
        COMMENT 'Whether ticket returns/refunds require confirmation before processing'
        AFTER `void_requires_confirmation`;
