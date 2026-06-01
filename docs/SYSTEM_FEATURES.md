# TMS - KEY FEATURES

## 1. AUTHENTICATION & SECURITY

### Description
Responsible for securing the system, managing user access, authentication, authorization, and activity monitoring.

### Features
• User registration and management
• Secure login using JWT + Refresh Tokens
• Role-based access control (Super Admin, Admin, Manager, Cashier)
• Permission-based access control
• Password hashing and encryption
• Session management
• Device session tracking
• Login history monitoring
• Audit trail for all critical actions

---

## 2. DASHBOARD & ANALYTICS

### Description
Provides a real-time overview of business operations, sales performance, key business metrics, and sales target tracking.

### Features
• Today's, Weekly, Monthly, and Yearly Sales
• Sales Trend Charts
• Cashier Performance Tracking
• Total Orders, Revenue, and Average Order Value
• Transactions per Hour Analysis
• Sales Target Widget with Achievement Status
• Date Range Filtering (Today, Last 7 Days, Last 30 Days, This Year)
• Branch-wise Analytics
• Payment Method Breakdown
• Recent Activities Feed

---

## 3. BANK ACCOUNT MANAGEMENT

### Description
Comprehensive bank account tracking with balance management, transaction logging, and deposit confirmation workflow.

### Features
• Bank Account Registration and Maintenance
• Real-time Balance Tracking
• Bank Transaction Logging
• Transaction Types: RECEIPT, DISBURSEMENT, DEPOSIT, WITHDRAWAL, TRANSFER_IN, TRANSFER_OUT, ADJUSTMENT
• Balance Before/After Tracking
• Transaction Reference Linking
• Confirmation Workflow (PENDING, CONFIRMED, REJECTED)
• Audit Trail (created_by, confirmed_by)
• Unique Transaction Code Generation

---

## 4. BANK TRANSFER & E-WALLET PAYMENT CONFIRMATION

### Description
Manages bank transfer and e-wallet payment confirmation workflow to ensure funds are properly recorded in bank accounts.

### Features
• Bank Transfer Payment Recording
• E-Wallet Payment Recording (GCash, Maya)
• PENDING Confirmation Status
• Manager Approval Workflow
• Automatic Bank Transaction Creation on Confirmation
• Bank Account Balance Updates
• Payment Rejection Support
• Transaction Notes and Remarks

---

## 5. CASH DEPOSIT TRACKING

### Description
Tracks cash deposits from cashier shifts to bank accounts with confirmation workflow.

### Features
• Shift Cash Deposit Recording
• Deposit Status Tracking (PENDING, DEPOSITED, NOT_APPLICABLE)
• Bank Account Selection for Deposits
• Deposit Now Option (Immediate Confirmation)
• Delayed Deposit Recording
• Deposit Confirmation Workflow
• Deposit Timestamp and User Tracking
• Deposit History in Shifts Page

---

## 6. BANK CONFIRMATIONS

### Description
Unified confirmation page for both bank transfers/e-wallet payments and cash deposits requiring manager approval.

### Features
• Unified View for PAYMENT and DEPOSIT Items
• PENDING Status Display
• Type Badges (PAYMENT/DEPOSIT)
• Confirm or Reject Actions
• Balance Updates on Confirmation
• Transaction Notes Support
• Confirmation History
• Manager Approval Workflow

---

## 7. SALES TARGETS MANAGEMENT

### Description
Allows setting and tracking daily sales targets per branch with actual sales comparison and achievement status.

### Features
• Daily Sales Target Setting
• Monthly Target Configuration
• Same Target for Whole Month Mode
• Manual Per-Day Target Mode
• Branch-wise Targets
• Date Range Filtering
• Actual Sales Aggregation
• Achievement Status (Exceeded, On Target, Below Target)
• Progress Bar Visualization
• Target Notes Support
• Summary Display (Days Set, Total Target)

---

## 8. PAYMENT MANAGEMENT

### Description
Handles all payment transactions including cash, digital wallets, bank payments, split payments, and partial payments.

### Features
• Cash Payments
• GCash Payments
• Maya Payments
• Bank Transfer Payments
• Credit/Debit Cards
• Split Payments
• Partial Payments
• Payment History
• Payment Confirmation Workflow
• Payment Method Configuration

---

## 9. POS SESSION MANAGEMENT

### Description
Tracks cashier shifts, cash drawer activities, cash reconciliation, and daily balancing.

### Features
• Cash Drawer Control
• Open/Close Shift
• Cash In / Cash Out
• Cash Reconciliation
• Variance Monitoring
• Shift Deposit Options
• Deposit Status Tracking
• Session History
• Shift Summary Reports

---

## 10. POS TRANSACTIONS

### Description
The primary cashier module used to process customer transactions quickly and efficiently.

### Features
• Touchscreen-Friendly POS Interface
• Barcode Scanning
• Product Search
• Add-ons and Variants Support
• Discounts
• Hold and Resume Transactions
• Receipt Printing
• Multiple Payment Methods
• Refund Processing
• Transaction History

---

## 11. REFUND CONFIRMATIONS

### Description
Manages refund request confirmation workflow to ensure proper approval and tracking.

### Features
• Refund Request Recording
• PENDING Confirmation Status
• Manager Approval Workflow
• Refund Reason Tracking
• Refund Amount Validation
• Confirmation History
• Refund Status Updates

---

## 12. BRANCH MANAGEMENT

