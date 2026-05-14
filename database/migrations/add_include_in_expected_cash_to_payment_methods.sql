-- Add include_in_expected_cash column to payment_methods table
-- This flag determines whether a payment method should be included in the Expected Cash calculation for closing sessions

ALTER TABLE payment_methods
ADD COLUMN include_in_expected_cash BOOLEAN DEFAULT FALSE
AFTER requires_reference;

-- Set CASH payment methods to be included in expected cash by default
UPDATE payment_methods
SET include_in_expected_cash = TRUE
WHERE method_type = 'CASH';
