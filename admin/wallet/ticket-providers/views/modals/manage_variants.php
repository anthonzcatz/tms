<!-- Manage Variants Modal -->
<div class="modal fade" id="manageVariantsModal" tabindex="-1" aria-labelledby="manageVariantsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="manageVariantsModalLabel">
            <span class="fas fa-palette me-2"></span>Manage Variants
          </h4>
          <p class="fs-10 mb-0 text-white" id="manageVariantsProviderSubtext">Provider variants</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>

      <div class="modal-body">
        <input type="hidden" id="manageVariantProviderId">
        <input type="hidden" id="manageVariantId">

        <ul class="nav nav-tabs mb-3" id="variantsTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="variants-list-tab" data-bs-toggle="tab" data-bs-target="#variants-list-pane" type="button" role="tab" aria-controls="variants-list-pane" aria-selected="true" onclick="resetVariantForm()">
              <span class="fas fa-list me-1"></span>Variants
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="variants-add-tab" data-bs-toggle="tab" data-bs-target="#variants-add-pane" type="button" role="tab" aria-controls="variants-add-pane" aria-selected="false">
              <span class="fas fa-plus me-1"></span>Add Variant
            </button>
          </li>
        </ul>

        <div class="tab-content" id="variantsTabContent">
          <!-- Variants List Tab -->
          <div class="tab-pane fade show active" id="variants-list-pane" role="tabpanel" aria-labelledby="variants-list-tab">
            <div class="d-flex justify-content-end mb-2">
              <button class="btn btn-primary btn-sm" type="button" onclick="showAddVariantTab()">
                <span class="fas fa-plus me-1"></span>Add Variant
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover" id="variantsTable">
                <thead class="table-light">
                  <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Color</th>
                    <th>Stock</th>
                    <th>Ticket #</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="8" class="text-center text-muted py-4">Loading variants...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Add/Edit Variant Tab -->
          <div class="tab-pane fade" id="variants-add-pane" role="tabpanel" aria-labelledby="variants-add-tab">
            <form id="variantForm">
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="manageVariantCode" class="form-label fw-bold">Variant Code <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="manageVariantCode" maxlength="50" placeholder="e.g. ECONOMY, BIZ">
                </div>
                <div class="col-md-4">
                  <label for="manageVariantName" class="form-label fw-bold">Variant Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="manageVariantName" maxlength="150" placeholder="e.g. Economy Class">
                </div>
                <div class="col-md-4">
                  <label for="manageVariantColor" class="form-label fw-bold">Display Color</label>
                  <input type="color" class="form-control form-control-color" id="manageVariantColor" value="#0d6efd" title="Choose display color">
                </div>
                <div class="col-12">
                  <label for="manageVariantDescription" class="form-label fw-bold">Description</label>
                  <textarea class="form-control" id="manageVariantDescription" rows="2" placeholder="Optional description"></textarea>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="manageVariantStockControlled" checked>
                    <label class="form-check-label" for="manageVariantStockControlled">Stock Controlled</label>
                  </div>
                  <small class="text-muted">Uncheck for non-stock variants (e.g. open tickets).</small>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="manageVariantRequiresTicketNumber">
                    <label class="form-check-label" for="manageVariantRequiresTicketNumber">Requires Ticket Number</label>
                  </div>
                  <small class="text-muted">Require ticket/series numbers during POS sale.</small>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="manageVariantActive" checked>
                    <label class="form-check-label" for="manageVariantActive">Active</label>
                  </div>
                  <small class="text-muted">Inactive variants are hidden in POS.</small>
                </div>
              </div>
              <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-secondary" onclick="showVariantsListTab()">
                  <span class="fas fa-arrow-left me-1"></span>Back
                </button>
                <button type="button" class="btn btn-primary" id="saveVariantBtn" onclick="saveVariant()">
                  <span class="fas fa-save me-1"></span>Save Variant
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
