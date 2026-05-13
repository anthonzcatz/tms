<!-- Edit Bank Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="editAccountModalLabel">
            <span class="fas fa-edit me-2"></span>Edit Bank Account
          </h4>
          <p class="fs-10 mb-0 text-white">Update bank account details</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editAccountId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Bank / Wallet Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editBankName" maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editAccountName" maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Number / Mobile <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editAccountNumber" maxlength="100">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Account Type</label>
            <input type="text" class="form-control" id="editAccountType" maxlength="50">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Branch</label>
            <select class="form-select" id="editBranchId">
              <option value="">Company-wide (All Branches)</option>
              <?php foreach ($branches as $branch): ?>
                <option value="<?php echo $branch['branch_id']; ?>"><?php echo htmlspecialchars($branch['branch_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Payment Method</label>
            <select class="form-select" id="editPaymentMethodId">
              <option value="">Select Payment Method</option>
              <?php foreach ($paymentMethods as $pm): ?>
                <option value="<?php echo $pm['method_id']; ?>"><?php echo htmlspecialchars($pm['method_name']); ?> (<?php echo $pm['method_type']; ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <textarea class="form-control" id="editNotes" rows="2"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <div class="form-check form-switch mt-1">
              <input class="form-check-input" type="checkbox" id="editIsActive" checked>
              <label class="form-check-label" for="editIsActive">Active</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" onclick="submitEditAccount()">
          <span class="fas fa-save me-1"></span>Update Account
        </button>
      </div>
    </div>
  </div>
</div>
