-- Migration: Allow multiple branch IDs in user_accounts
-- Changes branch_id from bigint (single FK) to VARCHAR(255) (comma-separated IDs)
-- Run this migration before using the multiple branch feature

-- Step 1: Drop the existing foreign key constraint
ALTER TABLE `user_accounts` DROP FOREIGN KEY `user_accounts_ibfk_1`;

-- Step 2: Drop the index on branch_id
ALTER TABLE `user_accounts` DROP INDEX `idx_branch_id`;

-- Step 3: Change branch_id column from bigint to varchar to allow multiple values
ALTER TABLE `user_accounts` MODIFY COLUMN `branch_id` VARCHAR(255) DEFAULT NULL;

-- Note: branch_id will now store comma-separated branch IDs (e.g. "1,2,3")
-- The foreign key constraint is removed since it cannot reference multiple rows
