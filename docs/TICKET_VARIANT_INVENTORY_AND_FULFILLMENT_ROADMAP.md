# Ticket Variant Inventory and Fulfillment Roadmap

## Objective

Add a complete, branch-aware inventory and fulfillment workflow for physical or controlled ticket stock that has provider-specific variants.

Example:

- **Cokaliong Shipping Lines** provides tickets identified by a color such as **White** or **Yellow**.
- A branch requests **10 White** and **20 Yellow** tickets.
- The request must show its full lifecycle: who requested it, what was approved, what was dispatched, where it was received, who received it, when it was received, and any discrepancy.
- Once received, the confirmed quantities become available inventory for that branch and provider variant.
- At POS, a cashier selling a Cokaliong ticket must select an available color. The system deducts one unit of the selected color only after a successful sale.

The design must also support future variants such as ticket class, booklet/series, route, terminal, denomination, paper type, validity period, or other provider-defined characteristics.

---

## Core Design Decisions

### 1. Separate monetary wallet value from physical ticket stock

The existing provider wallet balance tracks money used to fund ticket sales. It must not be overloaded to represent ticket pieces, colors, or inventory.

Introduce a dedicated **Ticket Inventory** domain:

- **Wallet balance**: monetary amount available for provider transactions.
- **Ticket stock balance**: number of usable ticket units per provider, variant, and branch.
- **Fulfillment request**: a document for requesting, approving, dispatching, receiving, and reconciling stock.
- **Stock movement**: immutable ledger entry for every inventory increase, deduction, transfer, return, adjustment, or reservation.

This separation prevents incorrect balances such as treating `₱10,000` as equivalent to ten physical ticket pieces.

### 2. Model color as a provider ticket variant, not as a hard-coded field

Do **not** add a global `color` column directly to `ticket_providers` or `ticket_transactions`.

Instead, create reusable variants linked to providers. Cokaliong can have `White` and `Yellow`; a different provider can have `Economy`, `Business`, or `Terminal A` without another schema change.

Each variant should support:

- Display name, e.g. `White`, `Yellow`.
- Stable code, e.g. `WHITE`, `YELLOW`.
- Optional visual color value, e.g. `#FFFFFF`, `#F3C614`, for UI badges only.
- Optional ticket number range / series metadata.
- Active or inactive status.
- Optional stock control flag for providers that do not use controlled ticket pieces.

### 3. Use an append-only stock movement ledger

Current quantity is a useful fast read model, but movement history is the source of truth.

Every stock-affecting event creates a row with:

- Movement type.
- Signed quantity.
- Stock balance before and after.
- Provider, variant, branch, related request, related POS ticket transaction, and acting user.
- Reason, date/time, and optional evidence attachment.

Never silently update stock balances without an accompanying movement row.

### 4. Only completed receipt increases available branch stock

Submitting or approving a request must not increase branch stock.

Inventory is increased only when an authorized receiving user confirms actual quantities received at the destination branch. If requested, approved, dispatched, and received quantities differ, preserve all values and require discrepancy handling.

### 5. POS must reserve or atomically deduct stock

The POS checkout process must perform ticket stock validation and stock deduction inside the same database transaction as ticket creation, wallet deduction, payment creation, and order completion.

This prevents two cashiers from selling the last White ticket simultaneously.

---

## Current System Context

The current provider hierarchy already distinguishes the operating provider from the wallet owner. The new inventory layer must use the **operating provider** because Cokaliong ticket colors belong to Cokaliong, even if its payment wallet is owned by another provider.

The current POS flow accepts a provider and resolved wallet, then records the ticket and deducts the base amount from the wallet. This roadmap adds a selected ticket variant and inventory deduction without changing the wallet resolution concept.

Existing `ticket_transactions.ticket_number` should be retained. It is the sold ticket number, while stock receipt may later record a number range or a serialized inventory item.

---

## Scope

### Included

- Provider-level ticket variants.
- Branch-level ticket inventory balances.
- Stock request, approval, dispatch, receipt, discrepancy, rejection, cancellation, and closure workflow.
- Receipt confirmation with branch and receiver identity.
- POS variant selection and inventory deduction.
- Inventory reservations during checkout, if needed for concurrency protection.
- Stock returns, controlled adjustments, audit trail, notifications, filters, and reports.
- Responsive desktop, tablet, and mobile user interfaces.
- Permissions, CSRF validation, ID encoding, activity logs, and export support.

### Not Included in the first release

- Supplier purchase orders and accounting payable integration.
- Barcode scanner integration.
- Individual ticket serial allocation for every ticket sold.
- Multi-warehouse routing beyond a source and destination branch.

These remain compatible future extensions.

---

## Data Model

### A. `provider_ticket_variants`

Defines selectable ticket variants for one operating provider.

