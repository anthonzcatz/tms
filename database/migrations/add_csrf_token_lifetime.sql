-- Add CSRF token lifetime setting to system_settings
ALTER TABLE system_settings 
ADD COLUMN csrf_token_lifetime_minutes INT(11) DEFAULT 480 AFTER session_lifetime_minutes;

-- Set default to 8 hours (480 minutes)
UPDATE system_settings SET csrf_token_lifetime_minutes = 480 WHERE csrf_token_lifetime_minutes IS NULL;
