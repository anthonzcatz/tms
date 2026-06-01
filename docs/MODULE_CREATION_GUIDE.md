# Module Creation Guide

## Overview

This guide ensures all new admin modules follow the same structural pattern so that:
- The **Settings panel** (customize/styles) works correctly across all pages
- `NAVBAR_POSITION` is respected (vertical, top, combo, double-top)
- No broken guard chains or missing includes
- Consistent file organization and low risk of regression

---

## 1. Directory Structure

Create your module under `admin/<category>/<module-name>/`:

```
admin/<category>/<module-name>/
├── index.php              # Controller / entry point
├── _guard.php             # Module guard (if sub-module of a category)
├── views/
│   ├── index.php          # Main view
│   ├── modals/            # Optional: modal partials
│   └── partials/          # Optional: reusable view fragments
├── assets/
│   ├── css/
│   │   └── <module>.css   # Module-specific styles
│   └── js/
│       └── <module>.js    # Module-specific scripts
```

**Examples:**
- `admin/settings/role-dashboards/`
- `admin/wallet/wallet-transactions/`
- `admin/wallet/provider-wallets/`

---

## 2. Controller (`index.php`)

### A. If your module is directly under `admin/` (e.g., `admin/dashboard/`)

Include the global admin guard:

```php
<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/admin/_guard.php';

// Your controller logic here...

include __DIR__ . '/views/index.php';
```

### B. If your module is under a category (e.g., `admin/wallet/<module>/`)

Include the **category guard**, which in turn includes the global admin guard:

```php
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(__DIR__) . '/_guard.php';  // category guard (e.g., admin/wallet/_guard.php)

// Your controller logic here...

include __DIR__ . '/views/index.php';
```

> **Never skip the guard chain.** The guard is what sets `NAVBAR_POSITION` from the user's session.

---

## 3. Category Guard (`admin/<category>/_guard.php`)

If you create a new category folder (e.g., `admin/reports/`), create a `_guard.php` inside it that **includes the global admin guard first**:

```php
<?php
/**
 * <Category> Module Guard
 */

require_once dirname(__DIR__) . '/_guard.php';  // Global admin/_guard.php

$user = Auth::user();

if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::can('VIEW_<PERMISSION>')) {
    $message = 'You do not have permission to access the <Category> module.';
    include dirname(__DIR__) . '/includes/access-denied.php';
    exit;
}
```

> **Critical:** `require_once dirname(__DIR__) . '/_guard.php';` ensures `NAVBAR_POSITION` is defined.
> 
> **⚠️ IMPORTANT:** Always use `include dirname(__DIR__) . '/includes/access-denied.php';` for the access-denied page. Do NOT use `dirname(dirname(__DIR__)) . '/includes/access-denied.php'` as this will cause a "file not found" error.

---

## 4. View (`views/index.php`)

### Required structure:

```php
<?php
require_once dirname(dirname(dirname(__DIR__))) . '/includes/head.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/<category>/<module>/assets/css/<module>.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/<module>.css'); ?>">

<!-- Optional: scoped Settings panel styles -->
<style>
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"] {
    min-width: 240px;
    border-radius: 6px;
    border-color: #d0d5dd;
    background-color: #ffffff;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"]:hover {
    border-color: #b6beca;
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"]:focus {
    border-color: #84c5f4;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"] option {
    font-size: 0.9rem;
  }
</style>

<body>
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) {
          var container = document.querySelector('[data-layout]');
          container.classList.remove('container');
          container.classList.add('container-fluid');
        }
      </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php';
                break;
            case 'top':
            case 'double-top':
            default:
                break;
        }
        ?><?php endif; ?>

        <!-- Header Card (Standard Pattern) -->
        <div class="row g-4 mb-4">
          <div class="col-12">
            <div class="card border-0 shadow-sm mb-4">
              <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-bg.png);"></div>
              <div class="card-header z-1">
                <div class="row flex-between-center gx-0">
                  <div class="col-lg-auto d-flex align-items-center">
                    <img class="img-fluid" style="max-height: 60px; max-width: 60px; object-fit: contain;" src="<?php echo BASE_URL; ?>/resources/assets/img/illustrations/reports-greeting.png" alt="" />
                    <div class="ms-x1">
                      <h4 class="mb-0 text-primary fw-bold">Module <span class="text-info fw-medium">Title</span></h4>
                      <h6 class="mb-1 text-primary">
                        <nav aria-label="breadcrumb">
                          <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a>Home</a></li>
                            <li class="breadcrumb-item active">Module Name</li>
                          </ol>
                        </nav>
                      </h6>
                    </div>
                  </div>
                  <div class="col-lg-auto d-flex gap-2">
                    <!-- Optional: Action buttons here -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- YOUR PAGE CONTENT HERE -->

      </div>
    </div>
  </main>

  <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
  </div>
  <?php endif; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/body-top.php'; ?>
</body>
</html>
```