| Field | Purpose |
|---|---|
| `variant_id` | Primary key |
| `provider_id` | Operating provider; foreign key to `ticket_providers` |
| `variant_code` | Stable unique code per provider, e.g. `WHITE` |
| `variant_name` | User-facing label, e.g. `White` |
| `display_color` | Optional hex color strictly for UI display |
| `description` | Optional operational notes |
| `stock_controlled` | Whether POS must validate physical stock for this variant |
| `requires_ticket_number` | Whether POS requires a ticket number before sale |
| `is_active` | Prevents new requests and POS sales without deleting history |
| `created_by`, `created_at`, `updated_at` | Audit columns |

Constraints and indexes:

- Unique `(provider_id, variant_code)`.
- Index `(provider_id, is_active)`.
- Restrict deletion when referenced by inventory, requests, or sales.

### B. `branch_ticket_stocks`

Fast balance projection for available inventory at each branch.

| Field | Purpose |
|---|---|
| `stock_id` | Primary key |
| `branch_id` | Branch currently holding the tickets |
| `provider_id` | Operating provider |
| `variant_id` | Provider ticket variant |
| `on_hand_qty` | Physical quantity confirmed in branch custody |
| `reserved_qty` | Quantity temporarily held for an in-progress POS checkout |
| `available_qty` | Derived as `on_hand_qty - reserved_qty`; do not independently edit |
| `reorder_level` | Optional per-branch threshold for low-stock alerts |
| `updated_at` | Projection update timestamp |

Constraints and indexes:

- Unique `(branch_id, provider_id, variant_id)`.
- Do not hard-code a database `on_hand_qty >= 0` check if the `allow_negative_ticket_stock` setting may be enabled. Always enforce `reserved_qty >= 0` and `reserved_qty <= on_hand_qty` where supported, and enforce non-negative `on_hand_qty` in the application layer when `allow_negative_ticket_stock` is disabled.
- Indexes for `(branch_id, variant_id)` and low-stock reporting.

### C. `ticket_stock_requests`

Header record for a stock request or branch transfer request.

| Field | Purpose |
|---|---|
| `stock_request_id` | Primary key |
| `request_code` | Human-readable unique code, e.g. `TSR-YYYYMMDD-###` |
| `source_branch_id` | Optional source branch; `NULL` for external/provider-issued stock, or set to the head-office/central branch when issuing from central inventory |
| `destination_branch_id` | Branch requesting and receiving stock |
| `provider_id` | Operating provider |
| `status` | Current lifecycle status |
| `request_reason` | Replenishment, opening stock, transfer, emergency, return replacement, other |
| `requested_by`, `requested_at` | Requester identity and time |
| `approved_by`, `approved_at` | Approval audit |
| `dispatched_by`, `dispatched_at` | Dispatch audit |
| `received_by`, `received_at` | Actual receiver and receipt time |
| `closed_by`, `closed_at` | Reconciliation closure audit |
| `remarks` | General notes |
| `rejection_reason`, `cancellation_reason` | Required when applicable |
| `created_at`, `updated_at` | Audit timestamps |

Recommended status machine:

```text
DRAFT
  -> SUBMITTED
  -> CANCELLED

SUBMITTED
  -> APPROVED
  -> REJECTED
  -> CANCELLED

APPROVED
  -> DISPATCHED
  -> CANCELLED

DISPATCHED
  -> PARTIALLY_RECEIVED
  -> RECEIVED
  -> DISPUTED

PARTIALLY_RECEIVED
  -> RECEIVED
  -> DISPUTED
  -> CLOSED

RECEIVED
  -> CLOSED

DISPUTED
  -> RESOLVED
  -> CLOSED
```

Rules:

- A closed request is immutable except for a documented corrective adjustment.
- `DISPATCHED` must have a dispatch actor and timestamp.
- `RECEIVED` or `PARTIALLY_RECEIVED` must have a receiving actor, timestamp, and destination branch.
- A request cannot be received by a user whose assigned branch does not include the destination branch, except authorized administrators.

### D. `ticket_stock_request_items`

Variant-specific quantities within a request.

| Field | Purpose |
|---|---|
| `request_item_id` | Primary key |
| `stock_request_id` | Request header |
| `variant_id` | Requested provider ticket variant |
| `requested_qty` | Initial requested quantity |
| `approved_qty` | Quantity authorized to fulfill |
| `dispatched_qty` | Quantity physically sent |
| `received_qty` | Quantity physically counted by receiver |
| `rejected_qty` | Quantity rejected/damaged at receipt |
| `discrepancy_qty` | Derived difference; do not manually overwrite |
| `ticket_series_from`, `ticket_series_to` | Optional initial support for ticket ranges |
| `remarks` | Per-variant notes |

Constraints:

