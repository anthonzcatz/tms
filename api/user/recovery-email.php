<?php
/**
 * Recovery Email API
 * Handles saving and verifying recovery email addresses
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../app/helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/EmailService.php';

// Ensure user is authenticated
Auth::requireLogin();

// Set JSON response header
header('Content-Type: application/json');

// Get current user
$currentUser = Auth::user();
$userId = $currentUser['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get action from POST
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            saveRecoveryEmail($userId);
            break;
        case 'send_verification':
            sendVerificationEmail($userId);
            break;
        case 'verify':
            verifyToken($userId);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Recovery Email API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

/**
 * Save recovery email
 */
function saveRecoveryEmail($userId): void
{
    // CSRF validation
    if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh and try again.']);
        exit;
    }

    // Rate limiting
    $rateKey = 'save_recovery_email_' . $userId;
    if (!SecurityHelper::checkRateLimit($rateKey, 3, 3600)) {
        echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
        exit;
    }

    $email = trim((string) ($_POST['email'] ?? ''));

    // Validate email
    if (!SecurityHelper::validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }

    // Check if email is different from primary email
    $primaryEmail = Database::fetch("SELECT email FROM user_accounts WHERE user_id = :id", ['id' => $userId]);
    if ($primaryEmail && $email === $primaryEmail['email']) {
        echo json_encode(['success' => false, 'message' => 'Recovery email cannot be the same as your primary email.']);
        exit;
    }

    // Check if email is already used by another user
    $existing = Database::fetch(
        "SELECT user_id FROM user_accounts WHERE email = :email AND user_id != :user_id",
        ['email' => $email, 'user_id' => $userId]
    );
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'This email is already in use by another account.']);
        exit;
    }

    // Update user_accounts with recovery email (reset verification status if email changed)
    $currentRecoveryEmail = Database::fetch("SELECT recovery_email FROM user_accounts WHERE user_id = :id", ['id' => $userId]);
    
    if ($currentRecoveryEmail && $currentRecoveryEmail['recovery_email'] !== $email) {
        // Email changed, reset verification
        Database::execute(
            "UPDATE user_accounts 
             SET recovery_email = :email, recovery_email_verified_at = NULL, 
                 recovery_email_verification_token = NULL, recovery_email_verification_expires_at = NULL
             WHERE user_id = :id",
            ['email' => $email, 'id' => $userId]
        );
    } elseif (!$currentRecoveryEmail || empty($currentRecoveryEmail['recovery_email'])) {
        // New recovery email
        Database::execute(
            "UPDATE user_accounts SET recovery_email = :email WHERE user_id = :id",
            ['email' => $email, 'id' => $userId]
        );
    }

    echo json_encode(['success' => true, 'message' => 'Recovery email saved successfully. Please verify it to use for password reset.']);
}

/**
 * Send verification email
 */
function sendVerificationEmail($userId): void
{
    // CSRF validation
    if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh and try again.']);
        exit;
    }

    // Rate limiting
    $rateKey = 'send_recovery_email_verify_' . $userId;
    if (!SecurityHelper::checkRateLimit($rateKey, 5, 3600)) {
        echo json_encode(['success' => false, 'message' => 'Too many verification requests. Please try again later.']);
        exit;
    }

    // Get user and recovery email
    $user = Database::fetch(
        "SELECT ua.user_id, ua.recovery_email, ua.email, ua.username,
                e.first_name, e.last_name
         FROM user_accounts ua
         LEFT JOIN employees e ON ua.emp_id = e.emp_id
         WHERE ua.user_id = :id",
        ['id' => $userId]
    );

    if (!$user || empty($user['recovery_email'])) {
        echo json_encode(['success' => false, 'message' => 'No recovery email set.']);
        exit;
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Store token in user_accounts
    Database::execute(
        "UPDATE user_accounts 
         SET recovery_email_verification_token = :token, recovery_email_verification_expires_at = :expires_at
         WHERE user_id = :id",
        ['token' => $token, 'expires_at' => $expiresAt, 'id' => $userId]
    );

    // Also store in email_verification_tokens table for audit trail
    Database::execute(
        "INSERT INTO email_verification_tokens 
         (user_id, email, token, token_type, expires_at, ip_address, user_agent, created_at)
         VALUES 
         (:user_id, :email, :token, 'recovery_email', :expires_at, :ip, :ua, NOW())",
        [
            'user_id' => $userId,
            'email' => $user['recovery_email'],
            'token' => $token,
            'expires_at' => $expiresAt,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]
    );

    // Format name
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'];

    // Send verification email
    try {
        $emailService = new EmailService();
        $emailService->sendRecoveryEmailVerification($user['recovery_email'], $token, $name);
        echo json_encode(['success' => true, 'message' => 'Verification email sent to ' . $user['recovery_email'] . '.']);
    } catch (Exception $e) {
        error_log("Failed to send recovery email verification: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to send verification email. Please try again.']);
    }
}

/**
 * Verify token (for GET request from email link)
 */
function verifyToken($userId): void
{
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification link.']);
        exit;
    }

    // Check token in user_accounts
    $user = Database::fetch(
        "SELECT user_id, recovery_email_verification_token, recovery_email_verification_expires_at, recovery_email
         FROM user_accounts 
         WHERE user_id = :id",
        ['id' => $userId]
    );

    if (!$user || $user['recovery_email_verification_token'] !== $token) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification link.']);
        exit;
    }

    // Check expiration
    if (strtotime($user['recovery_email_verification_expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Verification link has expired. Please request a new one.']);
        exit;
    }

    // Verify the email
    Database::execute(
        "UPDATE user_accounts 
         SET recovery_email_verified_at = NOW(), 
             recovery_email_verification_token = NULL, 
             recovery_email_verification_expires_at = NULL
         WHERE user_id = :id",
        ['id' => $userId]
    );

    // Mark token as used in verification table
    Database::execute(
        "UPDATE email_verification_tokens SET used_at = NOW() WHERE token = :token",
        ['token' => $token]
    );

    echo json_encode(['success' => true, 'message' => 'Recovery email verified successfully!']);
}
