<!-- Close Cashier Session Modal -->
<div class="modal fade" id="closeSessionModal" tabindex="-1" aria-labelledby="closeSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
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
        <!-- Permission Warning (shown when user cannot close) -->
        <div id="closePermissionWarning" class="alert alert-warning d-none mb-4">
          <div class="d-flex align-items-center">
            <span class="fas fa-lock me-3 fs-4"></span>
            <div>
              <strong>View Only Mode</strong>
              <div class="small">You are not authorized to close this session. Please contact your manager to close the session.</div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-12">
            <div class="card mb-4">
              <div class="card-body py-3">
                <div id="closeSummary" class="small">Loading...</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-2 mb-4">
          <div class="col-6">
            <div class="card h-100 border-primary">
              <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center h-100">
                  <div class="icon-circle icon-circle-primary me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 1.125rem;">
                    <span class="fas fa-wallet text-primary"></span>
                  </div>
                  <div class="flex-grow-1 min-width-0">
                    <div class="text-muted small mb-0">Opening Cash</div>
                    <div class="fw-bold text-primary fs-6" id="openingCash">₱0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 border-success">
              <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center h-100">
                  <div class="icon-circle icon-circle-success me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 1.125rem;">
                    <span class="fas fa-chart-line text-success"></span>
                  </div>
                  <div class="flex-grow-1 min-width-0">
                    <div class="text-muted small mb-0">Total Sales</div>
                    <div class="fw-bold text-success fs-6" id="totalSales">₱0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 border-info">
              <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center h-100">
                  <div class="icon-circle icon-circle-info me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 1.125rem;">
                    <span class="fas fa-coins text-info"></span>
                  </div>
                  <div class="flex-grow-1 min-width-0">
                    <div class="text-muted small mb-0">Expected Cash</div>
                    <div class="fw-bold text-info fs-6" id="expectedCash">₱0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 border-warning">
              <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center h-100">
                  <div class="icon-circle icon-circle-warning me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 1.125rem;">
                    <span class="fas fa-balance-scale text-warning"></span>
                  </div>
                  <div class="flex-grow-1 min-width-0">
                    <div class="text-muted small mb-0">Variance</div>
                    <div class="fw-bold text-warning fs-6" id="varianceDisplay">₱0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Type Breakdown -->
        <div id="paymentBreakdownSection"></div>

        <div class="row g-3 mt-4">
          <div class="col-12">
            <label class="form-label fw-semibold" for="closingCash">Actual Closing Cash (₱) <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-end fw-bold" id="closingCash" name="closingCash" placeholder="0.00" oninput="computeVariance()" autofocus>
            <div class="form-text">Physical cash count at end of shift.</div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" for="closingNotes">Closing Notes</label>
            <textarea class="form-control" id="closingNotes" name="closingNotes" rows="2" placeholder="Optional closing notes or remarks"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="closeModalCancelBtn">Cancel</button>
        <button type="button" class="btn btn-danger" id="closeModalSubmitBtn" onclick="submitCloseSession()">
          <span class="fas fa-stop-circle me-1"></span>Close Session
        </button>
        <button type="button" class="btn btn-outline-danger d-none" id="closeModalDisabledBtn" disabled>
          <span class="fas fa-lock me-1"></span>Not Authorized
        </button>
      </div>
    </div>
  </div>
</div>
