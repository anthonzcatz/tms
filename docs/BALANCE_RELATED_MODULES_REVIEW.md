# Balance-Related Modules — Review & Recommended Fixes

## 1. Summary

After implementing the cancellation balance restoration, I audited the other modules that touch `provider_wallets`, `customer_charges`, `bank_accounts`, and `branch_ticket_stocks`. Several race conditions and stock leaks were found that can corrupt balances in production under concurrent load or abandoned carts.

## 2. Findings

### 2.1 `api/wallet-transactions/index.php` — Race condition when creating transactions

**Severity:** HIGH  
**Files:** `c:\xampp\htdocs\TMS\api\wallet-transactions\index.php`

The `handlePost()` method reads `provider_wallets.current_balance` without `FOR UPDATE`, then writes the new balance in a separate query. Two concurrent transactions can read the same balance, both compute a new balance, and one update overwrites the other.

Also:
- The `UPDATE provider_wallets` does not set `updated_at` (inconsistent with sale/cancellation code).
- SUPER_ADMINs are notified twice when a low-balance alert fires (first via the branch admin query, then via the SUPER_ADMIN query).
- The branch-admin query uses `ua.branch_id = wallet.branch_id` to find "provider admins", which conflates branch users with wallet owners.

### 2.2 `api/bank-confirmations/index.php` — Missing row locks

**Severity:** MEDIUM  
**Files:** `c:\xampp\htdocs\TMS\api\bank-confirmations\index.php`

When confirming or rejecting a charge payment, the code updates:
- `charge_payments.confirmation_status`
- `customer_charges.total_paid` / `balance`
- `bank_accounts.current_balance`

None of these rows are locked with `FOR UPDATE` inside the transaction. Concurrent confirmations can produce incorrect balances.

### 2.3 `api/charges/index.php` — Missing row lock on `customer_charges`

**Severity:** MEDIUM  
**Files:** `c:\xampp\htdocs\TMS\api\charges\index.php`

Recording a charge payment reads `customer_charges` and immediately updates `total_paid` / `balance` without locking the row. Concurrent payments for the same passenger can over-apply or under-apply.

### 2.4 `api/pos/transactions.php` — No cancellation/refund path for service transactions

**Severity:** MEDIUM  
**Files:** `c:\xampp\htdocs\TMS\api\pos\transactions.php`

Service transactions can create `customer_charges` entries (when payment method `tracks_credit = 1`), but there is no equivalent of `ticket-cancel.php` or `cancellation-approval.php` for services. If a service transaction is reversed, the charge debt remains on the customer's account and no wallet or stock effects are reversed.

Also, `customer_charges` updates happen without row locking.

### 2.5 Ticket stock reservations are never expired

**Severity:** HIGH  
**Files:** `c:\xampp\htdocs\TMS\app\helpers\TicketStockHelper.php`

`reserveStock()` creates rows in `ticket_stock_reservations` with an `expires_at` column, and `releaseReservation()` can clean them up. However, there is **no cron / scheduled script** to expire abandoned reservations automatically. Over time this permanently reduces `available_qty` (`on_hand_qty - reserved_qty`) and blocks sales even though physical tickets are still on hand.

### 2.6 `WalletResolver` variant-wallet fallback may hide configuration errors

**Severity:** LOW  
**Files:** `c:\xampp\htdocs\TMS\app\helpers\WalletResolver.php`

When a variant-specific wallet is requested and not found, `WalletResolver` silently falls back to the provider-level wallet. This is useful for parent wallets but can hide cases where an admin forgot to create a variant-specific wallet. Logging or an optional strict mode would help.

## 3. Implemented fixes

### 3.1 Wallet transaction creation now locks the wallet row

**File:** `api/wallet-transactions/index.php`

- Moved the `provider_wallets` read inside the transaction and added `FOR UPDATE`.
- Added `updated_at = NOW()` to the wallet update.
- Rolled back the transaction on branch-access denial or insufficient balance.
- Removed duplicate SUPER_ADMIN notifications for low-balance alerts.

### 3.2 Bank confirmation balance updates are now locked

**File:** `api/bank-confirmations/index.php`

- Added `FOR UPDATE` to `transaction_payments`, `bank_transactions`, `charge_payments`, and `bank_accounts` reads that precede balance updates.
- Added `updated_at = NOW()` to `bank_accounts` updates.
- Locked `customer_charges` before reverting charge payment rejections and made the balance/status computation safer.

### 3.3 Charge payment posting now locks `customer_charges` and `bank_accounts`

**File:** `api/charges/index.php`

- Moved the `customer_charges` read inside the transaction and added `FOR UPDATE`.
- Added `FOR UPDATE` to the `bank_accounts` read when creating an immediate bank transaction.
- Added `updated_at = NOW()` to the bank account update.

### 3.4 POS sale customer_charges updates now lock rows

**Files:** `api/pos/transactions.php`, `api/pos/tickets.php`

- Added `FOR UPDATE` to `customer_charges` lookups before incrementing charge balances during ticket and service sales.

### 3.5 WalletResolver fallback audit logging

**File:** `app/helpers/WalletResolver.php`

- Added `logFallback()` helper and called it whenever the resolver falls back from a variant wallet to a provider-level wallet or from a sub-provider to a parent provider wallet.
- Added `require_once` for `database.php`.

### 3.6 Service transaction cancellation/refund flow

**Files:** `api/pos/service-cancel.php`, `api/pos/service-cancellation-approval.php`, `database/migrations/add_service_cancellation_refund_tables.sql`

- New `service_cancellations` and `service_refunds` tables.
- New `service-cancel.php` endpoint: supports immediate and pending cancellation, reverses charge debts, zeros `pos_order_items`, updates `pos_orders.total_refunded_amount`, and tracks refunds.
- New `service-cancellation-approval.php` endpoint: approves/rejects pending service cancellations with the same financial effects.

### 3.7 Expired ticket stock reservations are now cleanable

**File:** `scripts/cleanup-expired-ticket-reservations.php`

- New CLI/cron script that releases stale reservations from `ticket_stock_reservations` and decrements `branch_ticket_stocks.reserved_qty`.
- Processes in small batches to avoid long transactions.
- Supports `--dry-run` for testing.
- Must be scheduled via Windows Task Scheduler (or cron on Linux) every 1–5 minutes.

## 4. Verification

All new and modified PHP files passed `php -l` syntax checks.
