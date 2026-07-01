// Notification Preferences Module

(function() {
    'use strict';

    // Store preferences in memory
    let preferences = {};
    const availableChannels = ['email', 'sms', 'push', 'in-app'];

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Notification Preferences module initialized');
        console.log('BASE_URL:', window.BASE_URL);
        console.log('CSRF_TOKEN:', window.CSRF_TOKEN);
        
        initEventListeners();
        loadPreferences();
    });

    // Load preferences from API
    async function loadPreferences() {
        console.log('Loading preferences from API...');
        try {
            const response = await fetch(`${window.BASE_URL}/api/notification-preferences/`);
            console.log('Response status:', response.status);
            const result = await response.json();
            console.log('API response:', result);

            if (result.success) {
                result.data.forEach(pref => {
                    preferences[pref.type] = {
                        channels: pref.channels,
                        enabled: !!pref.enabled // Convert to boolean
                    };
                });
                console.log('Loaded preferences:', preferences);
                
                // Update UI for each loaded preference
                Object.keys(preferences).forEach(type => {
                    updateUI(type);
                });
            } else {
                console.error('API returned error:', result.error);
            }
        } catch (error) {
            console.error('Error loading preferences:', error);
        }
    }

    // Initialize all event listeners using event delegation
    function initEventListeners() {
        console.log('Initializing event listeners...');

        // Toggle switch change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('preference-toggle')) {
                console.log('Toggle changed for:', e.target.dataset.type);
                const type = e.target.dataset.type;
                const enabled = e.target.checked;

                if (!preferences[type]) {
                    preferences[type] = { channels: ['in-app'], enabled: true };
                }

                preferences[type].enabled = enabled;
                
                // Update label immediately for real-time feel
                const label = e.target.closest('.form-check').querySelector('.form-check-label');
                if (label) {
                    label.textContent = enabled ? 'Enabled' : 'Disabled';
                }
                
                // Save in background
                savePreference(type);
                showToast(`Notification ${enabled ? 'enabled' : 'disabled'}`, 'success');
            }
        });

        // Add button click - show dropdown
        document.addEventListener('click', function(e) {
            const addBtn = e.target.closest('.add-channel-btn');
            if (addBtn) {
                console.log('Add button clicked for type:', addBtn.dataset.type);
                const type = addBtn.dataset.type;
                showDropdown(type);
                return;
            }

            // Remove channel
            const removeBtn = e.target.closest('.remove-channel');
            if (removeBtn) {
                console.log('Remove channel clicked:', removeBtn.dataset.type, removeBtn.dataset.channel);
                const type = removeBtn.dataset.type;
                const channel = removeBtn.dataset.channel;
                removeChannel(type, channel);
                return;
            }

            // Add channel from dropdown
            const addLink = e.target.closest('.add-channel-link');
            if (addLink) {
                console.log('Add channel link clicked:', addLink.dataset.type, addLink.dataset.channel);
                e.preventDefault();
                e.stopPropagation();
                const type = addLink.dataset.type;
                const channel = addLink.dataset.channel;
                addChannel(type, channel);
                return;
            }

            // Close dropdown when clicking outside
            if (!e.target.closest('.dropdown-wrapper')) {
                closeAllDropdowns();
            }
        });

        console.log('Event listeners initialized');
    }

    // Show dropdown for a specific type
    function showDropdown(type) {
        closeAllDropdowns();
        const dropdown = document.getElementById(`channelDropdown_${type}`);
        if (dropdown) {
            dropdown.style.display = 'block';
        }
    }

    // Close all dropdowns
    function closeAllDropdowns() {
        document.querySelectorAll('.channel-dropdown').forEach(dropdown => {
            dropdown.style.display = 'none';
        });
    }

    // Add a channel to a notification type
    async function addChannel(type, channel) {
        console.log('addChannel called:', type, channel);
        if (!preferences[type]) {
            preferences[type] = { channels: ['in-app'], enabled: true };
        }

        if (!preferences[type].channels.includes(channel)) {
            console.log('Adding channel to preferences');
            preferences[type].channels.push(channel);
            console.log('Updated preferences:', preferences[type]);
            
            // Update UI immediately for real-time feel
            console.log('Calling updateUI');
            updateUI(type);
            closeAllDropdowns();
            
            // Save in background
            const success = await savePreference(type);
            if (success) {
                showToast(`Added ${capitalize(channel)} channel`, 'success');
            } else {
                showToast('Failed to add channel', 'danger');
                // Revert UI on failure
                preferences[type].channels = preferences[type].channels.filter(c => c !== channel);
                updateUI(type);
            }
        } else {
            console.log('Channel already exists in preferences');
        }
    }

    // Remove a channel from a notification type
    async function removeChannel(type, channel) {
        if (preferences[type] && preferences[type].channels.includes(channel)) {
            preferences[type].channels = preferences[type].channels.filter(c => c !== channel);

            // Ensure at least one channel remains
            if (preferences[type].channels.length === 0) {
                preferences[type].channels = ['in-app'];
            }

            // Update UI immediately for real-time feel
            updateUI(type);
            
            // Save in background
            const success = await savePreference(type);
            if (success) {
                showToast(`Removed ${capitalize(channel)} channel`, 'success');
            } else {
                showToast('Failed to remove channel', 'danger');
                // Revert UI on failure
                preferences[type].channels.push(channel);
                updateUI(type);
            }
        }
    }

    // Save a single preference to API
    async function savePreference(type) {
        try {
            const response = await fetch(`${window.BASE_URL}/api/notification-preferences/`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: JSON.stringify({
                    type: type,
                    channels: preferences[type].channels,
                    enabled: preferences[type].enabled
                })
            });

            const result = await response.json();

            if (!result.success) {
                console.error('Error saving preference:', result.error);
                return false;
            }
            return true;
        } catch (error) {
            console.error('Error saving preference:', error);
            return false;
        }
    }

    // Update UI for a specific notification type
    function updateUI(type) {
        const row = document.querySelector(`tr[data-type="${type}"]`);
        if (!row) {
            console.log('Row not found for type:', type);
            return;
        }

        const channelsDiv = row.querySelector('.d-flex');
        if (!channelsDiv) {
            console.log('Channels div not found for type:', type);
            return;
        }

        const dropdown = channelsDiv.querySelector('.dropdown');
        if (!dropdown) {
            console.log('Dropdown not found for type:', type);
            return;
        }

        // Update toggle switch
        const toggle = row.querySelector('.preference-toggle');
        if (toggle) {
            const enabled = preferences[type] ? preferences[type].enabled : true;
            toggle.checked = enabled;
            const label = row.querySelector('.form-check-label');
            if (label) {
                label.textContent = enabled ? 'Enabled' : 'Disabled';
            }
        }

        // Remove all channel badges
        const existingBadges = channelsDiv.querySelectorAll('.channel-badge');
        existingBadges.forEach(badge => badge.remove());

        // Add channel badges
        const channels = preferences[type] ? preferences[type].channels : ['in-app'];
        channels.forEach(channel => {
            const badge = createChannelBadge(type, channel);
            channelsDiv.insertBefore(badge, dropdown);
        });

        // Rebuild dropdown menu
        const dropdownMenu = dropdown.querySelector('.dropdown-menu');
        if (dropdownMenu) {
            dropdownMenu.innerHTML = '';
            availableChannels.forEach(availableChannel => {
                if (!channels.includes(availableChannel)) {
                    const item = createDropdownItem(type, availableChannel);
                    dropdownMenu.appendChild(item);
                }
            });
        }

        // Re-initialize Bootstrap dropdown to keep it working after DOM changes
        if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
            const dropdownToggle = dropdown.querySelector('.dropdown-toggle');
            if (dropdownToggle) {
                const existingInstance = bootstrap.Dropdown.getInstance(dropdownToggle);
                if (existingInstance) {
                    existingInstance.dispose();
                }
                new bootstrap.Dropdown(dropdownToggle);
            }
        }
    }

    // Create a channel badge element
    function createChannelBadge(type, channel) {
        const badge = document.createElement('span');
        badge.className = 'badge bg-primary channel-badge';
        badge.dataset.channel = channel;
        badge.innerHTML = `
            <span class="fas ${getChannelIcon(channel)} me-1"></span>
            ${capitalize(channel)}
            <span class="ms-1 cursor-pointer remove-channel" data-type="${type}" data-channel="${channel}">&times;</span>
        `;
        return badge;
    }

    // Create a dropdown item element
    function createDropdownItem(type, channel) {
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'dropdown-item add-channel-link';
        link.dataset.type = type;
        link.dataset.channel = channel;
        link.innerHTML = `
            <span class="fas ${getChannelIcon(channel)} me-2"></span>
            ${capitalize(channel)}
        `;
        li.appendChild(link);
        return li;
    }

    // Get icon class for channel
    function getChannelIcon(channel) {
        const icons = {
            'email': 'fa-envelope',
            'sms': 'fa-sms',
            'push': 'fa-bell',
            'in-app': 'fa-desktop'
        };
        return icons[channel] || 'fa-circle';
    }

    // Capitalize first letter
    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Show toast notification
    function showToast(message, type = 'success') {
        const existingToast = document.querySelector('.notification-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = `notification-toast alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            <span class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></span>
            ${message}
            <button type="button" class="btn-close" aria-label="Close"></button>
        `;

        toast.querySelector('.btn-close').addEventListener('click', function() {
            toast.remove();
        });

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
})();
