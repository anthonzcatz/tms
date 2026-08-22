<!-- Select Charge Account Modal -->
<div class="modal fade" id="selectCustomerModal" tabindex="-1" aria-labelledby="selectCustomerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="selectCustomerModalLabel">
            <span class="fas fa-user-tie me-2"></span>Select Charge Account
          </h4>
          <p class="fs-10 mb-0 text-white">Choose the account that will receive this CHARGE payment</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-12">
            <label class="form-label fw-semibold" for="customerSearch">Search Charge Account</label>
            <div class="input-group">
              <span class="input-group-text bg-light">
                <span class="fas fa-search text-muted"></span>
              </span>
              <input type="text" class="form-control" id="customerSearch" name="customerSearch" placeholder="Search by account name or mobile number..." oninput="searchCustomers(this.value)">
              <button type="button" class="btn btn-outline-secondary" id="clearCustomerSearchBtn" onclick="clearCustomerSearch()" title="Clear search" aria-label="Clear search" style="display:none;">
                <span class="fas fa-times"></span>
              </button>
            </div>
            <small class="text-muted">Start typing to search active passenger accounts</small>
          </div>
        </div>
        <div class="table-responsive" style="max-height: 400px;">
          <table class="table table-hover mb-0" id="customersTable">
            <thead class="table-light sticky-top">
              <tr>
                <th>Name</th>
                <th>Mobile Number</th>
                <th class="text-end">Balance</th>
                <th class="text-center">Select</th>
              </tr>
            </thead>
            <tbody id="customersTableBody">
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">Loading accounts...</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div id="noCustomersMsg" class="text-center py-4 text-muted" style="display:none;">
          <span class="fas fa-search me-2"></span>No accounts found matching your search.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmCustomerBtn" onclick="confirmCustomerSelection()" disabled>
          <span class="fas fa-check me-1"></span>Confirm Selection
        </button>
      </div>
    </div>
  </div>
</div>