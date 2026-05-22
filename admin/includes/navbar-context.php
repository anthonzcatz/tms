<?php
require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/Auth.php';
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(__DIR__) . '/helpers/SidebarHelper.php';

// Fetch system settings for branding
$systemSettings = Database::fetch("SELECT * FROM system_settings WHERE setting_id = 1");
$systemName = htmlspecialchars($systemSettings['system_name'] ?? 'Falcon', ENT_QUOTES, 'UTF-8');
$systemLogo = $systemSettings['system_logo'] ?? null;
$developerName = htmlspecialchars($systemSettings['developer_name'] ?? '', ENT_QUOTES, 'UTF-8');
$developerDetails = htmlspecialchars($systemSettings['developer_details'] ?? '', ENT_QUOTES, 'UTF-8');
$footerCopyright = htmlspecialchars($systemSettings['footer_copyright'] ?? '', ENT_QUOTES, 'UTF-8');

// Validate logo URL to prevent XSS attacks
if ($systemLogo) {
    // Only allow relative URLs starting with / or absolute URLs with http/https
    $systemLogo = trim($systemLogo);
    if (!preg_match('/^\/|https?:\/\//i', $systemLogo)) {
        $systemLogo = null; // Invalid URL, use default
    }
}

$currentUser = Auth::user();
$profileImage = null;
$initials = '?';
$userProfile = [];

if ($currentUser) {
    $sql = "SELECT ua.profile_image, ua.email, ua.username, ua.recovery_email, ua.recovery_email_verified_at,
                   ua.user_code, ua.status, ua.last_login_at, ua.created_at, ua.updated_at,
                   ua.branch_id, ua.has_restricted_transport, ua.is_time_restricted,
                   ua.allowed_login_start, ua.allowed_login_end, ua.allowed_days,
                   ua.password_changed_at, ua.require_password_change,
                   e.first_name, e.last_name, e.middle_name,
                   e.b_email, e.b_cont_no, e.b_address, e.b_permanent_address,
                   e.emp_province_code, e.emp_city_code, e.emp_barangay_code,
                   p.position_name,
                   d.department_name,
                   prov.province_name,
                   cm.city_municipality_name,
                   r.role_name,
                   bb.branch_name, bb.branch_code
            FROM user_accounts ua
            LEFT JOIN employees e ON ua.emp_id = e.emp_id
            LEFT JOIN `position` p ON e.job_title = p.pos_id
            LEFT JOIN department d ON e.b_department_id = d.dept_id
            LEFT JOIN psgc_provinces prov ON e.emp_province_code = prov.province_code
            LEFT JOIN psgc_cities_municipalities cm ON e.emp_city_code = cm.city_municipality_code
            LEFT JOIN user_roles r ON ua.role_id = r.role_id
            LEFT JOIN business_branches bb ON ua.branch_id = bb.branch_id
            WHERE ua.user_id = :user_id";
    $user = Database::fetch(
        $sql,
        ['user_id' => $currentUser['user_id']]
    );
    if ($user) {
        $profileImage = $user['profile_image'];
        if ($user['first_name'] || $user['last_name']) {
            $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
        }

        // Build global user profile array for use across pages
        $fullname = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $fullname = preg_replace('/\s+/', ' ', $fullname); // collapse multiple spaces

        // Build location: prefer PSGC city/province, fallback to b_permanent_address
        $locationParts = array_filter([
            $user['city_municipality_name'] ?? '',
            $user['province_name'] ?? ''
        ]);
        $location = implode(', ', $locationParts);
        if (empty($location)) {
            $location = $user['b_permanent_address'] ?? $user['b_address'] ?? '';
        }

        $userProfile = [
            'fullname'        => $fullname ?: ($currentUser['fullname'] ?? 'User'),
            'first_name'      => $user['first_name'] ?? '',
            'last_name'       => $user['last_name'] ?? '',
            'middle_name'     => $user['middle_name'] ?? '',
            'email'           => $user['email'] ?? $user['b_email'] ?? '',
            'username'        => $user['username'] ?? '',
            'phone'           => $user['b_cont_no'] ?? '',
            'profile_image'   => $profileImage,
            'position'        => $user['position_name'] ?? '',
            'department'      => $user['department_name'] ?? '',
            'role_name'       => $user['role_name'] ?? ($currentUser['role_name'] ?? ''),
            'address'         => $user['b_address'] ?? $user['b_permanent_address'] ?? '',
            'province'        => $user['province_name'] ?? '',
            'city'            => $user['city_municipality_name'] ?? '',
            'location'        => $location,
            'initials'        => $initials,
            'recovery_email' => $user['recovery_email'] ?? '',
            'recovery_email_verified_at' => $user['recovery_email_verified_at'] ?? null,
            // Additional user account fields
            'user_code'       => $user['user_code'] ?? '',
            'status'          => $user['status'] ?? 'active',
            'last_login_at'   => $user['last_login_at'] ?? null,
            'created_at'      => $user['created_at'] ?? null,
            'updated_at'      => $user['updated_at'] ?? null,
            'branch_id'       => $user['branch_id'] ?? null,
            'branch_name'     => $user['branch_name'] ?? '',
            'branch_code'     => $user['branch_code'] ?? '',
            'has_restricted_transport' => $user['has_restricted_transport'] ?? 0,
            'is_time_restricted' => $user['is_time_restricted'] ?? 0,
            'allowed_login_start' => $user['allowed_login_start'] ?? null,
            'allowed_login_end' => $user['allowed_login_end'] ?? null,
            'allowed_days'    => $user['allowed_days'] ?? '',
            'password_changed_at' => $user['password_changed_at'] ?? null,
            'require_password_change' => $user['require_password_change'] ?? 0,
        ];
    }
}