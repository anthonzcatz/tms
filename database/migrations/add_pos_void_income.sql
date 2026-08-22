ALTER TABLE `ticket_cancellations`
    ADD COLUMN IF NOT EXISTS `void_fee` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Optional fee recorded for a Void operation'
        AFTER `responsibility_amount`,
    ADD COLUMN IF NOT EXISTS `void_service_fee` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Optional service fee recorded for a Void operation'
        AFTER `void_fee`;