- Unique `(stock_request_id, variant_id)`.
- Non-negative quantities.
- `approved_qty` cannot exceed requested quantity without a documented override permission.
- `dispatched_qty` cannot exceed approved quantity without a documented override permission.
- `received_qty + rejected_qty` cannot exceed dispatched quantity unless a discrepancy resolution explicitly records an over-receipt.

### E. `ticket_stock_movements`

Immutable ledger recording every inventory event.

| Field | Purpose |
|---|---|
| `movement_id` | Primary key |
| `movement_code` | Unique traceable code |
| `branch_id` | Branch whose stock changed |
| `provider_id` | Operating provider |
| `variant_id` | Ticket variant |
| `movement_type` | See movement taxonomy below |
| `quantity_delta` | Signed quantity; positive adds, negative deducts |
| `balance_before`, `balance_after` | Auditable running balance |
| `reference_type`, `reference_id` | Request, POS ticket sale, return, adjustment, etc. |
| `source_branch_id`, `destination_branch_id` | Transfer traceability where applicable |
| `remarks` | Required for manual movements |
| `performed_by`, `performed_at` | User and timestamp |
| `approved_by` | Required for sensitive adjustments |
| `created_at` | Immutable event time |

Movement taxonomy:

- `OPENING_BALANCE`
- `RECEIPT`
- `TRANSFER_OUT`
- `TRANSFER_IN`
- `POS_SALE`
- `POS_SALE_REVERSAL`
- `RETURN_TO_SOURCE`
- `RETURN_FROM_BRANCH`
- `DAMAGE_OR_VOID`
- `COUNT_ADJUSTMENT`
- `RESERVATION`
- `RESERVATION_RELEASE`

### F. `ticket_stock_reservations` (recommended)

Protects inventory during a pending POS checkout without permanently deducting stock too early.

| Field | Purpose |
|---|---|
| `reservation_id` | Primary key |
| `branch_id`, `provider_id`, `variant_id` | Reserved stock identity |
| `quantity` | Reserved quantity, usually one per ticket line |
| `session_id` | Cashier session |
| `reservation_token` | Browser/session-safe token |
| `status` | `ACTIVE`, `CONSUMED`, `RELEASED`, `EXPIRED` |
| `expires_at` | Automatically release abandoned reservations |
| `created_by`, `created_at`, `released_at` | Audit |

For the first implementation, an atomic stock decrement inside final checkout is acceptable. Add reservations when the UI permits long-lived cart items, multi-ticket carts, or high concurrent cashier volume.

### G. `ticket_stock_discrepancies` (recommended)

Stores formal investigation records when received quantity differs from dispatched quantity or tickets are damaged/missing.

| Field | Purpose |
|---|---|
| `discrepancy_id` | Primary key |
| `stock_request_id`, `request_item_id` | Related receipt item |
| `discrepancy_type` | Short, damaged, excess, wrong variant, serial mismatch |
| `expected_qty`, `actual_qty` | Count comparison |
| `reported_by`, `reported_at` | Reporter audit |
| `status` | Open, investigating, resolved, written off |
| `resolution`, `resolved_by`, `resolved_at` | Formal resolution |
| `evidence_path` | Optional receiving photo/document |

---

## Required Database Migration Strategy

Create a new idempotent migration under `database/migrations/`, for example:

```text
add_ticket_variant_inventory_and_fulfillment.sql
```

Migration phases:

1. Create the variant, balance, request, request-item, movement, reservation, and discrepancy tables.
2. Add foreign keys only after confirming existing table engines and compatible key types.
3. Add required unique keys and reporting indexes.
4. Seed no production stock automatically.
5. Optionally create a Cokaliong `White` and `Yellow` variant only after administrator confirmation.
6. Create initial stock only through an `OPENING_BALANCE` workflow with a mandatory reason and approver.
7. Add `variant_id` to `ticket_transactions` and `pos_order_items` as nullable first.
8. Backfill historical sales as `variant_id = NULL`; do not invent a color for historical records.
9. Make `ticket_transactions.variant_id` conditionally required only in application validation when the selected service type requires a ticket variant and the provider has active stock-controlled variants. Do not force it globally because legacy and non-controlled providers remain valid.
10. Add `service_types.requires_ticket_variant` (or a `provider_service_types` per-provider override) to control which service types must select a ticket variant at POS.
11. Add `system_settings.allow_negative_ticket_stock` as a system-wide toggle that lets stock balances go negative; default is disabled. This must be respected by POS checkout, dispatch, receipt, and manual adjustment validations.

The migration must not alter or repurpose `provider_wallets.current_balance`.

---

## Provider Configuration UX

Extend the Ticket Providers area with a **Ticket Variants** action or a dedicated tab/modal.

### Provider controls

