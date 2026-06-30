<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
        <div class="position-relative z-1">
          <h4 class="mb-0 text-white">Edit Employee</h4>
          <p class="fs-10 mb-0 text-white">Update employee details</p>
        </div>
        <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Wizard Step Indicators -->
      <div class="modal-header border-bottom py-3 px-4">
        <div class="d-flex align-items-center w-100 justify-content-between" id="editWizardSteps">
          <?php
          $steps = [
            1 => ['icon' => 'fa-user',       'label' => 'Personal'],
            2 => ['icon' => 'fa-phone',      'label' => 'Contact'],
            3 => ['icon' => 'fa-briefcase',  'label' => 'Employment'],
            4 => ['icon' => 'fa-id-card',    'label' => 'Gov Benefits'],
            5 => ['icon' => 'fa-heart',      'label' => 'Emergency'],
          ];
          foreach ($steps as $num => $step): ?>
            <div class="d-flex flex-column align-items-center wizard-step <?php echo $num === 1 ? 'active' : ''; ?>" id="editWizardStep<?php echo $num; ?>" style="flex:1">
              <div class="wizard-step-circle rounded-circle d-flex align-items-center justify-content-center mb-1"
                   style="width:36px;height:36px;font-size:14px;transition:all .2s;">
                <span class="fas <?php echo $step['icon']; ?>"></span>
              </div>
              <small class="fw-semibold" style="font-size:10px;"><?php echo $step['label']; ?></small>
            </div>
            <?php if ($num < count($steps)): ?>
              <div class="wizard-step-line flex-grow-1" style="height:2px;margin-top:-18px;"></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="modal-body p-4">
        <form id="editEmployeeForm" novalidate>
          <input type="hidden" id="editEmployeeId">

          <!-- Step 1: Personal Information -->
          <div class="wizard-pane" id="editWizardPane1">
            <h6 class="text-primary fw-bold mb-3"><span class="fas fa-user me-2"></span>Personal Information</h6>
            <div class="row g-3">
              <div class="col-md-4"><label class="form-label fw-bold">First Name</label><input type="text" class="form-control" id="editFirstName"></div>
              <div class="col-md-4"><label class="form-label fw-bold">Last Name</label><input type="text" class="form-control" id="editLastName"></div>
              <div class="col-md-4"><label class="form-label fw-bold">Middle Name</label><input type="text" class="form-control" id="editMiddleName"></div>
              <div class="col-md-4"><label class="form-label fw-bold">Birthdate</label><input type="date" class="form-control" id="editBDate"></div>
              <div class="col-md-4"><label class="form-label fw-bold">Sex</label><select class="form-select" id="editSex"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
              <div class="col-md-4"><label class="form-label fw-bold">Civil Status</label><select class="form-select" id="editCivilStatus"><option value="">Select</option><option value="Single">Single</option><option value="Married">Married</option><option value="In a Relationship">In a Relationship</option></select></div>
              <div class="col-md-6"><label class="form-label fw-bold">Citizenship</label><input type="text" class="form-control" id="editCitizenship"></div>
              <div class="col-md-6"><label class="form-label fw-bold">Religion</label><input type="text" class="form-control" id="editReligion"></div>
              <div class="col-12"><label class="form-label fw-bold">Place of Birth</label><input type="text" class="form-control" id="editPlaceBirth"></div>
            </div>
          </div>

          <!-- Step 2: Contact Information -->
          <div class="wizard-pane d-none" id="editWizardPane2">
            <h6 class="text-primary fw-bold mb-3"><span class="fas fa-phone me-2"></span>Contact Information</h6>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label fw-bold">Contact Number</label><input type="text" class="form-control" id="editContactNo"></div>
              <div class="col-md-6"><label class="form-label fw-bold">Email</label><input type="email" class="form-control" id="editEmail"></div>
              <div class="col-12"><label class="form-label fw-bold">Region</label><select class="form-select" id="editRegion" onchange="loadProvinces('edit')"><option value="">Select Region</option></select></div>
              <div class="col-md-6"><label class="form-label fw-bold">Province</label><select class="form-select" id="editProvince" onchange="loadCities('edit')"><option value="">Select Province</option></select></div>
              <div class="col-md-6"><label class="form-label fw-bold">City / Municipality</label><select class="form-select" id="editCity" onchange="loadBarangays('edit')"><option value="">Select City / Municipality</option></select></div>
              <div class="col-12"><label class="form-label fw-bold">Barangay</label><select class="form-select" id="editBarangay" onchange="buildFullAddress('edit')"><option value="">Select Barangay</option></select></div>
              <div class="col-12"><label class="form-label fw-bold">Street / House Details</label><input type="text" class="form-control" id="editStreetAddress" oninput="buildFullAddress('edit')" placeholder="House No., Street, Building, etc."></div>
              <div class="col-12"><label class="form-label fw-bold">Full Address</label><textarea class="form-control" id="editAddress" rows="2" readonly></textarea></div>
            </div>
          </div>

          <!-- Step 3: Employment Details -->
          <div class="wizard-pane d-none" id="editWizardPane3">
            <h6 class="text-primary fw-bold mb-3"><span class="fas fa-briefcase me-2"></span>Employment Details</h6>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label fw-bold">Position</label><select class="form-select" id="editPosition"><option value="">Select Position</option><?php foreach ($positions as $p): ?><option value="<?php echo $p['pos_id']; ?>"><?php echo htmlspecialchars($p['position_name']); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-6"><label class="form-label fw-bold">Department</label><select class="form-select" id="editDepartment" onchange="loadSubDepartments('edit')"><option value="">Select Department</option><?php foreach ($departments as $d): ?><option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-6"><label class="form-label fw-bold">Sub-Department</label><select class="form-select" id="editSubDepartment"><option value="">Select Sub-Department</option></select></div>
              <div class="col-md-6"><label class="form-label fw-bold">Company</label><select class="form-select" id="editCompany"><option value="">Select Company</option><?php foreach ($companies as $c): ?><option value="<?php echo $c['comp_id']; ?>"><?php echo htmlspecialchars($c['comp_name']); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-6"><label class="form-label fw-bold">Employment Status</label><select class="form-select" id="editEmploymentStatus"><option value="">Select Status</option><?php foreach ($employmentStatuses as $es): ?><option value="<?php echo $es['emp_stat_id']; ?>"><?php echo htmlspecialchars($es['emp_stat_name']); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-6"><label class="form-label fw-bold">Branch</label><select class="form-select" id="editBranch"><option value="">Select Branch</option><?php foreach ($branches as $b): ?><option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option><?php endforeach; ?></select></div>
              <div class="col-md-4"><label class="form-label fw-bold">Date Hired</label><input type="date" class="form-control" id="editDateHired"></div>
              <div class="col-md-4"><label class="form-label fw-bold">Daily Rate</label><input type="number" class="form-control" id="editDailyRate" min="0" step="0.01"></div>
              <div class="col-md-4"><label class="form-label fw-bold">COLA</label><input type="number" class="form-control" id="editCola" min="0" step="0.01"></div>
            </div>
          </div>

          <!-- Step 4: Government Benefits -->
          <div class="wizard-pane d-none" id="editWizardPane4">
            <h6 class="text-primary fw-bold mb-3"><span class="fas fa-id-card me-2"></span>Government Benefits</h6>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label fw-bold">PhilHealth No.</label><input type="text" class="form-control" id="editPhilHealth"></div>
              <div class="col-md-6"><label class="form-label fw-bold">SSS No.</label><input type="text" class="form-control" id="editSSS"></div>
              <div class="col-md-6"><label class="form-label fw-bold">Pag-IBIG No.</label><input type="text" class="form-control" id="editPagibig"></div>
              <div class="col-md-6"><label class="form-label fw-bold">TIN No.</label><input type="text" class="form-control" id="editTin"></div>
            </div>
          </div>

          <!-- Step 5: Emergency Contact & Remarks -->
          <div class="wizard-pane d-none" id="editWizardPane5">
            <h6 class="text-primary fw-bold mb-3"><span class="fas fa-heart me-2"></span>Emergency Contact</h6>
            <div class="row g-3">
              <div class="col-md-4"><label class="form-label fw-bold">Contact Name</label><input type="text" class="form-control" id="editEmergencyName"></div>
              <div class="col-md-4"><label class="form-label fw-bold">Relationship</label><input type="text" class="form-control" id="editEmergencyRelationship"></div>
              <div class="col-md-4"><label class="form-label fw-bold">Contact Number</label><input type="text" class="form-control" id="editEmergencyNumber"></div>
              <div class="col-12"><hr class="my-2"></div>
              <div class="col-12"><label class="form-label fw-bold">Remarks</label><textarea class="form-control" id="editRemarks" rows="2"></textarea></div>
              <div class="col-12"><label class="form-label fw-bold">Employment Remarks</label><textarea class="form-control" id="editEmploymentRemarks" rows="2"></textarea></div>
            </div>
          </div>

        </form>
      </div>

      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-falcon-default" id="editWizardPrevBtn" onclick="wizardStep('edit', -1)" style="display:none!important">Previous</button>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-falcon-default" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="editWizardNextBtn" onclick="wizardStep('edit', 1)">Next <span class="fas fa-arrow-right ms-1"></span></button>
          <button type="button" class="btn btn-success d-none" id="editWizardSaveBtn" onclick="updateEmployee()"><span class="fas fa-save me-1"></span>Update Employee</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.wizard-step .wizard-step-circle { background: #e9ecef; color: #6c757d; }
.wizard-step.active .wizard-step-circle { background: #2c7be5; color: #fff; }
.wizard-step.completed .wizard-step-circle { background: #00d27a; color: #fff; }
.wizard-step small { color: #6c757d; }
.wizard-step.active small { color: #2c7be5; font-weight:700!important; }
.wizard-step.completed small { color: #00d27a; }
.wizard-step-line { background: #e9ecef; align-self: center; }
.wizard-step-line.completed { background: #00d27a; }
</style>
