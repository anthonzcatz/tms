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
      <div class="modal-body p-4">
        <form id="editEmployeeForm" novalidate>
          <input type="hidden" id="editEmployeeId">
          <div class="row g-3">
            <div class="col-12"><h6 class="text-primary fw-bold">Personal Information</h6></div>
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
          <hr class="my-4">
          <div class="row g-3">
            <div class="col-12"><h6 class="text-primary fw-bold">Contact Information</h6></div>
            <div class="col-md-6"><label class="form-label fw-bold">Contact Number</label><input type="text" class="form-control" id="editContactNo"></div>
            <div class="col-md-6"><label class="form-label fw-bold">Email</label><input type="email" class="form-control" id="editEmail"></div>
            <div class="col-12"><label class="form-label fw-bold">Address</label><textarea class="form-control" id="editAddress" rows="2"></textarea></div>
          </div>
          <hr class="my-4">
          <div class="row g-3">
            <div class="col-12"><h6 class="text-primary fw-bold">Employment Details</h6></div>
            <div class="col-md-6"><label class="form-label fw-bold">Position</label><select class="form-select" id="editPosition"><option value="">Select Position</option><?php foreach ($positions as $p): ?><option value="<?php echo $p['pos_id']; ?>"><?php echo htmlspecialchars($p['position_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label fw-bold">Department</label><select class="form-select" id="editDepartment" onchange="loadSubDepartments('edit')"><option value="">Select Department</option><?php foreach ($departments as $d): ?><option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label fw-bold">Sub-Department</label><select class="form-select" id="editSubDepartment"><option value="">Select Sub-Department</option></select></div>
            <div class="col-md-6"><label class="form-label fw-bold">Company</label><select class="form-select" id="editCompany"><option value="">Select Company</option><?php foreach ($companies as $c): ?><option value="<?php echo $c['comp_id']; ?>"><?php echo htmlspecialchars($c['comp_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label fw-bold">Employment Status</label><select class="form-select" id="editEmploymentStatus"><option value="">Select Status</option><?php foreach ($employmentStatuses as $es): ?><option value="<?php echo $es['emp_stat_id']; ?>"><?php echo htmlspecialchars($es['emp_stat_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label fw-bold">Branch</label><select class="form-select" id="editBranch"><option value="">Select Branch</option><?php foreach ($branches as $b): ?><option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label fw-bold">Date Hired</label><input type="date" class="form-control" id="editDateHired"></div>
            <div class="col-md-4"><label class="form-label fw-bold">Daily Rate</label><input type="number" class="form-control" id="editDailyRate"></div>
            <div class="col-md-4"><label class="form-label fw-bold">COLA</label><input type="number" class="form-control" id="editCola"></div>
          </div>
          <hr class="my-4">
          <div class="row g-3">
            <div class="col-12"><h6 class="text-primary fw-bold">Government IDs</h6></div>
            <div class="col-md-3"><label class="form-label fw-bold">PhilHealth</label><input type="text" class="form-control" id="editPhilHealth"></div>
            <div class="col-md-3"><label class="form-label fw-bold">SSS</label><input type="text" class="form-control" id="editSSS"></div>
            <div class="col-md-3"><label class="form-label fw-bold">Pag-IBIG</label><input type="text" class="form-control" id="editPagibig"></div>
            <div class="col-md-3"><label class="form-label fw-bold">TIN</label><input type="text" class="form-control" id="editTin"></div>
          </div>
          <hr class="my-4">
          <div class="row g-3">
            <div class="col-12"><h6 class="text-primary fw-bold">Emergency Contact</h6></div>
            <div class="col-md-4"><label class="form-label fw-bold">Contact Name</label><input type="text" class="form-control" id="editEmergencyName"></div>
            <div class="col-md-4"><label class="form-label fw-bold">Relationship</label><input type="text" class="form-control" id="editEmergencyRelationship"></div>
            <div class="col-md-4"><label class="form-label fw-bold">Contact Number</label><input type="text" class="form-control" id="editEmergencyNumber"></div>
          </div>
          <hr class="my-4">
          <div class="row g-3">
            <div class="col-12"><label class="form-label fw-bold">Remarks</label><textarea class="form-control" id="editRemarks" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label fw-bold">Employment Remarks</label><textarea class="form-control" id="editEmploymentRemarks" rows="2"></textarea></div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-falcon-default" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="updateEmployee()">Update Employee</button>
      </div>
    </div>
  </div>
</div>
