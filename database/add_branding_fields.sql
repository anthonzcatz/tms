-- Add branding fields to system_settings table (if not already exists)
-- Note: These columns may already exist if the script was run previously
-- ALTER TABLE system_settings 
-- ADD COLUMN system_name VARCHAR(100) DEFAULT 'Falcon' AFTER company_tagline,
-- ADD COLUMN system_logo VARCHAR(255) NULL AFTER system_name,
-- ADD COLUMN developer_name VARCHAR(255) NULL AFTER system_logo,
-- ADD COLUMN developer_details TEXT NULL AFTER developer_name,
-- ADD COLUMN footer_copyright TEXT NULL AFTER developer_details;

-- Update default values
UPDATE system_settings SET 
    system_name = 'Falcon',
    developer_name = 'Your Company',
    developer_details = 'Designed and developed by Your Development Team',
    footer_copyright = '© 2024 Your Company. All rights reserved.'
WHERE setting_id = 1;
