-- Migration: Fix total_refunded_amount for rejected cancellations
-- Purpose: Remove refunded amounts from pos_orders for cancellations that were rejected
-- Run this in phpMyAdmin or MySQL CLI

-- Recalculate total_refunded_amount for all orders based on approved/completed cancellations only
UPDATE pos_orders o
SET total_refunded_amount = (
    SELECT COALESCE(SUM(tc.refund_amount), 0)
    FROM pos_order_items oi
    LEFT JOIN ticket_transactions tt ON oi.reference_id = tt.transaction_id AND oi.item_type = 'TICKET'
    LEFT JOIN ticket_cancellations tc ON tt.transaction_id = tc.transaction_id
    WHERE oi.order_id = o.order_id AND tc.status IN ('approved', 'completed')
);

SELECT "Migration completed successfully - total_refunded_amount recalculated for all orders" AS message;