- `Uses controlled ticket stock` toggle.
- `Require ticket variant at POS` toggle, enabled only if controlled stock is used.
- Variant grid with code, name, color preview, status, current total stock, and actions.
- Add/edit/deactivate variant.
- Block deletion when the variant has inventory, requests, or historical POS transactions.

### Cokaliong configuration example

| Code | Variant | UI color | Controlled | POS selectable |
|---|---|---|---|---|
| `WHITE` | White | `#FFFFFF` with border | Yes | Yes |
| `YELLOW` | Yellow | `#F3C614` | Yes | Yes |

Color must always be supplemented by text and never be the only identifier, for accessibility and printing clarity.

---

## Ticket Stock Request and Receiving Module

Create a dedicated Wallet submodule named **Ticket Stock Requests**.

Suggested location:

```text
admin/wallet/ticket-stock-requests/
api/ticket-stock-requests/index.php
```

Recommended module structure must follow the existing admin module guard, responsive UI, CSRF, and ID encoding patterns.

### A. List dashboard

Include summary cards:

- Draft / pending approval.
- Approved awaiting dispatch.
- In transit / dispatched awaiting receipt.
- Partially received / disputed.
- Low-stock variants for branches the user can access.

Include filters:

- Request code / keyword search.
- Provider.
- Variant.
- Source branch.
- Destination branch.
- Request status.
- Date requested, dispatched, received.
- Requester, approver, dispatcher, receiver.
- Has discrepancy.

Table columns:

- Request code.
- Provider.
- Destination branch.
- Requested variants and totals.
- Status.
- Requested by/date.
- Received by/date.
- Discrepancy indicator.
- Available actions.

### B. Create request

The requester selects:

- Destination branch, defaulting to their authorized branch.
- Source branch or external/provider source.
- Operating provider.
- One or more active variants.
- Quantity per variant.
- Request reason.
- Notes and optional attachment.

Example:

```text
Provider: Cokaliong Shipping Lines
Destination branch: Cebu City Branch
Requested items:
- White: 10
- Yellow: 20
Reason: Weekly replenishment
```

Validations:

- At least one item with quantity greater than zero.
- Only active variants belonging to the chosen provider.
- User must be authorized for the destination branch.
- Drafts may be edited; submitted requests are immutable until returned for revision.

### C. Approve / reject

Authorized inventory manager or administrator can:

- Approve requested quantities per item, including partial approvals.
- Reject with mandatory reason.
- Return to draft for correction with comments.

Approval must log the exact approved quantities and actor; later changes create a new history entry rather than overwrite silently.

### D. Dispatch

The dispatch user confirms quantities physically sent per variant.

For branch-to-branch stock transfers:

- Create `TRANSFER_OUT` movements at the source branch when dispatch is confirmed.
- Do not create destination `TRANSFER_IN` balances until receipt confirmation.
- Require source stock availability before dispatch.

For external/provider-issued stock:

- Record dispatch detail without decrementing an internal source branch.

Dispatch form should capture:

- Dispatch date/time.
- Dispatch user.
- Courier/delivery reference, optional.
- Dispatch note.
- Optional supporting document or photo.
- Actual dispatched quantities.

### E. Receive and count

Only users with receiving permission and access to the destination branch can receive.

Receiving form must display, by variant:

- Requested quantity.
- Approved quantity.
- Dispatched quantity.
- Counted received quantity.
- Rejected/damaged quantity.
- Discrepancy quantity.
- Optional ticket number range / series confirmation.

Upon confirmation:

1. Lock the request and prevent duplicate receipt.
2. Validate the receiver's branch access.
3. Add stock through a `RECEIPT` or `TRANSFER_IN` ledger movement for actual accepted quantities.
4. Update `branch_ticket_stocks.on_hand_qty` atomically.
5. Save receiver user, actual destination branch, timestamp, and notes.
6. Mark the request `RECEIVED`, `PARTIALLY_RECEIVED`, or `DISPUTED`.
7. Create a discrepancy record when quantities differ or tickets are damaged/wrong variant.
8. Write an activity log and notify requester/approver as configured.

A received request should provide a printable **Receiving Acknowledgment** containing all quantities, receiver, branch, timestamp, and signatures/confirmation fields if needed.

### F. Resolve discrepancy and close

The inventory manager can:

- Confirm shortage.
- Receive the missing remainder later through a subsequent controlled receipt.
- Record replacement stock.
- Write off damaged stock with approval.
- Correct a wrong variant through paired movements.
- Close the request only after its discrepancy is resolved or formally accepted.

Do not allow a direct edit of the received quantity after posting stock. Corrections must create compensating movements.

---

## POS Changes

### POS ticket entry sequence

For providers without controlled stock:

```text
Provider -> Wallet -> Service fee -> Cost -> Ticket details -> Cart
```

For providers with active stock-controlled variants:

