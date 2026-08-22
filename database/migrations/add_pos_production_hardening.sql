-- Migration: POS production hardening
-- Purpose: protect POS identifiers and accelerate multi-user/multi-branch reads.
-- Run the preflight queries first. Resolve any duplicate order_code values before
-- relying on the existing unique key; do not delete production records automatically.

-- Preflight: these must return zero rows before relying on existing unique/session safeguards.
SELECT order_code, COUNT(*) AS duplicate_count
FROM pos_orders
GROUP BY order_code
HAVING COUNT(*) > 1;

SELECT cashier_user_id, COUNT(*) AS open_session_count
FROM cashier_sessions
WHERE status = 'OPEN'
GROUP BY cashier_user_id
HAVING COUNT(*) > 1;

-- order_code is already protected by the existing uq_order_code key in this schema.
-- IF NOT EXISTS keeps this migration safe to re-run on MariaDB.
CREATE INDEX IF NOT EXISTS idx_pos_orders_branch_created
    ON pos_orders (branch_id, created_at);

CREATE INDEX IF NOT EXISTS idx_pos_orders_creator_created
    ON pos_orders (created_by, created_at);

CREATE INDEX IF NOT EXISTS idx_pos_orders_status_created
    ON pos_orders (status, created_at);

CREATE INDEX IF NOT EXISTS idx_cashier_sessions_cashier_status
    ON cashier_sessions (cashier_user_id, status);

CREATE INDEX IF NOT EXISTS idx_cashier_sessions_branch_status
    ON cashier_sessions (branch_id, status);

CREATE INDEX IF NOT EXISTS idx_pos_order_items_order_type
    ON pos_order_items (order_id, item_type);

CREATE INDEX IF NOT EXISTS idx_transaction_payments_session_created
    ON transaction_payments (cashier_session_id, created_at);

CREATE INDEX IF NOT EXISTS idx_transaction_payments_source
    ON transaction_payments (source_type, source_id);

CREATE INDEX IF NOT EXISTS idx_wallet_transactions_wallet_created
    ON wallet_transactions (wallet_id, created_at);

CREATE INDEX IF NOT EXISTS idx_bank_transactions_account_created
    ON bank_transactions (bank_account_id, created_at);
