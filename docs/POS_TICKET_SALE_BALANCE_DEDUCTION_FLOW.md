# POS Ticket Sale — Wallet & Balance Deduction Flow

## Overview

This document describes the end-to-end flow of how a ticket sale in the POS deducts the correct wallet balance, including when a ticket variant has its own dedicated wallet and when a non-variant (physical-stock) ticket is sold.

## Key concepts

- **`provider_wallets`** — stores the monetary balance available per provider/branch/variant.
- **`branch_ticket_stocks`** — stores physical ticket inventory quantity per branch/provider/variant.
- **`WalletResolver`** — decides which wallet should receive the sale debit.
- **`TicketStockHelper`** — manages physical branch stock reservations and deductions.

A ticket can be sold in two mutually-exclusive inventory modes:

1. **Wallet-backed variant** — the variant has its own `provider_wallets` row (`variant_id IS NOT NULL`). Availability is governed by `provider_wallets.current_balance`, not by physical stock.
2. **Branch-stock / provider-level wallet** — the variant or provider relies on physical stock in `branch_ticket_stocks`. The wallet may only exist at provider/branch level (`variant_id IS NULL`) and is used for accounting, not for quantity availability.

---

## 1. Wallet resolution

**File:** `app/helpers/WalletResolver.php`

When a POS ticket is prepared or saved, the system calls:

```php
WalletResolver::resolve($providerId, $branchId, $variantId);
```

Resolution order:

1. Look for an active wallet with the exact `provider_id`, `branch_id`, and `variant_id`.
2. If not found, look for an active provider/branch wallet with `variant_id IS NULL`.
3. If still not found and the provider has a `parent_provider_id`, recurse to the parent provider with the same `variant_id`.
4. Return `false` if no wallet can be resolved.

The returned wallet row contains `wallet_id`, `provider_id`, `branch_id`, `variant_id`, and `current_balance`.

### Variant-specific wallet detection

A resolved wallet is considered **variant-specific** when:

```php
!empty($resolvedWallet['variant_id'])
```

If true, the sale is treated as wallet-backed and physical branch stock is **not** deducted.

---

## 2. Variant selection UI

**File:** `admin/pos/assets/js/pos.js` (`loadTicketVariants`)

The variant dropdown (`ticketVariant`) calls:

```
GET /api/ticket-variants?provider_id={id}&branch_id={id}
```

**API:** `api/ticket-variants/index.php`

Each variant item returned contains:

- `available_qty` from `branch_ticket_stocks`
- `wallet_id` and `wallet_balance` from `provider_wallets`

### Frontend display rules

- If the variant has a **variant-specific wallet** (`wallet_id` is set):
  - Positive `wallet_balance` → shown as `Available (wallet)`
  - Zero or negative `wallet_balance` → shown as `Insufficient wallet balance`
- If the variant has **no variant wallet**:
  - `available_qty > 0` → shown as `{qty} available`
  - `available_qty <= 0` and `allow_negative_ticket_stock` is enabled → shown as `negative stock allowed`
  - otherwise → shown as `out of stock`

The dropdown is sorted so in-stock / wallet-funded variants appear first.

---

## 3. Completing the POS sale

**File:** `api/pos/tickets.php`

### 3.1 Request payload

```json
{
  "session_id": 5,
  "branch_id": 1,
  "tickets": [
    {
      "passenger_id": "1",
      "provider_id": "1",
      "variant_id": "3",
      "accommodation_id": "2",
      "discount_id": "4",
      "ticket_number": "dfdfde",
      "base_amount": 5000,
      "service_fee": 120,
      "total_amount": 5120
    }
  ],
  "payments": [...],
  "services": []
}
```

### 3.2 Per-ticket processing flow

1. **Resolve wallet**
   ```php
   $resolvedWallet = WalletResolver::resolve($providerId, $branchId, $variantId);
   ```
   If no wallet, the sale aborts with:  
   `No active wallet found for the selected provider, branch and variant.`

2. **Validate variant** (only when `variant_id` is present)  
   The variant must belong to the provider and be active.

3. **Create `ticket_transactions` record**  
   Saves the ticket with `wallet_id`, `variant_id`, `base_amount`, `service_fee`, `discount_amount`, and `total_amount`.

4. **Deduct physical branch stock** (if applicable)  
   ```php
   if ($variantId && !$isVariantWallet) {
       TicketStockHelper::deductForSale($branchId, $providerId, $variantId, 1, ...);
   }
   ```
   - **Wallet-backed variant** (`$isVariantWallet = true`) → skip this step.
   - **Non-wallet variant** → decrease `branch_ticket_stocks.on_hand_qty` by `1` and create a `ticket_stock_movements` ledger row.

