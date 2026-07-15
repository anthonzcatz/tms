# Wallet Provider Hierarchy Roadmap

## Objective

Enable a parent-provider wallet structure where multiple operating providers can share one parent wallet for balance, while keeping the existing per-provider wallet behavior for standalone providers.

Example use case:

- **Via** is the parent wallet owner.
- **PAL**, **Cebu Pacific**, and other providers operate under Via.
- Top-up (`IN`) is done to the **Via** wallet.
- Sales (`OUT`) for PAL, Cebu Pacific, etc. are deducted from the **Via** wallet.
- Standalone providers (e.g. 2GO) can still keep their own wallet and use it for both `IN` and `OUT`.

---

## Current Flow

### Wallet loading / top-up

- UI: `admin/wallet/wallet-transactions/`
- API: `api/wallet-transactions/index.php`
- Creates a `wallet_transactions` record with `direction = IN` or `OUT`.
- Updates `provider_wallets.current_balance` for the selected `wallet_id`.

### POS ticket sale

- UI: `admin/pos/`
- API: `api/pos/tickets.php`
- Cashier selects a wallet from the `ticketWallet` dropdown (`admin/pos/views/index.php`).
- Deducts `base_amount` from `provider_wallets.current_balance`.
- Inserts a `wallet_transactions` record with `txn_type = ADJUSTMENT`, `direction = OUT`.
- The provider used for service fees and reporting is derived from the selected wallet.

### Current limitation

- `ticket_providers` has no parent/child relationship.
- `provider_wallets` is unique per `(provider_id, branch_id)`.
- The POS ticket form has no separate "operating provider" field; the wallet itself defines the provider.
- Because of this, a PAL ticket cannot deduct from a Via wallet.

---

## Recommended Architecture

Use a **provider hierarchy with inherited wallet resolution**.

### Design principles

1. Keep `provider_wallets` and `wallet_transactions` unchanged.
2. Add hierarchy to `ticket_providers`.
3. Separate the **operating provider** (used for service fee and reporting) from the **wallet** (used for balance).
4. Use a single `WalletResolver` helper to determine which wallet to debit/credit.
5. If a provider has its own wallet for a branch, use it. If not, walk up the parent chain.

### Schema changes

- `ticket_providers`
  - Add `parent_provider_id` (self-referencing foreign key, nullable).
- `ticket_transactions`
  - Add `provider_id` to store the operating provider.
- `pos_order_items`
  - Add `provider_id` to store the operating provider.

`provider_wallets` and `wallet_transactions` do not need structural changes.

### Wallet resolution logic

Create a helper function, e.g. `WalletResolver::resolve(providerId, branchId)`:

1. Look for an active `provider_wallets` row for `provider_id` + `branch_id`.
2. If found, return that wallet.
3. If not found and `parent_provider_id` is set, repeat the lookup for the parent provider.
4. If no wallet is found, return null and block the transaction.

This supports:

- A parent provider with its own wallet.
- A child provider with no wallet, inheriting the parent wallet.
- A standalone provider with its own wallet.
- A child provider that has its own wallet, using its own wallet instead of the parent.

### Service fee handling

- `provider_service_fees` is keyed by `provider_id` and `branch_id`.
- Service fees must be looked up using the **operating provider** (the selected `provider_id`), not the wallet provider.
- POS JavaScript `loadServiceFeeForWallet()` should be updated to use the operating provider.

### POS UI changes

- Add a **Provider** dropdown to the ticket form.
- Wallet selection can be auto-resolved based on the selected provider and branch.
- Display the resolved wallet to the cashier.
- Pass both `provider_id` (operating) and `wallet_id` (resolved) to `api/pos/tickets.php`.

### Refunds and cancellations

- Refunds must be credited back to the same wallet that was debited.
- Use `WalletResolver::resolve(providerId, branchId)` on the original ticket to find the correct wallet.
- Update `api/pos/ticket-cancel.php` and `api/pos/cancellation-approval.php`.

---

## Implementation Roadmap

### Phase 1: Database migration

