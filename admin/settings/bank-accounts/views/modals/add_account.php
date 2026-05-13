<!-- Add Bank Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addAccountModalLabel">
            <span class="fas fa-university me-2"></span>Add Bank Account
          </h4>
          <p class="fs-10 mb-0 text-white">Add a company bank or e-wallet account</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Bank / Wallet Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="addBankName" placeholder="e.g. BPI, GCash, BDO" maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="addAccountName" placeholder="Name on the account" maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Number / Mobile <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="addAccountNumber" placeholder="e.g. 0917-123-4567 or account number" maxlength="100">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Type</label>
            <input type="text" class="form-control" id="addAccountType" placeholder="e.g. Savings, Checking, E-Wallet" maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Branch</label>
            <select class="form-select" id="addBranchId">
              <option value="">Company-wide (All Branches)</option>
              <?php foreach ($branches as $branch): ?>
                <option value="<?php echo $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Leave blank if this account is used by all branches.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Payment Method</label>
            <select class="form-select" id="addPaymentMethodId">
              <option value="">Select Payment Method</option>
              <?php foreach ($paymentMethods as $pm): ?>
                <option value="<?php echo $pm['method_id']; ?>"><?php echo htmlspecialchars($pm['method_name']); ?> (<?php echo $pm['method_type']; ?>)</option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Link to a payment method (Bank Transfer, GCash, etc.)</div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <textarea class="form-control" id="addNotes" rows="2" placeholder="Optional notes"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitAddAccount()">
          <span class="fas fa-save me-1"></span>Save Account
        </button>
      </div>
    </div>
  </div>
</div>