```text
Provider -> Ticket Variant -> Available stock display -> Resolved wallet -> Service fee -> Cost -> Ticket details -> Cart
```

### Required POS controls

After a provider and service type are selected:

1. Determine whether the active service type is flagged `requires_ticket_variant` (or has a provider-specific override).
2. Load active variants for that operating provider.
3. If the service type does not require variants or no active controlled variants exist, hide the variant input and preserve the current experience.
4. If variants are required, show a required **Ticket Color / Variant** selector.
5. Display each option using text, color swatch, and live available quantity, e.g. `Yellow — 20 available`.
6. Disable zero-stock options when `allow_negative_ticket_stock` is disabled; when enabled, allow selection but show a clear negative-stock warning.
7. Display a warning badge for low stock.
8. Add the selected `variant_id`, variant name, and available stock snapshot to the cart item.
9. Revalidate the inventory on checkout server-side. Browser values are never trusted.

### API checkout changes

The POS ticket payload must include `variant_id` when the service type is flagged `requires_ticket_variant` and the provider has active controlled variants.

During the existing database transaction, for each ticket:

1. Validate the provider is active and allowed for the cashier.
2. Validate the selected service type and, when required, that the variant belongs to the selected provider and is active.
3. Lock the matching `branch_ticket_stocks` row using a transaction-safe `SELECT ... FOR UPDATE` pattern.
4. If `allow_negative_ticket_stock` is disabled, verify `available_qty >= quantity`; otherwise allow the deduction and record a `NEGATIVE_BALANCE` note or warning with the movement.
5. Insert the `ticket_transactions` row with `provider_id` and `variant_id`.
6. Create the existing wallet deduction using the resolved wallet.
7. Decrement the stock projection atomically.
8. Insert a `POS_SALE` stock movement linked to the ticket transaction and POS order.
9. Insert the POS order item with `provider_id` and `variant_id`.
10. Commit all operations together.

If any step fails, roll back the entire POS order including wallet and stock changes.

### Cancellation and refund rules

Inventory reversal must be explicit and must not automatically happen for every financial refund.

Add a cancellation/refund decision. The cashier or receiver must choose a disposition; the system must not automatically return damaged tickets to usable stock:

- **Returned to usable stock**: create `POS_SALE_REVERSAL` and increase the same branch/provider/variant stock.
- **Returned to provider**: create a `RETURN_TO_SOURCE` movement (or dispatch note) so the physical tickets are sent back to the supplier; usable inventory is not restored.
- **Written off internally / not reusable**: do not return to usable inventory; create a `DAMAGE_OR_VOID` movement with reason and approval if required.
- **Replaced with equivalent variant**: perform a paired adjustment (out from the returned variant, in to the replacement variant) with reason and approver.
- **Financial refund only**: wallet and payment logic follows current rules; inventory behavior depends on the selected disposition and must be audited.

Every reversal must reference the original ticket transaction and preserve the original `variant_id`.

---

## APIs

### `api/ticket-provider-variants/index.php`

Responsibilities:

- List provider variants.
- Create, update, activate, and deactivate variants.
- Return variants permitted for POS based on selected provider and branch.
- Enforce provider-level permissions and references before deletion.

### `api/ticket-stock-requests/index.php`

Responsibilities:

- List requests with server-side filters and pagination.
- Create drafts and submit requests.
- Approve, reject, cancel, dispatch, receive, resolve discrepancy, and close.
- Enforce status transitions server-side.
- Use a database transaction for dispatch, receipt, resolution, and inventory posting.
- Decode ID-encoded inputs and validate CSRF for all mutations.

Suggested actions:

```text
GET    ?action=list
GET    ?action=detail&id=...
GET    ?action=dashboard
POST   action=create
PUT    action=submit
PUT    action=approve
PUT    action=reject
PUT    action=dispatch
PUT    action=receive
PUT    action=resolve-discrepancy
PUT    action=close
DELETE action=cancel
```

### `api/ticket-stock/index.php`

Responsibilities:

- Return branch stock balances for dashboard, POS, and reports.
- Return stock movements with filters.
- Handle controlled adjustments and returns.
- Optionally manage reservations.

Suggested read endpoints:

```text
GET ?action=balances&branch_id=...&provider_id=...
GET ?action=availability&branch_id=...&provider_id=...
GET ?action=movements&branch_id=...&variant_id=...
GET ?action=low-stock
```

### Existing APIs to update

- POS ticket processing endpoint: validate and deduct variant inventory during checkout.
- Ticket cancellation and approval endpoints: perform inventory return or void movement based on the authorized disposition.
- Ticket provider endpoint: expose `uses_controlled_ticket_stock` and variant relationship/counts as needed.
- Reports endpoints: include provider variant in ticket, POS, and inventory reports.

---

## Permissions and Roles

