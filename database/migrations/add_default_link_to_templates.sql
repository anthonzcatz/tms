-- Migration: Add default_link column to notification_templates
-- This adds the default_link column and updates existing templates with default redirect URLs

-- Add default_link column if it doesn't exist
ALTER TABLE notification_templates 
ADD COLUMN IF NOT EXISTS default_link VARCHAR(500) NULL COMMENT 'Default redirect URL for this notification type' 
AFTER default_channels;

-- Update existing templates with default links (relative paths, no domain/folder prefix)
UPDATE notification_templates SET default_link = '/admin/support/management' WHERE type = 'support';
UPDATE notification_templates SET default_link = '/admin/wallet/provider-wallets' WHERE type = 'wallet_low';
UPDATE notification_templates SET default_link = '/admin/wallet/wallet-transactions' WHERE type = 'wallet_transaction';
UPDATE notification_templates SET default_link = '/admin/pos/sessions' WHERE type = 'pos_session';
UPDATE notification_templates SET default_link = '/admin/payments' WHERE type = 'payment_failed';
UPDATE notification_templates SET default_link = '/admin/payments' WHERE type = 'payment_confirmed';
UPDATE notification_templates SET default_link = '/admin/system-settings' WHERE type = 'system_maintenance';
UPDATE notification_templates SET default_link = '/admin/system-settings' WHERE type = 'system_security';
