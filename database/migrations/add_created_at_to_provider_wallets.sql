-- Migration: Add created_at column to provider_wallets table
-- Fixes the 500 error when creating wallets

ALTER TABLE `provider_wallets` 
ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `status`;

-- Also ensure updated_at is properly set
ALTER TABLE `provider_wallets` 
MODIFY COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
