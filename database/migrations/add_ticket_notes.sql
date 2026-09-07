ALTER TABLE `ticket_transactions`
    ADD COLUMN IF NOT EXISTS `ticket_notes` TEXT DEFAULT NULL;

ALTER TABLE `pos_order_items`
    ADD COLUMN IF NOT EXISTS `ticket_notes` TEXT DEFAULT NULL;
