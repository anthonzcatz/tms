<!-- Manager Session Control Modal -->
<div class="modal fade" id="managerSessionModal" tabindex="-1" aria-labelledby="managerSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-4 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="managerSessionModalLabel">
            <span class="fas fa-user-cog me-2"></span>Manager Session Control
          </h4>
          <p class="fs-10 mb-0 text-white" id="managerSessionSubtitle">Open/Close session for cashier</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <input type="hidden" id="managerSessionCashierId">
        <input type="hidden" id="managerSessionCashierName">
        
        <!-- OPEN SESSION SECTION -->
        <div id="openSessionSection">
          <h6 class="fw-bold text-primary mb-3"><span class="fas fa-play-circle me-2"></span>Open New Session</h6>
          
          <!-- Filters -->
          <div class="card bg-light mb-3">
            <div class="card-body py-2">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Filter by Branch</label>
                  <select class="form-select form-select-sm" id="managerOpenBranchFilter" onchange="filterCashierOptions()">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                      <option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Search Cashier</label>
                  <input type="text" class="form-control form-control-sm" id="managerOpenCashierSearch" placeholder="Type to search..." oninput="filterCashierOptions()">
                </div>
              </div>
            </div>
          </div>
          
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Cashier <span class="text-danger">*</span></label>
              <select class="form-select" id="managerOpenCashierSelect" onchange="onCashierSelect(this)">
                <option value="">Select Cashier</option>
                <!-- Options loaded dynamically via JS -->
              </select>
              <input type="hidden" id="managerSessionCashierId">
              <input type="hidden" id="managerSessionCashierName">
              <input type="hidden" id="managerOpenBranch">
              <div class="form-text text-muted small">
                <span class="fas fa-info-circle me-1"></span>
                Only active cashiers with assigned branches and no open sessions are shown.
              </div>
            </div>
            <div class="col-12">
              <div class="card bg-light border-primary">
                <div class="card-body py-2">
                  <div class="d-flex align-items-center">
                    <span class="fas fa-building text-primary me-2 fs-5"></span>
                    <div>
                      <div class="text-muted small">Assigned Branch</div>
                      <div class="fw-bold" id="managerOpenBranchDisplay">—</div>
                    </div>
                  </div>
                </div>
              </div>
              <div id="noBranchWarning" class="alert alert-warning d-none mt-2">
                <span class="fas fa-exclamation-triangle me-1"></span>
                <small>This cashier has no assigned branch and cannot open a session.</small>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Starting Cash (₱) <span class="text-danger">*</span></label>
              <input type="number" class="form-control" id="managerOpenStartingCash" placeholder="0.00" step="0.01" min="0">
              <div class="form-text small">Physical cash at the start of shift</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Expected End Time</label>
              <input type="time" class="form-control" id="managerOpenExpectedEnd">
              <div class="form-text small">Optional: Expected shift end time</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Notes</label>
              <textarea class="form-control" id="managerOpenNotes" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
          <div class="d-grid mt-4">
            <button type="button" class="btn btn-success btn-lg" id="managerOpenSubmitBtn" onclick="submitManagerOpenSession()">
              <span class="fas fa-play-circle me-1"></span>Open Session
            </button>
          </div>
          <hr class="my-4">
        </div>

        <!-- CLOSE SESSION SECTION -->
        <div id="closeSessionSection" style="display: none;">
          <h6 class="fw-bold text-danger mb-3"><span class="fas fa-stop-circle me-2"></span>Close Active Session</h6>
          
          <!-- Filters -->
          <div class="card bg-light mb-3">
            <div class="card-body py-2">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Filter by Branch</label>
                  <select class="form-select form-select-sm" id="managerCloseBranchFilter" onchange="filterCloseSessionOptions()">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $b): ?>
                      <option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">Search Cashier</label>
                  <input type="text" class="form-control form-select-sm" id="managerCloseCashierSearch" placeholder="Type to search..." oninput="filterCloseSessionOptions()">
                </div>
              </div>
            </div>
          </div>
          
          <!-- Session Selection -->
          <div class="row g-3 mb-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Select Open Session <span class="text-danger">*</span></label>
              <select class="form-select" id="managerCloseSessionSelect" onchange="onCloseSessionSelect(this)">
                <option value="">Select Cashier with Open Session</option>
              </select>
              <input type="hidden" id="managerCloseSessionId">
              <div class="form-text text-muted small">
                <span class="fas fa-info-circle me-1"></span>
                Shows all open cashier sessions. Select one to view details and close.
              </div>
            </div>
          </div>
          
          <!-- Session Summary Cards (shown when session selected) -->
          <div id="closeSessionSummary" class="d-none">
            <div class="row g-2 mb-3">
              <!-- Info Cards -->
              <div class="col-6">
                <div class="card bg-light h-100">
                  <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center h-100">
                      <div class="flex-grow-1">
                        <div class="text-muted small">Session Started</div>
                        <div class="fw-bold fs-9" id="closeSummaryStarted">—</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-6">
                <div class="card bg-light h-100">
                  <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center h-100">
                      <div class="flex-grow-1">
                        <div class="text-muted small">Branch</div>
                        <div class="fw-bold fs-9" id="closeSummaryBranch">—</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Financial Cards with Icons -->
              <div class="col-6">
                <div class="card h-100 border-primary">
                  <div class="card-body py-3 px-3">
                    <div class="d-flex align-items-center h-100">
                      <div class="icon-circle icon-circle-primary me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 1.125rem;">
                        <span class="fas fa-wallet text-primary"></span>
                      </div>
                      <div class="flex-grow-1 min-width-0">
                        <div class="text-muted small mb-0">Opening Cash</div>
                        <div class="fw-bold text-primary fs-6" id="closeSummaryOpening">₱0.00</div>
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
                        <div class="fw-bold text-success fs-6" id="closeSummarySales">₱0.00</div>
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
                        <div class="fw-bold text-info fs-6" id="closeSummaryExpected">₱0.00</div>
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
                        <span class="fas fa-receipt text-warning"></span>
                      </div>
                      <div class="flex-grow-1 min-width-0">
                        <div class="text-muted small mb-0">Transactions</div>
                        <div class="fw-bold text-warning fs-6" id="closeSummaryTxns">0</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Payment Type Breakdown -->
            <div id="closePaymentBreakdown" class="mb-3">
              <!-- Loaded dynamically via JS -->
            </div>
            
            <!-- Variance Calculator -->
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Actual Closing Cash (₱) <span class="text-danger">*</span></label>
                <input type="number" class="form-control form-control-lg text-end fw-bold" id="managerCloseCash" placeholder="0.00" step="0.01" min="0" oninput="computeManagerCloseVariance()">
                <div class="form-text">Enter physical cash count from drawer</div>
              </div>
              <div class="col-12">
                <div class="card" id="managerVarianceCard">
                  <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <div class="text-muted small">Variance</div>
                        <div class="fw-bold fs-5" id="managerVarianceDisplay">₱0.00</div>
                      </div>
                      <div id="managerVarianceStatus">
                        <span class="badge bg-soft-secondary text-secondary">Enter closing cash</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Closing Notes</label>
                <textarea class="form-control" id="managerCloseNotes" rows="2" placeholder="Optional notes or remarks about the closing..."></textarea>
              </div>
            </div>
            <div class="d-grid mt-4">
              <button type="button" class="btn btn-danger btn-lg" id="managerCloseSubmitBtn" onclick="submitManagerCloseSession()">
                <span class="fas fa-stop-circle me-1"></span>Close Session
              </button>
            </div>
          </div>
          
          <!-- No Session Selected State -->
          <div id="closeSessionEmptyState" class="text-center py-4 text-muted">
            <span class="fas fa-hand-pointer fs-1 mb-2"></span>
            <p class="mb-0">Select a session above to view details and close</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
