-- Migration: Fix activity_logs log_id auto-increment
-- Fixes #1068 - Multiple primary key defined error

-- If log_id is already primary key, just ensure it's auto_increment
-- If log_id exists but is not auto_increment, fix it
ALTER TABLE activity_logs 
MODIFY COLUMN log_id BIGINT NOT NULL AUTO_INCREMENT;

-- Ensure it's the primary key (this won't fail if it already is)
-- First, check if there's a different primary key and drop it if needed
-- Note: Run this only if log_id is NOT already the primary key

-- Alternative approach: Just ensure the column exists with proper settings
-- and let MySQL handle the primary key constraint
