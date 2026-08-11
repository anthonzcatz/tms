-- Migration: Ticket Variant Inventory and Fulfillment - Phase 1 Core Schema
-- Date: 2026-07-16
-- Purpose: Create core ticket inventory tables and extend existing sales tables
--          with variant_id so the negative-stock setting can be enforced later.

-- =========================================================
-- 1. PROVIDER TICKET VARIANTS
-- =========================================================
CREATE TABLE IF NOT EXISTS `provider_ticket_variants` (
    `variant_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `provider_id` bigint(20) NOT NULL,
    `variant_code` varchar(50) NOT NULL,
    `variant_name` varchar(150) NOT NULL,
    `description` text DEFAULT NULL,
    `display_color` varchar(20) DEFAULT NULL,
    `stock_controlled` tinyint(1) DEFAULT 1,
    `requires_ticket_number` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_by` bigint(20) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`variant_id`),
    UNIQUE KEY `uq_provider_variant_code` (`provider_id`, `variant_code`),
    KEY `idx_provider_id` (`provider_id`),
    KEY `idx_is_active` (`is_active`),
    CONSTRAINT `fk_ptv_provider_id` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ptv_created_by` FOREIGN KEY (`created_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Provider-specific ticket variants (e.g. color/class)';

-- =========================================================
-- 2. BRANCH TICKET STOCK PROJECTION
-- =========================================================
CREATE TABLE IF NOT EXISTS `branch_ticket_stocks` (
    `stock_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `branch_id` bigint(20) NOT NULL,
    `provider_id` bigint(20) NOT NULL,
    `variant_id` bigint(20) NOT NULL,
    `on_hand_qty` int(11) DEFAULT 0,
    `reserved_qty` int(11) DEFAULT 0,
    `reorder_level` int(11) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`stock_id`),
    UNIQUE KEY `uq_branch_provider_variant` (`branch_id`, `provider_id`, `variant_id`),
    KEY `idx_bts_branch_id` (`branch_id`),
    KEY `idx_bts_provider_id` (`provider_id`),
    KEY `idx_bts_variant_id` (`variant_id`),
    KEY `idx_bts_low_stock` (`branch_id`, `provider_id`, `variant_id`, `on_hand_qty`, `reorder_level`),
    CONSTRAINT `fk_bts_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bts_provider_id` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bts_variant_id` FOREIGN KEY (`variant_id`) REFERENCES `provider_ticket_variants` (`variant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Current stock projection per branch/provider/variant';

-- =========================================================
-- 3. TICKET STOCK REQUESTS
-- =========================================================
CREATE TABLE IF NOT EXISTS `ticket_stock_requests` (
    `stock_request_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `request_code` varchar(50) NOT NULL,
    `source_branch_id` bigint(20) DEFAULT NULL,
    `destination_branch_id` bigint(20) NOT NULL,
    `provider_id` bigint(20) NOT NULL,
    `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED','DISPATCHED','RECEIVED','PARTIALLY_RECEIVED','DISPUTED','CLOSED','CANCELLED') DEFAULT 'DRAFT',
    `request_reason` enum('REPLENISHMENT','OPENING_BALANCE','TRANSFER','EMERGENCY','RETURN_REPLACEMENT','OTHER') DEFAULT 'OTHER',
    `requested_by` bigint(20) NOT NULL,
    `requested_at` timestamp NULL DEFAULT NULL,
    `approved_by` bigint(20) DEFAULT NULL,
    `approved_at` timestamp NULL DEFAULT NULL,
    `dispatched_by` bigint(20) DEFAULT NULL,
    `dispatched_at` timestamp NULL DEFAULT NULL,
    `received_by` bigint(20) DEFAULT NULL,
    `received_at` timestamp NULL DEFAULT NULL,
    `closed_by` bigint(20) DEFAULT NULL,
    `closed_at` timestamp NULL DEFAULT NULL,
    `remarks` text DEFAULT NULL,
    `rejection_reason` text DEFAULT NULL,
    `cancellation_reason` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`stock_request_id`),
    UNIQUE KEY `uq_request_code` (`request_code`),
    KEY `idx_tsr_source_branch` (`source_branch_id`),
    KEY `idx_tsr_destination_branch` (`destination_branch_id`),
    KEY `idx_tsr_provider_id` (`provider_id`),
    KEY `idx_tsr_status` (`status`),
    KEY `idx_tsr_requested_at` (`requested_at`),
    CONSTRAINT `fk_tsr_source_branch` FOREIGN KEY (`source_branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tsr_destination_branch` FOREIGN KEY (`destination_branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tsr_provider_id` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tsr_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tsr_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tsr_dispatched_by` FOREIGN KEY (`dispatched_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tsr_received_by` FOREIGN KEY (`received_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tsr_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Header for ticket stock requests and branch transfers';

-- =========================================================
-- 4. TICKET STOCK REQUEST ITEMS
-- =========================================================
CREATE TABLE IF NOT EXISTS `ticket_stock_request_items` (
    `request_item_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `stock_request_id` bigint(20) NOT NULL,
    `variant_id` bigint(20) NOT NULL,
    `requested_qty` int(11) NOT NULL,
    `approved_qty` int(11) DEFAULT 0,
    `dispatched_qty` int(11) DEFAULT 0,
    `received_qty` int(11) DEFAULT 0,
    `rejected_qty` int(11) DEFAULT 0,
    `discrepancy_qty` int(11) DEFAULT 0,
    `ticket_series_from` varchar(100) DEFAULT NULL,
    `ticket_series_to` varchar(100) DEFAULT NULL,
    `remarks` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`request_item_id`),
    UNIQUE KEY `uq_request_variant` (`stock_request_id`, `variant_id`),
    KEY `idx_tsri_stock_request_id` (`stock_request_id`),
    KEY `idx_tsri_variant_id` (`variant_id`),
    CONSTRAINT `fk_tsri_stock_request_id` FOREIGN KEY (`stock_request_id`) REFERENCES `ticket_stock_requests` (`stock_request_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tsri_variant_id` FOREIGN KEY (`variant_id`) REFERENCES `provider_ticket_variants` (`variant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Line items for ticket stock requests';

-- =========================================================
-- 5. TICKET STOCK MOVEMENT LEDGER
-- =========================================================
CREATE TABLE IF NOT EXISTS `ticket_stock_movements` (
    `movement_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `branch_id` bigint(20) NOT NULL,
    `provider_id` bigint(20) NOT NULL,
    `variant_id` bigint(20) NOT NULL,
    `movement_type` enum('OPENING_BALANCE','POS_SALE','POS_SALE_REVERSAL','RETURN_TO_SOURCE','DAMAGE_OR_VOID','STOCK_IN','STOCK_OUT','DISPATCH','RECEIPT','ADJUSTMENT','REPLACEMENT','NEGATIVE_BALANCE') NOT NULL,
    `quantity_delta` int(11) NOT NULL,
    `balance_before` int(11) NOT NULL,
    `balance_after` int(11) NOT NULL,
    `reference_type` varchar(50) DEFAULT NULL,
    `reference_id` bigint(20) DEFAULT NULL,
    `source_branch_id` bigint(20) DEFAULT NULL,
    `destination_branch_id` bigint(20) DEFAULT NULL,
    `ticket_number_from` varchar(100) DEFAULT NULL,
    `ticket_number_to` varchar(100) DEFAULT NULL,
    `remarks` text DEFAULT NULL,
    `performed_by` bigint(20) NOT NULL,
    `performed_at` timestamp NULL DEFAULT NULL,
    `approved_by` bigint(20) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`movement_id`),
    KEY `idx_tsm_branch_provider_variant` (`branch_id`, `provider_id`, `variant_id`),
    KEY `idx_tsm_movement_type` (`movement_type`),
    KEY `idx_tsm_reference` (`reference_type`, `reference_id`),
    KEY `idx_tsm_performed_at` (`performed_at`),
    KEY `idx_tsm_performed_by` (`performed_by`),
    CONSTRAINT `fk_tsm_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tsm_provider_id` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tsm_variant_id` FOREIGN KEY (`variant_id`) REFERENCES `provider_ticket_variants` (`variant_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tsm_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tsm_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tsm_source_branch` FOREIGN KEY (`source_branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tsm_destination_branch` FOREIGN KEY (`destination_branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Immutable stock movement ledger for audit and reconciliation';

-- =========================================================
-- 6. TICKET STOCK DISCREPANCIES
-- =========================================================
CREATE TABLE IF NOT EXISTS `ticket_stock_discrepancies` (
    `discrepancy_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `stock_request_id` bigint(20) NOT NULL,
    `request_item_id` bigint(20) DEFAULT NULL,
    `discrepancy_type` enum('SHORTAGE','EXCESS','DAMAGED','WRONG_VARIANT','SERIAL_MISMATCH','OTHER') NOT NULL,
    `expected_qty` int(11) NOT NULL,
    `actual_qty` int(11) NOT NULL,
    `status` enum('OPEN','INVESTIGATING','RESOLVED','WRITTEN_OFF') DEFAULT 'OPEN',
    `resolution_notes` text DEFAULT NULL,
    `resolved_by` bigint(20) DEFAULT NULL,
    `resolved_at` timestamp NULL DEFAULT NULL,
    `reported_by` bigint(20) NOT NULL,
    `reported_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    PRIMARY KEY (`discrepancy_id`),
    KEY `idx_tsd_stock_request_id` (`stock_request_id`),
    KEY `idx_tsd_request_item_id` (`request_item_id`),
    KEY `idx_tsd_status` (`status`),
    CONSTRAINT `fk_tsd_request_id` FOREIGN KEY (`stock_request_id`) REFERENCES `ticket_stock_requests` (`stock_request_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tsd_request_item_id` FOREIGN KEY (`request_item_id`) REFERENCES `ticket_stock_request_items` (`request_item_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tsd_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_tsd_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `user_accounts` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Formal discrepancy records for receiving and audit';

-- =========================================================
-- 7. EXTEND EXISTING TABLES
-- =========================================================

-- Link ticket transactions to a selected variant
ALTER TABLE `ticket_transactions`
    ADD COLUMN IF NOT EXISTS `variant_id` bigint(20) DEFAULT NULL AFTER `discount_id`;

-- Link POS order items to provider and variant for reporting and reversal
ALTER TABLE `pos_order_items`
    ADD COLUMN IF NOT EXISTS `provider_id` bigint(20) DEFAULT NULL AFTER `reference_id`,
    ADD COLUMN IF NOT EXISTS `variant_id` bigint(20) DEFAULT NULL AFTER `provider_id`;

-- Service type flag controlling whether the POS variant selector is required
ALTER TABLE `service_types`
    ADD COLUMN IF NOT EXISTS `requires_ticket_variant` tinyint(1) DEFAULT 0 AFTER `requires_wallet`;

-- =========================================================
-- 8. OPTIONAL RESERVATIONS TABLE (for concurrent POS checkout)
-- =========================================================
CREATE TABLE IF NOT EXISTS `ticket_stock_reservations` (
    `reservation_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `branch_id` bigint(20) NOT NULL,
    `provider_id` bigint(20) NOT NULL,
    `variant_id` bigint(20) NOT NULL,
    `reserved_qty` int(11) NOT NULL,
    `pos_order_id` bigint(20) DEFAULT NULL,
    `session_id` varchar(100) DEFAULT NULL,
    `expires_at` timestamp NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`reservation_id`),
    UNIQUE KEY `uq_reservation` (`branch_id`, `provider_id`, `variant_id`, `session_id`),
    KEY `idx_expires_at` (`expires_at`),
    CONSTRAINT `fk_tsv_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `business_branches` (`branch_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tsv_provider_id` FOREIGN KEY (`provider_id`) REFERENCES `ticket_providers` (`provider_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tsv_variant_id` FOREIGN KEY (`variant_id`) REFERENCES `provider_ticket_variants` (`variant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Temporary POS stock reservations for concurrency control';