### ⚠️ DO NOT add this:

```php
// ❌ WRONG - This overrides the session-based NAVBAR_POSITION
if (!defined('NAVBAR_POSITION')) {
    define('NAVBAR_POSITION', 'vertical');
}
```

> **Why:** The global `admin/_guard.php` already defines `NAVBAR_POSITION` from `$_SESSION['navbarPosition']`. Adding this fallback forces "vertical" on every page load, breaking the Settings panel.

### ⚠️ IMPORTANT: Sticky Navbar Positioning

The navbar structure pattern above (with conditional includes and no whitespace between script and navbar) is critical for **sticky positioning** to work correctly when `NAVBAR_POSITION` is set to `top` or `double-top`.

**CSS Requirements:**
The following CSS is already in `resources/assets/css/user.css` and must be present:

```css
.navbar-top {
  position: sticky !important;
  top: 0 !important;
  z-index: 1030 !important;
}

.container[data-layout="container"] {
  overflow: visible !important;
}
```

**Why this structure matters:**
- No whitespace between `</script>` and navbar include prevents layout shift
- Navbar is placed directly after the script block for top/double-top layouts
- The conditional `div.content` wrapper only wraps content for vertical/combo layouts
- This allows the navbar to be sticky within the container viewport

---

## 5. How the Settings Panel Works

When a user changes **Navigation Position** in the Settings offcanvas:

1. User selects a value in the Settings panel dropdown
2. `theme.js` (line ~4080) detects the change event on `[data-theme-control="navbarPosition"]`
3. Stores the value in `localStorage.navbarPosition`
4. **Reloads the page with `?layout=<value>` parameter** (this is critical!)
5. `admin/_guard.php` detects `?layout=` parameter and saves it to `$_SESSION['navbarPosition']`
6. `admin/_guard.php` defines the PHP constant `NAVBAR_POSITION` from session
7. The view includes the correct navbar based on `NAVBAR_POSITION`

**Flow:**
```
User selects "Top" in Settings
  ↓
theme.js → localStorage.setItem('navbarPosition', 'top')
  ↓
theme.js → window.location.replace(currentUrl + '?layout=top')
  ↓
admin/_guard.php → $_SESSION['navbarPosition'] = 'top'
  ↓
admin/_guard.php → define('NAVBAR_POSITION', 'top')
  ↓
View includes navbar-top.php instead of sidebar.php
```

**Important:** The `?layout=` parameter is what syncs localStorage with the PHP session. Without it, the page would reload but PHP would still use the old session value.

---

## 6. Common Pitfalls to Avoid

### ❌ Wrong access-denied.php path

**Wrong:**
```php
include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
// This resolves to: c:\xampp\htdocs\TMS\includes/access-denied.php (WRONG - file doesn't exist)
```

**Correct:**
```php
include dirname(__DIR__) . '/includes/access-denied.php';
// This resolves to: c:\xampp\htdocs\TMS\admin\includes/access-denied.php (CORRECT)
```

### ❌ Missing index.php in directory

If you create a directory like `admin/user/` without an `index.php`, Apache will return a 403 Forbidden error. Always create an `index.php` file that either:
- Contains the page content, or
- Redirects to the appropriate page (e.g., `header('Location: ' . BASE_URL . '/admin/user/profile.php');`)

### ❌ Filtering transactions by cashier_id instead of session_id

When displaying transactions in Close Cashier Session modal or Shifts reports:
- **Wrong:** Filter by `created_by` (cashier user ID) - this shows transactions from all sessions of that cashier
- **Correct:** Filter by `cashier_session_id` (session ID) - this shows only transactions from the specific session

**Example:**
```php
// ❌ WRONG - shows all transactions from this cashier
WHERE tp.created_by = :uid

// ✅ CORRECT - shows only transactions from this session
WHERE tp.cashier_session_id = :sid
```

---

## 7. Maintenance Mode

The system has a global maintenance mode feature that can be configured in `admin/system-settings/`. When maintenance mode is active, non-admin users see a maintenance page instead of accessing admin pages.

### How Maintenance Mode Works

1. **Configuration:** Admins can enable maintenance mode in System Settings with:
   - Enable/Disable toggle
   - Custom maintenance message
   - Start and end time window
   - Option to allow admin access during maintenance

