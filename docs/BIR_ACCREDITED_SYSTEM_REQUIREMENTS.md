# BIR Accredited Computerized Accounting System (CAS) Requirements

## Overview
This document outlines the requirements for the TMS system to become BIR-accredited as a Computerized Accounting System (CAS) in the Philippines.

---

## 1. Official Receipt (OR) Numbering System

### Requirements:
- **Sequential OR numbering** per branch
- OR number format: Branch Code + Year + Sequence (e.g., 001-2024-000001)
- OR number range management with automatic increment
- Void/cancelled OR tracking with audit trail
- OR number cannot be reused once issued
- OR number must be printed on all receipts

### Implementation Needed:
- Table: `bir_or_numbers`
  - branch_id
  - or_number (sequence)
  - or_series (e.g., 001-2024)
  - status (issued, void, cancelled)
  - order_id (reference)
  - issued_at
  - voided_at
  - voided_by
  - void_reason

---

## 2. VAT Computation & Breakdown

### Requirements:
- **12% VAT** on taxable sales
- **VAT-exempt** transactions (senior citizen, PWD, agricultural products)
- **Zero-rated** transactions (export sales)
- Separate VAT amount display on receipts
- VAT input/output tracking
- Monthly VAT summary report

### Implementation Needed:
- Table: `bir_vat_transactions`
  - order_id
  - vat_type (12%, exempt, zero-rated)
  - vat_amount
  - taxable_amount
  - non_taxable_amount
  - exemption_type (senior_citizen, pwd, export, etc.)
  - exemption_id_number

- Update `pos_orders` table:
  - vat_amount
  - vat_type
  - taxable_amount
  - non_taxable_amount

---

## 3. BIR-Required Reports

### 3.1 Daily Sales Report (DSR)
- Total sales per day
- VAT sales breakdown
- Non-VAT sales
- Void/cancelled transactions
- Cash, credit, and other payment breakdown
- Running totals for the day

### 3.2 Monthly Sales Report
- Monthly summary of all sales
- VAT collected per month
- Taxable vs non-taxable breakdown
- Comparison with previous month
- Year-to-date totals

### 3.3 Summary List of Sales (SLS)
- List of all sales transactions
- OR number, date, customer name
- Amount, VAT amount
- Payment method
- Status

### 3.4 Alphalist of Purchases
- List of all purchases/expenses
- Supplier details
- VAT input tax
- Non-VAT purchases

### 3.5 VAT Returns (2550M)
- Monthly VAT return format
- Output VAT vs Input VAT
- Net VAT payable/refundable

### Implementation Needed:
- Table: `bir_reports`
  - report_type (DSR, Monthly, SLS, Alphalist, 2550M)
  - report_date
  - branch_id
  - generated_by
  - data_json
  - status

---

## 4. POS Machine Accreditation Details

### Requirements:
- **Machine Serial Number** (unique per POS terminal)
- **Accreditation Certificate Number** (issued by BIR)
- **Accreditation Expiry Date**
- **Machine Type** (CAS/POS)
- **Machine Identification Number (MIN)**
- **Permit Number**
- **Validity Period**

### Implementation Needed:
- Table: `bir_pos_machines`
  - machine_id
  - branch_id
  - serial_number
  - accreditation_number
  - accreditation_expiry
  - machine_type (CAS, POS)
  - min (Machine Identification Number)
  - permit_number
  - validity_from
  - validity_to
  - status (active, expired, suspended)
  - created_at
  - updated_at

- Update `system_settings`:
  - bir_accreditation_number
  - bir_accreditation_expiry
  - bir_permit_number
  - bir_validity_from
  - bir_validity_to

---

## 5. Enhanced Audit Trail

### Requirements:
- **User activity logging** per transaction
- **Modification tracking** (who changed what, when)
- **Void/cancellation audit trail** with reason
- **Price change tracking**
- **Discount modification tracking**
- Logs must be tamper-proof

