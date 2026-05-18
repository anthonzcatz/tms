<?php
/**
 * Gmail OAuth Callback Handler
 * Handles the OAuth callback from Google and stores the refresh token
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

// Get authorization code from Google
$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    $_SESSION['error'] = 'OAuth authorization failed: ' . $error;
    header('Location: ' . BASE_URL . '/admin/settings/email-settings/');
    exit;
}

if (empty($code)) {
    $_SESSION['error'] = 'No authorization code received from Google';
    header('Location: ' . BASE_URL . '/admin/settings/email-settings/');
    exit;
}

// Get email settings
$emailSettings = Database::fetch("SELECT * FROM email_settings WHERE setting_id = 1");

if (!$emailSettings || empty($emailSettings['gmail_client_id']) || empty($emailSettings['gmail_client_secret'])) {
    $_SESSION['error'] = 'Email settings not configured. Please set up Gmail Client ID and Client Secret first.';
    header('Location: ' . BASE_URL . '/admin/settings/email-settings/');
    exit;
}

// Exchange authorization code for refresh token
$postData = [
    'code' => $code,
    'client_id' => $emailSettings['gmail_client_id'],
    'client_secret' => $emailSettings['gmail_client_secret'],
    'redirect_uri' => BASE_URL . '/gmail-callback',
    'grant_type' => 'authorization_code'
];

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $_SESSION['error'] = 'Failed to exchange authorization code: ' . $response;
    header('Location: ' . BASE_URL . '/admin/settings/email-settings/');
    exit;
}

$tokenData = json_decode($response, true);

if (!isset($tokenData['refresh_token'])) {
    $_SESSION['error'] = 'No refresh token received. Please ensure you requested offline access.';
    header('Location: ' . BASE_URL . '/admin/settings/email-settings/');
    exit;
}

// Store refresh token in database
Database::execute(
    "UPDATE email_settings SET gmail_refresh_token = :token, updated_at = NOW() WHERE setting_id = 1",
    ['token' => $tokenData['refresh_token']]
);

$_SESSION['success'] = 'Gmail authorization successful! You can now send emails via Gmail API.';
header('Location: ' . BASE_URL . '/admin/settings/email-settings/');
exit;
