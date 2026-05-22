# Time Restrictions — Implementation Roadmap

## What It Does
Limits when specific user accounts are allowed to log in.
Admins set allowed days of week and a start/end time window per user.
Attempts outside the window are denied with a clear message and logged for audit.

---

## ✅ Phase 1 — Core Enforcement (DONE)

| # | File | Change |
|---|------|--------|
| 1 | `app/models/User.php` | `checkTimeRestrictions()` — validates day + time window, supports overnight windows (e.g. 22:00–06:00), timezone-aware |
| 1 | `app/models/User.php` | `logTimeRestrictionViolation()` — writes denied attempt to `time_restriction_logs` |
| 2 | `app/controllers/AuthController.php` | Calls `checkTimeRestrictions()` after password verify, before `Auth::login()` |
| 3 | `database/migrations/add_time_restriction_logs.sql` | Creates `time_restriction_logs` table with FK to `user_accounts` |

### Run this migration:
```sql
SOURCE database/migrations/add_time_restriction_logs.sql;
```

---

## ✅ Phase 2 — Admin UI Polish (DONE)

| # | File | Change |
|---|------|--------|
| 4 | `admin/settings/users/assets/js/users.js` | `applyTimeRestrictionToggle()` — dims/enables time fields + clears values when toggled off |
| 5 | `admin/settings/users/assets/js/users.js` | `saveUser()` — explicitly sends `null` for start/end/days when restriction is disabled, preventing stale DB values |
| 5 | `admin/settings/users/assets/js/users.js` | `openEditUserModal()` — calls `applyTimeRestrictionToggle()` with loaded user state |
| 6 | `admin/settings/users/assets/js/users.js` | User list card shows **Time Restricted** badge (yellow, clock icon) when active |

---

## 🔲 Phase 3 — Timezone Support (Future)

**Goal:** Each user or branch can have its own timezone so restrictions apply to
local time, not server time.

**Steps:**
1. Add `timezone` column to `user_accounts` (or `business_branches`):
   ```sql
   ALTER TABLE user_accounts ADD COLUMN timezone VARCHAR(60) NULL DEFAULT NULL;
   ```
2. Add a timezone selector in the user wizard Step 5 (use a `<select>` populated from PHP `DateTimeZone::listIdentifiers()`).
3. Pass `$user['timezone']` to `User::checkTimeRestrictions($user, $user['timezone'])` in `AuthController`.
4. The method already accepts a `$timezone` parameter — **no model changes needed**.

---

## 🔲 Phase 4 — Admin Violation Log Viewer (Future)

**Goal:** SUPER_ADMIN can see who was blocked, when, and from what IP.

**Steps:**
1. Create `admin/settings/users/views/time-restriction-logs.php` — table view.
2. Create `api/users/time-restriction-logs.php` — GET endpoint, paginated, filterable by user/date.
3. Add a **"View Logs"** button on the user list page that opens a modal/drawer.

---

## 🔲 Phase 5 — Notification on Blocked Attempt (Future)

**Goal:** Notify admin or the user themselves when a login is blocked.

**Steps:**
1. In `User::logTimeRestrictionViolation()`, optionally dispatch an email via `EmailService`.
2. Add a `notify_on_block` flag per user (tinyint in `user_accounts`) to opt-in.
3. Throttle notifications (e.g. max 1 email per user per hour) using the existing `time_restriction_logs` table.

---

## 🔲 Phase 6 — Lock Active Sessions Outside Window (Future)

**Goal:** If a user is already logged in but their allowed window expires, terminate the session.

**Steps:**
1. Add a background job or extend `Auth::requireLogin()`:
   ```php
   $user = User::findById(Auth::id());
   $err  = User::checkTimeRestrictions($user);
   if ($err) { Auth::logout(); redirect to login with $err message; }
   ```
2. Consider calling this only every N minutes (cache last-checked time in session) to avoid a DB hit on every page load.

---

## Security Notes

- Restriction check runs **after** rate-limit + CSRF + lockout checks — denial does **not** increment `failed_login_attempts`, preventing accidental self-lockout.
- The error message shown to the user intentionally includes **what** is blocked (time/day) but never reveals whether the credentials were correct — an attacker cannot use the message to enumerate valid accounts.
- `time_restriction_logs` has a CASCADE DELETE on `user_id` — purging a user cleans up their log history automatically.

---

## DB Schema Reference

```
user_accounts
  is_time_restricted      tinyint(1)   default 0
  allowed_login_start     time         nullable
  allowed_login_end       time         nullable
  allowed_days            varchar(100) nullable  -- comma-separated: "Monday,Tuesday,..."

time_restriction_logs
  log_id          bigint PK AI
  user_id         bigint FK -> user_accounts.user_id (CASCADE)
  attempted_at    timestamp
  ip_address      varchar(45)
  denial_reason   varchar(255)
```