### Description
Manages multiple business branches with location tracking and configuration.

### Features
• Branch Registration
• Branch Information Management
• Branch Location Settings
• Branch Assignment to Users
• Branch-wise Data Filtering
• Branch Performance Analytics

---

## 13. PROVIDER WALLET MANAGEMENT

### Description
Tracks service provider wallet balances and transactions for third-party payment processing.

### Features
• Provider Wallet Registration
• Wallet Balance Tracking
• Wallet Transaction Logging
• Service Fee Deduction
• Balance Monitoring
• Low Balance Alerts
• Provider Assignment
• Wallet Transaction History

---

## 14. USER MANAGEMENT

### Description
Manages system users, roles, permissions, and access control.

### Features
• User Registration
• User Profile Management
• Role Assignment (Super Admin, Admin, Manager, Cashier)
• Permission Configuration
• Branch Assignment
• User Status Management
• User Activity Tracking
• Password Management

---

## 15. SYSTEM SETTINGS

### Description
Provides centralized configuration of business information, payment methods, and application behavior.

### Features
• Business Information Setup
• Payment Method Configuration
• Discount Type Configuration
• Service Type Configuration
• Email Settings
• Branch Configuration
• Role Dashboard Configuration
• System-wide Settings

---

## 16. CHARGES & PAYMENTS

### Description
API endpoint for processing payments and charges with bank transaction integration.

### Features
• Payment Processing
• Bank Transaction Creation
• Payment Confirmation
• Reference Tracking
• Transaction Logging
• Payment Status Updates

---

## 17. ANALYTICS API

### Description
Provides data endpoints for dashboard analytics and reporting.

### Features
• Sales Data Aggregation
• Branch Sales Reporting
• Date Range Filtering
• Performance Metrics Calculation
• Chart Data Generation
• Real-time Analytics

---

## 18. SALES TARGETS API

### Description
API endpoints for sales targets CRUD operations and actual sales aggregation.

### Features
• Target Creation
• Target Update
• Target Deletion
• Date Range Filtering
• Branch Filtering
• Actual Sales Calculation
• Target Aggregation
• Notes Support

---

## 19. WALLET TRANSACTIONS API

### Description
API endpoints for provider wallet transaction management.

### Features
• Wallet Transaction Logging
• Balance Updates
• Transaction History
• Provider Filtering
• Transaction Type Support

---

## 20. BANK TRANSACTIONS API

### Description
API endpoints for bank transaction management and balance tracking.

### Features
• Transaction Creation
• Balance Updates
• Transaction History
• Confirmation Status Management
• Bank Account Filtering
• Transaction Type Support

---

## 21. DISCOUNT TYPES MANAGEMENT

### Description
Manages discount types and configurations for the system.

### Features
• Discount Type Registration
• Default Discount Type Setting
• Discount Type Configuration
• Discount Type Assignment
• Discount Type Filtering

---

## 22. EMAIL SETTINGS

### Description
Configures email settings for system notifications and communications.

### Features
• SMTP Configuration
• Email Template Setup
• Notification Settings
• Email Testing
• Email History

---

## 23. PERMISSIONS MANAGEMENT

### Description
Manages system permissions and access control for different user roles.

### Features
• Permission Definition
• Role-Based Permissions
• Module Access Control
• Permission Assignment
• Permission Groups

---

## 24. ROLE DASHBOARDS

### Description
Configures default dashboards for different user roles.

### Features
• Role Dashboard Assignment
• Dashboard Configuration
• Role-Based Dashboard Routing
• Dashboard Customization

---

## 25. SERVICE TYPES

### Description
Manages service types for the system.

### Features
• Service Type Registration
• Service Type Configuration
• Service Type Assignment
• Service Type Filtering

---

## 26. ACCOMMODATION TYPES

### Description
Manages accommodation types for the system.

### Features
• Accommodation Type Registration
• Accommodation Type Configuration
• Accommodation Type Assignment
• Accommodation Type Filtering

---

## 27. CASHIER PROVIDER ASSIGNMENTS

### Description
Manages assignment of cashiers to payment providers.

### Features
• Cashier-Provider Assignment
• Provider Selection
• Cashier Assignment
• Assignment History
• Assignment Filtering

---

## 28. DEVICES MANAGEMENT

### Description
Manages devices and terminals for the system.

### Features
• Device Registration
• Device Configuration
• Terminal Assignment
• Device Status Tracking
• Device History

---

## 29. PROVIDER SERVICE FEES

### Description
Manages service fees for payment providers.

### Features
• Service Fee Configuration
• Provider Fee Setup
• Fee Calculation Rules
• Fee History
• Fee Reporting

---

## 30. TICKET PROVIDERS

### Description
Manages ticket providers for the system.

### Features
• Ticket Provider Registration
• Provider Configuration
• Provider Assignment
• Provider Status Tracking
• Provider History

---

## 31. ROLES MANAGEMENT

### Description
Manages user roles and role-based access control.

### Features
• Role Registration
• Role Configuration
• Role Assignment
• Role Permissions
• Role History

---

## 32. IMAGES API

### Description
API endpoints for image upload and management.

### Features
• Image Upload
• Image Storage
• Image Retrieval
• Image Deletion
• Image Compression

---

## 33. PSGC API

### Description
API endpoints for Philippine Standard Geographic Code data.

### Features
• Region Data
• Province Data
• City/Municipality Data
• Barangay Data
• Geographic Code Lookup
