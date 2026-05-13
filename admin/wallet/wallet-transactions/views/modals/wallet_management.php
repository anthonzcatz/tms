<!-- Wallet Management Modal -->
<div class="modal fade" id="walletManagementModal" tabindex="-1" aria-labelledby="walletManagementModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="walletManagementModalLabel">
            <span class="fas fa-wallet me-2"></span>Wallet Management
          </h4>
          <p class="fs-10 mb-0 text-white">View and manage wallet balances</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
      <div class="table-responsive">
        <table class="table table-bordered mb-0">
          <thead class="bg-200">
            <tr>
              <th>Wallet</th>
              <th>Provider</th>
              <th>Branch</th>
              <th>Current Balance</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="walletsTableBody">
            <tr>
              <td colspan="6" class="text-center py-4">
                <span class="fas fa-spinner fa-spin"></span> Loading wallets...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <span class="fas fa-times me-2"></span>Close
      </button>
    </div>
  </div>
</div>
</div>
