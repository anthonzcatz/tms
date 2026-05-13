<!-- Open Cashier Session Modal -->
<div class="modal fade" id="openSessionModal" tabindex="-1" aria-labelledby="openSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="openSessionModalLabel">
            <span class="fas fa-play-circle me-2"></span>Open Cashier Session
          </h4>
          <p class="fs-10 mb-0 text-white">Start your shift to begin processing transactions</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold" for="sessionBranchId">Branch <span class="text-danger">*</span></label>
            <select class="form-select" id="sessionBranchId" name="sessionBranchId">
              <?php foreach ($branches as $b): ?>
                <option value="<?php echo $b['branch_id']; ?>" <?php echo ($userBranchId == $b['branch_id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($b['branch_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" for="sessionOpeningCash">Opening Cash Balance (₱)</label>
            <input type="number" class="form-control" id="sessionOpeningCash" name="sessionOpeningCash" value="0.00" min="0" step="0.01">
            <div class="form-text">Count your starting cash and enter here.</div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" for="sessionNotes">Notes</label>
            <textarea class="form-control" id="sessionNotes" name="sessionNotes" rows="2" placeholder="Optional session notes"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="submitOpenSession()">
          <span class="fas fa-play-circle me-1"></span>Open Session
        </button>
      </div>
    </div>
  </div>
</div>
