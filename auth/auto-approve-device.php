<?php
/**
 * Emergency device auto-approval
 * Use this to quickly approve a pending device when locked out
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/helpers/Auth.php';

$ip = $_GET['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$type = $_GET['type'] ?? 'desktop';

// Approve the device
$now = date('Y-m-d H:i:s');
Database::execute(
    "UPDATE system_devices 
     SET status = 'approved', 
         approved_at = :approved_at, 
         device_remark = CONCAT(device_remark, ' [Emergency auto-approved]')
     WHERE ip_address = :ip 
       AND device_type = :type 
       AND status = 'pending'",
    ['ip' => $ip, 'type' => $type, 'approved_at' => $now]
);

// Check if device was updated
$deviceAfter = Database::fetch(
    "SELECT status FROM system_devices WHERE ip_address = :ip AND device_type = :type ORDER BY last_used_at DESC LIMIT 1",
    ['ip' => $ip, 'type' => $type]
);

if ($deviceAfter && $deviceAfter['status'] === 'approved') {
    $_SESSION['success'] = "Device approved successfully! You can now log in.";
} else {
    // Check if device already exists and is approved
    $device = Database::fetch(
        "SELECT status FROM system_devices WHERE ip_address = :ip AND device_type = :type ORDER BY last_used_at DESC LIMIT 1",
        ['ip' => $ip, 'type' => $type]
    );
    
    if ($device) {
        if ($device['status'] === 'approved') {
            $_SESSION['success'] = "Device is already approved. Please try logging in.";
        } elseif ($device['status'] === 'blocked') {
            $_SESSION['error'] = "Device is blocked. Please contact administrator.";
        } else {
            $_SESSION['error'] = "Device status: " . $device['status'] . ". Please contact administrator.";
        }
    } else {
        $_SESSION['error'] = "No pending device found for IP=$ip, Type=$type. Try logging in first to register the device.";
    }
}

header('Location: ' . LOGIN_URL);
exit;
