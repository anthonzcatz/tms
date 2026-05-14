## Falcon, a theme by ThemeWagon team.

---
Unzip the **Falcon-v3.26.0.zip** to any folder and open a command line or terminal at that location. theme's dev tools require [Node](https://nodejs.org/en/) and [Git](https://git-scm.com/) . If you do not have them in your machine, please install their latest stable version from their corresponding website. As you have **Node and Git installed and accessible from your terminal or command line**, install [Gulp CLI](https://gulpjs.com/) package globally with the following command:

```
npm i gulp-cli -g
```

When you’re done, install the rest of the theme’s dependencies with:

```
npm i
```

Now run:

```
gulp
```

Running gulp will compile the SCSS, transpile the javascript, copy all required libraries form `node_modules`
to the corresponding `public/assets/vendors` directory and will open a browser window to `public/index.html`

All of the following folders are monitored for changes, which will tell the browser to reload automatically after any changes are made:

```
public/assets/fonts/
public/assets/video/
public/assets/img/
public/vendors
src/pug/
src/scss/
src/js/
```

Now you can edit any pug file from `src/pug`, change SCSS variable with `scss/\_user-variables.scss`, or write your own SCSS code in `scss/\_user.scss` and add or update javaScript from `src/js` directory.

**Running the gulp command will discard and regenerate all the files in following directories:**

```
public/**/*.html
public/assets/css/
public/assets/js/
public/vendors
```

Hit **Ctrl+C** or just close the command line window to stop the server.

Happy editing!



Core: user_accounts, user_roles, user_sessions, permissions, role_permissions

PSGC: psgc_regions, psgc_provinces, psgc_cities_municipalities, psgc_barangays

Business: business_branches, employees, department, position, employment_status, sub_department

Ticketing: ticket_providers, ticket_transactions, ticket_adjustments, accommodation_types, discount_types

Wallets: provider_wallets, wallet_transactions, provider_service_fees

Payments: payment_methods, bank_accounts, transaction_payments, charge_payments, charge_payment_allocations, customer_charges

Services: service_types, service_transactions

Cashier: cashier_sessions, cashier_session_details

System: system_devices, system_settings, system_maintenance_logs, activity_logs

Passenger: passenger_accounts


## Module Roadmap

### Phase 1: Settings / Lookup Modules
- [x] Payment Methods (admin/settings/payment-methods/) — Manage CASH, GCash, Bank Transfer, etc. Add/edit/disable methods
- [x] Bank Accounts (admin/settings/bank-accounts/) — Manage company bank accounts per branch
- [x] Service Types (admin/settings/service-types/) — Manage Print Fee, Photocopy, etc.

### Phase 2: Operational Modules
- [x] Cashier POS (admin/pos/) — Main checkout screen: sell tickets, print fees, accept mixed payments, track cashier session
- [x] Bank Transfer Confirmations (admin/bank-confirmations/) — Manager confirms bank transfers from cashiers
- [x] Customer Charges (Utang) (admin/charges/) — View customer balances, accept payments, track collections
- [x] Cashier Shift Reports (admin/shifts/) — Daily reconciliation, variance reports per cashier

## Module Creation Guidelines

When creating new admin modules, follow these guidelines for permission handling and access control:

### 1. Use the Global Access Denied Page
Always use the global access denied page located at `admin/includes/access-denied.php`. Do not create custom access denied pages for individual modules.

**Correct path for access-denied.php:**
- From `admin/module-name/index.php`: `include dirname(__DIR__) . '/includes/access-denied.php';`
- From `admin/module-name/submodule/index.php`: `include dirname(dirname(__DIR__)) . '/includes/access-denied.php';`
- From `admin/settings/module-name/index.php`: `include dirname(dirname(__DIR__)) . '/includes/access-denied.php';`

### 2. Use Flexible Permission System
Use the `Auth::canAccessModule()` method to check permissions based on the `menu_url` field from the `permissions` table. Do not use hardcoded permission codes.

**Example:**
```php
$user = Auth::user();
// SUPER_ADMIN has access to everything
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/your-module/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}
```

### 3. Permission Database Setup
When adding a new module:
1. Add a permission record to the `permissions` table with the `menu_url` field set to the module's URL (e.g., `admin/your-module/`)
2. Assign the permission to roles using the `role_permissions` table
3. The `Auth::canAccessModule()` method will automatically check if the user's role has the permission for that `menu_url`

### 4. SUPER_ADMIN Bypass
The `SUPER_ADMIN` role automatically has access to all modules regardless of permissions. The `canAccessModule()` method handles this automatically.

