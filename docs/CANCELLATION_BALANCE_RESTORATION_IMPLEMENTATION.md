# Cancellation & Refund Confirmations — Balance Restoration Implementation

## 1. Objective

Ensure that every ticket cancellation (immediate or manager-approved) reverses only the eligible financial/inventory effects. Variant tickets are consumed and are never returned to physical availability or provider-wallet balance; the work also centralizes cancellation effects in one reusable service.

## 2. Sale behavior that cancellation must mirror

**File:** `api/pos/tickets.php`

1. `WalletResolver::resolve($providerId, $branchId, $variantId)` is called.
2. If the resolved wallet is **variant-specific** (`variant_id IS NOT NULL`), physical `branch_ticket_stocks` is **not** touched.
3. If the resolved wallet is **provider-level** (`variant_id IS NULL`), `branch_ticket_stocks.on_hand_qty` is decreased by 1 via `TicketStockHelper::deductForSale()`.
4. The wallet balance (`provider_wallets.current_balance`) is reduced by the **base amount** only (not the service fee).
5. A `wallet_transactions` ledger row (`txn_type = 'ADJUSTMENT'`, `direction = 'OUT'`) is inserted.

> Wallet-backed variants are controlled purely by `provider_wallets` balance. Non-wallet variants use physical stock.

## 3. What was implemented

### 3.1 New shared cancellation service

**File:** `app/helpers/CancellationService.php`

A single helper that both cancellation APIs now delegate to:

- `processCancellationEffects(...)`
  - Marks `ticket_transactions.status = 'cancelled'`.
  - Reverses the CHARGE portion from `customer_charges`.
  - Resolves the correct wallet via `WalletResolver::resolve(...)` with `variant_id` only when restoration is allowed.
  - Credits the provider wallet proportionally on `base_amount` only for non-variant tickets.
  - Inserts a `wallet_transactions` refund row (`txn_type = 'REFUND'`, `direction = 'IN'`) only when a provider-wallet restoration is allowed.
  - Consumed variant tickets do not restore physical stock and do not receive provider-wallet credit.
  - Zeros or reduces the `pos_order_items` row and updates `pos_orders.total_refunded_amount` for real refunds.
  - Inserts a `ticket_refunds` record.

Helper utilities in the same file:

- `computeChargePaymentsTotal(int $ticketTxnId)` — total CHARGE payment amount for a ticket.
- `resolveProviderId(array $ticketTxn)` — provider ID with legacy wallet fallback.
- `reverseCustomerCharge(int $passengerId, float $chargeAmount)` — debt reversal.

### 3.2 Stock restore helper

**File:** `app/helpers/TicketStockHelper.php`

`restoreOnSale(...)` remains available for inventory workflows, but consumed POS variant tickets must not call it during cancellation, refund, or void. Variant tickets are not reusable after sale, regardless of whether the provider wallet is variant-specific.

### 3.3 Refactored cancellation APIs

**Files:** `api/pos/ticket-cancel.php`, `api/pos/cancellation-approval.php`

- Removed duplicated wallet-credit, `wallet_transactions`, `pos_order_items`, and `ticket_refunds` code.
- Both APIs now call `CancellationService::processCancellationEffects(...)` after handling their own request-specific steps (pending request creation, session tracking, etc.).
- `ticket-cancel.php` still handles the immediate vs. pending decision and cashier-session refund tracking.
- `cancellation-approval.php` still handles the approve vs. reject decision and reverses the pending session counter on rejection.

### 3.4 Refund Confirmations UI improvement

**Files:**

- `api/refund-confirmations/index.php` — listing query now includes `wallet_id`, `variant_id`, `provider_name`, `branch_name`, `variant_name`.
- `admin/refund-confirmations/assets/js/refund-confirmations.js` — passes wallet/variant info to the review modal and renders the wallet label.
- `admin/refund-confirmations/views/modals/confirm_cancellation.php` — added a read-only **"Wallet to Credit"** field so the approver knows exactly which provider wallet receives the refund.

## 4. Cancellation paths after implementation

### 4.1 Immediate cancellation from POS / transaction history

**File:** `api/pos/ticket-cancel.php`

When `cancellation_requires_confirmation = 0`:

1. Computes the CHARGE portion via `CancellationService::computeChargePaymentsTotal()`.
2. Inserts an `approved` `ticket_cancellations` row.
3. Updates the cashier session refund total.
4. Calls `CancellationService::processCancellationEffects(...)`.
   - Customer CHARGE debt is reversed according to the original payment account.
   - Provider wallet/stock restoration occurs only for non-variant tickets.
   - Consumed variant tickets receive no physical-stock or provider-wallet restoration.
5. Logs and responds.

### 4.2 Approval of pending cancellation from Refund Confirmations

**File:** `api/pos/cancellation-approval.php`

When a pending cancellation is approved:

1. Updates `ticket_cancellations.status = 'approved'`.
2. Calls `CancellationService::processCancellationEffects(...)`.
   - Same customer CHARGE reversal and non-variant restoration rules as immediate cancellation.
   - Consumed variant tickets receive no stock or provider-wallet restoration.
3. Logs and responds.

## 5. Files changed

| File | Change |
|---|---|
| `app/helpers/CancellationService.php` | New shared service. |
| `app/helpers/TicketStockHelper.php` | Added `restoreOnSale()` for cancellation stock restoration. |
| `api/pos/tickets.php` | Pre-existing: skips branch stock for variant-specific wallets. |
| `api/pos/ticket-cancel.php` | Delegates financial/restorative effects to `CancellationService`. |
| `api/pos/cancellation-approval.php` | Delegates approve-path effects to `CancellationService`. |
| `api/refund-confirmations/index.php` | Includes wallet/variant columns in listing. |
| `admin/refund-confirmations/assets/js/refund-confirmations.js` | Shows wallet-to-credit info in modal. |
| `admin/refund-confirmations/views/modals/confirm_cancellation.php` | Added "Wallet to Credit" field. |
| `docs/POS_TICKET_SALE_BALANCE_DEDUCTION_FLOW.md` | Reference documentation for the sale flow. |

## 6. Decision matrix

```
Ticket has variant_id?
├── No  → non-variant ticket
│         Sale: deduct the applicable provider wallet balance
│         Refund/Void: restore the applicable provider wallet balance when allowed
│         Physical variant stock: not involved
└── Yes → consumed variant ticket
          Sale: consume the selected variant according to sale rules
          Refund/Void: do not restore branch stock or provider wallet balance
          CHARGE debt: reverse the original charged account when applicable
```
