ALTER TABLE `system_settings`
    ADD COLUMN IF NOT EXISTS `void_requires_confirmation` tinyint(1) NOT NULL DEFAULT 1
        COMMENT 'Whether ticket Void operations require confirmation before processing'
        AFTER `cancellation_requires_confirmation`;