2. **Global Guard Check:** The `admin/_guard.php` checks maintenance mode on every admin page load:
   - Fetches maintenance settings from database (cached for 5 minutes for performance)
   - Checks if current time is within maintenance window
   - Auto-disables maintenance mode when end time passes
   - Shows maintenance page for non-admin users or if admin access is disabled

3. **Admin Bypass:** SUPER_ADMIN and ADMIN roles can access the system during maintenance if "Allow Admin Access During Maintenance" is enabled.

### Maintenance Page Features

The global maintenance page (`admin/includes/maintenance.php`) includes:
- Clean UI with tools icon
- Custom maintenance message
- Start and end time display
- Realtime countdown timer (updates every second)
- Refresh page button
- Contact support button (if admin access is allowed)

### System Settings Notification

When maintenance mode is active, the System Settings page displays a notification banner:
- Shows "Maintenance Mode is Active" warning
- Includes realtime countdown timer
- Cannot be dismissed (always visible during maintenance)
- Helps admins stay aware of active maintenance

### Database Storage

Maintenance settings are stored in the `system_settings` table:
- `maintenance_mode` - Enable/Disable (0 or 1)
- `maintenance_message` - Custom message
- `maintenance_start` - Start datetime
- `maintenance_end` - End datetime
- `allow_admin_during_maintenance` - Allow admin access (0 or 1)

### Performance Optimization

Maintenance settings are cached in session for 5 minutes to avoid database queries on every page load. The cache is automatically:
- Cleared when settings are updated
- Cleared when maintenance end time passes
- Cleared when accessing System Settings page

---

## 8. ID Encryption

The system supports ID encryption for security. When enabled, database IDs in URLs are encrypted to prevent direct ID enumeration attacks. This feature can be toggled in System Settings → Security tab.

### How ID Encryption Works

1. **Configuration:** Admins can enable/disable ID encryption in System Settings
2. **Toggle Control:** When enabled, all API endpoints expect encrypted IDs
3. **Backward Compatibility:** When disabled, plain numeric IDs work for development/debugging
4. **Automatic Handling:** The `IdEncoder` helper handles both encrypted and plain IDs

### Implementation in API Endpoints

All API endpoints that receive ID parameters must decode them using `IdEncoder::decode()`:

```php
<?php
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';

// Decode ID parameter
$id = $_GET['id'] ?? null;
if ($id) {
    $decodedId = IdEncoder::decode($id);
    if ($decodedId === false) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        return;
    }
    $id = $decodedId;
}
```

**Important:** Always check if `IdEncoder::decode()` returns `false` (invalid ID) and return an error response.

### Implementation in JavaScript

All JavaScript files that make API calls with IDs must encode them using `IdEncoder.encode()`:

```javascript
// Encode ID before sending to API
const encodedId = IdEncoder.encode(id);
const response = await fetch(`${window.BASE_URL}/api/endpoint?id=${encodedId}`);
```

**Note:** The `IdEncoder` helper is available globally in `resources/assets/js/id-encoder.js` and is included in the main scripts.

### Common ID Parameters

These are the most common ID parameters that need encoding/decoding:
- `id` - Generic ID (user, role, branch, etc.)
- `user_id` - User account ID
- `branch_id` - Business branch ID
- `provider_id` - Ticket provider ID
- `wallet_id` - Provider wallet ID
- `passenger_id` - Passenger account ID
- `session_id` - Cashier session ID
- `device_id` - System device ID
- `permission_id` - Permission ID
- `role_id` - User role ID
- `payment_method_id` - Payment method ID
- `bank_account_id` - Bank account ID

### Example: Complete API Endpoint with ID Decoding

```php
<?php
header('Content-Type: application/json');
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/IdEncoder.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';

Auth::requireLogin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    // Decode ID if provided
    if ($id) {
        $decodedId = IdEncoder::decode($id);
        if ($decodedId === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            return;
        }
        $id = $decodedId;
    }

    // Use the decoded ID in your query
    $result = Database::fetch("SELECT * FROM table WHERE id = :id", ['id' => $id]);
    echo json_encode(['success' => true, 'data' => $result]);
}
```

### Example: Complete JavaScript with ID Encoding

```javascript
// Fetch single item
async function fetchItem(id) {
    const encodedId = IdEncoder.encode(id);
    const response = await fetch(`${window.BASE_URL}/api/endpoint?id=${encodedId}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    });
    const result = await response.json();
    return result;
}

