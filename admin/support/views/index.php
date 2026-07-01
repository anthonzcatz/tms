<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <?php include dirname(dirname(__DIR__)) . '/includes/head.php'; ?>

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
        </script><?php if (NAVBAR_POSITION === 'top' || NAVBAR_POSITION === 'double-top'): ?><?php if (NAVBAR_POSITION === 'top'): ?><?php include dirname(dirname(__DIR__)) . '/includes/navbar-top.php'; ?><?php elseif (NAVBAR_POSITION === 'double-top'): ?><?php include dirname(dirname(__DIR__)) . '/includes/navbar-double-top.php'; ?><?php endif; ?><?php else: ?><?php include dirname(dirname(__DIR__)) . '/includes/sidebar.php'; ?><?php endif; ?><?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?><div class="content">
         <?php
         switch (NAVBAR_POSITION) {
             case 'combo':
                 include dirname(dirname(__DIR__)) . '/includes/navbar-top.php';
                 break;
             case 'vertical':
                 include dirname(dirname(__DIR__)) . '/includes/navbar.php';
                 break;
             case 'top':
             case 'double-top':
             default:
                 break;
         }
         ?><?php endif; ?>
        
        <!-- Support Page Content -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-body p-5">
                <div class="text-center mb-5">
                  <div class="icon-item bg-soft-primary rounded-circle mx-auto mb-3" style="width: 100px; height: 100px; display: flex; align-items: center; justify-content: center;">
                    <span class="fas fa-headset fs-1 text-primary"></span>
                  </div>
                  <h2 class="mb-2">Support Center</h2>
                  <p class="text-muted fs-5">We're here to help you with any issues or questions.</p>
                </div>

                <div class="row g-4">
                  <div class="col-md-4">
                    <div class="card h-100 border-0 bg-light">
                      <div class="card-body text-center">
                        <div class="mb-3">
                          <span class="fas fa-envelope fs-2 text-primary"></span>
                        </div>
                        <h5 class="mb-2">Email Support</h5>
                        <p class="text-muted mb-3">Send us an email and we'll get back to you within 24 hours.</p>
                        <a href="mailto:support@tms.com" class="btn btn-primary btn-sm">
                          <span class="fas fa-paper-plane me-2"></span>Send Email
                        </a>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card h-100 border-0 bg-light">
                      <div class="card-body text-center">
                        <div class="mb-3">
                          <span class="fas fa-phone fs-2 text-success"></span>
                        </div>
                        <h5 class="mb-2">Phone Support</h5>
                        <p class="text-muted mb-3">Call us for immediate assistance during business hours.</p>
                        <a href="tel:+639123456789" class="btn btn-success btn-sm">
                          <span class="fas fa-phone me-2"></span>Call Now
                        </a>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card h-100 border-0 bg-light">
                      <div class="card-body text-center">
                        <div class="mb-3">
                          <span class="fas fa-book fs-2 text-info"></span>
                        </div>
                        <h5 class="mb-2">Documentation</h5>
                        <p class="text-muted mb-3">Browse our knowledge base for common issues and solutions.</p>
                        <a href="#" class="btn btn-info btn-sm">
                          <span class="fas fa-book-open me-2"></span>View Docs
                        </a>
                      </div>
                    </div>
                  </div>
                </div>

                <hr class="my-5">

                <div class="row">
                  <div class="col-lg-8 mx-auto">
                    <h4 class="text-center mb-4">Quick Contact Form</h4>
                    <form id="supportForm">
                      <div class="row g-3">
                        <div class="col-md-6">
                          <label class="form-label fw-semibold">Your Name</label>
                          <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label fw-semibold">Email Address</label>
                          <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-12">
                          <label class="form-label fw-semibold">Subject</label>
                          <select class="form-select" name="subject" required>
                            <option value="">Select a topic...</option>
                            <option value="technical">Technical Issue</option>
                            <option value="billing">Billing Question</option>
                            <option value="feature">Feature Request</option>
                            <option value="other">Other</option>
                          </select>
                        </div>
                        <div class="col-12">
                          <label class="form-label fw-semibold">Message</label>
                          <textarea class="form-control" rows="5" name="message" placeholder="Describe your issue or question..." required></textarea>
                        </div>
                        <div class="col-12 text-center">
                          <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span class="fas fa-paper-plane me-2"></span>Submit Request
                          </button>
                        </div>
                      </div>
                    </form>
                    <div id="formAlert" class="mt-3"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <?php if (NAVBAR_POSITION === 'vertical' || NAVBAR_POSITION === 'combo'): ?>
    </div>
    <?php endif; ?>
    <?php include dirname(dirname(__DIR__)) . '/includes/footer.php'; ?>
    <?php include dirname(dirname(__DIR__)) . '/includes/scripts.php'; ?>
    <?php include dirname(dirname(__DIR__)) . '/includes/body-top.php'; ?>
    
    <script>
    document.getElementById('supportForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const formAlert = document.getElementById('formAlert');
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Sending...';
        
        const formData = new FormData(this);
        const data = {
            subject: formData.get('subject'),
            message: formData.get('message')
        };
        
        fetch('<?php echo BASE_URL; ?>/api/support/', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                formAlert.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + result.message + '</div>';
                this.reset();
            } else {
                formAlert.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + (result.error || 'Failed to submit request') + '</div>';
            }
        })
        .catch(error => {
            formAlert.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>An error occurred. Please try again.</div>';
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span class="fas fa-paper-plane me-2"></span>Submit Request';
        });
    });
    </script>
  </body>
</html>
