-- Add credit tracking column to payment_methods table
-- Migration Date: 2026-05-19

-- Add column to track if payment method requires credit/billing tracking
ALTER TABLE payment_methods 
ADD COLUMN tracks_credit BOOLEAN DEFAULT FALSE COMMENT 'Whether this payment method tracks customer credit/billing (e.g., CHARGE/utang)' AFTER requires_reference;

-- Add index for faster filtering
ALTER TABLE payment_methods 
ADD INDEX idx_tracks_credit (tracks_credit);

-- Update existing CHARGE method to track credit
UPDATE payment_methods 
SET tracks_credit = TRUE 
WHERE method_type = 'CHARGE';
