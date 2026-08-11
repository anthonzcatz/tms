# Cancellation & Refund Confirmations — Balance Restoration Review & Plan

## 1. Objective

Review whether the cancellation and refund-approval flows restore wallet balance in the **same way** the POS sale now deducts it, and produce a recommended action plan.

## 2. Current sale behavior (after the fix)

**Files:** `api/pos/tickets.php`

1. `WalletResolver::resolve($providerId, $branchId, $variantId)` is called.
2. If the resolved wallet is **variant-specific** (`variant_id IS NOT NULL`), the physical `branch_ticket_stocks` deduction is **skipped**.
3. The wallet balance (`provider_wallets.current_balance`) is reduced by the **base amount** only.
4. A `wallet_transactions` ledger row (`txn_type = 'ADJUSTMENT'`, `direction = 'OUT'`) is inserted.

> Wallet-backed variants are now controlled purely by `provider_wallets` balance. Physical stock is not touched.

---

## 3. Cancellation paths reviewed

### 3.1 Immediate cancellation from POS / admin transaction history

**Files:** `api/pos/ticket-cancel.php`

When `cancellation_requires_confirmation = 0` (immediate cancel):

1. Marks `ticket_transactions.status = 'cancelled'`.
2. Resolves the wallet the **same way** as the sale:
   ```php
   $variantId = !empty($ticketTxn['variant_id']) ? (int)$ticketTxn['variant_id'] : null;
   $resolvedWallet = WalletResolver::resolve($providerId, $ticketTxn['branch_id'], $variantId);
   ```
3. Re-fetches the wallet with `FOR UPDATE`.
4. Computes the proportional wallet refund:
   ```php
   $walletRefundAmount = round($ticketBaseAmount * ($refundAmount / $ticketTotalAmount), 2);
   ```
5. Credits the wallet:
   ```php
   $balanceAfter = $balanceBefore + $walletRefundAmount;
   UPDATE provider_wallets SET current_balance = :new_balance WHERE wallet_id = :wid;
   ```
6. Inserts a `wallet_transactions` row (`txn_type = 'REFUND'`, `direction = 'IN'`).

**Verdict:** ✅ Already mirrors the sale wallet-deduction logic for variant wallets. No code change needed for wallet restoration.

---

### 3.2 Approval of pending cancellation from Refund Confirmations

**Files:** `api/pos/cancellation-approval.php`, `admin/refund-confirmations/`

When a pending cancellation is approved:

1. Marks `ticket_cancellations.status = 'approved'` and `ticket_transactions.status = 'cancelled'`.
2. Resolves the wallet the **same way**:
   ```php
   $variantId = !empty($ticketTxn['variant_id']) ? (int)$ticketTxn['variant_id'] : null;
   $resolvedWallet = WalletResolver::resolve($providerId, $ticketTxn['branch_id'], $variantId);
   ```
3. Re-fetches the wallet with `FOR UPDATE`.
4. Computes the **same** proportional refund on `base_amount`.
5. Credits the wallet and inserts a `wallet_transactions` row (`txn_type = 'REFUND'`, `direction = 'IN'`).

**Verdict:** ✅ Already mirrors the sale wallet-deduction logic. No code change needed for wallet restoration.

---

## 4. Existing inconsistencies found

### 4.1 Branch stock is never restored on any cancellation

**Files:** `api/pos/ticket-cancel.php`, `api/pos/cancellation-approval.php`

- **Sale** calls `TicketStockHelper::deductForSale()` for non-wallet variants, decreasing `branch_ticket_stocks.on_hand_qty`.
- **Cancellation** does **not** call any `TicketStockHelper` function to return the physical stock.

**Impact:** For non-wallet variants, every cancellation permanently consumes physical inventory. After several cancellations, the stock count will be lower than reality, eventually causing false “Insufficient ticket stock” errors.

**Status:** Pre-existing bug, **not directly related to the wallet fix**.

### 4.2 Duplicate cancellation/approval logic

**Files:** `api/pos/ticket-cancel.php` and `api/pos/cancellation-approval.php`

Both files contain nearly identical code blocks for:

- Wallet resolution
- Wallet balance calculation
- `wallet_transactions` insert
- `pos_order_items` zeroing
- `ticket_refunds` insert
- Cashier session refund tracking

**Impact:** Future fixes (like adding physical stock restoration or partial-refund logic) must be duplicated across two files, increasing maintenance risk.

---

## 5. Recommended action plan

### Phase 1 — Preserve wallet consistency (no implementation needed)

- [ ] **Confirm** that `ticket-cancel.php` and `cancellation-approval.php` correctly restore variant-specific wallet balances.
- [ ] **Update docs** (`POS_TICKET_SALE_BALANCE_DEDUCTION_FLOW.md`) to explicitly state that both immediate and approval cancellation paths already restore `current_balance` proportionally.

### Phase 2 — Fix non-wallet variant stock restoration (optional but important)

- [ ] Add a `TicketStockHelper::restoreOnSale($branchId, $providerId, $variantId, $qty, ...)` helper, or reuse `adjustOnHand` with a positive delta.
- [ ] Call it inside `api/pos/ticket-cancel.php` (immediate path) and `api/pos/cancellation-approval.php` (approve path) when the ticket is a **non-wallet variant**.
- [ ] Ensure `ticket_stock_movements` row uses a `POS_CANCEL` or `POS_REFUND` movement type.
- [ ] Gate the restore with `allow_negative_ticket_stock` to match sale behavior.

### Phase 3 — Deduplicate cancellation logic (optional)

- [ ] Create a shared helper/service such as `app/helpers/CancellationService.php` or `app/helpers/RefundService.php`.
- [ ] Move wallet credit, `wallet_transactions` insert, `pos_order_items` zeroing, and `ticket_refunds` insert into the shared service.
- [ ] Update `ticket-cancel.php` and `cancellation-approval.php` to delegate to the shared service.
- [ ] Unit test the shared service with both full and partial refund amounts.

### Phase 4 — UI/UX in Refund Confirmations (optional)

- [ ] In `admin/refund-confirmations/assets/js/refund-confirmations.js` and `views/modals/confirm_cancellation.php`, display whether the ticket was wallet-backed so the approver can see that the wallet will be credited.
- [ ] Add a read-only “Wallet to Credit” field in the modal if a resolved wallet exists.

---

## 6. Files involved

| File | Current responsibility |
|---|---|
| `api/pos/tickets.php` | Sale endpoint; now skips branch stock for variant wallets. |
| `api/pos/ticket-cancel.php` | Immediate cancellation; already credits resolved wallet. |
| `api/pos/cancellation-approval.php` | Manager approval of pending cancellations; already credits resolved wallet. |
| `admin/refund-confirmations/` | UI for reviewing/approving pending cancellations. |
| `app/helpers/WalletResolver.php` | Shared wallet resolution. |
| `app/helpers/TicketStockHelper.php` | Physical stock helper; missing a restore-on-cancel call. |

---

## 7. Suggested next step

If the goal is to keep wallet-backed variant behavior consistent with sales, **no code changes are required** — the existing cancellation and refund-confirmation paths already restore the wallet balance the same way it is deducted. The only recommended next step is to document this confirmation and decide later whether to fix the unrelated branch-stock restoration bug.
