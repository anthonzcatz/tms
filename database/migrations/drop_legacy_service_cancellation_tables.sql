-- Migration: Remove legacy service cancellation tables
-- Date: 2026-08-12
-- Scope: POS cancellation is ticket-only. The service cancellation tables are
-- intentionally removed after confirming they contain no data and have no
-- foreign-key dependents in the target database.
--
-- Run only after taking a database backup and verifying the preflight query:
-- SELECT TABLE_NAME, TABLE_ROWS
-- FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME IN ('service_cancellations', 'service_refunds');

DROP TABLE IF EXISTS `service_refunds`;
DROP TABLE IF EXISTS `service_cancellations`;
