# BIR Module - User Guide

## Table of Contents
1. [Getting Started](#getting-started)
2. [BIR Settings](#bir-settings)
3. [OR Number Management](#or-number-management)
4. [VAT Transactions](#vat-transactions)
5. [BIR Reports](#bir-reports)
6. [POS Machine Accreditation](#pos-machine-accreditation)
7. [Audit Trail](#audit-trail)
8. [Backup & Restore](#backup--restore)
9. [POS Integration](#pos-integration)

---

## Getting Started

### Access Requirements
- You must have one of these roles: SUPER_ADMIN, ADMIN, MANAGER, ACCOUNTANT
- Navigate to: **Admin → BIR Module**

### Overview
The BIR module helps your business comply with BIR (Bureau of Internal Revenue) requirements for:
- Official Receipt (OR) numbering
- VAT computation and tracking
- BIR-required reports
- POS machine accreditation
- Audit trail for all transactions

---

## BIR Settings

### Location
**Admin → BIR → Settings**

### Configuration Steps

1. **Set Company TIN**
   - Enter your Tax Identification Number
   - Format: 000-000-000-000

2. **BIR Accreditation Details**
   - **Accreditation Number**: From BIR certificate
   - **Accreditation Expiry**: Date when accreditation expires
   - **Permit Number**: BIR permit number
   - **Validity Period**: Start and end dates

3. **Machine Details**
   - **MIN (Machine Identification Number)**: From BIR
   - **Serial Number**: POS machine serial number

4. **VAT Settings**
   - **VAT Rate**: Default is 12% (can be changed)
   - **Auto OR Assignment**: Enable to automatically assign OR numbers on sales

5. **Click "Save Settings"**

### Important Notes
- All fields are required for BIR compliance
- Keep accreditation expiry dates updated
- Enable "Auto OR Assignment" for automatic OR number generation

---

## OR Number Management

### Location
**Admin → BIR → OR Numbers**

### Understanding OR Series
- **OR Series**: Format is BRANCH-YEAR (e.g., 001-2024)
- **OR Number**: Sequential number within series (e.g., 000001)
- **Full OR Number**: Complete format (e.g., 001-2024-000001)

### Creating OR Series

1. Click **"Create New Series"** button
2. Select **Branch**
3. **Year** is auto-filled with current year
4. Set **Start Number** (usually 1)
5. Set **End Number** (usually 999999)
6. Click **"Create Series"**

### Managing OR Numbers

**View All OR Numbers:**
- Filter by branch, status, date range
- Search by OR number or order code
- View issued, voided, and cancelled ORs

**Void an OR Number:**
1. Find the OR number in the list
2. Click **"Void"** button
3. Enter reason for voiding
4. Click **"Confirm Void"**

**Important:**
- Voided OR numbers cannot be reused
- Always provide a reason for voiding
- Voided ORs are logged in audit trail

### OR Series Statistics
- **Total Issued**: Number of ORs issued
- **Remaining**: Available ORs in series
- **Voided**: Number of voided ORs
- **Cancelled**: Number of cancelled ORs

---

## VAT Transactions

### Location
**Admin → BIR → VAT**

### VAT Types

1. **12% VAT** - Standard VAT rate
   - Applied to regular sales
   - VAT amount is calculated: Total / 1.12 × 0.12

2. **Exempt** - VAT-exempt transactions
   - Senior citizens (with OSCA ID)
   - PWD (with PWD ID)
   - Agricultural products
   - Educational services

3. **Zero-Rated** - Zero VAT
   - Export sales
   - Certain international transactions

### Viewing VAT Transactions

**Dashboard:**
- Total VAT collected today
- VAT breakdown by type
- Monthly VAT summary

**Transaction List:**
- Filter by VAT type, date range
- View exemption details
- See taxable vs non-taxable amounts

### Exemption Details

For exempt transactions, provide:
- **Exemption Type**: Senior Citizen, PWD, etc.
- **Exemption ID Number**: OSCA ID, PWD ID, etc.
- **Exemption Name**: Customer name

---

## BIR Reports

### Location
**Admin → BIR → Reports**

### Available Reports

#### 1. Daily Sales Report (DSR)
- **Purpose**: Daily sales summary
- **Includes**: Total sales, VAT breakdown, payment methods, hourly sales
- **Generate**: Select date and branch, click "Generate DSR"

#### 2. Monthly Sales Report
- **Purpose**: Monthly sales summary
- **Includes**: Monthly totals, daily breakdown, VAT collected
- **Generate**: Select date range and branch, click "Generate Monthly"

#### 3. Summary List of Sales (SLS)
- **Purpose**: List of all sales transactions
- **Includes**: OR number, date, amount, VAT, payment method
- **Generate**: Select date range and branch, click "Generate SLS"

#### 4. VAT Returns (2550M)
- **Purpose**: Monthly VAT return for BIR
- **Includes**: Output VAT, input VAT, net VAT payable
- **Generate**: Select month and branch, click "Generate 2550M"

### Generating Reports

1. Select **Report Type** from dropdown
2. Select **Date Range**
3. Select **Branch** (optional for all branches)
4. Click **"Generate Report"**
5. Report will appear in the list below
6. Click **"View"** to see details
7. Click **"Export"** to download (PDF/Excel)

### Report History
- All generated reports are saved
- View past reports anytime
- Export historical reports

---

## POS Machine Accreditation

### Location
**Admin → BIR → Machines**

### Adding a POS Machine

1. Click **"Add Machine"** button
2. Fill in machine details:
   - **Machine Name**: e.g., "Main Counter Terminal"
   - **Branch**: Select branch
   - **Serial Number**: Unique serial from manufacturer
   - **Accreditation Number**: From BIR certificate
   - **Accreditation Expiry**: Expiry date
   - **Machine Type**: CAS or POS
   - **MIN**: Machine Identification Number
   - **Permit Number**: BIR permit number
   - **Validity Period**: Start and end dates
3. Click **"Save Machine"**

### Managing Machines

**View All Machines:**
- Filter by branch, status
- See accreditation expiry dates
- Expiring machines are highlighted

**Edit Machine:**
- Click **"Edit"** button
- Update details as needed
- Click **"Save Changes"**

**Delete Machine:**
- Click **"Delete"** button
- Confirm deletion
- Machine will be marked as decommissioned

### Expiry Alerts
- Machines expiring in 30 days are shown in alerts
- Renew accreditation before expiry
- Expired machines cannot be used

---

## Audit Trail

### Location
**Admin → BIR → Audit Trail**

### What is Logged
- **Create**: New orders, OR numbers
- **Modify**: Price changes, discount changes
- **Void**: Voided OR numbers
- **Cancel**: Cancelled transactions
- **Refund**: Refunded transactions
- **Delete**: Deleted records

### Viewing Audit Trail

**Filters:**
- **Action Type**: Select specific action
- **Table**: Select affected table
- **User**: Filter by user
- **Date Range**: Select period

**Audit Log Details:**
- User who performed action
- Action type
- Field changed (if applicable)
- Old value and new value
- Reason for change
- IP address
- Timestamp

### Export Audit Trail
- Click **"Export"** button
- Download as CSV or Excel
- Useful for BIR audits

---

## Backup & Restore

### Location
**Admin → BIR → Backups**

### Creating Backups

1. Click **"Create Backup"** button
2. Select **Backup Type**:
   - **Full**: Complete database backup
   - **Incremental**: Changes since last backup
3. Click **"Start Backup"**
4. Wait for backup to complete
5. Backup will appear in the list

### Backup Details
- **Backup Date**: When backup was created
- **Type**: Full or incremental
- **File Size**: Size of backup file
- **Status**: Success, failed, or corrupted
- **Checksum**: For integrity verification

### Restoring from Backup

1. Find the backup in the list
2. Click **"Restore"** button
3. Confirm restore action
4. Wait for restore to complete
5. System will be restored to backup state

**Warning:** Restore will overwrite current data. Proceed with caution.

### Backup Best Practices
- Create daily backups
- Keep backups for at least 3 years (BIR requirement)
- Store backups off-site
- Verify backup integrity regularly
- Test restore process periodically

---

## POS Integration

### Automatic OR Assignment

When enabled in BIR Settings:
- OR numbers are automatically assigned when orders are completed
- No manual intervention needed
- OR number appears on receipt

### VAT in POS

**Processing Sales:**
1. Select VAT type (default: 12%)
2. For exempt sales:
   - Select exemption type
   - Enter exemption ID number
   - Enter customer name
3. Complete sale as normal
4. VAT is automatically calculated
5. OR number is automatically assigned

**Receipt Shows:**
- OR number
- VAT amount
- VAT type
- Exemption details (if applicable)

### Viewing BIR Data in POS

After completing a sale:
- OR number is displayed
- VAT breakdown is shown
- Transaction is logged to audit trail

---

## Troubleshooting

### OR Number Not Assigned
- Check if "Auto OR Assignment" is enabled in Settings
- Verify OR series exists for the branch and year
- Check if OR series has available numbers

### VAT Not Calculating
- Verify VAT rate is set in Settings
- Check VAT type is selected
- Ensure order total is valid

### Reports Not Generating
- Check date range is valid
- Verify data exists for the period
- Check database connection

### Backup Failed
- Check disk space
- Verify database connection
- Check file permissions

---

## BIR Compliance Checklist

- [ ] Company TIN is set
- [ ] BIR accreditation details are entered
- [ ] POS machines are registered
- [ ] OR series are created for all branches
- [ ] Auto OR assignment is enabled
- [ ] VAT rate is configured
- [ ] Daily backups are scheduled
- [ ] Reports are generated regularly
- [ ] Audit trail is reviewed periodically
- [ ] Accreditation expiry dates are monitored

---

## Support

For issues or questions:
1. Check this user guide
2. Review audit trail for errors
3. Contact system administrator
4. Consult BIR documentation for compliance requirements

---

**Document Version:** 1.0  
**Last Updated:** June 2, 2026  
**For:** TMS BIR Module Users
