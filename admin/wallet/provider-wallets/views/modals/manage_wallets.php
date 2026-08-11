<!-- Manage Provider Wallets Modal -->
<div class="modal fade" id="manageProviderWalletsModal" tabindex="-1" aria-labelledby="manageProviderWalletsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="manageProviderWalletsModalLabel">
            <span class="fas fa-wallet me-2"></span>Manage Provider Wallets
          </h4>
          <p class="fs-10 mb-0 text-white" id="manageProviderWalletsSubtext">Provider / Branch</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <input type="hidden" id="manageProviderWalletsProviderId">
        <input type="hidden" id="manageProviderWalletsBranchId">

        <div class="d-flex justify-content-end mb-2">
          <button class="btn btn-primary btn-sm" type="button" onclick="createProviderLevelWalletFromManage()">
            <span class="fas fa-plus me-1"></span>Add Provider Wallet
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-hover" id="manageProviderWalletsTable">
            <thead class="table-light">
              <tr>
                <th>Variant / Wallet</th>
                <th>Status</th>
                <th class="text-end">Current Balance</th>
                <th class="text-end">Min. Balance</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">Loading wallets...</td>
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