5. **Create `pos_order_items` record**  
   Links the ticket to `pos_orders`.

6. **Deduct wallet balance**  
   ```php
   $wallet = Database::fetch(
       "SELECT * FROM provider_wallets WHERE wallet_id = :wid AND status = 'active' FOR UPDATE",
       ['wid' => $walletId]
   );
   ```
   If the wallet balance is lower than `base_amount` and `pos_allow_insufficient_wallet` is disabled, abort with:  
   `Insufficient wallet balance. Required: ₱..., Available: ₱... .`

   Otherwise:
   ```php
   $balanceAfter = $currentBalance - $baseAmount;
   UPDATE provider_wallets SET current_balance = :new_balance WHERE wallet_id = :wid
   ```
   Only the **base amount** is deducted from the wallet. The service fee is profit and is not deducted from provider credit.

---

## 4. Cancellation / refund flow

**File:** `api/pos/ticket-cancel.php`

When a ticket is cancelled and approved:

1. Mark `ticket_transactions.status = 'cancelled'`.
2. Resolve the same wallet using `WalletResolver::resolve($providerId, $branchId, $variantId)`.
3. Re-fetch the wallet with `FOR UPDATE`.
4. Calculate the proportional wallet refund:
   ```php
   $walletRefundAmount = round($baseAmount * ($refundAmount / $totalAmount), 2);
   ```
5. Credit the wallet:
   ```php
   $balanceAfter = $currentBalance + $walletRefundAmount;
   UPDATE provider_wallets SET current_balance = :new_balance WHERE wallet_id = :wid
   ```
6. Insert a `wallet_transactions` record with `txn_type = 'REFUND'`, `direction = 'IN'`.

> **Note:** Branch stock is **not** restored on cancellation for wallet-backed variants. The refund is recorded purely as a wallet credit. If the project later requires physical stock to be restored, `TicketStockHelper::adjustOnHand` should be called in the cancellation path for non-wallet variants.

---

## 5. Wallet top-up flow (for reference)

**File:** `api/wallet-transactions/index.php`

1. User selects a wallet and enters an amount.
2. Direction `IN` increases `provider_wallets.current_balance`.
3. A `wallet_transactions` ledger row is created for audit.

This top-up is the source of the balance that POS sales later consume.

---

## 6. System settings that affect the flow

| Setting | Table / column | Effect |
|---|---|---|
| Allow insufficient wallet (overdraft) | `system_settings.pos_allow_insufficient_wallet` | When `1`, a sale can proceed even if `current_balance < base_amount`. |
| Allow negative ticket stock | `system_settings.allow_negative_ticket_stock` | When `1`, physical `branch_ticket_stocks` can go negative. Does not affect wallet-backed variants. |

---

## 7. Files involved

| File | Responsibility |
|---|---|
| `app/helpers/WalletResolver.php` | Resolves the correct wallet for a provider/branch/variant, including parent-provider fallback. |
| `app/helpers/TicketStockHelper.php` | Validates and deducts physical branch stock; used only for non-wallet variants. |
| `api/pos/tickets.php` | POS sale endpoint: creates order, ticket transaction, deducts stock (if needed), and debits wallet. |
| `api/pos/ticket-cancel.php` | Cancels ticket and credits the resolved wallet. |
| `api/wallet-transactions/index.php` | Top-up and direct wallet adjustment. |
| `api/ticket-variants/index.php` | Returns variants with `available_qty` and `wallet_balance`. |
| `admin/pos/assets/js/pos.js` | Loads variants, computes totals, builds cart, and sends the sale payload. |
| `admin/pos/views/index.php` | POS ticket form including `ticketVariant`, `ticketBaseAmount`, and payment UI. |

---

## 8. Quick decision diagram

```
Ticket has variant_id?
├── No  → deduct from branch_ticket_stocks if stock_controlled
└── Yes → WalletResolver::resolve(provider, branch, variant)
            ├── Has variant-specific wallet (variant_id NOT NULL)
            │       └── Deduct base_amount from provider_wallets.current_balance
            │       └── Do NOT touch branch_ticket_stocks
            └── Has only provider/parent wallet (variant_id IS NULL)
                    └── Deduct 1 qty from branch_ticket_stocks
                    └── Deduct base_amount from provider_wallets.current_balance
```