Introduce granular permissions instead of granting all stock capabilities through wallet access.

| Permission | Capability |
|---|---|
| `VIEW_TICKET_STOCK` | See balances, movements, and stock reports for authorized branches |
| `MANAGE_TICKET_VARIANTS` | Configure provider variants |
| `CREATE_TICKET_STOCK_REQUEST` | Create and submit branch requests |
| `APPROVE_TICKET_STOCK_REQUEST` | Approve, reject, or return requests |
| `DISPATCH_TICKET_STOCK` | Confirm outgoing dispatch |
| `RECEIVE_TICKET_STOCK` | Confirm delivery at assigned destination branches |
| `ADJUST_TICKET_STOCK` | Make controlled manual adjustments |
| `RESOLVE_TICKET_STOCK_DISCREPANCY` | Resolve shortages/damage/overage |
| `VIEW_ALL_TICKET_STOCK` | Cross-branch visibility for management |

Policy rules:

- Requester cannot approve their own request by default.
- When the primary branch manager is unavailable, use the following fallback for approval authority:
  1. Any active user with `APPROVE_TICKET_STOCK_REQUEST` permission who is assigned to the destination branch.
  2. Any active user with `VIEW_ALL_TICKET_STOCK` permission (e.g., operations manager or inventory supervisor).
  3. A `SUPER_ADMIN` user.
  4. If no eligible approver exists, the request remains in `SUBMITTED` status and an escalation notification is sent.
- Receiver cannot close their own unresolved discrepancy without a separate resolution permission.
- Manual stock adjustment requires reason, before/after values, and an approver when policy requires it.
- Cashiers can only see stock for their active session branch and assigned providers.

---

## Reporting and Audit

### Inventory dashboard

Display by provider, variant, and branch:

- On hand.
- Reserved.
- Available.
- Low stock indicator.
- In-transit quantity from dispatched but unreceived requests.
- Requested / approved / dispatched / received totals for a selected period.

### Movement ledger report

Filters:

- Branch.
- Provider.
- Variant.
- Movement type.
- Date range.
- Related request code.
- POS order / ticket transaction code.
- Acting user.

Columns:

- Timestamp.
- Movement code.
- Provider / variant.
- Branch.
- Type.
- Quantity in/out.
- Balance before/after.
- Reference.
- Performed by.
- Remarks.

### Fulfillment report

Track service quality and accountability:

- Request lead time: submitted to approved, approved to dispatched, dispatched to received.
- Fill rate: received / requested.
- Discrepancy rate by provider, variant, and branch.
- Overdue dispatched requests.
- Requests received by user and branch.

### POS and financial reports

Add provider variant fields to ticket and POS reporting:

- Operating provider.
- Ticket variant / color.
- Wallet owner.
- Branch.
- Ticket number.
- Sale and reversal/void status.

Exports must use text labels such as `White` and `Yellow`; never depend on color alone.

---

## Notifications

Use the existing notification approach to notify responsible users on:

- Request submitted.
- Request approved or rejected.
- Stock dispatched.
- Stock partially received, received, or disputed.
- Discrepancy assigned or resolved.
- Low stock threshold reached.
- Stock request overdue for dispatch or receipt.

Notifications should include request code, provider, branch, status, and a direct link to the request detail view.

---

## UI and Responsive Requirements

- Use responsive grids: full-width cards/forms on mobile, multi-column layout on tablet/desktop.
- Wrap list tables in `.table-responsive`.
- On mobile, show compact request cards with status, provider, branch, item counts, and primary action; keep full audit details in a modal/detail page.
- Use text labels with color swatches; ensure sufficient contrast for `White` by adding a border.
- Use status badges consistently: Draft, Pending Approval, Approved, Dispatched, Partially Received, Received, Disputed, Closed, Rejected, Cancelled.
- Disable destructive or invalid workflow actions and show the reason.
- Use confirmation dialogs for receiving, dispatching, closing, and adjustments.

---

## Implementation Phases

### Phase 0: Confirm business rules

Before coding, confirm:

1. Is stock physically held per branch, a central office, or both?
2. Does a request always come from another branch, or can it be directly supplied by the provider?
3. Are colors the only initial variants, and which providers require them?
4. Are ticket serial numbers or ranges mandatory for Cokaliong receiving and sale?
5. Does a cancelled/refunded ticket return to usable stock, or is it always voided?
6. Which roles may approve, dispatch, receive, adjust, and resolve discrepancies?
7. Are partial deliveries allowed and should missing quantities remain open for later receipt?
8. Does a branch need an approval threshold for high quantities?

### Phase 1: Schema and foundation

- Add all new tables, indexes, constraints, and `variant_id` references.
- Add new permissions and navigation registration.
- Add PHP helpers for status transitions, inventory balance updates, stock movement creation, and availability validation.
- Add migration validation and rollback guidance.

