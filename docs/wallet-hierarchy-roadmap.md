# Wallet Hierarchy Implementation Roadmap

## 1. Goal

Implement a wallet structure that supports:

- **Main Provider wallet sharing**: Sub-providers can transact but charge to the Main Provider wallet.
- **Variant-specific wallets**: Each ticket variant can have its own separate wallet balance.
- **Clear POS wallet resolution**: Every transaction resolves to the correct wallet without ambiguity.

---

## 2. Database Structure

### `ticket_providers`

| Column | Purpose |
|--------|---------|
| `provider_id` | Provider identifier |
| `parent_provider_id` | Points to the Main Provider (NULL if standalone/main) |

### `provider_ticket_variants`

| Column | Purpose |
|--------|---------|
| `variant_id` | Variant identifier |
| `provider_id` | Provider that owns the variant |
| `variant_code`, `variant_name`, `display_color` | Variant details |

### `provider_wallets`

| Column | Purpose |
|--------|---------|
| `wallet_id` | Wallet identifier |
| `provider_id` | Wallet owner provider |
| `branch_id` | Wallet branch |
| `variant_id` | NULL for provider-level wallet, value for variant-specific wallet |
| `current_balance` | Wallet balance |
| `status` | `active` or `inactive` |

---

## 3. Wallet Types

### 3.1 Provider-level Wallet

- `variant_id IS NULL`
- Used for transactions that do not specify a variant.
- Sub-providers without their own provider-level wallet fall back to their Main Provider's wallet.

### 3.2 Variant-specific Wallet

- `variant_id` has a value.
- Separate balance per ticket class/variant.
- Falls back to the provider-level wallet if the variant wallet does not exist.

---

## 4. Wallet Resolution Logic

`WalletResolver::resolve($providerId, $branchId, $variantId = null)`

Resolution order:

1. **Exact variant wallet**: `provider_id + branch_id + variant_id`
2. **Provider-level wallet**: `provider_id + branch_id` with `variant_id IS NULL`
3. **Parent provider wallet**: if current provider is a sub-provider, repeat steps 1-2 using `parent_provider_id`
4. **No wallet found**: return `false`

### Example: PAL sells a White variant ticket

1. Look for `PAL + branch + White variant` wallet.
2. If not found, look for `PAL + branch` provider-level wallet.
3. If not found, look for `VIA + branch + White variant` wallet.
4. If not found, look for `VIA + branch` provider-level wallet.
5. Return `false` if still not found.

### Example: VIA sells a Red variant ticket

1. Look for `VIA + branch + Red variant` wallet.
2. If not found, look for `VIA + branch` provider-level wallet.
3. Return wallet or `false`.

---

## 5. POS Transaction Flow

1. POS sends `provider_id` (operating provider), `branch_id`, and optional `variant_id`.
2. `WalletResolver::resolve()` determines the actual wallet.
3. If no wallet, return error and abort transaction.
4. Deduct or credit the resolved wallet's `current_balance`.
5. Store `wallet_id` in the transaction record.
6. For reporting, keep `operating_provider_id` (PAL/Cebu Pacific) and `wallet_provider_id` (VIA).

---

## 6. UI Components

### `admin/wallet/provider-wallets/`

- Wallet cards per provider/branch/variant.
- Show Main/Sub-provider badges.
- Show variant badges.
- Manage Provider Wallets modal lists variants with balances.
- Hide Variants button when provider has no variants.

### `admin/wallet/ticket-providers/`

- Main Provider column on the left.
- Combined Provider Code/Provider Name column.
- Clear visual hierarchy of main and sub-providers.

### `admin/wallet/wallet-transactions/`

- Show Sub-provider (operating provider).
- Show Main Provider (wallet owner).
- Show variant if applicable.

---

## 7. Implementation Steps

1. **Restore parent fallback in `WalletResolver.php`**
   - Bring back `resolveRecursive()` and `getParentProviderId()`.
   - Keep variant wallet resolution.

2. **Update API endpoints to handle `wallet_provider_id` vs `operating_provider_id`**
   - `api/pos/tickets.php`
   - `api/pos/ticket-cancel.php`
   - `api/pos/cancellation-approval.php`
   - `api/wallet-transactions/index.php`

3. **Update UI labels and columns**
   - Wallet-transactions list and export.
   - Wallet transaction detail view.

4. **Ensure each Main Provider has a wallet**
   - Add UI/UX guidance or validation where appropriate.

5. **Verify variant wallet fallback behavior**
   - Variant wallet → provider-level wallet → parent provider variant wallet → parent provider-level wallet.

---

## 8. Testing Scenarios

### 8.1 Main/Sub Wallet Sharing

