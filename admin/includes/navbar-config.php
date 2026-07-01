<?php
/**
 * Navbar Configuration
 * Centralized configuration for all navbar components
 * Edit this file to add/remove links across all navbar layouts
 */

return [
    // Nine-dots dropdown links
    'nine_dots_links' => [
        // TMS Core Modules
        [
            'url' => BASE_URL . '/admin/user/',
            'icon' => 'account',
            'label' => 'Account',
            'type' => 'avatar',
        ],
        [
            'url' => BASE_URL . '/admin/support/',
            'icon' => 'support',
            'label' => 'Support',
            'type' => 'image',
        ],
        [
            'url' => BASE_URL . '/admin/wallet/provider-wallets/',
            'icon' => 'wallet',
            'label' => 'Wallet',
            'type' => 'image',
        ],
        [
            'url' => BASE_URL . '/admin/pos/',
            'icon' => 'pos',
            'label' => 'POS',
            'type' => 'image',
        ],
        [
            'url' => BASE_URL . '/admin/dashboard/analytics',
            'icon' => 'data-analysis',
            'label' => 'Analytics',
            'type' => 'image',
        ],
        [
            'url' => BASE_URL . '/admin/settings/organization/',
            'icon' => 'Organization',
            'label' => 'Organization',
            'type' => 'image',
        ],
        [
            'url' => BASE_URL . '/admin/system-settings/',
            'icon' => 'system',
            'label' => 'Settings',
            'type' => 'image',
        ],
        [
            'url' => BASE_URL . '/admin/dashboard/',
            'icon' => 'dashboard',
            'label' => 'Dashboard',
            'type' => 'image',
        ],
    ],

    // User dropdown links
    'user_dropdown_links' => [
        [
            'url' => BASE_URL . '/admin/user/',
            'label' => 'Profile & account',
        ],
        [
            'url' => BASE_URL . '/admin/settings/notification-preferences/',
            'label' => 'Notification Preferences',
        ],
        [
            'url' => BASE_URL . '/admin/settings/permissions',
            'label' => 'Permission Management',
        ],
        [
            'url' => BASE_URL . '/admin/settings/users',
            'label' => 'User Management',
        ],
        [
            'url' => BASE_URL . '/admin/settings/role-dashboards',
            'label' => 'Role Dashboards',
        ],
        [
            'url' => '#',
            'label' => 'Logout',
            'type' => 'logout', // Special type for logout button
            'data_toggle' => 'modal',
            'data_target' => '#logoutModal',
        ],
    ],

    // Icon image paths
    'icon_path' => BASE_URL . '/resources/assets/img/nav-icons/',
];
