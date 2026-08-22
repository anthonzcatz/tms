<!-- Open Cashier Session Modal -->
<div class="modal fade" id="openSessionModal" tabindex="-1" aria-labelledby="openSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered" role="document">
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
              <?php
              // Parse branch IDs for comparison (handles comma-separated like "1,2")
              $userBranchIds = array_map('trim', explode(',', $userBranchId ?? ''));
              foreach ($branches as $b):
                $isSelected = in_array($b['branch_id'], $userBranchIds);
              ?>
                <option value="<?php echo $b['branch_id']; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($b['branch_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" for="sessionOpeningCash">Opening Cash Balance (₱) <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-end fw-bold" id="sessionOpeningCash" name="sessionOpeningCash" placeholder="0.00" inputmode="decimal" pattern="[0-9,.]*" onkeypress="return /[0-9.,]/.test(event.key)" oninput="formatNumberInput(this)" required autofocus>
            <div class="form-text">Count your starting cash and enter here.</div>
          </div>
          <div class="col-12" id="cashierTransportAccessSection" style="display:none;">
            <div class="card border-primary bg-soft-primary">
              <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <div>
                    <h6 class="fw-bold mb-1"><span class="fas fa-route me-2 text-primary"></span>Transportation Type Access</h6>
                    <div class="small text-muted">Cashier Only · By Transportation Type</div>
                  </div>
                  <span class="badge bg-soft-primary text-primary" id="cashierTransportAccessMode">Loading...</span>
                </div>
                <div id="cashierTransportAccessStatus" class="small text-muted mb-2">Loading your current access...</div>
                <div id="cashierTransportTypeForm" class="row g-2" style="display:none;">
                  <div class="col-6 col-md-3">
                    <div class="form-check">
                      <input class="form-check-input cashier-transport-type" type="checkbox" value="airline" id="cashierTransportAirline">
                      <label class="form-check-label" for="cashierTransportAirline">Airlines</label>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="form-check">
                      <input class="form-check-input cashier-transport-type" type="checkbox" value="shipping" id="cashierTransportShipping">
                      <label class="form-check-label" for="cashierTransportShipping">Shipping</label>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="form-check">
                      <input class="form-check-input cashier-transport-type" type="checkbox" value="bus" id="cashierTransportBus">
                      <label class="form-check-label" for="cashierTransportBus">Bus Lines</label>
                    </div>
                  </div>
                  <div class="col-6 col-md-3">
                    <div class="form-check">
                      <input class="form-check-input cashier-transport-type" type="checkbox" value="other" id="cashierTransportOther">
                      <label class="form-check-label" for="cashierTransportOther">Other</label>
                    </div>
                  </div>
                  <div class="col-12 mt-2">
                    <small class="text-muted">Changes will be saved when you open the session.</small>
                  </div>
                </div>
              </div>
            </div>
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
