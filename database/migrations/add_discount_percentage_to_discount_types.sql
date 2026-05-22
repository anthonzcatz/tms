-- Add discount_percentage column to discount_types table
-- This allows proper discount calculation based on percentage

-- Try to add column (will fail if already exists, which is fine)
ALTER TABLE discount_types 
ADD COLUMN discount_percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Discount percentage (e.g., 20.00 for 20%)' 
AFTER description;

-- Add is_default column for default selection in dropdown
ALTER TABLE discount_types 
ADD COLUMN is_default TINYINT(1) DEFAULT 0 COMMENT 'Mark as default discount for dropdown selection' 
AFTER discount_percentage;

-- Update existing discount types with standard percentages
UPDATE discount_types SET discount_percentage = 20.00 WHERE code = 'STUDENT';
UPDATE discount_types SET discount_percentage = 20.00 WHERE code = 'SENIOR';
UPDATE discount_types SET discount_percentage = 20.00 WHERE code = 'PWD';
UPDATE discount_types SET discount_percentage = 20.00 WHERE code = 'MINOR';

-- Set SENIOR as default discount
UPDATE discount_types SET is_default = 1 WHERE code = 'SENIOR';
