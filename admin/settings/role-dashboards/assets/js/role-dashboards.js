/**
 * Role Dashboard Management JavaScript
 * Handles saving default dashboard settings for user roles
 */

const API_BASE = window.BASE_URL + '/api/role-dashboards';

document.addEventListener('DOMContentLoaded', function() {
  // Any initialization code here
});

/**
 * Get CSRF token from meta tag
 */
function getCSRFToken() {
  const metaTag = document.querySelector('meta[name="csrf-token"]');
  return metaTag ? metaTag.getAttribute('content') : '';
}

/**
 * Save dashboard setting for a role
 */
function saveDashboard(roleId) {
  const dashboardInput = document.getElementById('dashboard_' + roleId);
  const dashboardPath = dashboardInput.value;
  
  console.log('Saving dashboard:', roleId, dashboardPath);
  console.log('API URL:', API_BASE);
  
  fetch(API_BASE, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': getCSRFToken()
    },
    body: JSON.stringify({
      role_id: roleId,
      default_dashboard: dashboardPath
    })
  })
  .then(response => {
    console.log('Response status:', response.status);
    if (!response.ok) {
      return response.json().then(err => { throw err; });
    }
    return response.json();
  })
  .then(data => {
    console.log('Response data:', data);
    if (data.success) {
      const toast = new bootstrap.Toast(document.getElementById('successToast'));
      toast.show();
    } else {
      throw new Error(data.error || 'Operation failed');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const errorToast = document.getElementById('errorToast');
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = error.error || error.message;
    const toast = new bootstrap.Toast(errorToast);
    toast.show();
  });
}
