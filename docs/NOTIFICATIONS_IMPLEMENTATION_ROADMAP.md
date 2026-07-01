# Notifications Implementation Roadmap

## Overview
This roadmap outlines the implementation of notification triggers for various TMS modules, permissions, and user preferences.

## Phase 1: Support Request Notifications
**Priority: HIGH (Immediate - user has existing support request)** ✅ COMPLETED

### Tasks
- [x] Find support request creation endpoint/file
- [x] Add NotificationService call when support request is created
- [x] Test support request notification trigger

### Implementation Details
- Trigger: When a new support request is created
- Recipients: SUPER_ADMIN and ADMIN users
- Template: `support` type with placeholders: `{user}`, `{subject}`
- Real-time: TRUE (critical notification)

### Files to Modify
- `api/support/index.php` or support creation endpoint
- Add `NotificationService::createFromTemplate('support', $adminUserId, ['user' => $userName, 'subject' => $subject])`

---

## Phase 2: Wallet Transaction Notifications
**Priority: HIGH (financial alerts)** ✅ COMPLETED

### Tasks
- [x] Find wallet transaction endpoints/files
- [x] Add low balance alert trigger
- [x] Add large transaction alert trigger
- [x] Test wallet notification triggers

### Implementation Details
- **Low Balance Alert**
  - Trigger: When wallet balance falls below threshold
  - Recipients: Wallet owner and SUPER_ADMIN
  - Template: `wallet_low` type with placeholders: `{wallet_id}`, `{balance}`
  - Real-time: FALSE (polling)

- **Large Transaction Alert**
  - Trigger: When transaction exceeds threshold amount
  - Recipients: SUPER_ADMIN and ADMIN
  - Template: `wallet_transaction` type with placeholders: `{transaction_type}`, `{amount}`, `{wallet_id}`
  - Real-time: FALSE (polling)

### Files to Modify
- Wallet transaction processing endpoints
- Wallet balance check logic

---

## Phase 3: POS Session Notifications
**Priority: MEDIUM** ✅ COMPLETED

### Tasks
- [x] Find POS session close endpoint/file
- [x] Add session discrepancy alert trigger
- [x] Test POS notification trigger

### Implementation Details
- Trigger: When POS session is closed with cash discrepancy
- Recipients: SUPER_ADMIN and ADMIN
- Template: `pos_session` type with placeholders: `{session_id}`, `{status}`
- Real-time: TRUE (critical notification)

### Files to Modify
- POS session close endpoint
- Cash discrepancy detection logic

---

## Phase 4: Payment Notifications
**Priority: HIGH (financial alerts)** ✅ COMPLETED

### Tasks
- [x] Find payment processing endpoints/files
- [x] Add payment failed alert trigger
- [x] Add payment confirmed alert trigger
- [x] Test payment notification triggers

### Implementation Details
- **Payment Failed**
  - Trigger: When payment processing fails
  - Recipients: User and SUPER_ADMIN
  - Template: `payment_failed` type with placeholders: `{amount}`, `{reference}`
  - Real-time: TRUE (critical notification)

- **Payment Confirmed**
  - Trigger: When payment is successfully processed
  - Recipients: User
  - Template: `payment_confirmed` type with placeholders: `{amount}`, `{reference}`
  - Real-time: FALSE (polling)

### Files to Modify
- Payment processing endpoints
- Payment status update logic

---

## Phase 5: System Event Notifications
**Priority: MEDIUM** ✅ COMPLETED

### Tasks
- [x] Find system settings/maintenance endpoints
- [x] Add maintenance mode change trigger
- [x] Add security alert trigger
- [x] Test system notification triggers

### Implementation Details
- **Maintenance Mode Change**
  - Trigger: When maintenance mode is enabled/disabled
  - Recipients: SUPER_ADMIN and ADMIN
  - Template: `system_maintenance` type with placeholders: `{status}`
  - Real-time: TRUE (critical notification)

- **Security Alert**
  - Trigger: When security event is detected (failed login, suspicious activity)
  - Recipients: SUPER_ADMIN
  - Template: `system_security` type with placeholders: `{alert_type}`, `{details}`
  - Real-time: TRUE (critical notification)

### Files to Modify
- System settings endpoints
- Security event detection logic

---

## Phase 6: Notification Permissions
**Priority: HIGH (access control)** ✅ COMPLETED

### Tasks
- [x] Add VIEW_NOTIFICATIONS permission to role_permissions table
- [x] Update API to check VIEW_NOTIFICATIONS permission
- [x] Update frontend to handle permission errors

### Implementation Details
- Add `VIEW_NOTIFICATIONS` permission to role_permissions table
- Update `/api/notifications/` to check permission instead of hardcoded role check
- Update notification dropdown to hide if no permission
- Update admin/notifications page to show access denied if no permission

### Files to Modify
- Database migration for role_permissions
- `api/notifications/index.php`
- `admin/includes/navbar-components/notification-dropdown.php`
- `admin/notifications/index.php`

---

## Phase 7: Notification Preferences UI
**Priority: MEDIUM (user customization)** ✅ COMPLETED

### Tasks
- [x] Create admin/settings/notification-preferences page
- [x] Create API for user notification preferences
- [x] Add preference UI with channel toggles
- [x] Test notification preferences functionality

### Implementation Details
- Create page at `admin/settings/notification-preferences/`
- Allow users to:
  - Enable/disable notification types
  - Select preferred channels (email, SMS, push, in-app)
  - Set notification thresholds (e.g., wallet balance threshold)
- Create API endpoints for CRUD operations on notification_preferences table

### Files to Create
- `admin/settings/notification-preferences/index.php`
- `admin/settings/notification-preferences/views/index.php`
- `api/notification-preferences/index.php`

---

## Implementation Order
1. **Phase 1: Support Request Notifications** (immediate)
2. **Phase 2: Wallet Transaction Notifications** (high priority)
3. **Phase 4: Payment Notifications** (high priority)
4. **Phase 3: POS Session Notifications** (medium priority)
5. **Phase 5: System Event Notifications** (medium priority)
6. **Phase 6: Notification Permissions** (important for access control)
7. **Phase 7: Notification Preferences UI** (nice to have)

---

## Notes
- All notification triggers should use `NotificationService::createFromTemplate()` for consistency
- Critical notifications should have `real_time: TRUE` for SSE delivery
- Non-critical notifications should have `real_time: FALSE` for polling
- User preferences should be respected before creating notifications
- Error handling should be robust to prevent notification failures from breaking main functionality

 Phase 1: Support Request Notifications
✅ Phase 2: Wallet Transaction Notifications
✅ Phase 3: POS Session Notifications

✅ Phase 4: Payment Notifications
✅ Phase 5: System Event Notifications
✅ Phase 6: Notification Permissions
✅ Phase 7: Notification Preferences UI

/admin/settings/notification-preferences/
