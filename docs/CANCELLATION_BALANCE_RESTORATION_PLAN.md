# Cancellation & Refund Confirmations — Balance Restoration Review & Plan

## 1. Objective

Review cancellation/refund balance effects and document the current rule: consumed variant tickets are not reusable, so their physical availability and provider wallet balance are never restored; only eligible non-variant financial effects may be reversed.

## 2. Current sale behavior (after the fix)

**Files:** `api/pos/tickets.php`

1. `WalletResolver::resolve($providerId, $branchId, $variantId)` is called.
2. If the resolved wallet is **variant-specific** (`variant_id IS NOT NULL`), the physical `branch_ticket_stocks` deduction is **skipped**.
3. The wallet balance (`provider_wallets.current_balance`) is reduced by the **base amount** only.
4. A `wallet_transactions` ledger row (`txn_type = 'ADJUSTMENT'`, `direction = 'OUT'`) is inserted.

> Sale availability and refund disposition are separate. Once a variant ticket is sold, it is consumed: cancellation/refund/void does not restore physical availability or provider wallet balance, even if the original sale used a variant-specific wallet.

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

**Verdict:** The prior proportional wallet-restoration behavior is superseded for consumed variant tickets. Variant cancellation/refund/void must not credit the provider wallet.

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

**Verdict:** The existing approval path must follow the consumed-variant rule: no physical-stock or provider-wallet restoration for variant tickets; CHARGE debt reversal remains allowed.

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

### Phase 1 — Enforce consumed variant non-restoration

- [ ] Ensure `CancellationService` skips provider-wallet credit and physical-stock restoration whenever `ticket_transactions.variant_id` is present.
- [ ] Keep the original CHARGE debt reversal tied to `transaction_payments.charged_to_passenger_id`.
- [ ] Add a visible audit/report note that the variant ticket was consumed and is not reusable.

### Phase 2 — Preserve eligible non-variant financial reversal

- [ ] Restore the applicable provider wallet only for non-variant tickets where the original sale debited that wallet.
- [ ] Do not create a physical-stock reversal for consumed variant tickets.
- [ ] Ensure `ticket_stock_movements` never records a cancellation stock return for a consumed variant.

### Phase 3 — Deduplicate cancellation logic (optional)

- [ ] Create a shared helper/service such as `app/helpers/CancellationService.php` or `app/helpers/RefundService.php`.
- [ ] Move wallet credit, `wallet_transactions` insert, `pos_order_items` zeroing, and `ticket_refunds` insert into the shared service.
- [ ] Update `ticket-cancel.php` and `cancellation-approval.php` to delegate to the shared service.
- [ ] Unit test the shared service with both full and partial refund amounts.

### Phase 4 — UI/UX in Refund Confirmations (optional)

- [ ] In `admin/refund-confirmations/assets/js/refund-confirmations.js` and `views/modals/confirm_cancellation.php`, display whether the ticket is a consumed variant and show that no stock/provider-wallet restoration will occur.
- [ ] Keep the original payment/CHARGE reversal details visible for the approver.

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

The active business rule is that sold variant tickets are consumed. The next implementation must keep their original CHARGE reversal and customer refund behavior, but must not restore physical stock or provider-wallet balance. Non-variant wallet reversals remain eligible for restoration.
