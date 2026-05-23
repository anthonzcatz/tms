-- Add encrypt_ids setting to system_settings table
-- This allows developers to toggle ID encryption on/off for debugging/production
ALTER TABLE system_settings ADD COLUMN encrypt_ids BOOLEAN DEFAULT TRUE;
