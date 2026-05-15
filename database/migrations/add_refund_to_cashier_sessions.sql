-- Migration: Add refund tracking to cashier_sessions table
-- Date: 2026-05-15

-- Add refund-related columns to cashier_sessions table
ALTER TABLE `cashier_sessions`
ADD COLUMN `total_refunds` decimal(12,2) DEFAULT 0.00 COMMENT 'Total amount refunded to customers from cashier cash',
ADD COLUMN `total_refunds_wallet` decimal(12,2) DEFAULT 0.00 COMMENT 'Total amount refunded to wallet balance';
