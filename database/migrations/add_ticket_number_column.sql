-- Add ticket_number column to ticket_transactions table for tracking
-- Migration Date: 2026-05-19

ALTER TABLE ticket_transactions 
ADD COLUMN ticket_number VARCHAR(50) NULL AFTER travel_date;

-- Add index for faster searches on ticket number
ALTER TABLE ticket_transactions 
ADD INDEX idx_ticket_number (ticket_number);
