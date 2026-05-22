-- Migration: add_device_user_tracking
-- Adds user tracking and geolocation columns to system_devices.
-- Run once against the TMS database.

ALTER TABLE `system_devices`
    ADD COLUMN IF NOT EXISTS `last_user_id`        INT            NULL DEFAULT NULL COMMENT 'ID of the last user who attempted login from this device',
    ADD COLUMN IF NOT EXISTS `last_user_username`  VARCHAR(50)    NULL DEFAULT NULL COMMENT 'Username of the last user who attempted login from this device',
    ADD COLUMN IF NOT EXISTS `last_user_fullname`  VARCHAR(255)   NULL DEFAULT NULL COMMENT 'Full name of the last user who attempted login from this device',
    ADD COLUMN IF NOT EXISTS `city`                VARCHAR(100)   NULL DEFAULT NULL COMMENT 'City from IP geolocation',
    ADD COLUMN IF NOT EXISTS `country`             VARCHAR(100)   NULL DEFAULT NULL COMMENT 'Country from IP geolocation',
    ADD COLUMN IF NOT EXISTS `latitude`            DECIMAL(10,7)  NULL DEFAULT NULL COMMENT 'Latitude from IP geolocation',
    ADD COLUMN IF NOT EXISTS `longitude`           DECIMAL(10,7)  NULL DEFAULT NULL COMMENT 'Longitude from IP geolocation';

-- Add index for faster user-based lookups
ALTER TABLE `system_devices`
    ADD INDEX IF NOT EXISTS `idx_last_user_id` (`last_user_id`);
