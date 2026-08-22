ALTER TABLE `ticket_transactions`
    ADD COLUMN IF NOT EXISTS `ticket_action` varchar(50) DEFAULT NULL AFTER `ticket_number`;

ALTER TABLE `pos_order_items`
    ADD COLUMN IF NOT EXISTS `ticket_action` varchar(50) DEFAULT NULL AFTER `variant_id`;
