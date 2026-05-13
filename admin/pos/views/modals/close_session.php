<!-- Close Cashier Session Modal -->
<div class="modal fade" id="closeSessionModal" tabindex="-1" aria-labelledby="closeSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="closeSessionModalLabel">
            <span class="fas fa-stop-circle me-2"></span>Close Cashier Session
          </h4>
          <p class="fs-10 mb-0 text-white">End your shift and submit cash count</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <div class="alert alert-info mb-0">
              <strong>Session Summary</strong>
              <div id="closeSummary" class="mt-2 small">Loading...</div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" for="closingCash">Actual Closing Cash (₱) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="closingCash" name="closingCash" value="0.00" min="0" step="0.01" oninput="computeVariance()">
            <div class="form-text">Physical cash count at end of shift.</div>
          </div>
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
              <span class="fw-semibold">Expected Cash:</span>
              <span id="expectedCash" class="fw-bold">₱0.00</span>
            </div>
            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded mt-2">
              <span class="fw-semibold">Variance:</span>
              <span id="varianceDisplay" class="fw-bold text-muted">₱0.00</span>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" for="closingNotes">Closing Notes</label>
            <textarea class="form-control" id="closingNotes" name="closingNotes" rows="2" placeholder="Optional closing notes or remarks"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="submitCloseSession()">
          <span class="fas fa-stop-circle me-1"></span>Close Session
        </button>
      </div>
    </div>
  </div>
</div>