Acceptance criteria:

- No change to existing wallet amounts.
- A provider can have active or inactive variants.
- A branch has one balance projection per provider/variant.
- Every stock change produces a movement record.

### Phase 2: Provider variant management and stock balances

- Add controlled-stock settings and Ticket Variants UI to Ticket Providers.
- Add stock balance/ledger API and dashboard.
- Add controlled opening-balance adjustment flow.

Acceptance criteria:

- Cokaliong can be configured with White and Yellow variants.
- Each branch can show independent available quantities.
- Deactivated variants remain visible in history but are unavailable for new activity.

### Phase 3: Request, approval, dispatch, and receipt

- Build Ticket Stock Requests module.
- Implement workflow transitions, item-level quantities, receipt posting, and discrepancy handling.
- Implement activity logs, notifications, attachments if supported, and printable receiving acknowledgment.

Acceptance criteria:

- A request for 10 White and 20 Yellow can be submitted, approved, dispatched, and received.
- Receiver, branch, timestamp, and actual quantities are always recorded.
- Received quantities, not requested quantities, increase available stock.
- Partial and disputed receipts are traceable and cannot be silently overwritten.

### Phase 4: POS integration

- Add provider-dependent variant selector and availability display.
- Persist `variant_id` to ticket and order records.
- Atomically validate and deduct inventory on checkout.
- Add cancellation/refund inventory disposition and reversal handling.

Acceptance criteria:

- POS blocks White ticket sale when White stock is zero, even if Yellow stock is available.
- A Yellow sale decrements Yellow only.
- A failed payment/order saves neither wallet nor inventory change.
- A returned usable ticket restores its original provider/variant stock with a linked movement.

### Phase 5: Reporting, controls, and hardening

- Add inventory, movement, fulfillment, discrepancy, and POS variant reports with CSV export.
- Add low-stock alerts and overdue request monitoring.
- Add concurrent cashier testing, permission testing, branch isolation testing, and reconciliation tooling.

Acceptance criteria:

- Management can reconcile stock using movements and current balances.
- Users see only allowed branches/actions.
- Reports distinguish operating provider, ticket variant, wallet owner, and branch.

---

## Data Integrity and Security Requirements

- All inventory mutations use database transactions.
- Lock stock rows before validating and decrementing to prevent overselling.
- Validate branch access, provider access, request status, quantities, and reference ownership server-side.
- Enforce `allow_negative_ticket_stock` in POS, dispatch, receipt, and adjustment validations; when disabled, reject any transaction that would make a branch/provider/variant balance negative.
- Tie variant selection to the active service type via `service_types.requires_ticket_variant` (or a provider override); do not require `variant_id` for non-ticket service types or legacy providers without controlled variants.
- Require CSRF tokens for all mutating APIs.
- Decode/encode IDs using the project ID-encoding convention.
- Record activity logs for create, submit, approve, reject, dispatch, receive, discrepancy resolution, close, return, and adjustment actions.
- Never delete stock movements, historical variants, received request items, or sold ticket references.
- Use soft deactivation for variants and configuration records where appropriate.
- Preserve original quantities and statuses; corrections must be compensating movements with reason.

---

## Test Plan

### Configuration

- Create Cokaliong White and Yellow variants.
- Deactivate Yellow and confirm it cannot be added to new requests or selected in POS.
- Confirm historical Yellow records remain visible.

### Fulfillment lifecycle

- Submit a request for White 10 and Yellow 20.
- Approve White 10 and Yellow 15 as a partial approval.
- Dispatch all approved quantities.
- Receive White 10 and Yellow 13, with Yellow 2 damaged.
- Confirm inventory increases only by White 10 and Yellow 13.
- Confirm request becomes partial/disputed and creates discrepancy history.
- Resolve the discrepancy and close the request.

### Transfers

- Dispatch White stock from Branch A to Branch B.
- Confirm Branch A decreases at dispatch and Branch B does not increase before receipt.
- Confirm Branch B increases only at receipt.
- Reject duplicate receipt submission.

### POS

- Sell one White ticket and confirm only White branch stock decreases by one.
- Attempt sale of a zero-stock variant and confirm API and UI both block it.
- Use two concurrent checkout attempts against one remaining Yellow ticket and confirm only one succeeds.
- Cancel a sale as reusable and confirm a linked inventory reversal.
- Cancel a sale as void/damaged and confirm no usable stock return.

### Security and access

- Cashier sees only their branch and allowed provider variants.
- Receiver cannot receive for an unauthorized branch.
- Requester cannot self-approve by default.
- Manual adjustment requires the configured approval path.
- CSRF and encrypted-ID modes work for every new endpoint.

### Regression

