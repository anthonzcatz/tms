<?php
/**
 * Email Settings Controller
 * Manages email configuration for password reset and system notifications
 */

require_once dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/SecurityHelper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/_guard.php';

// Prevent caching of admin pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Require login and permission
Auth::requireLogin();
// SUPER_ADMIN has access to everything
$user = Auth::user();
if ($user && $user['role_code'] === 'SUPER_ADMIN') {
    // Allow
} elseif (!Auth::canAccessModule('admin/settings/email-settings/')) {
    http_response_code(403);
    include dirname(dirname(__DIR__)) . '/includes/access-denied.php';
    exit;
}

// Handle test email request
if (isset($_GET['action']) && $_GET['action'] === 'test_email') {
    header('Content-Type: application/json');
    
    if (!SecurityHelper::validateCSRFToken($_GET['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $testEmail = $_GET['test_email'] ?? '';
    if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    $emailSettings = Database::fetch("SELECT * FROM email_settings WHERE setting_id = 1");
    if (!$emailSettings) {
        echo json_encode(['success' => false, 'message' => 'Email settings not configured']);
        exit;
    }
    
    // Override email method if specified
    $testMethod = $_GET['test_method'] ?? $emailSettings['email_method'];
    $originalMethod = $emailSettings['email_method'];
    $emailSettings['email_method'] = $testMethod;
    
    require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/EmailService.php';
    
    try {
        $emailService = new EmailService();
        // Manually set the method for testing
        $emailService->method = $testMethod;
        $emailService->settings = $emailSettings;
        
        $result = $emailService->send(
            $testEmail,
            'Test Email from TMS',
            '<h1>Test Email</h1><p>This is a test email from TMS Email Settings.</p><p>If you received this email, your email configuration is working correctly!</p>'
        );
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Test email sent successfully to ' . $testEmail . ' via ' . strtoupper($testMethod)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send test email via ' . strtoupper($testMethod) . '. Check your email configuration.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        error_log("Email test error: " . $e->getMessage());
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $emailMethod = $_POST['email_method'] ?? 'smtp';
    $smtpHost = $_POST['smtp_host'] ?? '';
    $smtpPort = $_POST['smtp_port'] ?? '587';
    $smtpUsername = $_POST['smtp_username'] ?? '';
    $smtpPassword = $_POST['smtp_password'] ?? '';
    $smtpEncryption = $_POST['smtp_encryption'] ?? 'tls';
    $senderName = $_POST['sender_name'] ?? '';
    $senderEmail = $_POST['sender_email'] ?? '';
    $gmailClientId = $_POST['gmail_client_id'] ?? '';
    $gmailClientSecret = $_POST['gmail_client_secret'] ?? '';
    $gmailRefreshToken = $_POST['gmail_refresh_token'] ?? '';
    
    // Validate required fields based on method
    $errors = [];
    if ($emailMethod === 'smtp') {
        if (empty($smtpHost)) $errors[] = 'SMTP host is required';
        if (empty($smtpUsername)) $errors[] = 'SMTP username is required';
        if (empty($senderEmail)) $errors[] = 'Sender email is required';
    } elseif ($emailMethod === 'gmail_api') {
        if (empty($gmailClientId)) $errors[] = 'Gmail Client ID is required';
        if (empty($gmailClientSecret)) $errors[] = 'Gmail Client Secret is required';
        if (empty($senderEmail)) $errors[] = 'Sender email is required';
    }
    
    if (empty($errors)) {
        // Check if email settings already exist
        $existingSettings = Database::fetch("SELECT * FROM email_settings WHERE setting_id = 1");
        
        if ($existingSettings) {
            // Update existing settings
            Database::execute(
                "UPDATE email_settings SET 
                    email_method = :method,
                    smtp_host = :smtp_host,
                    smtp_port = :smtp_port,
                    smtp_username = :smtp_username,
                    smtp_password = :smtp_password,
                    smtp_encryption = :smtp_encryption,
                    sender_name = :sender_name,
                    sender_email = :sender_email,
                    gmail_client_id = :gmail_client_id,
                    gmail_client_secret = :gmail_client_secret,
                    gmail_refresh_token = :gmail_refresh_token,
                    updated_at = NOW()
                WHERE setting_id = 1",
                [
                    'method' => $emailMethod,
                    'smtp_host' => $smtpHost,
                    'smtp_port' => $smtpPort,
                    'smtp_username' => $smtpUsername,
                    'smtp_password' => $smtpPassword,
                    'smtp_encryption' => $smtpEncryption,
                    'sender_name' => $senderName,
                    'sender_email' => $senderEmail,
                    'gmail_client_id' => $gmailClientId,
                    'gmail_client_secret' => $gmailClientSecret,
                    'gmail_refresh_token' => $gmailRefreshToken
                ]
            );
        } else {
            // Insert new settings
            Database::execute(
                "INSERT INTO email_settings (
                    email_method, smtp_host, smtp_port, smtp_username, smtp_password,
                    smtp_encryption, sender_name, sender_email, gmail_client_id,
                    gmail_client_secret, gmail_refresh_token, created_at, updated_at
                ) VALUES (
                    :method, :smtp_host, :smtp_port, :smtp_username, :smtp_password,
                    :smtp_encryption, :sender_name, :sender_email, :gmail_client_id,
                    :gmail_client_secret, :gmail_refresh_token, NOW(), NOW()
                )",
                [
                    'method' => $emailMethod,
                    'smtp_host' => $smtpHost,
                    'smtp_port' => $smtpPort,
                    'smtp_username' => $smtpUsername,
                    'smtp_password' => $smtpPassword,
                    'smtp_encryption' => $smtpEncryption,
                    'sender_name' => $senderName,
                    'sender_email' => $senderEmail,
                    'gmail_client_id' => $gmailClientId,
                    'gmail_client_secret' => $gmailClientSecret,
                    'gmail_refresh_token' => $gmailRefreshToken
                ]
            );
        }
        
        $_SESSION['success'] = 'Email settings saved successfully.';
        header('Location: ' . BASE_URL . '/admin/settings/email-settings/');
        exit;
    }
}

// Get current email settings
$emailSettings = Database::fetch("SELECT * FROM email_settings WHERE setting_id = 1");

// Include the main view
include __DIR__ . '/views/index.php';
