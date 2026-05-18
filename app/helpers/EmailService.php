<?php
/**
 * EmailService Class
 * Handles email sending via SMTP or Gmail API
 * Used for password reset and system notifications
 */

class EmailService {
    public $settings;
    public $method;
    
    public function __construct() {
        $this->loadSettings();
    }
    
    /**
     * Load email settings from database
     */
    private function loadSettings() {
        $this->settings = Database::fetch("SELECT * FROM email_settings WHERE setting_id = 1");
        if ($this->settings) {
            $this->method = $this->settings['email_method'] ?? 'smtp';
        } else {
            $this->method = 'smtp';
            $this->settings = [
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'sender_name' => 'Ticketing Services Inc.',
                'sender_email' => 'noreply@ticketingservices.com'
            ];
        }
    }
    
    /**
     * Send email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param array $attachments Optional attachments
     * @return bool Success status
     */
    public function send($to, $subject, $body, $attachments = []) {
        if ($this->method === 'gmail_api') {
            return $this->sendViaGmailAPI($to, $subject, $body, $attachments);
        } else {
            return $this->sendViaSMTP($to, $subject, $body, $attachments);
        }
    }
    
    /**
     * Send email via SMTP
     */
    private function sendViaSMTP($to, $subject, $body, $attachments = []) {
        try {
            // Try PHPMailer first if available
            $vendorPath = dirname(dirname(__DIR__)) . '/vendor';
            
            if (file_exists($vendorPath . '/phpmailer/phpmailer/src/PHPMailer.php')) {
                require_once $vendorPath . '/phpmailer/phpmailer/src/PHPMailer.php';
                require_once $vendorPath . '/phpmailer/phpmailer/src/SMTP.php';
                require_once $vendorPath . '/phpmailer/phpmailer/src/Exception.php';
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host = $this->settings['smtp_host'];
                $mail->Port = (int)$this->settings['smtp_port'];
                $mail->SMTPAuth = true;
                $mail->Username = $this->settings['smtp_username'];
                $mail->Password = $this->settings['smtp_password'];
                
                // Encryption
                if ($this->settings['smtp_encryption'] === 'tls') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($this->settings['smtp_encryption'] === 'ssl') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
                
                // Recipients
                $mail->setFrom($this->settings['sender_email'], $this->settings['sender_name']);
                $mail->addAddress($to);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
                
                // Attachments
                foreach ($attachments as $attachment) {
                    $mail->addAttachment($attachment);
                }
                
                try {
                    $mail->send();
                    return true;
                } catch (Exception $e) {
                    error_log("PHPMailer Error: " . $mail->ErrorInfo);
                    return false;
                }
            } else {
                error_log("PHPMailer not found, using fallback");
            }
            
            // Fallback: Use PHP built-in mail() function
            $headers = [
                'From' => $this->settings['sender_name'] . ' <' . $this->settings['sender_email'] . '>',
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=UTF-8'
            ];
            
            $headerString = '';
            foreach ($headers as $key => $value) {
                $headerString .= "$key: $value\r\n";
            }
            
            return mail($to, $subject, $body, $headerString);
            
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via Gmail API
     */
    private function sendViaGmailAPI($to, $subject, $body, $attachments = []) {
        try {
            // Check if Google Client library is available
            $vendorPath = dirname(dirname(__DIR__)) . '/vendor';
            if (!file_exists($vendorPath . '/google/apiclient/src/Google/Client.php')) {
                error_log("Gmail API Error: Google Client library not found. Please install via composer: composer require google/apiclient:^2.0");
                return false;
            }
            
            // Suppress deprecation warnings from Google libraries
            $oldErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED);
            
            // Use Composer autoloader
            require_once $vendorPath . '/autoload.php';
            
            $client = new Google\Client();
            $client->setClientId($this->settings['gmail_client_id']);
            $client->setClientSecret($this->settings['gmail_client_secret']);
            $client->refreshToken($this->settings['gmail_refresh_token']);
            
            error_log("Gmail API: Access token obtained successfully");
            
            if ($client->getAccessToken()) {
                $service = new Google\Service\Gmail($client);
                
                // Create email message
                $rawMessage = $this->createRawMessage($to, $subject, $body, $attachments);
                
                error_log("Gmail API: Sending message to $to");
                
                // Send message
                $message = new Google\Service\Gmail\Message();
                $message->setRaw($rawMessage);
                $service->users_messages->send('me', $message);
                
                error_log("Gmail API: Message sent successfully");
                
                // Restore error reporting
                error_reporting($oldErrorReporting);
                
                return true;
            }
            
            error_log("Gmail API Error: Failed to obtain access token");
            error_reporting($oldErrorReporting);
            return false;
            
        } catch (Exception $e) {
            error_log("Gmail API Error: " . $e->getMessage());
            error_log("Gmail API Error Trace: " . $e->getTraceAsString());
            error_reporting($oldErrorReporting);
            return false;
        }
    }
    
    /**
     * Create raw email message for Gmail API
     */
    private function createRawMessage($to, $subject, $body, $attachments = []) {
        $boundary = uniqid(rand(), true);
        
        $headers = [
            'To' => $to,
            'From' => $this->settings['sender_name'] . ' <' . $this->settings['sender_email'] . '>',
            'Subject' => $subject,
            'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"'
        ];
        
        $message = '';
        
        // Add headers
        foreach ($headers as $name => $value) {
            $message .= "$name: $value\r\n";
        }
        $message .= "\r\n";
        
        // Plain text version
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $message .= strip_tags($body) . "\r\n";
        
        // HTML version
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
        $message .= $body . "\r\n";
        
        $message .= "--$boundary--\r\n";
        
        return rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    }
    
    /**
     * Send password reset email
     * 
     * @param string $to Recipient email
     * @param string $token Reset token
     * @param string $name Employee name (formatted as "First M. Last")
     * @return bool Success status
     */
    public function sendPasswordResetEmail($to, $token, $name = '') {
        $resetLink = BASE_URL . '/reset-password?token=' . $token;
        
        $subject = 'Password Reset Request';
        
        $body = $this->getPasswordResetTemplate($name, $resetLink);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Get password reset email template
     */
    private function getPasswordResetTemplate($name, $resetLink) {
        $companyName = $this->settings['sender_name'] ?? 'Ticketing Services Inc.';
        $companyAbbreviation = defined('COMPANY_ABBREVIATION') ? COMPANY_ABBREVIATION : 'TMS';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset Request</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: #0d6efd; color: white; padding: 40px 30px; text-align: center; }
                .header h1 { font-size: 28px; font-weight: 600; margin-bottom: 10px; }
                .header p { font-size: 14px; opacity: 0.9; }
                .body { padding: 40px 30px; }
                .body p { margin-bottom: 16px; font-size: 15px; color: #555; }
                .body .highlight { color: #0d6efd; font-weight: 600; }
                .button { display: inline-block; padding: 14px 32px; background: #0d6efd; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; margin: 24px 0; transition: all 0.3s ease; }
                .button:hover { background: #0b5ed7; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4); }
                .link-box { background: #f8f9fa; border-left: 4px solid #0d6efd; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .link-box p { margin: 0; font-size: 13px; color: #666; word-break: break-all; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .warning p { margin: 0; font-size: 14px; color: #856404; }
                .footer { background: #f8f9fa; padding: 25px 30px; text-align: center; border-top: 1px solid #e9ecef; }
                .footer p { margin: 5px 0; font-size: 13px; color: #6c757d; }
                .footer a { color: #0d6efd; text-decoration: none; }
                .footer a:hover { text-decoration: underline; }
                .logo { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>" . htmlspecialchars($companyAbbreviation) . "</div>
                    <h1>Password Reset Request</h1>
                    <p>Secure Account Recovery</p>
                </div>
                <div class='body'>
                    <p>Dear <span class='highlight'>" . htmlspecialchars($name ?: 'User') . "</span>,</p>
                    <p>We received a request to reset your password for your <strong>" . htmlspecialchars($companyName) . "</strong> account.</p>
                    <p>To proceed with resetting your password, please click the button below:</p>
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($resetLink) . "' class='button' style='color: white !important; text-decoration: none;'>Reset Password</a>
                    </p>
                    <div class='link-box'>
                        <p><strong>Alternative:</strong> Copy and paste this link into your browser:</p>
                        <p>" . htmlspecialchars($resetLink) . "</p>
                    </div>
                    <div class='warning'>
                        <p><strong>⚠️ Important:</strong> This link will expire in 1 hour for security reasons.</p>
                    </div>
                    <p>If you did not request this password reset, please ignore this email or contact support if you have concerns about your account security.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . htmlspecialchars($companyName) . ". All rights reserved.</p>
                    <p>This is an automated email. Please do not reply directly to this message.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Test email configuration
     * 
     * @return array Result with success status and message
     */
    public function testConfiguration() {
        $testEmail = $this->settings['sender_email'] ?? 'test@example.com';
        $subject = 'Email Configuration Test';
        $body = '<h1>Test Email</h1><p>If you received this email, your email configuration is working correctly.</p>';
        
        $result = $this->send($testEmail, $subject, $body);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Test email sent successfully to ' . $testEmail
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send test email. Please check your configuration.'
            ];
        }
    }
}
