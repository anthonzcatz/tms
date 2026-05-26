-- Backfill change_amount for existing orders
-- Run once to fix orders where change_amount is NULL

UPDATE pos_orders 
SET change_amount = GREATEST(0, amount_paid - grand_total)
WHERE change_amount IS NULL OR change_amount = 0;
