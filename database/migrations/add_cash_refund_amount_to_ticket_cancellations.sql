-- Add cash_refund_amount column to ticket_cancellations table
-- This stores the actual cash amount to be given to the customer (refund_amount - charge_amount)

ALTER TABLE ticket_cancellations
ADD COLUMN cash_refund_amount DECIMAL(12,2) DEFAULT 0.00 AFTER charge_amount;

-- Update existing records to calculate cash_refund_amount
UPDATE ticket_cancellations
SET cash_refund_amount = COALESCE(refund_amount, 0) - COALESCE(charge_amount, 0)
WHERE cash_refund_amount = 0 OR cash_refund_amount IS NULL;