### Implementation Needed:
- Table: `bir_audit_trail`
  - audit_id
  - order_id
  - user_id
  - action (create, modify, void, cancel, refund)
  - field_changed
  - old_value
  - new_value
  - reason
  - ip_address
  - created_at

- Table: `bir_transaction_modifications`
  - modification_id
  - order_id
  - modified_by
  - modification_type
  - field_name
  - previous_value
  - new_value
  - modified_at
  - reason

---

## 6. Backup & Restore System

### Requirements:
- **Automated daily backups**
- BIR-compliant backup format
- Backup encryption
- Backup retention (minimum 3 years)
- Restore functionality
- Backup integrity verification
- Off-site backup storage

### Implementation Needed:
- Table: `bir_backups`
  - backup_id
  - backup_date
  - backup_type (full, incremental)
  - file_path
  - file_size
  - checksum
  - status (success, failed)
  - created_by
  - restored_at
  - restored_by

- Backup script for automated daily backups
- Restore interface for admin users

---

## 7. Receipt Requirements

### Required Fields on Receipt:
- **TIN** (Tax Identification Number)
- **OR Number** (Official Receipt Number)
- **Date**
- **Customer Name** (if applicable)
- **Address**
- **Item Description**
- **Quantity**
- **Unit Price**
- **Total Amount**
- **VAT Amount** (if applicable)
- **VAT Type** (12%, Exempt, Zero-rated)
- **Payment Method**
- **Amount Paid**
- **Change**
- **Permit Number**
- **Accreditation Number**
- **Validity Period**
- **MIN** (Machine Identification Number)
- **Serial Number**
- **Cashier Name**
- **Branch Name**
- **"This serves as Official Receipt"** disclaimer

### Implementation Needed:
- Update receipt template to include all BIR-required fields
- Add digital signature capability
- Add QR code for verification (optional but recommended)

---

## 8. Additional BIR Requirements

### 8.1 Security Features
- User authentication with unique credentials
- Role-based access control
- Password encryption
- Session timeout
- Failed login attempt tracking

### 8.2 Data Integrity
- Transaction data cannot be deleted
- Only void/cancel with audit trail
- Timestamps on all records
- Database integrity checks

### 8.3 Reporting Period
- Daily sales must be reported by end of day
- Monthly reports must be generated by 20th of following month
- Annual reports must be generated by April 15

### 8.4 BIR Submission
- Electronic submission capability
- XML/CSV export for BIR format
- Digital signature for submissions
- Acknowledgment receipt tracking

---

## 9. Implementation Priority

### Phase 1 (Critical - High Priority)
1. OR Numbering System
2. VAT Computation & Breakdown
3. Enhanced Audit Trail
4. Receipt Updates (TIN, OR Number, VAT)

### Phase 2 (Important - Medium Priority)
5. BIR Reports (DSR, Monthly, SLS)
6. POS Machine Accreditation Details
7. Security Enhancements

### Phase 3 (Compliance - Lower Priority)
8. Backup & Restore System
9. Alphalist of Purchases
10. VAT Returns (2550M)
11. Electronic Submission

---

## 10. BIR Accreditation Process

### Steps:
1. **Application** - Submit application form to BIR RDO
2. **Testing** - BIR will test the system
3. **Inspection** - BIR will inspect the system implementation
4. **Accreditation** - Issuance of accreditation certificate
5. **Monitoring** - Annual compliance check

### Required Documents:
- Application letter
- System documentation
- User manual
- Technical specifications
- Sample reports
- Test data

---

## 11. References

- BIR Revenue Regulations No. 9-2018
- BIR Revenue Memorandum Circular No. 57-2020
- BIR Revenue Memorandum Order No. 21-2021
- Tax Code of the Philippines (NIRC)

---

## 12. Notes

- All BIR-related features must be tested thoroughly before submission
- System must be able to generate all required reports on demand
- Audit trail must be comprehensive and tamper-proof
- Backup system must be reliable and tested regularly
- User training is essential for BIR compliance

---

**Document Version:** 1.0  
**Last Updated:** June 1, 2026  
**Status:** Draft - For Review
