<?php
/**
 * Add Company Modal
 */
?>
<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white" id="addCompanyModalLabel">
            <span class="fas fa-building me-2"></span>Add Company
          </h4>
          <p class="fs-10 mb-0 text-white">Create a new company record</p>
        </div>
        <div data-bs-theme="dark">
          <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-4">
        <form id="addCompanyForm" novalidate>
          <div class="mb-3">
            <label for="addCompanyName" class="form-label fw-bold">Company Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="addCompanyName" required>
            <div class="invalid-feedback">Company name is required</div>
          </div>
          <div class="mb-3">
            <label for="addCompanyCode" class="form-label fw-bold">Company Abbreviation</label>
            <input type="text" class="form-control" id="addCompanyCode">
          </div>
          <div class="mb-3">
            <label for="addCompanyAddress" class="form-label fw-bold">Address</label>
            <textarea class="form-control" id="addCompanyAddress" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label for="addCompanyContact" class="form-label fw-bold">Contact Number</label>
            <input type="text" class="form-control" id="addCompanyContact">
          </div>
          <div class="mb-3">
            <label for="addCompanyEmail" class="form-label fw-bold">Email</label>
            <input type="email" class="form-control" id="addCompanyEmail">
          </div>
          <div class="mb-3">
            <label for="addCompanyStatus" class="form-label fw-bold">Company Status</label>
            <select class="form-select" id="addCompanyStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-falcon-default" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveCompany()">
          <span class="fas fa-save me-2"></span>Save Company
        </button>
      </div>
    </div>
  </div>
</div>
