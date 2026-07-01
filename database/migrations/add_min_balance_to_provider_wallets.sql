-- Migration: Add min_balance column to provider_wallets
-- This adds a configurable minimum balance threshold for low balance alerts

ALTER TABLE provider_wallets 
ADD COLUMN IF NOT EXISTS min_balance DECIMAL(12,2) DEFAULT 1000.00 COMMENT 'Minimum balance threshold for low balance alerts' 
AFTER current_balance;
