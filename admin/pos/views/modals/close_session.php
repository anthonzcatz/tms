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
            <div class="card h-100">
              <div class="card-body py-2">
                <div class="d-flex flex-row align-items-center">
                  <div class="icon-circle icon-circle-primary me-2" style="width: 36px; height: 36px; font-size: 1rem;"><span class="fas fa-wallet text-primary"></span></div>
                  <div>
                    <div class="text-muted small mb-0" style="font-size: 0.75rem;">Opening Cash</div>
                    <div class="fw-bold" style="font-size: 1rem;" id="openingCash">₱0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100">
              <div class="card-body py-2">
                <div class="d-flex flex-row align-items-center">
                  <div class="icon-circle icon-circle-success me-2" style="width: 36px; height: 36px; font-size: 1rem;"><span class="fas fa-chart-line text-success"></span></div>
                  <div>
                    <div class="text-muted small mb-0" style="font-size: 0.75rem;">Total Sales</div>
                    <div class="fw-bold text-success" style="font-size: 1rem;" id="totalSales">₱0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100">
              <div class="card-body py-2">
                <div class="d-flex flex-row align-items-center">
                  <div class="icon-circle icon-circle-info me-2" style="width: 36px; height: 36px; font-size: 1rem;"><span class="fas fa-coins text-info"></span></div>
                  <div>
                    <div class="text-muted small mb-0" style="font-size: 0.75rem;">Expected Cash</div>
                    <div class="fw-bold" style="font-size: 1rem;" id="expectedCash">₱0.00</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100">
              <div class="card-body py-2">
                <div class="d-flex flex-row align-items-center">
                  <div class="icon-circle icon-circle-warning me-2" style="width: 36px; height: 36px; font-size: 1rem;"><span class="fas fa-balance-scale text-warning"></span></div>
                  <div>
                    <div class="text-muted small mb-0" style="font-size: 0.75rem;">Variance</div>
                    <div class="fw-bold text-muted" style="font-size: 1rem;" id="varianceDisplay">₱0.00</div>
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
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="submitCloseSession()">
          <span class="fas fa-stop-circle me-1"></span>Close Session
        </button>
      </div>
    </div>
  </div>
</div>
