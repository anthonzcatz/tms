-- Migration: add add-on tracking to customer_charges
-- Scope: POS ticket + service add-on transactions

ALTER TABLE `customer_charges`
    ADD COLUMN IF NOT EXISTS `add_on_charged` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Service add-on amounts charged to customer' AFTER `fee_charged`,
    ADD COLUMN IF NOT EXISTS `add_on_paid` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Payments applied to add-on balance' AFTER `fee_paid`,
    ADD COLUMN IF NOT EXISTS `add_on_balance` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Outstanding add-on balance' AFTER `fee_balance`;
