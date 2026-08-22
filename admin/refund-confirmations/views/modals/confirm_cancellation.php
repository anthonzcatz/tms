<!-- Confirm Cancellation Modal -->
<div class="modal fade" id="confirmCancellationModal" tabindex="-1" aria-labelledby="confirmCancellationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape bg-success">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="confirmCancellationModalLabel">
            <span class="fas fa-money-bill-wave me-2"></span>Review Refund Request
          </h4>
          <p class="fs-10 mb-0 text-white">Approve or reject this ticket refund request</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <!-- Left Column: Cancellation Details -->
          <div class="col-lg-6">
            <div class="card bg-soft-light h-100">
              <div class="card-body">
                <h6 class="card-title mb-3"><span class="fas fa-info-circle me-2"></span>Refund Details</h6>
                <div class="row g-3">
                  <div class="col-md-6">
                    <small class="text-muted d-block">Transaction Code</small>
                    <strong id="modalTransactionCode">-</strong>
                  </div>
                  <div class="col-md-6" id="modalTicketNumberContainer" style="display: none;">
                    <small class="text-muted d-block">Ticket Number</small>
                    <strong id="modalTicketNumber" class="text-info">-</strong>
                  </div>
                  <div class="col-md-6">
                    <small class="text-muted d-block">Net Refund Amount</small>
                    <strong id="modalRefundAmount" class="text-success">₱0.00</strong>
                  </div>
                  <div class="col-md-6" id="modalGrossRefundAmountContainer">
                    <small class="text-muted d-block">Gross Refund Amount</small>
                    <strong id="modalGrossRefundAmount" class="text-primary">₱0.00</strong>
                  </div>
                  <div class="col-md-6" id="modalVoidFeeContainer" style="display:none;">
                    <small class="text-muted d-block">Void Fee Income</small>
                    <strong id="modalVoidFee" class="text-warning">₱0.00</strong>
                  </div>
                  <div class="col-md-6" id="modalVoidServiceFeeContainer" style="display:none;">
                    <small class="text-muted d-block">Service Fee Income</small>
                    <strong id="modalVoidServiceFee" class="text-info">₱0.00</strong>
                  </div>
                  <div class="col-12" id="modalRefundBreakdown" style="display: none;">
                    <!-- Refund breakdown will be populated by JS -->
                  </div>
                  <!-- Charge Reversal Warning -->
                  <div class="col-12" id="modalChargeReversalWarning" style="display: none;">
                    <div class="alert alert-warning border-warning border-2">
                      <div class="d-flex align-items-start">
                        <span class="fas fa-exclamation-triangle text-warning fs-4 me-2"></span>
                        <div>
                          <strong class="text-warning">Charge Reversal Notice</strong>
                          <p class="mb-1 small">
                            Approving this cancellation will reverse <strong id="modalChargeReversalAmount" class="text-warning">₱0.00</strong> from the selected Charge Account's outstanding balance.
                          </p>
                          <p class="mb-0 small text-muted">
                            This amount will be deducted from the account's debt in <a href="<?php echo BASE_URL; ?>/admin/charges/" target="_blank">Accounts Receivable</a>.
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <small class="text-muted d-block">Operation</small>
                    <strong id="modalOperationType">Cancellation / Refund</strong>
                  </div>
                  <div class="col-md-6">
                    <small class="text-muted d-block">Refund Type</small>
                    <strong id="modalCancellationType">-</strong>
                  </div>
                  <div class="col-md-6" id="modalResponsibilityContainer">
                    <small class="text-muted d-block">Responsibility</small>
                    <strong id="modalResponsibility">None</strong>
                  </div>
                  <div class="col-md-6" id="modalResponsibilityAmountContainer">
                    <small class="text-muted d-block">Responsibility Amount</small>
                    <strong id="modalResponsibilityAmount">₱0.00</strong>
                  </div>
                  <div class="col-12" id="modalResponsibleCashierContainer" style="display:none;">
                    <label class="form-label small text-muted mb-1" for="modalResponsibleCashier">Responsible Cashier</label>
                    <select class="form-select form-select-sm" id="modalResponsibleCashier">
                      <option value="">Select responsible cashier</option>
                    </select>
                  </div>
                  <div class="col-md-6" id="modalWalletToCreditContainer">
                    <small class="text-muted d-block">Wallet to Credit</small>
                    <strong id="modalWalletToCredit">-</strong>
                  </div>
                  <div class="col-12">
                    <small class="text-muted d-block">Original Payment Sources</small>
                    <div id="modalPaymentSources" class="border rounded p-2 bg-white mt-1"></div>
                  </div>
                  <div class="col-md-6">
                    <small class="text-muted d-block">Passenger</small>
                    <strong id="modalPassenger">-</strong>
                  </div>
                  <div class="col-md-6">
                    <small class="text-muted d-block">Route</small>
                    <strong id="modalRoute">-</strong>
                  </div>
                  <div class="col-md-6">
                    <small class="text-muted d-block">Requested By</small>
                    <strong id="modalRequestedBy">-</strong>
                  </div>
                  <div class="col-12">
                    <small class="text-muted d-block">Reason</small>
                    <strong id="modalReason">-</strong>
                  </div>
                  <div class="col-12">
                    <small class="text-muted d-block">Requested At</small>
                    <strong id="modalRequestedAt">-</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column: Action Section -->
          <div class="col-lg-6">
            <label class="form-label fw-semibold d-block mb-2">Action <span class="text-danger">*</span></label>
            <div class="row g-2 mb-3" id="modalActionChoices">
              <div class="col-6">
                <button type="button" class="btn btn-outline-success w-100 py-3 text-start" id="btnApprove" data-action="approve" onclick="selectCancellationAction('approve')">
                  <span class="fas fa-check-circle fa-lg me-2"></span>
                  <strong>Approve</strong><br>
                  <small class="text-muted">Process refund</small>
                </button>
              </div>
              <div class="col-6">
                <button type="button" class="btn btn-outline-danger w-100 py-3 text-start" id="btnReject" data-action="reject" onclick="selectCancellationAction('reject')">
                  <span class="fas fa-times-circle fa-lg me-2"></span>
                  <strong>Reject</strong><br>
                  <small class="text-muted">Deny request</small>
                </button>
              </div>
            </div>
            <input type="hidden" id="modalAction" value="">

            <!-- Action Info Alert -->
            <div class="alert alert-info d-flex align-items-start mb-3" id="actionInfoAlert">
              <span class="fas fa-info-circle me-2 mt-1"></span>
              <div>
                <strong>What happens on Approval:</strong>
                <ul class="mb-0 mt-1 ps-3 small">
                  <li>Ticket status will change to <strong>Cancelled</strong></li>
                  <li>Cash portion (if any) will be <strong>given to passenger from cashier cash drawer</strong></li>
                  <li>Charge/debt portion (if any) will be <strong>reversed from passenger's outstanding balance</strong></li>
                  <li id="modalWalletRestoreLine">Total refund amount <span id="modalRefundAmountInline" class="fw-bold text-success">₱0.00</span> will be <strong>restored to the provider wallet</strong></li>
                  <li id="modalVariantNoCreditLine" class="d-none text-warning">This ticket uses a consumed variant. Physical availability and provider wallet balance <strong>will not be restored</strong> because the ticket is no longer reusable.</li>
                  <li id="modalWalletTxnRecordLine">A wallet transaction record will be created for audit</li>
                </ul>
              </div>
            </div>

            <div class="alert alert-warning d-flex align-items-start mb-3 d-none" id="rejectInfoAlert">
              <span class="fas fa-exclamation-triangle me-2 mt-1"></span>
              <div>
                <strong>What happens on Rejection:</strong>
                <ul class="mb-0 mt-1 ps-3 small">
                  <li>Refund request will be marked as <strong>Rejected</strong></li>
                  <li>Ticket remains <strong>Active</strong> — no changes to the ticket</li>
                  <li><strong>No refund</strong> will be processed</li>
                  <li>A rejection reason must be provided</li>
                </ul>
              </div>
            </div>

            <div class="mb-3" id="remarksDiv">
              <label class="form-label fw-semibold" for="modalRemarks">Remarks (Optional)</label>
              <textarea class="form-control" id="modalRemarks" rows="2" placeholder="Add any notes about this approval..."></textarea>
            </div>

            <div class="mb-3" id="rejectionReasonDiv" style="display: none;">
              <label class="form-label fw-semibold" for="modalRejectionReason">Rejection Reason <span class="text-danger">*</span></label>
              <textarea class="form-control" id="modalRejectionReason" rows="3" placeholder="Enter reason for rejection"></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-1"></span>Cancel
        </button>
        <button type="button" class="btn btn-success" id="modalSubmitDecision" onclick="submitCancellationDecision()">
          <span class="fas fa-check me-1"></span>Submit Decision
        </button>
      </div>
    </div>
  </div>
</div>
