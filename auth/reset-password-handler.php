<?php
session_start();
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/SecurityHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: ' . BASE_URL . '/auth/forgot-password');
    exit;
}

if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid request. Please refresh and try again.';
    header('Location: ' . BASE_URL . '/auth/forgot-password');
    exit;
}

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate inputs
if (empty($token)) {
    $_SESSION['error'] = 'Invalid reset link. Please request a new password reset.';
    header('Location: ' . BASE_URL . '/auth/forgot-password');
    exit;
}

if (empty($password) || strlen($password) < 8) {
    $_SESSION['error'] = 'Password must be at least 8 characters.';
    header('Location: ' . BASE_URL . '/auth/reset-password?token=' . urlencode($token));
    exit;
}

if ($password !== $confirmPassword) {
    $_SESSION['error'] = 'Passwords do not match.';
    header('Location: ' . BASE_URL . '/auth/reset-password?token=' . urlencode($token));
    exit;
}

// Validate token and get user info
$resetToken = Database::fetch(
    "SELECT prt.*, ua.user_id, ua.username 
     FROM password_reset_tokens prt
     JOIN user_accounts ua ON prt.user_id = ua.user_id
     WHERE prt.token = :token 
     AND prt.used_at IS NULL 
     AND prt.expires_at > NOW()
     LIMIT 1",
    ['token' => $token]
);

if (!$resetToken) {
    $_SESSION['error'] = 'Invalid or expired reset link. Please request a new password reset.';
    header('Location: ' . BASE_URL . '/auth/forgot-password');
    exit;
}

// Hash new password
$passwordHash = password_hash($password, PASSWORD_ARGON2ID);

// Update user password
Database::execute(
    "UPDATE user_accounts SET 
        password_hash = :hash,
        password_changed_at = NOW(),
        require_password_change = 0,
        failed_login_attempts = 0,
        locked_until = NULL
     WHERE user_id = :user_id",
    [
        'hash' => $passwordHash,
        'user_id' => $resetToken['user_id']
    ]
);

// Mark token as used
Database::execute(
    "UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = :token_id",
    ['token_id' => $resetToken['token_id']]
);

// Log the password reset
Database::execute(
    "INSERT INTO activity_logs (user_id, action, module_name, ip_address, created_at)
     VALUES (:user_id, 'PASSWORD_RESET', 'AUTH', :ip, NOW())",
    [
        'user_id' => $resetToken['user_id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]
);

// Success
$_SESSION['success'] = 'Password reset successfully! You can now log in with your new password.';
header('Location: ' . BASE_URL . '/auth/login');
exit;
