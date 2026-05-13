<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addTransactionModalLabel">
            <span class="fas fa-plus-circle me-2"></span>Add Transaction
          </h4>
          <p class="fs-10 mb-0 text-white">Create a new wallet transaction</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <form id="addTransactionForm">
        <div class="modal-body">
          <input type="hidden" id="transaction_id" name="transaction_id">
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light border-0 py-2">
              <h6 class="mb-0 fw-bold">Transaction Details</h6>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="addWalletId" class="form-label fw-bold">Wallet <span class="text-danger">*</span></label>
                  <select class="form-select" id="addWalletId" name="wallet_id" required>
                    <option value="">Select Wallet</option>
                    <?php foreach ($wallets as $wallet): ?>
                      <option value="<?php echo $wallet['wallet_id']; ?>">
                        <?php echo htmlspecialchars($wallet['wallet_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="addTxnType" class="form-label fw-bold">Transaction Type <span class="text-danger">*</span></label>
                  <select class="form-select" id="addTxnType" name="txn_type" required>
                    <option value="">Select Type</option>
                    <option value="TOPUP">Topup</option>
                    <option value="SALE">Sale</option>
                    <option value="REFUND">Refund</option>
                    <option value="ADJUSTMENT">Adjustment</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="addDirection" class="form-label fw-bold">Direction <span class="text-danger">*</span></label>
                  <select class="form-select" id="addDirection" name="direction" required>
                    <option value="">Select Direction</option>
                    <option value="IN">In</option>
                    <option value="OUT">Out</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="addAmount" class="form-label fw-bold">Amount <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="addAmount" name="amount" required placeholder="0.00" pattern="[0-9,.]*">
                </div>
                <div class="col-md-12">
                  <label for="addRemarks" class="form-label fw-bold">Remarks</label>
                  <textarea class="form-control" id="addRemarks" name="remarks" rows="3" placeholder="Enter transaction remarks"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="saveTransaction()">
          <span class="fas fa-save me-2"></span>Save Transaction
        </button>
      </div>
    </div>
  </div>
</div>
