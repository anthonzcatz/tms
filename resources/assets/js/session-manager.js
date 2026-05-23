/**
 * Session Manager
 * Handles session expiry detection, activity tracking, and automatic session refresh
 * 
 * Configuration is passed via data attributes on the <body> tag:
 * - data-session-lifetime: Session lifetime in milliseconds (from .env SESSION_LIFETIME)
 * - data-warning-minutes: Minutes before expiry to show warning (from system_settings)
 */

(function() {
    'use strict';

    // Configuration from data attributes
    const body = document.body;
    const SESSION_LIFETIME_MS = parseInt(body.getAttribute('data-session-lifetime') || '7200000'); // Default 2 hours
    const WARNING_BEFORE_EXPIRY_MINUTES = parseInt(body.getAttribute('data-warning-minutes') || '15');
    
    // Session management constants
    const SESSION_CHECK_INTERVAL = 30000; // Check every 30 seconds
    const MAX_RETRIES = 3;
    const ACTIVITY_TIMEOUT = SESSION_LIFETIME_MS;
    
    // Calculate warning timeout
    const SESSION_LIFETIME_MINUTES = SESSION_LIFETIME_MS / 60000;
    const EFFECTIVE_WARNING_MINUTES = Math.min(WARNING_BEFORE_EXPIRY_MINUTES, Math.max(SESSION_LIFETIME_MINUTES - 1, 1));
    const WARNING_TIMEOUT = Math.max(ACTIVITY_TIMEOUT - (EFFECTIVE_WARNING_MINUTES * 60 * 1000), 60000);
    
    // State variables
    let lastActivityTime = Date.now();
    let sessionCheckCount = 0;
    let warningShown = false;
    let sessionInterval = null;

    // Detect user activity
    const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
    activityEvents.forEach(event => {
        document.addEventListener(event, function() {
            lastActivityTime = Date.now();
        }, true);
    });

    // Function to refresh/extend session
    function refreshSession() {
        if (!window.BASE_URL) {
            console.warn('BASE_URL not defined');
            return;
        }
        
        fetch(window.BASE_URL + '/api/refresh-session.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Session refreshed');
            }
        })
        .catch(error => {
            console.warn('Failed to refresh session:', error);
        });
    }

    // Function to check if session is still valid
    function checkSession() {
        if (!window.BASE_URL) {
            console.warn('BASE_URL not defined');
            return;
        }
        
        fetch(window.BASE_URL + '/api/check-session.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Session check failed');
            }
            return response.json();
        })
        .then(data => {
            sessionCheckCount = 0; // Reset retry count on success
            if (!data.valid && !data.error) {
                // Only show alert if explicitly invalid (not on error)
                showSessionExpiredAlert();
            }
        })
        .catch(error => {
            // On network error, increment retry count
            sessionCheckCount++;
            if (sessionCheckCount >= MAX_RETRIES) {
                // Only show alert after multiple consecutive failures
                showSessionExpiredAlert();
            }
            console.warn('Session check error:', error);
        });
    }

    // Show session expired alert modal
    function showSessionExpiredAlert() {
        // Remove existing modal if present
        const existingModal = document.getElementById('sessionExpiredAlertModal');
        if (existingModal) {
            return; // Don't show duplicate modals
        }

        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="sessionExpiredAlertModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning bg-opacity-10">
                            <h5 class="modal-title text-warning">
                                <span class="fas fa-exclamation-triangle me-2"></span>Session Expired
                            </h5>
                        </div>
                        <div class="modal-body">
                            <p>Your session has expired due to inactivity. You will be redirected to the login page.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="window.SessionManager.redirectToLogin()">
                                <span class="fas fa-sign-in-alt me-2"></span>Go to Login
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('sessionExpiredAlertModal'));
        modal.show();
    }

    // Show session warning alert modal
    function showSessionWarningAlert() {
        // Remove existing warning modal if present
        const existingModal = document.getElementById('sessionWarningModal');
        if (existingModal) {
            return; // Don't show duplicate modals
        }

        // Calculate actual remaining minutes
        const timeSinceLastActivity = Date.now() - lastActivityTime;
        const remainingMs = Math.max(ACTIVITY_TIMEOUT - timeSinceLastActivity, 0);
        const remainingMinutes = Math.ceil(remainingMs / 60000);

        // Create warning modal HTML
        const modalHtml = `
            <div class="modal fade" id="sessionWarningModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-info bg-opacity-10">
                            <h5 class="modal-title text-info">
                                <span class="fas fa-clock me-2"></span>Session Expiring Soon
                            </h5>
                        </div>
                        <div class="modal-body">
                            <p>Your session will expire in approximately <strong>${remainingMinutes} minutes</strong> due to inactivity.</p>
                            <p>Would you like to extend your session?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <span class="fas fa-times me-2"></span>Ignore
                            </button>
                            <button type="button" class="btn btn-primary" onclick="window.SessionManager.extendSessionFromWarning()">
                                <span class="fas fa-redo me-2"></span>Extend Session
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('sessionWarningModal'));
        modal.show();
    }

    // Public API
    window.SessionManager = {
        extendSessionFromWarning: function() {
            // Close the warning modal
            const warningModal = document.getElementById('sessionWarningModal');
            if (warningModal) {
                const modal = bootstrap.Modal.getInstance(warningModal);
                if (modal) {
                    modal.hide();
                }
                warningModal.remove();
            }

            // Refresh session
            refreshSession();
            lastActivityTime = Date.now(); // Reset activity timer
            warningShown = false; // Reset warning flag
        },

        redirectToLogin: function() {
            if (!window.BASE_URL) {
                console.warn('BASE_URL not defined');
                return;
            }
            window.location.href = window.BASE_URL + '/auth/login.php?error=session_expired';
        },

        // Allow manual session refresh
        refresh: function() {
            refreshSession();
            lastActivityTime = Date.now();
        },

        // Stop session management (useful for testing or special cases)
        stop: function() {
            if (sessionInterval) {
                clearInterval(sessionInterval);
                sessionInterval = null;
            }
        },

        // Restart session management
        start: function() {
            if (sessionInterval) {
                this.stop();
            }
            startSessionManagement();
        }
    };

    // Main session management loop
    function startSessionManagement() {
        sessionInterval = setInterval(function() {
            const timeSinceLastActivity = Date.now() - lastActivityTime;
            
            if (timeSinceLastActivity < ACTIVITY_TIMEOUT) {
                // User is active, refresh session
                refreshSession();
                
                // Reset warning flag when user is active
                if (timeSinceLastActivity < WARNING_TIMEOUT) {
                    warningShown = false;
                }
            } else {
                // User has been inactive, check if session expired
                checkSession();
            }
            
            // Show warning before session expires
            if (timeSinceLastActivity >= WARNING_TIMEOUT && 
                timeSinceLastActivity < ACTIVITY_TIMEOUT && 
                !warningShown) {
                showSessionWarningAlert();
                warningShown = true;
            }
        }, SESSION_CHECK_INTERVAL);
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startSessionManagement);
    } else {
        startSessionManagement();
    }

})();
