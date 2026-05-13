-- Add created_by field to passenger_accounts table
ALTER TABLE passenger_accounts 
ADD COLUMN created_by BIGINT NULL AFTER created_at,
ADD INDEX idx_created_by (created_by);

-- Add foreign key constraint to user_accounts
ALTER TABLE passenger_accounts 
ADD CONSTRAINT fk_passenger_created_by 
FOREIGN KEY (created_by) REFERENCES user_accounts(user_id) 
ON DELETE SET NULL;
