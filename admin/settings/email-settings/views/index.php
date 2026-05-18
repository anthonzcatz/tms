<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<?php include dirname(dirname(dirname(__DIR__))) . '/includes/head.php'; ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/admin/settings/email-settings/assets/css/email-settings.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/email-settings.css'); ?>">

<!-- Optional: scoped Settings panel styles -->
<style>
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"] {
    min-width: 240px;
    border-radius: 6px;
    border-color: #d0d5dd;
    background-color: #ffffff;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"]:hover {
    border-color: #b6beca;
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"]:focus {
    border-color: #84c5f4;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
  }
  .settings-panel select.form-select.form-select-sm[data-theme-control="navbarPosition"] option {
    font-size: 0.9rem;
  }
</style>

<body>
  <main class="main" id="top">
    <div class="container" data-layout="container">
      <script>
        var isFluid = JSON.parse(localStorage.getItem('isFluid'));
        if (isFluid) {
          var container = document.querySelector('[data-layout]');
          container.classList.remove('container');
          container.classList.add('container-fluid');
        }
      </script>

      <?php include dirname(dirname(dirname(__DIR__))) . '/includes/sidebar.php'; ?>
      <?php if (NAVBAR_POSITION === 'top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php'; ?>
      <?php elseif (NAVBAR_POSITION === 'double-top'): ?>
        <?php include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-double-top.php'; ?>
      <?php endif; ?>

      <div class="content">
        <?php
        switch (NAVBAR_POSITION) {
            case 'combo':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar-top.php';
                break;
            case 'vertical':
                include dirname(dirname(dirname(__DIR__))) . '/includes/navbar.php';
                break;
            case 'top':
            case 'double-top':
            default:
                break;
        }
        ?>

        <!-- Page Header -->
        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h2 class="mb-1">Email Settings</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/dashboard">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/admin/settings">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Email Settings</li>
                  </ol>
                </nav>
              </div>
            </div>
          </div>
        </div>

        <!-- Email Settings Form -->
        <div class="row g-3">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">Email Configuration</h5>
                <p class="mb-0 text-muted fs-10">Configure email settings for password reset and system notifications</p>
              </div>
              <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                  <div class="alert alert-success">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                    ?>
                  </div>
                <?php endif; ?>
                
                <?php if (isset($errors)): ?>
                  <div class="alert alert-danger">
                    <ul class="mb-0">
                      <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>

                <form method="POST" action="">
                  <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::generateCSRFToken(); ?>">
                  
                  <!-- Email Method Selection -->
                  <div class="mb-4">
                    <label class="form-label">Email Method</label>
                    <div class="btn-group" role="group">
                      <input type="radio" class="btn-check" name="email_method" id="method-smtp" value="smtp" <?php echo ($emailSettings['email_method'] ?? 'smtp') === 'smtp' ? 'checked' : ''; ?>>
                      <label class="btn btn-outline-primary" for="method-smtp">
                        SMTP
                        <?php if (($emailSettings['email_method'] ?? 'smtp') === 'smtp'): ?>
                          <span class="badge bg-success ms-1">Active</span>
                        <?php endif; ?>
                      </label>
                      
                      <input type="radio" class="btn-check" name="email_method" id="method-gmail" value="gmail_api" <?php echo ($emailSettings['email_method'] ?? '') === 'gmail_api' ? 'checked' : ''; ?>>
                      <label class="btn btn-outline-primary" for="method-gmail">
                        Gmail API
                        <?php if (($emailSettings['email_method'] ?? '') === 'gmail_api'): ?>
                          <span class="badge bg-success ms-1">Active</span>
                        <?php endif; ?>
                      </label>
                    </div>
                  </div>

                  <!-- SMTP Settings -->
                  <div id="smtp-settings" class="<?php echo ($emailSettings['email_method'] ?? 'smtp') === 'smtp' ? '' : 'd-none'; ?>">
                    <h6 class="mb-3">SMTP Configuration</h6>
                    <div class="alert alert-info fs-10">
                      <strong>Setup Instructions:</strong>
                      <ol class="mb-0 mt-2">
                        <li>Go to your email provider settings (e.g., Gmail, Outlook)</li>
                        <li>Enable 2-Step Verification (required for Gmail)</li>
                        <li>Generate an App Password: <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a></li>
                        <li>Use the App Password as SMTP Password (not your regular password)</li>
                        <li>Gmail SMTP: smtp.gmail.com, Port 587 (TLS) or 465 (SSL)</li>
                      </ol>
                    </div>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($emailSettings['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($emailSettings['smtp_port'] ?? '587'); ?>" placeholder="587">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" class="form-control" name="smtp_username" value="<?php echo htmlspecialchars($emailSettings['smtp_username'] ?? ''); ?>" placeholder="your-email@gmail.com">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">SMTP Password</label>
                        <input type="password" class="form-control" name="smtp_password" value="<?php echo htmlspecialchars($emailSettings['smtp_password'] ?? ''); ?>" placeholder="Your app password">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Encryption</label>
                        <select class="form-select" name="smtp_encryption">
                          <option value="tls" <?php echo ($emailSettings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                          <option value="ssl" <?php echo ($emailSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                          <option value="none" <?php echo ($emailSettings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                      </div>
                    </div>
                    <div class="mt-3">
                      <button type="button" class="btn btn-outline-secondary" onclick="testEmail('smtp')">
                        <span class="fas fa-paper-plane me-2"></span>Send Test Email (SMTP)
                      </button>
                    </div>
                  </div>

                  <!-- Gmail API Settings -->
                  <div id="gmail-settings" class="<?php echo ($emailSettings['email_method'] ?? '') === 'gmail_api' ? '' : 'd-none'; ?>">
                    <h6 class="mb-3">Gmail API Configuration</h6>
                    <div class="alert alert-info fs-10">
                      <strong>Setup Instructions:</strong>
                      <ol class="mb-0 mt-2">
                        <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                        <li>Create a new project or select existing one</li>
                        <li>Enable Gmail API</li>
                        <li>Create OAuth 2.0 credentials (Web application)</li>
                        <li>Add authorized redirect URI: http://192.168.1.46:8080/TMS/gmail-callback (localhost) or https://myexpoapp.jdco.online/gmail-callback (production)</li>
                        <li>Add test users in OAuth consent screen (for testing)</li>
                        <li>Copy Client ID and Client Secret below</li>
                      </ol>
                    </div>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label">Gmail Client ID</label>
                        <input type="text" class="form-control" name="gmail_client_id" value="<?php echo htmlspecialchars($emailSettings['gmail_client_id'] ?? ''); ?>" placeholder="your-client-id.apps.googleusercontent.com">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Gmail Client Secret</label>
                        <input type="password" class="form-control" name="gmail_client_secret" value="<?php echo htmlspecialchars($emailSettings['gmail_client_secret'] ?? ''); ?>" placeholder="Your client secret">
                      </div>
                      <div class="col-12">
                        <label class="form-label">Refresh Token</label>
                        <input type="password" class="form-control" name="gmail_refresh_token" value="<?php echo htmlspecialchars($emailSettings['gmail_refresh_token'] ?? ''); ?>" placeholder="Obtained after OAuth authorization">
                        <small class="text-muted">Leave empty to authorize with Google</small>
                      </div>
                      <div class="col-12">
                        <button type="button" class="btn btn-info" onclick="authorizeGmail()">
                          <span class="fab fa-google me-2"></span>Authorize with Google
                        </button>
                      </div>
                    </div>
                    <div class="mt-3">
                      <button type="button" class="btn btn-outline-secondary" onclick="testEmail('gmail_api')">
                        <span class="fas fa-paper-plane me-2"></span>Send Test Email (Gmail API)
                      </button>
                    </div>
                  </div>

                  <hr class="my-4">

                  <!-- Sender Information -->
                  <h6 class="mb-3">Sender Information</h6>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Sender Name</label>
                      <input type="text" class="form-control" name="sender_name" value="<?php echo htmlspecialchars($emailSettings['sender_name'] ?? ''); ?>" placeholder="Ticketing Services Inc.">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Sender Email</label>
                      <input type="email" class="form-control" name="sender_email" value="<?php echo htmlspecialchars($emailSettings['sender_email'] ?? ''); ?>" placeholder="noreply@ticketingservices.com">
                    </div>
                  </div>

                  <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                      <span class="fas fa-save me-2"></span>Save Settings
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/footer.php'; ?>
  <?php include dirname(dirname(dirname(__DIR__))) . '/includes/scripts.php'; ?>
  
  <script>
    // Toggle settings based on email method
    document.querySelectorAll('input[name="email_method"]').forEach(radio => {
      radio.addEventListener('change', function() {
        if (this.value === 'smtp') {
          document.getElementById('smtp-settings').classList.remove('d-none');
          document.getElementById('gmail-settings').classList.add('d-none');
        } else {
          document.getElementById('smtp-settings').classList.add('d-none');
          document.getElementById('gmail-settings').classList.remove('d-none');
        }
      });
    });

    // Authorize with Google
    function authorizeGmail() {
      const clientId = document.querySelector('input[name="gmail_client_id"]').value;
      if (!clientId) {
        alert('Please enter Gmail Client ID first');
        return;
      }
      // Use current origin for redirect URI
      const redirectUri = window.location.origin + '/gmail-callback';
      const scope = 'https://www.googleapis.com/auth/gmail.send';
      const authUrl = `https://accounts.google.com/o/oauth2/v2/auth?client_id=${clientId}&redirect_uri=${encodeURIComponent(redirectUri)}&scope=${encodeURIComponent(scope)}&response_type=code&access_type=offline&prompt=consent`;
      window.location.href = authUrl;
    }

    // Test email
    function testEmail(method) {
      const testEmail = prompt('Enter email address to send test email:', 'catzanthonz@gmail.com');
      if (!testEmail) return;
      
      const csrfToken = document.querySelector('input[name="csrf_token"]').value;
      const url = new URL(window.location.href);
      url.searchParams.append('action', 'test_email');
      url.searchParams.append('test_email', testEmail);
      url.searchParams.append('csrf_token', csrfToken);
      url.searchParams.append('test_method', method);
      
      const btn = event.target;
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="fas fa-spinner fa-spin me-2"></span>Sending...';
      
      fetch(url.toString())
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Success: ' + data.message);
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          alert('Error: ' + error.message);
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = originalText;
        });
    }
  </script>
</body>
</html>
