-- Add receipt_address_source column to system_settings
-- Values: 'company' = use company_address from system_settings
--          'branch' = use address from business_branches table

ALTER TABLE system_settings
ADD COLUMN receipt_address_source ENUM('company', 'branch') DEFAULT 'company'
AFTER receipt_custom_footer;
