// System Settings Module JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Restore active tab from localStorage
    const activeTab = localStorage.getItem('systemSettingsActiveTab');
    if (activeTab) {
        const tabElement = document.querySelector(`#settings-tab [href="${activeTab}"]`);
        if (tabElement) {
            const tabTrigger = new bootstrap.Tab(tabElement);
            tabTrigger.show();
        }
    }
    
    // Save active tab to localStorage when clicked
    const tabLinks = document.querySelectorAll('#settings-tab .nav-link');
    tabLinks.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            localStorage.setItem('systemSettingsActiveTab', event.target.getAttribute('href'));
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => {
                alert.remove();
            }, 150);
        }, 5000);
    });
    
    // Toggle maintenance mode fields
    const maintenanceModeSwitch = document.getElementById('maintenanceMode');
    const maintenanceFields = document.querySelectorAll('[name="maintenance_message"], [name="maintenance_start"], [name="maintenance_end"]');
    
    if (maintenanceModeSwitch) {
        maintenanceModeSwitch.addEventListener('change', function() {
            maintenanceFields.forEach(field => {
                field.disabled = !this.checked;
                if (!this.checked) {
                    field.value = '';
                }
            });
        });
        
        // Initialize based on current state
        if (!maintenanceModeSwitch.checked) {
            maintenanceFields.forEach(field => {
                field.disabled = true;
            });
        }
    }
});
