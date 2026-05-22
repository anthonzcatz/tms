-- Add session_warning_timeout to system_settings table
-- This allows configurable session warning timeout (in minutes before expiry)
-- Default: 120 minutes (2 hours) - shows warning 2 hours before session expires

ALTER TABLE system_settings 
ADD COLUMN session_warning_timeout INT DEFAULT 120 COMMENT 'Session warning timeout in minutes before expiry (default: 120 = 2 hours)';

-- Update existing record to set default value
UPDATE system_settings SET session_warning_timeout = 120 WHERE session_warning_timeout IS NULL;