- Providers without controlled variants continue to follow the existing POS and wallet flow.
- Existing wallet top-ups and financial reports remain unchanged.
- Existing historical tickets remain readable with no required variant value.

---

## Recommended Initial Delivery

Start with Cokaliong as the pilot provider:

1. Configure `White` and `Yellow` variants.
2. Create branch-level balances.
3. Deliver request-to-receipt tracking with full user, branch, and timestamp audit.
4. Integrate POS deduction for Cokaliong only when a variant is selected.
5. Add stock dashboard and movement report.
6. Expand controlled variants to other providers after pilot reconciliation passes.

This delivers the requested operational control while preserving existing provider wallet behavior and keeping the platform extensible for future ticket classifications.

---

## Post-Pilot Expansion

After the Cokaliong pilot is reconciled and management signs off:

1. **Extend controlled variants** to additional shipping providers that issue color-coded, class-coded, or route-coded tickets.
2. **Add serial number tracking** for providers that require per-ticket unique identifiers.
3. **Introduce barcode scanner support** for high-volume receiving and dispatch.
4. **Connect provider purchase orders** and accounts payable when accounting integration is ready.
5. **Build a central-warehouse option** for stock held at the head office before branch allocation.
6. **Expose inventory in the mobile POS** if ticket sales move to tablets or phones.

## Go-Live and Adoption

- Train branch supervisors on the request-receipt workflow and discrepancy handling.
- Provide cashiers with a quick reference for selecting variants at POS.
- Run a parallel stock count for Cokaliong branches before switching to live POS deduction.
- Set initial reorder levels based on one week of normal sales volume.
- Publish the inventory dashboard URL to branch operations and finance.
- Create an internal help article explaining why a ticket color must be selected when the provider is stock-controlled.

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Cashiers skip the variant selector or pick the wrong color | Server-side validation requires `variant_id`; block checkout if missing or invalid for controlled providers. |
| Stock and wallet balances drift | Daily reconciliation compares `branch_ticket_stocks` totals against the `ticket_stock_movements` running balance. |
| Two cashiers sell the same last ticket | Use `SELECT ... FOR UPDATE` inside the checkout transaction and reject if `available_qty` is insufficient. |
| Branch receives stock without proper authority | Receiving requires `RECEIVE_TICKET_STOCK` permission and destination branch assignment. |
| Historical reports break after schema change | Keep `variant_id` nullable and backfill only by explicit mapping; preserve legacy transactions. |
| Discrepancy records accumulate unresolved | Set escalation alerts and require a resolution reason before a request can be closed with an open discrepancy. |
| Deactivated variants lose display context | Keep display name and color in movement records; never overwrite historical references. |

## Operational Runbook

### Daily

- Review the low-stock dashboard.
- Verify that all `DISPATCHED` requests have been received or marked as in-transit.
- Investigate any request stuck in `PARTIALLY_RECEIVED` or `DISPUTED` for more than 24 hours.

### Weekly

- Reconcile physical branch counts against the system balances.
- Run the movement ledger report and investigate unmatched balances.
- Review the fulfillment report for lead times and fill rates.

### Monthly

- Review the discrepancy rate by provider, variant, and branch.
- Adjust reorder levels and approval thresholds based on actual usage.
- Archive or export completed requests and supporting documents according to retention policy.

## Design Decisions

The following business questions were resolved and are now reflected in the design:

1. **Negative stock balance**: Allowed only when the system-wide setting `allow_negative_ticket_stock` is enabled in **System Settings > Ticket Stock**. Default is disabled to prevent overselling.
2. **Default approver fallback**: If the branch manager is unavailable, a stock request can be approved by any user with `APPROVE_TICKET_STOCK_REQUEST` permission for the destination branch, then by a user with `VIEW_ALL_TICKET_STOCK` permission, then by a `SUPER_ADMIN`. If none are available, the request stays `SUBMITTED` and an escalation notification is sent.
3. **Damaged tickets**: Flexible disposition at receipt or cancellation. The receiver/canceller chooses whether to return to provider, write off internally, replace with an equivalent variant, or (for usable returns) restore to branch stock.
4. **Provider stock issuance**: New stock can be issued directly to the head office/central branch, directly to a branch, or both. `source_branch_id` may be `NULL` for external/provider-issued stock or set to the head-office branch.
5. **POS variant selector**: Mandatory only for service types flagged `requires_ticket_variant`. Other service types and providers without controlled variants continue to use the existing POS flow.
6. **Returned ticket number range check**: Before a cancelled ticket is restored to usable stock, the system verifies that the returned ticket number matches the original sold ticket number or the series/range recorded on the original `ticket_transactions` / receipt line. This prevents invalid or swapped tickets from re-entering inventory. If the number cannot be verified, the ticket is treated as `DAMAGE_OR_VOID` or returned to the provider rather than restored to stock.