Initial state:

- VIA (Main): `₱50,000`
- PAL (Sub): no wallet
- Cebu Pacific (Sub): no wallet

Test transactions:

- POS sale by PAL: `₱10,000` → VIA becomes `₱40,000`
- POS sale by Cebu Pacific: `₱5,000` → VIA becomes `₱35,000`
- POS sale by VIA: `₱5,000` → VIA becomes `₱30,000`

### 8.2 Variant-specific Wallets

Initial state:

- Cokaliong White variant wallet: `₱10,000`
- Cokaliong Red variant wallet: `₱10,000`
- Cokaliong provider-level wallet: `₱0`

Test transactions:

- White variant sale: `₱2,000` → White becomes `₱8,000`
- Red variant sale: `₱3,000` → Red becomes `₱7,000`
- Non-variant sale by Cokaliong: uses provider-level wallet

---

## 9. User-Friendly UI/UX Flow

### 9.1 `admin/wallet/ticket-providers/`

**Provider list table:**

| Main Provider | Provider | Type | Variants | Status | Created At | Actions |
|---------------|----------|------|----------|--------|------------|---------|

- **Provider** column combines `provider_code` (bold) and `provider_name` (muted small).
- **Main Provider** column shows the parent provider or `—` if standalone.
- Clear visual hierarchy without clutter.

**Add/Edit Provider modal:**

- Label: **Main Provider** (optional)
- Dropdown option: `Standalone (no main provider)`
- Help text: "Leave empty if this is a Main Provider. Select a Main Provider if this is a Sub-provider."

### 9.2 `admin/wallet/provider-wallets/`

**Wallet cards:**

- Large `current_balance` display.
- Badges:
  - `Main Provider` (blue)
  - `Sub-provider of [VIA]` (green)
  - Variant badge with color dot.
- Shared wallet indicator: "Shared with: PAL, Cebu Pacific".
- Min balance shown clearly.

**Add Wallet modal:**

- Provider dropdown format:
  - `[CODE] - Provider Name (Type) - Main Provider` or `[CODE] - Provider Name (Type) - Sub-provider of [Main]`
- Branch dropdown with required validation.
- Variant dropdown with default `Provider-level wallet (no variant)`.
- Help text: "Sub-providers without their own wallet will charge to their Main Provider's wallet."

**Manage Provider Wallets modal:**

- Header: `Manage Provider Wallets - [Provider Name] ([Branch])`
- Table columns:
  - Variant / Wallet
  - Status
  - Current Balance
  - Min Balance
  - Actions
- Each variant row shows:
  - Variant badge with color
  - Balance
  - `Adjust` / `View` / `Create` buttons
- Provider-level row first, then variants.

### 9.3 `admin/wallet/wallet-transactions/`

**Transaction table:**

| Txn Code | Sub-provider | Main Provider | Variant | Wallet | Type | Direction | Amount | Date | Actions |
|----------|--------------|---------------|---------|--------|------|-----------|--------|------|---------|

- **Sub-provider**: operating provider.
- **Main Provider**: wallet owner.
- Filter dropdowns:
  - All Sub-providers
  - All Main Providers
  - All Wallets
  - All Variants

**Export CSV:**

- Headers: `Transaction Code, Sub-provider, Main Provider, Variant, Wallet, Type, Direction, Amount, Balance After, Remarks, Date`

### 9.4 POS Flow

**Provider selection:**

- Dropdown label: **Operating Provider**
- Option format:
  - Main: `VIA [VIA] — Main Provider`
  - Sub: `PAL [PAL] — Sub-provider of VIA`
- After selection, display info card:
  - **Operating**: PAL
  - **Wallet Owner**: VIA
  - **Available Balance**: ₱35,000.00

**Variant selection (if applicable):**

- Dropdown with variant name and current wallet balance:
  - `White — Wallet balance: ₱8,000.00`
  - `Red — Wallet balance: ₱7,000.00`
- Show `No variant` option for provider-level transactions.

**Before final transaction:**

- Confirmation summary:
  - Provider: **PAL**
  - Charged to: **VIA wallet**
  - Variant: **White**
  - Amount: **₱2,000.00**
  - New balance: **₱33,000.00**
- Confirm button to proceed.

---

## 10. Notes

- Sub-providers do not need their own wallets to transact.
- Main providers must have a provider-level wallet for sub-providers to share.
- Variant-specific wallets are optional; if missing, fallback to provider-level wallet.
- Wallet sharing follows the `parent_provider_id` chain only; no cross-branch or cross-provider sharing.
- UI must always show both the **operating provider** and the **wallet owner** to avoid confusion.
