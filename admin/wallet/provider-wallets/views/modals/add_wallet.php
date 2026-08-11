<!-- Add Wallet Modal -->
<div class="modal fade" id="addWalletModal" tabindex="-1" aria-labelledby="addWalletModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addWalletModalLabel">
            <span class="fas fa-wallet me-2"></span>Add Wallet
          </h4>
          <p class="fs-10 mb-0 text-white">Create a new provider wallet</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <form id="addWalletForm">
        <div class="modal-body">
          <input type="hidden" id="addWalletId" name="wallet_id">
          <div id="addExistingWalletAlert" class="alert alert-info d-none mb-3">
            <span class="fas fa-info-circle me-2"></span>
            <span id="addExistingWalletMessage">An existing wallet was found for this branch, provider and variant.</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="addBranchId" class="form-label fw-bold">Branch <span class="text-danger">*</span></label>
              <select class="form-select" id="addBranchId" name="branch_id" required>
                <option value="">Select Branch</option>
                <!-- Branches will be loaded dynamically -->
              </select>
              <small class="text-muted form-text">Select branch first to see available providers for this location.</small>
            </div>
            <div class="col-md-6">
              <label for="addProviderId" class="form-label fw-bold">Provider <span class="text-danger">*</span></label>
              <select class="form-select" id="addProviderId" name="provider_id" required disabled>
                <option value="">Select Branch first</option>
                <?php foreach ($allProviders as $provider): ?>
                  <?php
                    $providerLabel = $provider['provider_name'];
                    if (!empty($provider['provider_code'])) {
                        $providerLabel .= ' [' . $provider['provider_code'] . ']';
                    }
                    if (!empty($provider['provider_type'])) {
                        $providerLabel .= ' (' . ucfirst(str_replace('_', ' ', $provider['provider_type'])) . ')';
                    }
                    $subProviderCount = (int)($provider['sub_provider_count'] ?? 0);
                    if ($subProviderCount > 0) {
                        $providerLabel .= ' - Main Provider - ' . $subProviderCount . ' sub' . ($subProviderCount === 1 ? '' : 's');
                    } else {
                        $providerLabel .= ' - Standalone Provider';
                    }
                    $variantCount = (int)($provider['variant_count'] ?? 0);
                    if ($variantCount > 0) {
                        $providerLabel .= ' - ' . $variantCount . ' Variant' . ($variantCount === 1 ? '' : 's');
                    }
                  ?>
                  <option value="<?php echo (int)$provider['provider_id']; ?>" data-variant-count="<?php echo $variantCount; ?>"><?php echo htmlspecialchars($providerLabel); ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted form-text">Only Main and Standalone providers can have wallets. Sub-providers share the Main provider wallet.</small>
            </div>
            <div class="col-md-6">
              <label for="addVariantId" class="form-label fw-bold">Ticket Variant</label>
              <select class="form-select" id="addVariantId" name="variant_id" disabled>
                <option value="">Provider-level wallet (no variant)</option>
                <!-- Variants will be loaded when a provider is selected -->
              </select>
              <small class="text-muted form-text">Already-created wallets for this branch are disabled here.</small>
            </div>
            <div class="col-md-6">
              <label for="addInitialBalance" class="form-label fw-bold">Initial Balance</label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="text" inputmode="decimal" class="form-control number-format" id="addInitialBalance" name="initial_balance" placeholder="0.00" value="0.00">
              </div>
            </div>
            <div class="col-md-6">
              <label for="addMinBalance" class="form-label fw-bold">Min Balance Threshold</label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input type="text" inputmode="decimal" class="form-control number-format" id="addMinBalance" name="min_balance" placeholder="1,000.00" value="1,000.00">
              </div>
              <small class="text-muted form-text">Alert when balance falls below this amount</small>
            </div>
            <div class="col-md-6">
              <label for="addStatus" class="form-label fw-bold">Status</label>
              <select class="form-select" id="addStatus" name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
      </form>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <span class="fas fa-times me-2"></span>Cancel
        </button>
        <button type="button" class="btn btn-primary" onclick="saveWallet()">
          <span class="fas fa-save me-2"></span>Save Wallet
        </button>
      </div>
    </div>
  </div>
</div>
