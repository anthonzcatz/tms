-- Create bank_transactions table to track all bank movements
CREATE TABLE bank_transactions (
    bank_txn_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    bank_account_id BIGINT NOT NULL,
    
    txn_code VARCHAR(100) UNIQUE,
    
    txn_type ENUM(
        'RECEIPT',
        'DISBURSEMENT',
        'TRANSFER_IN',
        'TRANSFER_OUT',
        'ADJUSTMENT',
        'REFUND'
    ),
    
    direction ENUM(
        'IN',
        'OUT'
    ),
    
    amount DECIMAL(12,2),
    
    balance_before DECIMAL(12,2),
    balance_after DECIMAL(12,2),
    
    reference_table VARCHAR(100),
    reference_id BIGINT,
    
    remarks TEXT NULL,
    
    created_by BIGINT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (bank_account_id)
        REFERENCES bank_accounts(bank_account_id),
    
    FOREIGN KEY (created_by)
        REFERENCES user_accounts(user_id)
);
