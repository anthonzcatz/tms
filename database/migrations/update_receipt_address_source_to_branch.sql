-- Update receipt_address_source to 'branch' for testing
UPDATE system_settings
SET receipt_address_source = 'branch'
WHERE setting_id = 1;
