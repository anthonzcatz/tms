-- Migration: Add cash_amount and charge_reversal_amount to ticket_refunds
-- Date: 2026-05-25
-- Description: Store the breakdown of a refund — how much was actual cash given
--              to the passenger vs how much was a charge/debt reversal (system only).
--              This is needed for accurate cashier session and refund reports.

ALTER TABLE `ticket_refunds`
    ADD COLUMN IF NOT EXISTS `cash_amount` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Actual cash given to passenger from drawer'
        AFTER `refund_amount`,
    ADD COLUMN IF NOT EXISTS `charge_reversal_amount` decimal(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Amount reversed from customer_charges (debt cancelled, not cash)'
        AFTER `cash_amount`;

-- Backfill existing rows: assume all refunds were cash (no charge reversal data available)
UPDATE `ticket_refunds` SET `cash_amount` = `refund_amount`, `charge_reversal_amount` = 0.00
WHERE `cash_amount` = 0 AND `charge_reversal_amount` = 0;
