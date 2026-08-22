<!-- Cancel Ticket Modal -->
<div class="modal fade" id="cancelTicketModal" tabindex="-1" aria-labelledby="cancelTicketModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape bg-danger">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="cancelTicketModalLabel">
            <span class="fas fa-times-circle me-2"></span>Cancel Transaction
          </h4>
          <p class="fs-10 mb-0 text-white" id="cancelTicketModalSubtitle">Process cancellation with refund</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-4">
        <!-- Cancellation Settings Info -->
        <div class="alert alert-info d-flex align-items-center mb-4">
          <span class="fas fa-info-circle me-3 fs-4"></span>
          <div>
            <strong>Cancellation Policy:</strong>
            <ul class="mb-0 mt-2 ps-3 fs-10">
              <li>Refund: Amount will be refunded from cashier cash to passenger</li>
              <li id="cancelPolicyProcessing">Processing: Loading...</li>
              <li id="cancelPolicyApproval">Approval: Loading...</li>
            </ul>
          </div>
        </div>

        <!-- Pending Cancellation Alert -->
        <div id="pendingCancellationAlert" class="alert alert-warning d-flex align-items-center mb-4 d-none">
          <span class="fas fa-clock me-3 fs-4"></span>
          <div>
            <strong>Pending Cancellation:</strong> <span id="pendingCancellationText">This transaction has a pending cancellation request awaiting approval.</span>
            <div class="mt-1 small text-muted">Requested by: <span id="pendingRequestedBy">-</span> on <span id="pendingRequestedAt">-</span></div>
          </div>
        </div>

        <div class="alert alert-warning d-flex align-items-center mb-4" id="cancelImportantAlert">
          <span class="fas fa-exclamation-triangle me-3 fs-4"></span>
          <div>
            <strong>Important:</strong> This action cannot be undone. Please review the details before confirming.
          </div>
        </div>

        <div class="row">
          <!-- Left Column: Ticket Details -->
          <div class="col-md-6">
            <div id="cancelTicketDetails" class="card bg-soft-light mb-3" style="display: none;">
              <div class="card-body p-3">
                <h6 class="card-title mb-3 fw-bold"><span class="fas fa-info-circle me-2" id="cancelDetailsIcon"></span><span id="cancelDetailsTitle">Ticket Details</span></h6>
                <div class="row g-2">
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10" id="cancelPassengerLabel">Passenger</small>
                    <span id="cancelPassengerName" class="fw-semibold">-</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10" id="cancelTravelDateLabel">Ticket Number</small>
                    <span id="cancelTravelDate" class="fw-semibold">-</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10" id="cancelRouteLabel">Route</small>
                    <span id="cancelRoute" class="fw-semibold">-</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Provider</small>
                    <span id="cancelProvider" class="fw-semibold">-</span>
                  </div>
                  <div class="col-4">
                    <small class="text-muted d-block mb-1 fs-10" id="cancelBaseAmountLabel">Base fare</small>
                    <span id="cancelBaseAmount" class="fw-semibold">₱0.00</span>
                  </div>
                  <div class="col-4" id="cancelServiceFeeContainer">
                    <small class="text-muted d-block mb-1 fs-10">Service Fee</small>
                    <span id="cancelServiceFee" class="fw-semibold">₱0.00</span>
                  </div>
                  <div class="col-4">
                    <small class="text-muted d-block mb-1 fs-10">Total Amount</small>
                    <span id="cancelTotalAmount" class="fw-bold text-primary">₱0.00</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Status</small>
                    <span id="cancelStatus" class="fw-semibold">-</span>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block mb-1 fs-10">Transaction Date</small>
                    <span id="cancelTxnDate" class="fw-semibold">-</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Breakdown -->
            <div id="cancelPaymentBreakdown" class="card bg-soft-light mb-3" style="display: none;">
              <div class="card-body p-3">
                <h6 class="card-title mb-2 fw-bold"><span class="fas fa-credit-card me-2"></span>Original Payment</h6>
                <div id="cancelPaymentMethods" class="small">
                  <!-- Payment methods will be populated here -->
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column: Input Fields -->
          <div class="col-md-6">
            <input type="hidden" id="cancelTxnType" value="TICKET">

            <div class="mb-3" id="cancelOperationTypeRow" style="display: none;">
              <label class="form-label fw-semibold" for="cancelOperationType">Operation <span class="text-danger">*</span></label>
              <select class="form-select" id="cancelOperationType" onchange="toggleTicketAdjustmentFields()">
                <option value="REFUND">Cancellation / Refund</option>
                <option value="VOID">Void / Cancellation — No Refund</option>
              </select>
              <div class="form-text text-muted" id="cancelOperationHint">Refund the eligible amount through the original payment sources.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="cancelTicketCode">Transaction Code</label>
              <input type="text" class="form-control" id="cancelTicketCode" placeholder="Enter transaction code (e.g., TKT-20260515-123456-789-01)">
            </div>

            <!-- Refund Breakdown -->
            <div id="cancelRefundBreakdown" class="card bg-soft-success mb-3" style="display: none;">
              <div class="card-body p-3">
                <h6 class="card-title mb-2 fw-bold"><span class="fas fa-hand-holding-usd me-2"></span>Refund Distribution</h6>
                <div id="cancelRefundMethods" class="small">
                  <!-- Refund breakdown will be populated here -->
                </div>
              </div>
            </div>

            <div class="mb-3" id="cancelRefundAmountRow">
              <label class="form-label fw-semibold" for="cancelRefundAmount">Gross Refund Amount (₱)</label>
              <input type="number" class="form-control" id="cancelRefundAmount" placeholder="0.00" min="0" step="0.01">
              <small class="text-muted" id="cancelRefundHint">Refund will be given from cashier cash. Service Fee is non-refundable: <span id="cancelServiceFeeDisplay" style="display: none;">₱0.00</span>.</small>
            </div>

            <div class="mb-3" id="cancelVoidFeeSection" style="display:none;">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="cancelVoidFeeEnabled" onchange="toggleTicketAdjustmentFields()">
                <label class="form-check-label fw-semibold" for="cancelVoidFeeEnabled">Add Void Fee</label>
              </div>
              <div id="cancelVoidFeeRow" style="display:none;">
                <label class="form-label fw-semibold" for="cancelVoidFee">Void Fee (₱)</label>
                <input type="number" class="form-control" id="cancelVoidFee" placeholder="0.00" min="0" step="0.01" value="0.00" oninput="toggleTicketAdjustmentFields()">
                <small class="text-muted">Optional fee recorded as Void income. This field is not used for refunds.</small>
              </div>
            </div>

            <div class="mb-3" id="cancelVoidServiceFeeSection" style="display:none;">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="cancelVoidServiceFeeEnabled" onchange="toggleTicketAdjustmentFields()">
                <label class="form-check-label fw-semibold" for="cancelVoidServiceFeeEnabled">Add Service Fee</label>
              </div>
              <div id="cancelVoidServiceFeeRow" style="display:none;">
                <label class="form-label fw-semibold" for="cancelVoidServiceFee">Service Fee (₱)</label>
                <input type="number" class="form-control" id="cancelVoidServiceFee" placeholder="0.00" min="0" step="0.01" value="0.00" oninput="toggleTicketAdjustmentFields()">
                <small class="text-muted">Optional service fee recorded as income for this Void. It is not returned to the customer.</small>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="cancelReasonCategory">Reason Category <span class="text-danger">*</span></label>
              <select class="form-select" id="cancelReasonCategory" onchange="syncTicketResponsibilityFromReason(); toggleTicketAdjustmentFields()">
                <option value="CUSTOMER_REQUEST">Customer requested</option>
                <option value="CUSTOMER_ERROR">Customer error</option>
                <option value="CASHIER_ERROR">Cashier error</option>
                <option value="OTHER">Other</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="cancelResponsibility">Responsibility <span class="text-danger">*</span></label>
              <select class="form-select" id="cancelResponsibility" onchange="toggleTicketAdjustmentFields()">
                <option value="NONE">No responsibility charge</option>
                <option value="CUSTOMER">Customer</option>
                <option value="CASHIER">Cashier</option>
              </select>
              <small class="text-muted" id="cancelResponsibilityHint">Customer responsibility is deducted from the refund.</small>
            </div>

            <div class="mb-3" id="cancelResponsibilityAmountRow" style="display:none;">
              <label class="form-label fw-semibold" for="cancelResponsibilityAmount">Responsibility Amount (₱)</label>
              <input type="number" class="form-control" id="cancelResponsibilityAmount" placeholder="0.00" min="0" step="0.01" value="0.00">
              <small class="text-muted" id="cancelResponsibilityAmountHint">Optional amount for this adjustment.</small>
            </div>

            <div class="mb-3" id="cancelResponsibleCashierRow" style="display:none;">
              <label class="form-label fw-semibold" for="cancelResponsibleCashier">Responsible Cashier <span class="text-danger">*</span></label>
              <select class="form-select" id="cancelResponsibleCashier">
                <option value="">Select responsible cashier</option>
              </select>
              <small class="text-muted">All active cashiers assigned to this ticket's branch are available. Manager approval is required; the responsibility charge is recorded against the selected cashier even without an open session.</small>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="cancelReason">Reason Details <span class="text-danger">*</span></label>
              <textarea class="form-control" id="cancelReason" rows="4" placeholder="Enter reason for cancellation" required></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmCancelBtn" onclick="confirmCancelTicket()">
          <span class="fas fa-check me-1"></span>Confirm Cancellation
        </button>
      </div>
    </div>
  </div>
</div>
