<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <?php include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/head.php'; ?>

  <body>

    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
      <div class="container" data-layout="container">
        <script>
          var isFluid = JSON.parse(localStorage.getItem('isFluid'));
          if (isFluid) {
            var container = document.querySelector('[data-layout]');
            container.classList.remove('container');
            container.classList.add('container-fluid');
          }
        </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
         <?php
         switch (NAVBAR_POSITION) {
             case 'combo':
                 include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/navbar-top.php';
                 break;
             case 'vertical':
                 include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/navbar.php';
                 break;
             case 'top':
             case 'double-top':
             default:
                 break;
         }
         ?><?php endif; ?>
        
        <!-- Support Management Content -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                  <div>
                    <h3 class="mb-1">Support Requests Management</h3>
                    <p class="text-muted mb-0">View and manage user support tickets</p>
                  </div>
                  <a href="<?php echo BASE_URL; ?>/admin/support/" class="btn btn-outline-primary">
                    <span class="fas fa-plus me-2"></span>New Request
                  </a>
                </div>

                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                  <div class="col-md-3">
                    <div class="card bg-light border-0">
                      <div class="card-body">
                        <div class="d-flex align-items-center">
                          <div class="bg-soft-warning rounded-circle p-3 me-3">
                            <span class="fas fa-clock fs-4 text-warning"></span>
                          </div>
                          <div>
                            <h6 class="mb-0 text-muted">Pending</h6>
                            <h4 class="mb-0 fw-bold">
                              <?php echo count(array_filter($requests, fn($r) => $r['status'] === 'pending')); ?>
                            </h4>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card bg-light border-0">
                      <div class="card-body">
                        <div class="d-flex align-items-center">
                          <div class="bg-soft-info rounded-circle p-3 me-3">
                            <span class="fas fa-spinner fs-4 text-info"></span>
                          </div>
                          <div>
                            <h6 class="mb-0 text-muted">In Progress</h6>
                            <h4 class="mb-0 fw-bold">
                              <?php echo count(array_filter($requests, fn($r) => $r['status'] === 'in_progress')); ?>
                            </h4>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card bg-light border-0">
                      <div class="card-body">
                        <div class="d-flex align-items-center">
                          <div class="bg-soft-success rounded-circle p-3 me-3">
                            <span class="fas fa-check-circle fs-4 text-success"></span>
                          </div>
                          <div>
                            <h6 class="mb-0 text-muted">Resolved</h6>
                            <h4 class="mb-0 fw-bold">
                              <?php echo count(array_filter($requests, fn($r) => $r['status'] === 'resolved')); ?>
                            </h4>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card bg-light border-0">
                      <div class="card-body">
                        <div class="d-flex align-items-center">
                          <div class="bg-soft-secondary rounded-circle p-3 me-3">
                            <span class="fas fa-times-circle fs-4 text-secondary"></span>
                          </div>
                          <div>
                            <h6 class="mb-0 text-muted">Closed</h6>
                            <h4 class="mb-0 fw-bold">
                              <?php echo count(array_filter($requests, fn($r) => $r['status'] === 'closed')); ?>
                            </h4>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Requests Table -->
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead class="bg-light">
                      <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($requests)): ?>
                        <tr>
                          <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                              <span class="fas fa-inbox fs-1 mb-2 d-block"></span>
                              No support requests found
                            </div>
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                          <tr>
                            <td>#<?php echo $request['request_id']; ?></td>
                            <td>
                              <div class="d-flex align-items-center">
                                <?php 
                                $username = $request['username'];
                                $initials = strtoupper(substr($username, 0, 2));
                                ?>
                                <div class="avatar avatar-xs rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center me-2" style="width:32px; height:32px;">
                                    <span class="fw-bold small"><?php echo $initials; ?></span>
                                </div>
                                <div>
                                  <div class="fw-semibold"><?php echo htmlspecialchars($username); ?></div>
                                  <div class="text-muted small"><?php echo htmlspecialchars($request['email']); ?></div>
                                </div>
                              </div>
                            </td>
                            <td><?php echo htmlspecialchars($request['subject']); ?></td>
                            <td>
                              <?php
                              $statusColors = [
                                  'pending' => 'warning',
                                  'in_progress' => 'info',
                                  'resolved' => 'success',
                                  'closed' => 'secondary'
                              ];
                              $statusLabels = [
                                  'pending' => 'Pending',
                                  'in_progress' => 'In Progress',
                                  'resolved' => 'Resolved',
                                  'closed' => 'Closed'
                              ];
                              ?>
                              <span class="badge bg-soft-<?php echo $statusColors[$request['status']]; ?> text-<?php echo $statusColors[$request['status']]; ?>">
                                <?php echo $statusLabels[$request['status']]; ?>
                              </span>
                            </td>
                            <td>
                              <?php
                              $priorityColors = [
                                  'low' => 'secondary',
                                  'normal' => 'primary',
                                  'high' => 'warning',
                                  'urgent' => 'danger'
                              ];
                              ?>
                              <span class="badge bg-soft-<?php echo $priorityColors[$request['priority']]; ?> text-<?php echo $priorityColors[$request['priority']]; ?>">
                                <?php echo ucfirst($request['priority']); ?>
                              </span>
                            </td>
                            <td>
                              <?php echo $request['assigned_to_name'] ? htmlspecialchars($request['assigned_to_name']) : '<span class="text-muted">Unassigned</span>'; ?>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></td>
                            <td>
                              <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="viewRequest(<?php echo $request['request_id']; ?>)"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewModal">
                                  <span class="fas fa-eye"></span>
                                </button>
                                <button class="btn btn-sm btn-outline-success" 
                                        onclick="editRequest(<?php echo $request['request_id']; ?>)"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal">
                                  <span class="fas fa-edit"></span>
                                </button>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-light">
            <h5 class="modal-title">Support Request Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="viewModalBody">
            <!-- Content loaded via AJAX -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-light">
            <h5 class="modal-title">Update Support Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="editModalBody">
            <!-- Content loaded via AJAX -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
    </div>
    <?php endif; ?>
    <?php include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/footer.php'; ?>
    <?php include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/scripts.php'; ?>
    <?php include dirname(dirname(dirname(dirname(__DIR__)))) . '/admin/includes/body-top.php'; ?>
    
    <script>
    // Initialize Bootstrap modals inline (script is at bottom of body, DOM is ready)
    let viewModal, editModal;
    if (typeof bootstrap !== 'undefined') {
        viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
        editModal = new bootstrap.Modal(document.getElementById('editModal'));
        console.log('Modals initialized successfully');
    } else {
        console.error('Bootstrap is not loaded');
    }
    
    function viewRequest(requestId) {
        console.log('viewRequest called with ID:', requestId);
        document.getElementById('viewModalBody').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-2">Loading request details...</p>
            </div>
        `;
        fetch('<?php echo BASE_URL; ?>/api/support/' + requestId)
            .then(response => response.json())
            .then(data => {
                console.log('API response:', data);
                if (data.success) {
                    const request = data.request;
                    console.log('Request data:', request);
                    console.log('Profile image path:', request.profile_image);
                    
                    const statusColors = {
                        'pending': 'warning',
                        'in_progress': 'info',
                        'resolved': 'success',
                        'closed': 'secondary'
                    };
                    const statusLabels = {
                        'pending': 'Pending',
                        'in_progress': 'In Progress',
                        'resolved': 'Resolved',
                        'closed': 'Closed'
                    };
                    const priorityColors = {
                        'low': 'secondary',
                        'normal': 'primary',
                        'high': 'warning',
                        'urgent' : 'danger'
                    };
                    
                    const initials = request.username ? request.username.substring(0, 2).toUpperCase() : '??';
                    
                    document.getElementById('viewModalBody').innerHTML = `
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <small class="text-muted d-block mb-1">Request ID</small>
                                        <strong>#${request.request_id}</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <small class="text-muted d-block mb-1">Created</small>
                                        <strong>${new Date(request.created_at).toLocaleString()}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">User</label>
                            <div class="d-flex align-items-center">
                                <div class="avatar rounded-circle bg-soft-primary text-primary d-flex align-items-center justify-content-center me-2" style="width:40px; height:40px;">
                                    <span class="fw-bold">${initials}</span>
                                </div>
                                <div>
                                    <div class="fw-semibold">${request.username}</div>
                                    <div class="text-muted small">${request.email}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subject</label>
                            <div class="fs-5">${request.subject}</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message</label>
                            <div class="bg-light p-3 rounded border">${request.message}</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Status</label>
                                <span class="badge bg-soft-${statusColors[request.status]} text-${statusColors[request.status]} fs-6">
                                    ${statusLabels[request.status]}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Priority</label>
                                <span class="badge bg-soft-${priorityColors[request.priority]} text-${priorityColors[request.priority]} fs-6">
                                    ${request.priority.charAt(0).toUpperCase() + request.priority.slice(1)}
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Assigned To</label>
                            <div>${request.assigned_to_name ? request.assigned_to_name : '<span class="text-muted">Unassigned</span>'}</div>
                        </div>
                        
                        ${request.response ? `
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Response</label>
                            <div class="bg-success bg-opacity-10 p-3 rounded border border-success">${request.response}</div>
                        </div>
                        ` : `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No response has been provided yet.
                        </div>
                        `}
                        
                        ${request.resolved_at ? `
                        <div class="text-muted small mt-3">
                            <i class="fas fa-check-circle me-1"></i>Resolved on ${new Date(request.resolved_at).toLocaleString()}
                        </div>
                        ` : ''}
                    `;
                    
                    // Show the modal
                    console.log('Showing modal, viewModal:', viewModal);
                    if (viewModal) {
                        viewModal.show();
                    } else {
                        console.error('viewModal is not initialized');
                    }
                } else {
                    console.error('API returned error:', data.error);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
    }

    function editRequest(requestId) {
        console.log('editRequest called with ID:', requestId);
        document.getElementById('editModalBody').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-2">Loading request details...</p>
            </div>
        `;
        fetch('<?php echo BASE_URL; ?>/api/support/' + requestId)
            .then(response => response.json())
            .then(data => {
                console.log('Edit API response:', data);
                if (data.success) {
                    const request = data.request;
                    const assignableUsers = <?php echo json_encode($assignableUsers); ?>;
                    
                    let assignOptions = '<option value="">Unassigned</option>';
                    assignableUsers.forEach(u => {
                        assignOptions += `<option value="${u.user_id}" ${request.assigned_to == u.user_id ? 'selected' : ''}>${u.username}</option>`;
                    });
                    
                    document.getElementById('editModalBody').innerHTML = `
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Request #${request.request_id}</strong> by ${request.username}
                        </div>
                        
                        <form id="updateForm">
                            <input type="hidden" name="request_id" value="${request.request_id}">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="pending" ${request.status === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="in_progress" ${request.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                        <option value="resolved" ${request.status === 'resolved' ? 'selected' : ''}>Resolved</option>
                                        <option value="closed" ${request.status === 'closed' ? 'selected' : ''}>Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Priority</label>
                                    <select class="form-select" name="priority">
                                        <option value="low" ${request.priority === 'low' ? 'selected' : ''}>Low</option>
                                        <option value="normal" ${request.priority === 'normal' ? 'selected' : ''}>Normal</option>
                                        <option value="high" ${request.priority === 'high' ? 'selected' : ''}>High</option>
                                        <option value="urgent" ${request.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Assign To</label>
                                <select class="form-select" name="assigned_to">
                                    ${assignOptions}
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Original Message</label>
                                <div class="bg-light p-3 rounded border small">${request.message}</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Response</label>
                                <textarea class="form-control" name="response" rows="5" placeholder="Enter your response to the user...">${request.response || ''}</textarea>
                                <small class="text-muted">This response will be visible to the user.</small>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <span class="fas fa-save me-2"></span>Update Request
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="if(editModal) editModal.hide()">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    `;
                    
                    // Show the modal
                    if (editModal) {
                        editModal.show();
                    }
                    
                    document.getElementById('updateForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        const data = Object.fromEntries(formData);
                        
                        const submitBtn = this.querySelector('button[type="submit"]');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Saving...';
                        
                        fetch('<?php echo BASE_URL; ?>/api/support/' + requestId, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data)
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                location.reload();
                            } else {
                                alert('Failed to update: ' + (result.error || 'Unknown error'));
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<span class="fas fa-save me-2"></span>Update Request';
                            }
                        })
                        .catch(error => {
                            alert('An error occurred. Please try again.');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<span class="fas fa-save me-2"></span>Update Request';
                        });
                    });
                } else {
                    console.error('Edit API returned error:', data.error);
                    document.getElementById('editModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load request: ${data.error || 'Unknown error'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Edit fetch error:', error);
                document.getElementById('editModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        An error occurred while loading the request.
                    </div>
                `;
            });
    }
    </script>
  </body>
</html>
