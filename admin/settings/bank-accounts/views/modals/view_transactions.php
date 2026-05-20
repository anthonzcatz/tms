<!-- View Transactions Modal -->
<div class="modal fade" id="viewTransactionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <span class="fas fa-history me-2"></span>Transaction History
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="viewBankAccountId">
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label fw-semibold">Transaction Type</label>
                <select class="form-select" id="filterTxnType">
                  <option value="">All Types</option>
                  <option value="RECEIPT">Receipt</option>
                  <option value="DISBURSEMENT">Disbursement</option>
                  <option value="TRANSFER_IN">Transfer In</option>
                  <option value="TRANSFER_OUT">Transfer Out</option>
                  <option value="ADJUSTMENT">Adjustment</option>
                  <option value="REFUND">Refund</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold">Direction</label>
                <select class="form-select" id="filterDirection">
                  <option value="">All Directions</option>
                  <option value="IN">IN</option>
                  <option value="OUT">OUT</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold">Date From</label>
                <input type="date" class="form-control" id="filterDateFrom">
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold">Date To</label>
                <input type="date" class="form-control" id="filterDateTo">
              </div>
              <div class="col-12">
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-primary" onclick="applyTransactionFilters()">
                    <span class="fas fa-filter me-1"></span>Apply Filters
                  </button>
                  <button type="button" class="btn btn-secondary" onclick="clearTransactionFilters()">
                    <span class="fas fa-times me-1"></span>Clear
                  </button>
                  <button type="button" class="btn btn-success ms-auto" onclick="exportTransactions()">
                    <span class="fas fa-download me-1"></span>Export CSV
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Transactions Table -->
        <div class="card border-0 shadow-sm">
          <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
              <table class="table table-hover table-sm mb-0" style="font-size: 0.85rem;">
                <thead class="bg-light sticky-top">
                  <tr>
                    <th style="padding: 0.5rem;">Date</th>
                    <th style="padding: 0.5rem;">Code</th>
                    <th style="padding: 0.5rem;">Type</th>
                    <th style="padding: 0.5rem;">Dir</th>
                    <th style="padding: 0.5rem;">Amount</th>
                    <th style="padding: 0.5rem;">Bal Before</th>
                    <th style="padding: 0.5rem;">Bal After</th>
                    <th style="padding: 0.5rem;">Remarks</th>
                    <th style="padding: 0.5rem;">By</th>
                  </tr>
                </thead>
                <tbody id="transactionsTableBody">
                  <tr>
                    <td colspan="9" class="text-center py-4">
                      <span class="fas fa-spinner fa-spin"></span> Loading...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
              <div class="text-muted" id="transactionsPaginationInfo">
                Showing 0 of 0 transactions
              </div>
              <nav id="transactionsPagination">
                <ul class="pagination pagination-sm mb-0">
                </ul>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
