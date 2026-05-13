# Module Templates

Quick-start templates para sa paggawa ng bagong admin modules na may consistent structure at working Settings panel.

## Files

| File | Purpose |
|------|---------|
| `module-controller.php` | Controller template (`index.php`) |
| `module-view.php` | View template (`views/index.php`) |
| `module-guard.php` | Category guard template (`_guard.php`) |
| `module-styles.css` | CSS template (`assets/css/<module>.css`) |
| `module-scripts.js` | JS template (`assets/js/<module>.js`) |

## Quick Start: Creating a New Module

### Step 1: Create folder structure

```bash
# From project root
mkdir -p admin/<category>/<module-name>/views/modals
mkdir -p admin/<category>/<module-name>/assets/css
mkdir -p admin/<category>/<module-name>/assets/js
```

### Step 2: Copy templates

```bash
# Controller
cp docs/templates/module-controller.php admin/<category>/<module-name>/index.php

# View
cp docs/templates/module-view.php admin/<category>/<module-name>/views/index.php

# Guard (only if this is a new CATEGORY folder, e.g., admin/reports/)
cp docs/templates/module-guard.php admin/<category>/_guard.php

# Assets
cp docs/templates/module-styles.css admin/<category>/<module-name>/assets/css/<module-name>.css
cp docs/templates/module-scripts.js admin/<category>/<module-name>/assets/js/<module-name>.js
```

### Step 3: Replace placeholders

Search and replace these in ALL copied files:

| Placeholder | Replace with |
|-------------|--------------|
| `<ModuleName>` | Human-readable module name (e.g., "Wallet Transactions") |
| `<module-name>` | URL-friendly name (e.g., "wallet-transactions") |
| `<category>` | Category folder (e.g., "wallet", "settings", "reports") |
| `<PERMISSION>` | Permission code (e.g., "VIEW_WALLET_TRANSACTIONS") |

### Step 4: Adjust guard path in controller

In `admin/<category>/<module-name>/index.php`, set the correct guard:

```php
// If module is directly under admin/ (no category subfolder):
require_once dirname(__DIR__) . '/_guard.php';

// If module is under a category (e.g., admin/wallet/<module>/):
require_once dirname(__DIR__) . '/_guard.php';  // uses admin/wallet/_guard.php
```

### Step 5: Update sidebar link

Add your new module to `admin/includes/sidebar.php` so it appears in the navigation.

## Example: Creating "Customer Reports"

```bash
mkdir -p admin/reports/customer-reports/views/modals
mkdir -p admin/reports/customer-reports/assets/css
mkdir -p admin/reports/customer-reports/assets/js

cp docs/templates/module-controller.php admin/reports/customer-reports/index.php
cp docs/templates/module-view.php admin/reports/customer-reports/views/index.php
cp docs/templates/module-guard.php admin/reports/_guard.php
cp docs/templates/module-styles.css admin/reports/customer-reports/assets/css/customer-reports.css
cp docs/templates/module-scripts.js admin/reports/customer-reports/assets/js/customer-reports.js
```

Then replace placeholders in all files:
- `<ModuleName>` → "Customer Reports"
- `<module-name>` → "customer-reports"
- `<category>` → "reports"
- `<PERMISSION>` → "VIEW_CUSTOMER_REPORTS"

## Important Reminders

1. **Always include the guard chain** → `admin/_guard.php` must be in the chain so `NAVBAR_POSITION` works
2. **Never add `define('NAVBAR_POSITION', 'vertical')`** in views → it breaks the Settings panel
3. **Always include `footer.php` and `scripts.php`** at the bottom of views
4. **Test the Settings panel** after creating a module: change Navigation Position to "Top" and verify it works

## Working Examples

Copy from these if you need a complete working reference:

- `admin/settings/role-dashboards/` — Cleanest example
- `admin/wallet/wallet-transactions/` — Full example with data tables
