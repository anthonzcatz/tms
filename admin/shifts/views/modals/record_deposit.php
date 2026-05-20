<!-- Record Deposit Modal -->
<div class="modal fade" id="recordDepositModal" tabindex="-1" aria-labelledby="recordDepositModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="recordDepositModalLabel">
            <span class="fas fa-university me-2"></span>Record Cash Deposit
          </h4>
          <p class="fs-10 mb-0 text-white">Record shift cash deposit to bank account</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Session ID</label>
            <input type="text" class="form-control" id="depositSessionId" readonly>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Actual Cash Amount</label>
            <input type="text" class="form-control" id="depositAmount" readonly>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Bank Account <span class="text-danger">*</span></label>
            <select class="form-select" id="depositBankAccountId" required>
              <option value="">Select Bank Account</option>
              <?php foreach ($bankAccounts as $bank): ?>
                <option value="<?php echo $bank['bank_account_id']; ?>">
                  <?php echo htmlspecialchars($bank['bank_name']); ?> - <?php echo htmlspecialchars($bank['account_name']); ?> (<?php echo htmlspecialchars($bank['account_number']); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Deposit Amount</label>
            <input type="number" class="form-control" id="depositCustomAmount" step="0.01" placeholder="Leave empty to use actual cash amount">
            <div class="form-text">Leave empty to deposit the full actual cash amount</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="submitRecordDeposit()">
          <span class="fas fa-save me-1"></span>Record Deposit
        </button>
      </div>
    </div>
  </div>
</div>
