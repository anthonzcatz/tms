<!-- View Wallet / Provider Details Modal -->
<div class="modal fade" id="viewWalletModal" tabindex="-1" aria-labelledby="viewWalletModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold" id="viewWalletModalLabel"><span class="fas fa-wallet me-2 text-primary"></span>Wallet &amp; Provider Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="viewWalletModalBody">
        <div class="text-center py-4">
          <span class="fas fa-spinner fa-spin fa-2x text-primary"></span>
          <p class="text-muted mt-2">Loading wallet details...</p>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-primary" id="viewWalletTransactionsBtn">View Transactions</button>
        <button type="button" class="btn btn-outline-success" id="editWalletFromViewBtn">Edit Wallet</button>
        <button type="button" class="btn btn-outline-info" id="adjustWalletFromViewBtn">Adjust Balance</button>
      </div>
    </div>
  </div>
</div>
