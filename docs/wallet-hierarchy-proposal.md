# Wallet Hierarchy Flow Proposal

## Overview

This document proposes the wallet hierarchy and transaction flow for the TMS wallet system. The goal is to clarify how balances are shared between Main Providers, Sub-providers, and ticket variants.

Please review the scenarios below and confirm if the proposed flow is correct before implementation.

---

## Proposed Wallet Structure

### Main Provider Wallet Sharing

- A **Main Provider** owns the wallet balance.
- **Sub-providers** under a Main Provider can transact, but the balance is deducted from the Main Provider's wallet.
- Sub-providers do **not** need their own wallets.

### Variant-Specific Wallets

- A Main Provider or Provider can have **ticket variants** (e.g., Red, White).
- Each variant can have its own wallet balance.
- Transactions for a specific variant deduct from that variant's wallet.

---

## Scenario 1: Main / Sub-provider Wallet Sharing

### Initial Balances

| Provider | Type | Wallet Balance |
|----------|------|----------------|
| VIA | Main Provider | ₱50,000.00 |
| PAL | Sub-provider of VIA | No wallet |
| Cebu Pacific | Sub-provider of VIA | No wallet |

### Transactions

| Transaction | Operating Provider | Amount | Deducted From | New Balance |
|-------------|-------------------|--------|---------------|-------------|
| POS sale | PAL | ₱10,000.00 | VIA wallet | VIA: ₱40,000.00 |
| POS sale | Cebu Pacific | ₱5,000.00 | VIA wallet | VIA: ₱35,000.00 |

### Expected Result

- PAL and Cebu Pacific do not have their own wallets.
- All transactions by PAL and Cebu Pacific are charged to VIA's wallet.
- VIA's final balance after the two sub-provider transactions is **₱35,000.00**.

---

## Scenario 2: Variant-Specific Wallets

### Initial Balances

| Provider | Variant | Wallet Balance |
|----------|---------|----------------|
| Cokaliong | White | ₱10,000.00 |
| Cokaliong | Red | ₱10,000.00 |

### Transactions

| Transaction | Provider | Variant | Amount | Deducted From | New Balance |
|-------------|----------|---------|--------|---------------|-------------|
| POS sale | Cokaliong | White | ₱2,000.00 | White variant wallet | White: ₱8,000.00 |
| POS sale | Cokaliong | Red | ₱3,000.00 | Red variant wallet | Red: ₱7,000.00 |

### Expected Result

- White variant sale deducts from the White variant wallet only.
- Red variant sale deducts from the Red variant wallet only.
- Variant wallets do not share balances with each other.

---

## Summary

1. **Main Provider wallets can be shared** with Sub-providers.
2. **Sub-providers do not need their own wallets** to transact.
3. **Variant wallets are separate** and transactions for each variant deduct from that variant's own balance.
4. **No cross-variant or cross-provider sharing** except through the Main / Sub-provider relationship.

---

## Management Approval

Please confirm:

- [ ] The proposed Main / Sub-provider wallet sharing flow is correct.
- [ ] The proposed variant-specific wallet flow is correct.
- [ ] We can proceed with implementation.

If any scenario needs adjustment, please provide feedback before development begins.
