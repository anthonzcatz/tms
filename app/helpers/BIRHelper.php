<?php
/**
 * BIRHelper
 * Helper class for BIR-related operations
 * Handles OR number assignment, VAT computation, and audit trail logging
 */

class BIRHelper {
    
    /**
     * Assign OR number to an order
     * 
     * @param int $orderId The order ID
     * @param int $branchId The branch ID
     * @param int $userId The user ID who created the order
     * @return array|false Returns OR data or false on failure
     */
    public static function assignORNumber($orderId, $branchId, $userId) {
        try {
            $year = date('Y');
            
            // Get or create OR series for this branch and year
            // Note: runs inside the caller's transaction — no nested beginTransaction
            $series = Database::fetch(
                "SELECT * FROM bir_or_series 
                 WHERE branch_id = ? AND year = ? AND status = 'active'",
                [$branchId, $year]
            );
            
            if (!$series) {
                // Create new series
                $seriesCode = str_pad($branchId, 3, '0', STR_PAD_LEFT) . '-' . $year;
                Database::execute(
                    "INSERT INTO bir_or_series (branch_id, year, series_code, start_number, current_number, end_number, status, created_by) 
                     VALUES (?, ?, ?, 1, 0, 999999, 'active', ?)",
                    [$branchId, $year, $seriesCode, $userId]
                );
                $series = Database::fetch(
                    "SELECT * FROM bir_or_series WHERE series_code = ?",
                    [$seriesCode]
                );
            }
            
            // Increment and get next OR number
            $nextNumber = $series['current_number'] + 1;
            
            // Check if within range
            if ($nextNumber > $series['end_number']) {
                throw new Exception("OR series exhausted for branch $branchId year $year");
            }
            
            // Update series current number
            Database::execute(
                "UPDATE bir_or_series SET current_number = ? WHERE series_id = ?",
                [$nextNumber, $series['series_id']]
            );
            
            // Create OR number record
            $orFullNumber = $series['series_code'] . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            
            Database::execute(
                "INSERT INTO bir_or_numbers (branch_id, or_number, or_series, or_full_number, status, order_id, issued_at) 
                 VALUES (?, ?, ?, ?, 'issued', ?, NOW())",
                [$branchId, $nextNumber, $series['series_code'], $orFullNumber, $orderId]
            );
            
            $orId = Database::connection()->lastInsertId();
            
            // Update order with OR number reference
            Database::execute(
                "UPDATE pos_orders SET or_number_id = ? WHERE order_id = ?",
                [$orId, $orderId]
            );
            
            return [
                'or_id' => $orId,
                'or_number' => $nextNumber,
                'or_series' => $series['series_code'],
                'or_full_number' => $orFullNumber
            ];
            
        } catch (Exception $e) {
            error_log("BIRHelper::assignORNumber Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create VAT transaction record for an order
     * 
     * @param int $orderId The order ID
     * @param float $grandTotal The order total
     * @param string $vatType VAT type (12_percent, exempt, zero_rated)
     * @param string|null $exemptionType Exemption type if applicable
     * @param string|null $exemptionIdNumber Exemption ID number
     * @param string|null $exemptionName Exemption name
     * @return array|false Returns VAT data or false on failure
     */
    public static function createVATTransaction($orderId, $grandTotal, $vatType = '12_percent', $exemptionType = null, $exemptionIdNumber = null, $exemptionName = null) {
        try {
            // Get VAT rate from system settings
            $settings = Database::fetch("SELECT bir_vat_rate FROM system_settings WHERE setting_id = 1");
            $vatRate = $settings['bir_vat_rate'] ?? 12.00;
            
            // Calculate VAT amounts
            if ($vatType === '12_percent') {
                $vatAmount = round($grandTotal / (1 + ($vatRate / 100)) * ($vatRate / 100), 2);
                $taxableAmount = $grandTotal - $vatAmount;
                $nonTaxableAmount = 0;
            } elseif ($vatType === 'exempt' || $vatType === 'zero_rated') {
                $vatAmount = 0;
                $taxableAmount = 0;
                $nonTaxableAmount = $grandTotal;
            } else {
                throw new Exception("Invalid VAT type: $vatType");
            }
            
            // Insert VAT transaction
            Database::execute(
                "INSERT INTO bir_vat_transactions (order_id, vat_type, vat_amount, taxable_amount, non_taxable_amount, exemption_type, exemption_id_number, exemption_name) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$orderId, $vatType, $vatAmount, $taxableAmount, $nonTaxableAmount, $exemptionType, $exemptionIdNumber, $exemptionName]
            );
            
            return [
                'vat_type' => $vatType,
                'vat_amount' => $vatAmount,
                'taxable_amount' => $taxableAmount,
                'non_taxable_amount' => $nonTaxableAmount,
                'exemption_type' => $exemptionType,
                'exemption_id_number' => $exemptionIdNumber,
                'exemption_name' => $exemptionName
            ];
            
        } catch (Exception $e) {
            error_log("BIRHelper::createVATTransaction Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log audit trail entry
     * 
     * @param int|null $orderId The order ID (optional)
     * @param int $userId The user ID
     * @param string $action Action type (create, modify, void, cancel, refund, delete)
     * @param string $tableName Table name affected
     * @param int $recordId Record ID affected
     * @param string|null $fieldChanged Field that changed (optional)
     * @param string|null $oldValue Old value (optional)
     * @param string|null $newValue New value (optional)
     * @param string|null $reason Reason for change (optional)
     * @return bool Success status
     */
    public static function logAuditTrail($orderId, $userId, $action, $tableName, $recordId, $fieldChanged = null, $oldValue = null, $newValue = null, $reason = null) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            Database::execute(
                "INSERT INTO bir_audit_trail (order_id, user_id, action, table_name, record_id, field_changed, old_value, new_value, reason, ip_address, user_agent) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$orderId, $userId, $action, $tableName, $recordId, $fieldChanged, $oldValue, $newValue, $reason, $ipAddress, $userAgent]
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("BIRHelper::logAuditTrail Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Void an OR number
     * 
     * @param int $orId The OR ID
     * @param int $userId The user ID voiding the OR
     * @param string $reason Reason for voiding
     * @return bool Success status
     */
    public static function voidORNumber($orId, $userId, $reason) {
        try {
            Database::connection()->beginTransaction(); // voidORNumber is a standalone operation — nested transaction is intentional here
            
            // Get OR details
            $or = Database::fetch("SELECT * FROM bir_or_numbers WHERE or_id = ?", [$orId]);
            if (!$or) {
                throw new Exception("OR number not found");
            }
            
            if ($or['status'] !== 'issued') {
                throw new Exception("OR number is already voided or cancelled");
            }
            
            // Update OR status
            Database::execute(
                "UPDATE bir_or_numbers SET status = 'void', voided_at = NOW(), voided_by = ?, void_reason = ? WHERE or_id = ?",
                [$userId, $reason, $orId]
            );
            
            // Log audit trail
            self::logAuditTrail($or['order_id'], $userId, 'void', 'bir_or_numbers', $orId, 'status', 'issued', 'void', $reason);
            
            Database::connection()->commit();
            return true;
            
        } catch (Exception $e) {
            Database::connection()->rollBack();
            error_log("BIRHelper::voidORNumber Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get BIR settings
     * 
     * @return array BIR settings
     */
    public static function getBIRSettings() {
        try {
            $settings = Database::fetch(
                "SELECT 
                    company_tin,
                    bir_accreditation_number,
                    bir_accreditation_expiry,
                    bir_permit_number,
                    bir_validity_from,
                    bir_validity_to,
                    bir_min,
                    bir_machine_serial,
                    bir_auto_or_assignment,
                    bir_vat_rate
                 FROM system_settings 
                 WHERE setting_id = 1"
            );
            return $settings;
        } catch (Exception $e) {
            error_log("BIRHelper::getBIRSettings Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if BIR auto-assignment is enabled
     * 
     * @return bool
     */
    public static function isAutoORAssignmentEnabled() {
        $settings = self::getBIRSettings();
        return ($settings['bir_auto_or_assignment'] ?? 0) == 1;
    }
}