1. Add `parent_provider_id` to `ticket_providers`.
2. Add `provider_id` to `ticket_transactions`.
3. Add `provider_id` to `pos_order_items`.
4. Backfill existing data:
   - Set `parent_provider_id` for PAL, Cebu Pacific, and other providers under Via.
   - Set `provider_id` on existing `ticket_transactions` and `pos_order_items` from the wallet provider.

### Phase 2: Wallet resolver

1. Create `app/helpers/WalletResolver.php`.
2. Implement the recursive wallet lookup.
3. Add unit or integration tests for the resolver.

### Phase 3: Wallet management API

1. Update `api/wallets/index.php` to include parent/child provider relationships.
2. Update `api/wallet-transactions/index.php` to validate that top-up is performed on a valid wallet.
3. Ensure wallet list endpoints show hierarchy (parent/child grouping).

### Phase 4: POS changes

1. Update `admin/pos/views/index.php`:
   - Add operating provider dropdown.
   - Adjust wallet display to show resolved wallet.
2. Update `admin/pos/assets/js/pos.js`:
   - Load providers and resolve wallets.
   - Fetch service fees by operating provider.
   - Pass `provider_id` and `wallet_id` separately.
3. Update `api/pos/tickets.php`:
   - Use `WalletResolver` to confirm wallet.
   - Store `provider_id` in `ticket_transactions` and `pos_order_items`.
   - Deduct `base_amount` from the resolved wallet.

### Phase 5: Refund and cancellation

1. Update `api/pos/ticket-cancel.php` to resolve the original wallet.
2. Update `api/pos/cancellation-approval.php` to refund the resolved wallet.
3. Ensure `wallet_transactions` records reflect the correct wallet for refunds.

### Phase 6: Reporting and transactions list

1. Update `admin/wallet/wallet-transactions/`:
   - Show both operating provider and wallet owner.
   - Allow filtering by provider or wallet.
2. Update `admin/pos/` transaction history:
   - Show provider and wallet provider columns.
3. Update `admin/wallet/provider-wallets/`:
   - Show child providers linked to each wallet.

---

## Files to Modify

- `database/migrations/` — new migration for schema changes.
- `app/helpers/WalletResolver.php` — new helper.
- `api/wallets/index.php` — wallet CRUD and listing.
- `api/wallet-transactions/index.php` — top-up and transaction validation.
- `api/pos/tickets.php` — ticket sale wallet deduction.
- `api/pos/ticket-cancel.php` — cancellation refund.
- `api/pos/cancellation-approval.php` — cancellation approval refund.
- `admin/pos/views/index.php` — ticket form provider dropdown.
- `admin/pos/assets/js/pos.js` — wallet and service fee resolution.
- `admin/wallet/wallet-transactions/views/index.php` — transaction list.
- `admin/wallet/provider-wallets/views/index.php` — wallet list.

---

## Migration Example

For the Via group:

- Create or identify the Via provider record.
- Set `parent_provider_id` of PAL, Cebu Pacific, and other providers to the Via provider ID.
- Ensure a `provider_wallets` record exists for Via + each branch.
- Remove or leave unused `provider_wallets` records for PAL/Cebu Pacific if they exist and should not be used.

For standalone providers:

- Leave `parent_provider_id` as NULL.
- Keep their existing `provider_wallets` records.

---

## Reporting Considerations

- Wallet balance report should show wallet owner balances.
- Sales report should show operating provider sales.
- Wallet transaction report should show both the wallet owner and the operating provider.
- This gives finance/admin visibility into how much was deducted per provider while tracking balance in one parent wallet.

---

## Optional Flexibility

If non-hierarchical wallet sharing is needed later (e.g. a provider mapped to an unrelated wallet), add a `provider_wallet_mappings` table:

- `provider_id`, `branch_id`, `wallet_id`

This would override the parent inheritance. For the Via/PAL/CEB use case, the `parent_provider_id` approach is sufficient and simpler.

---

## Decision

Proceed with the **provider hierarchy + inherited wallet resolution** design.

It is the cleanest, least invasive, and most future-ready path for the parent wallet requirement.
