-- Add per-branch configurable signatories for the Financial Report print
ALTER TABLE `business_branches`
    ADD COLUMN IF NOT EXISTS `financial_report_signatories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
    COMMENT 'JSON array of branch signatory rows with employee and position references';
