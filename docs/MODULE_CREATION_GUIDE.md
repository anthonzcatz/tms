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
      </script>

      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
      <?php endif; ?>

      <div class="content">
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
        ?>

        <!-- YOUR PAGE CONTENT HERE -->

      </div>
    </div>
  </main>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
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

## 8. Maintenance Mode

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

## 9. Checklist for New Modules

Before finishing a new module, verify:

- [ ] Controller includes the correct guard chain
- [ ] Category `_guard.php` (if applicable) includes global `admin/_guard.php`
- [ ] View does **NOT** have `define('NAVBAR_POSITION', 'vertical')` fallback
- [ ] View includes `sidebar.php`, `navbar-top.php`, `navbar-double-top.php`, `navbar.php` based on `NAVBAR_POSITION`
- [ ] View includes `footer.php` and `scripts.php` at the bottom
- [ ] Settings panel styles are applied (optional but recommended)
- [ ] Test: change Navigation Position to "Top" → page reloads → top navbar shows
- [ ] Test: change back to "Vertical" → page reloads → sidebar shows

---

## 10. Existing Module Patterns

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
