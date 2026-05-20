# System Features Documentation

This document tracks all implemented system features, organized by module and functionality.

## Bank Account Management

### Overview
Comprehensive bank account tracking with balance management, transaction logging, and deposit confirmation workflow.

### Features Implemented

#### 1. Bank Account Balance Tracking
- **File**: `database/migrations/add_current_balance_to_bank_accounts.sql`
- **Description**: Added `current_balance` column to `bank_accounts` table
- **Purpose**: Track real-time balance of each bank account
- **Default Value**: 0.00

#### 2. Bank Transactions Logging
- **File**: `database/migrations/create_bank_transactions_table.sql`
- **Description**: Created `bank_transactions` table to log all bank movements
- **Columns**:
  - `bank_txn_id` - Primary key
  - `bank_account_id` - Foreign key to bank_accounts
  - `txn_code` - Unique transaction code (format: BANK-YYYYMMDD-HHII-XXXXX)
  - `txn_type` - Transaction type (RECEIPT, DISBURSEMENT, DEPOSIT, WITHDRAWAL, TRANSFER_IN, TRANSFER_OUT, ADJUSTMENT)
  - `direction` - IN or OUT
  - `amount` - Transaction amount
  - `balance_before` - Account balance before transaction
  - `balance_after` - Account balance after transaction
  - `reference_table` - Source table (transaction_payments, cashier_sessions, etc.)
  - `reference_id` - Source record ID
  - `remarks` - Transaction notes
  - `created_by` - User who created the transaction
  - `created_at` - Timestamp
  - `confirmation_status` - PENDING, CONFIRMED, REJECTED
  - `confirmed_by` - User who confirmed the transaction
  - `confirmed_at` - Confirmation timestamp

#### 3. Bank Transfer / E-Wallet Payment Confirmation
- **File**: `api/charges/index.php`
- **Description**: When a cashier records a bank transfer or e-wallet payment, a bank transaction is created upon confirmation
- **Flow**:
  1. Cashier records payment via bank transfer/e-wallet
  2. Payment is marked as PENDING confirmation
  3. Manager confirms the payment in bank-confirmations
  4. Bank transaction is created (RECEIPT type)
  5. Bank account balance is updated

#### 4. Shift Cash Deposit Tracking
- **File**: `database/migrations/add_current_balance_to_bank_accounts.sql`
- **Description**: Added columns to `cashier_sessions` table to track cash deposits
- **Columns**:
  - `cash_deposit_bank_id` - Bank account where cash was deposited
  - `deposit_status` - PENDING, DEPOSITED, NOT_APPLICABLE
  - `deposited_at` - Deposit timestamp
  - `deposited_by` - User who recorded the deposit

#### 5. POS Session Close with Deposit Options
- **File**: `admin/pos/views/modals/close_session.php`, `api/pos/sessions.php`
- **Description**: Added deposit options to session close modal
- **Features**:
  - Bank account selection dropdown
  - "Deposit Now" checkbox
  - If "Deposit Now" is checked: Creates bank transaction as CONFIRMED, updates balance immediately
  - If not checked: Marks session as PENDING deposit, can be recorded later

#### 6. Delayed Deposit Recording
- **File**: `admin/shifts/views/modals/record_deposit.php`, `api/pos/sessions.php`
- **Description**: Allows recording deposits after session closure
- **Flow**:
  1. Manager views closed sessions in shifts page
  2. Clicks "Record Deposit" on PENDING sessions
  3. Selects bank account and amount
  4. Creates bank transaction as PENDING (requires confirmation)
  5. Bank balance NOT updated until confirmation

#### 7. Deposit Confirmation Workflow
- **File**: `admin/bank-confirmations/` (controller, view, API)
- **Description**: Unified confirmation page for both bank transfers and cash deposits
- **Features**:
  - Shows both PAYMENT (bank transfers) and DEPOSIT (cash deposits) items
  - PENDING status for both types
  - Manager can confirm or reject
  - On CONFIRM: Bank account balance is updated
  - On REJECT: Transaction is marked as rejected, balance unchanged
- **API Endpoints**:
  - `PUT /api/bank-confirmations` with `payment_id` for payments
  - `PUT /api/bank-confirmations` with `deposit_id` for deposits

#### 8. Shifts Page Deposit Status Display
- **File**: `admin/shifts/views/index.php`
- **Description**: Shows deposit status badges on session cards
- **Features**:
  - PENDING badge (yellow) for sessions awaiting deposit
  - DEPOSITED badge (green) for completed deposits
  - "Record Deposit" button for PENDING sessions
  - Deposit info in session detail modal

### API Endpoints

#### Charges API
- `POST /api/charges` - Accept payments, creates bank transaction for bank/e-wallet on confirmation

#### Bank Confirmations API
- `PUT /api/bank-confirmations` - Confirm or reject payments and deposits
  - Parameters: `payment_id` OR `deposit_id`, `action` (CONFIRMED/REJECTED), `notes`

#### POS Sessions API
- `PUT /api/pos/sessions` - Close session with deposit options
  - Parameters: `session_id`, `action: close`, `cash_deposit_bank_id`, `deposit_now`
  - Parameters: `session_id`, `action: record_deposit`, `bank_account_id`, `deposit_amount`

### Database Tables

#### bank_accounts
- Added: `current_balance` (DECIMAL 12,2)

#### bank_transactions
- Created: Full transaction logging table
- Added: `confirmation_status`, `confirmed_by`, `confirmed_at`

#### cashier_sessions
- Added: `cash_deposit_bank_id`, `deposit_status`, `deposited_at`, `deposited_by`

### User Interface Changes

#### Bank Accounts Settings
- Display current balance for each account
- Add/edit accounts with balance tracking

#### Shifts Page
- Deposit status badges
- Record Deposit modal
- Deposit info in session detail

#### POS Session Close
- Bank account selection
- Deposit now checkbox

#### Bank Confirmations
- Unified view for payments and deposits
- Type badges (PAYMENT/DEPOSIT)
- Confirmation modal for both types

### Security & Permissions

- All bank transaction operations require appropriate permissions
- Confirmation workflow ensures manager approval for deposits
- Atomic database operations for financial data consistency
- Audit trail via `created_by`, `confirmed_by` fields

### Future Enhancements

- Bank transactions history page
- Bank account statements/reports
- Transfer between bank accounts
- Bank reconciliation features