// Delete item
async function deleteItem(id) {
    const encodedId = IdEncoder.encode(id);
    const response = await fetch(`${window.BASE_URL}/api/endpoint?id=${encodedId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': getCSRFToken() },
        credentials: 'same-origin'
    });
    const result = await response.json();
    return result;
}
```

### Testing ID Encryption

To test ID encryption:
1. Enable "ID Encryption" in System Settings → Security tab
2. Reload the page
3. All API calls should now use encrypted IDs
4. Disable the toggle to test with plain IDs
5. Both modes should work without errors

---

## 9. Responsive Design & Mobile Compatibility

All admin modules must be fully responsive and work seamlessly across all device sizes, from desktop to mobile. The system uses Bootstrap 5's responsive grid system.

### Bootstrap Grid System

Use Bootstrap's 12-column grid with responsive breakpoints:

```php
<div class="row g-3">
  <!-- On mobile: full width (12 cols), On tablet: half (6 cols), On desktop: quarter (3 cols) -->
  <div class="col-12 col-md-6 col-lg-3">
    <label class="form-label">Field Label</label>
    <input type="text" class="form-control" name="field_name">
  </div>
</div>
```

**Breakpoints:**
- `col-` or `col-xs-` - Extra small (<576px) - Mobile phones
- `col-sm-` - Small (≥576px) - Large phones
- `col-md-` - Medium (≥768px) - Tablets
- `col-lg-` - Large (≥992px) - Desktops
- `col-xl-` - Extra large (≥1200px) - Large desktops

### Responsive Tables

Tables must be wrapped in `.table-responsive` for horizontal scrolling on small screens:

```php
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead>
      <tr>
        <th>Column 1</th>
        <th>Column 2</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      <!-- Table rows -->
    </tbody>
  </table>
</div>
```

**Mobile table best practices:**
- Use `.table-sm` for smaller padding on mobile
- Limit columns to essential information on mobile
- Consider hiding less important columns on mobile using `.d-none .d-md-table-cell`
- Use `.text-nowrap` on columns with short content to prevent wrapping

### Responsive Forms

Forms should use responsive grid layouts:

```php
<div class="row g-3">
  <!-- Full width on mobile, half on tablet+ -->
  <div class="col-12 col-md-6">
    <label class="form-label">Field 1</label>
    <input type="text" class="form-control" name="field1">
  </div>
  <div class="col-12 col-md-6">
    <label class="form-label">Field 2</label>
    <input type="text" class="form-control" name="field2">
  </div>
</div>
```

**Form field sizing:**
- Use `.form-control-lg` for larger touch targets on mobile
- Use `.form-select-sm` for compact selects in tight spaces
- Ensure minimum 44px height for touch targets on mobile

### Responsive Navigation

The system's sidebar/navbar automatically adapts to screen size:
- Desktop: Full sidebar with icons and text
- Tablet: Collapsed sidebar (icons only)
- Mobile: Offcanvas sidebar with hamburger menu

**No custom mobile navigation needed** - the global layout handles this automatically.

### Responsive Cards

Cards should use flexible layouts:

```php
<div class="card">
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <!-- Stack vertically on mobile, horizontally on tablet+ -->
      <div class="col-12 col-md-auto">
        <img src="..." class="img-fluid" style="max-width: 100px;">
      </div>
      <div class="col">
        <h5 class="card-title">Title</h5>
        <p class="card-text">Description</p>
      </div>
    </div>
  </div>
</div>
```

### Responsive Buttons

Button groups should wrap on small screens:

```php
<div class="d-flex gap-2 flex-wrap">
  <button type="button" class="btn btn-primary">Action 1</button>
  <button type="button" class="btn btn-secondary">Action 2</button>
  <button type="button" class="btn btn-outline-primary">Action 3</button>
</div>
```

**Button sizing:**
- Use `.btn-sm` for compact buttons in tables
- Use standard size for primary actions
- Consider icon-only buttons on mobile with tooltips

### Mobile-Specific Considerations

**Hide elements on mobile:**
```php
<!-- Hidden on mobile, visible on tablet+ -->
<div class="d-none d-md-block">Desktop-only content</div>

<!-- Visible on mobile, hidden on tablet+ -->
<div class="d-md-none">Mobile-only content</div>
```

**Text sizing:**
- Use `.fs-10` (smaller font) for tables on mobile
- Use `.text-truncate` to truncate long text with ellipsis
- Limit text length in tables on mobile

**Touch targets:**
- Minimum 44x44px for buttons and links
- Adequate spacing between clickable elements
- Use `.form-control-lg` for better touch input

### Testing Responsive Design

Test your module on:
1. **Desktop** (1920x1080) - Full layout
2. **Laptop** (1366x768) - Compact layout
3. **Tablet** (768x1024) - Stacked layout
4. **Mobile** (375x667) - Single column layout

**Browser DevTools:**
- Use Chrome DevTools device emulation
- Test in landscape and portrait modes
- Check touch interactions work properly

### Common Responsive Patterns

**Header card with actions:**
```php
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="row align-items-center g-3">
          <div class="col-12 col-md">
            <h4 class="mb-0">Module Title</h4>
          </div>
          <div class="col-12 col-md-auto">
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-primary">Action</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

**Filter bar:**
```php
<div class="card mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-12 col-md-4 col-lg-3">
        <input type="text" class="form-control" placeholder="Search...">
      </div>
      <div class="col-12 col-md-4 col-lg-3">
        <select class="form-select">Filter...</select>
      </div>
      <div class="col-12 col-md-4 col-lg-3">
        <input type="date" class="form-control">
      </div>
      <div class="col-12 col-md-auto col-lg-3">
        <button class="btn btn-primary w-100">Apply</button>
      </div>
    </div>
  </div>
</div>
```

### Responsive Images

Always use `.img-fluid` for responsive images:

```php
<img src="..." class="img-fluid" alt="...">
```

This ensures images scale to their container width without overflowing.

---

## 10. Checklist for New Modules

Before finishing a new module, verify:

- [ ] Controller includes the correct guard chain
- [ ] Category `_guard.php` (if applicable) includes global `admin/_guard.php`
- [ ] View does **NOT** have `define('NAVBAR_POSITION', 'vertical')` fallback
- [ ] View includes `sidebar.php`, `navbar-top.php`, `navbar-double-top.php`, `navbar.php` based on `NAVBAR_POSITION`
- [ ] View includes `footer.php` and `scripts.php` at the bottom
- [ ] Settings panel styles are applied (optional but recommended)
- [ ] **Access-denied path is correct:** `dirname(__DIR__) . '/includes/access-denied.php'` NOT `dirname(dirname(__DIR__)) . '/includes/access-denied.php'`
- [ ] **Directory has index.php** to avoid Apache 403 Forbidden errors
- [ ] **Transaction filters use session_id** (not cashier_id) if displaying session-specific data
- [ ] **API endpoints include IdEncoder.php and decode all ID parameters**
- [ ] **JavaScript files encode all IDs using IdEncoder.encode() before API calls**
- [ ] **All tables are wrapped in `.table-responsive` for mobile scrolling**
- [ ] **Forms use responsive grid classes (col-12, col-md-6, etc.)**
- [ ] **Buttons use `.flex-wrap` to wrap on small screens**
- [ ] **Images use `.img-fluid` for responsive scaling**
- [ ] **Touch targets are at least 44x44px on mobile**
- [ ] Test: change Navigation Position to "Top" → page reloads → top navbar shows
- [ ] Test: change back to "Vertical" → page reloads → sidebar shows
- [ ] Test: enable ID Encryption → all API calls still work with encrypted IDs
- [ ] Test: disable ID Encryption → all API calls still work with plain IDs
- [ ] Test: mobile view (375px width) → layout stacks properly, no horizontal overflow
- [ ] Test: tablet view (768px width) → grid adjusts correctly
- [ ] Test: desktop view (1920px width) → full layout displays properly

---

## 11. Existing Module Patterns

| Module | Guard Chain | NAVBAR_STATUS |
|--------|-------------|---------------|
| `admin/settings/role-dashboards/` | `admin/_guard.php` directly | ✅ Works |
| `admin/wallet/wallet-transactions/` | `admin/wallet/_guard.php` → `admin/_guard.php` | ✅ Fixed |
| `admin/wallet/provider-wallets/` | `admin/wallet/_guard.php` → `admin/_guard.php` | ✅ Fixed |
| `admin/wallet/provider-service-fees/` | `admin/wallet/_guard.php` → `admin/_guard.php` | ✅ Fixed |

---

## 11. Quick Template

Copy this folder structure when creating a new module:

```bash
# From project root
mkdir -p admin/<category>/<module-name>/views/modals
mkdir -p admin/<category>/<module-name>/assets/css
mkdir -p admin/<category>/<module-name>/assets/js

# Create files (copy from an existing working module, then customize)
touch admin/<category>/<module-name>/index.php
touch admin/<category>/<module-name>/views/index.php
touch admin/<category>/<module-name>/assets/css/<module-name>.css
touch admin/<category>/<module-name>/assets/js/<module-name>.js
```

> **Recommended:** Copy from `admin/settings/role-dashboards/` as the base template — it's the cleanest working example.

---

**Last updated:** May 15, 2026
**Maintained by:** Development Team
