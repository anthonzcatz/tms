-- Migration: Add charge_amount column to ticket_cancellations
-- Purpose: Track how much of a cancelled ticket was paid via CHARGE (debt) so that
--          customer_charges balance can be properly reversed on approval/immediate cancel,
--          and NOT reversed on rejection.
-- Run once in phpMyAdmin or MySQL CLI.

ALTER TABLE `ticket_cancellations`
    ADD COLUMN `charge_amount` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Portion of the refund_amount that was originally paid via CHARGE (credit/debt). Used to reverse customer_charges on approval.'
        AFTER `refund_amount`;
