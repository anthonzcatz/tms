<!-- Service Fee Exemption Modal -->
<div class="modal fade" id="exemptionModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="exemptionModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="exemptionModalLabel">
            <span class="fas fa-cog me-2"></span>Service Fee Mode
          </h4>
          <p class="fs-10 mb-0 text-white">Set who pays the service fee for this customer</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <input type="hidden" id="exemptionPassengerId">

        <div class="alert alert-info d-flex align-items-start py-3">
          <span class="fas fa-info-circle me-3 fs-4 mt-1"></span>
          <div>
            <div class="fw-bold" id="exemptionCustomerName">—</div>
            <div class="small">Applies to <strong>future ticket transactions</strong> from the date set. Any <strong>currently outstanding service fee</strong> on this account will also be reallocated.</div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Who pays the service fee?</label>
            <select class="form-select" id="exemptionMode" onchange="toggleCompanySelect()">
              <option value="CUSTOMER">Customer pays full (base + service fee)</option>
              <option value="WAIVED">Waived (customer pays base only)</option>
              <option value="COMPANY">Bill to company/CEO (customer pays base only, fee goes to account)</option>
            </select>
          </div>
          <div class="col-12" id="exemptionCompanyRow" style="display:none;">
            <label class="form-label fw-semibold">Company/CEO Account</label>
            <select class="form-select mb-2" id="exemptionCompanyPassengerId" onchange="toggleNewCompanyForm()">
              <option value="">Select account</option>
              <option value="__NEW__">＋ Create new company/CEO account</option>
            </select>
            <div id="newCompanyForm" style="display:none;" class="border rounded p-3 bg-light">
              <div class="mb-2">
                <label class="form-label small fw-semibold">Account Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="newCompanyName" placeholder="e.g. ABC Company / CEO Juan Dela Cruz" maxlength="100">
              </div>
              <div class="mb-2">
                <label class="form-label small fw-semibold">Mobile Number</label>
                <input type="text" class="form-control" id="newCompanyMobile" placeholder="09XXXXXXXXX" maxlength="11">
              </div>
              <button type="button" class="btn btn-sm btn-primary" id="newCompanySubmitBtn" onclick="createCompanyAccount()">
                <span class="fas fa-save me-1" id="newCompanySubmitIcon"></span>
                <span id="newCompanySubmitLabel">Save Account</span>
              </button>
            </div>
            <div class="form-text">Choose an existing account or create a new one above.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="exemptionSubmitBtn" onclick="submitExemption()">
          <span class="fas fa-save me-1" id="exemptionSubmitIcon"></span>
          <span id="exemptionSubmitLabel">Save</span>
        </button>
      </div>
    </div>
  </div>
</div>
