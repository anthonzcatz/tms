<?php
/**
 * AuthController — DB-backed authentication.
 *
 * Authenticates against `user_accounts` (see complete_db_structure.sql).
 * Features:
 *   - PDO prepared statements (zero SQL injection surface)
 *   - Argon2id password hashing (via SecurityHelper)
 *   - Per-account lockout (5 failed attempts -> 15 min)
 *   - Per-IP rate limiting (5 attempts / 5 min)
 *   - CSRF token validation
 *   - Session fingerprinting (Auth::login)
 */

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/EmailService.php';

class AuthController
{
    public function __construct()
    {
        // Bootstrap already started a hardened session.
        SecurityHelper::addSecurityHeaders();
    }

    /* =========================================================
       LOGIN
       ========================================================= */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error'] = 'Invalid request method.';
            $this->redirect(LOGIN_URL);
        }

        $rateKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!SecurityHelper::checkRateLimit($rateKey, 5, 300)) {
            $_SESSION['error'] = 'Too many login attempts. Please try again in a few minutes.';
            $this->redirect(LOGIN_URL);
        }

        if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Invalid request. Please refresh and try again.';
            $this->redirect(LOGIN_URL);
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '') {
            $_SESSION['error'] = 'Username is required.';
            $this->redirect(LOGIN_URL);
        }
        if ($password === '') {
            $_SESSION['error'] = 'Password is required.';
            $_SESSION['login_username'] = $username;
            $this->redirect(LOGIN_URL);
        }

        $user = User::findByUsername($username);

        // Generic message — never reveal which field was wrong.
        if (!$user) {
            $_SESSION['error'] = 'Invalid username or password.';
            $_SESSION['login_username'] = $username;
            $this->redirect(LOGIN_URL);
        }

        if (User::isLocked($user)) {
            $_SESSION['error'] = 'Your account is locked. Please try again later or contact an administrator.';
            $_SESSION['login_username'] = $username;
            $this->redirect(LOGIN_URL);
        }

        if (!password_verify($password, $user['password_hash'] ?? '')) {
            User::recordFailedLogin((int) $user['user_id']);
            $_SESSION['error'] = 'Invalid username or password.';
            $_SESSION['login_username'] = $username;
            $this->redirect(LOGIN_URL);
        }

        // Optional: rehash if algorithm/parameters changed
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            Database::execute(
                "UPDATE user_accounts SET password_hash = :h WHERE user_id = :id",
                ['h' => password_hash($password, PASSWORD_ARGON2ID), 'id' => $user['user_id']]
            );
        }

        // Success
        User::recordSuccessfulLogin((int) $user['user_id']);
        Auth::login($user);
        unset($_SESSION['rate_limit'][$rateKey]);
        SecurityHelper::regenerateCSRFToken();

        $_SESSION['success'] = 'Login successful! Welcome back, ' . ($user['fullname'] ?? $user['username']) . '.';

        // Redirect to role's default dashboard
        $dashboard = $user['default_dashboard'] ?? '/admin/dashboard/analytics';
        header('Location: ' . BASE_URL . $dashboard);
        exit;
    }

    /* =========================================================
       REGISTER (kept compatible — wires into user_accounts)
       ========================================================= */
    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(REGISTER_URL);
        }

        $rateKey = 'register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!SecurityHelper::checkRateLimit($rateKey, 3, 3600)) {
            $this->fail(REGISTER_URL, 'Too many registration attempts. Please try again later.', 'rate_limit');
        }
        if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->fail(REGISTER_URL, 'Invalid request. Please try again.', 'csrf');
        }

        $name             = trim((string) ($_POST['name'] ?? ''));
        $email            = trim((string) ($_POST['email'] ?? ''));
        $password         = (string) ($_POST['password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        $errors = [];
        if ($name === '')                                   $errors[] = 'Name is required.';
        if (!SecurityHelper::validateEmail($email))         $errors[] = 'Invalid email address.';
        if (!SecurityHelper::validatePassword($password))   $errors[] = 'Password must be 8+ chars with upper, lower, number, and special char.';
        if ($password !== $confirm_password)                $errors[] = 'Passwords do not match.';

        if ($errors) {
            $_SESSION['form_data'] = ['name' => $name, 'email' => $email];
            $this->fail(REGISTER_URL, implode('<br>', $errors), 'validation');
        }

        if (User::findByEmail($email)) {
            $this->fail(REGISTER_URL, 'An account with that email already exists.', 'duplicate');
        }

        // Default role: CASHIER (lowest access level in their seeding.sql)
        $role = Database::fetch("SELECT role_id FROM user_roles WHERE role_code = 'CASHIER' LIMIT 1")
            ?? Database::fetch("SELECT role_id FROM user_roles ORDER BY role_id ASC LIMIT 1");

        if (!$role) {
            $this->fail(REGISTER_URL, 'No roles configured. Contact an administrator.', 'no_role');
        }

        Database::execute(
            "INSERT INTO user_accounts
                (user_code, role_id, fullname, email, password_hash, status, created_at)
             VALUES
                (:user_code, :role_id, :fullname, :email, :hash, 'active', NOW())",
            [
                'user_code' => 'U' . strtoupper(bin2hex(random_bytes(4))),
                'role_id'   => $role['role_id'],
                'fullname'  => $name,
                'email'     => $email,
                'hash'      => password_hash($password, PASSWORD_ARGON2ID),
            ]
        );

        $_SESSION['success'] = 'Registration successful! You may now log in.';
        header('Location: ' . LOGIN_URL);
        exit;
    }

    /* =========================================================
       FORGOT PASSWORD (DB-aware with EmailService)
       ========================================================= */
    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(FORGOT_PASSWORD_URL);
        }
        if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->fail(FORGOT_PASSWORD_URL, 'Invalid request.', 'csrf');
        }
        
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!SecurityHelper::validateEmail($email)) {
            $this->fail(FORGOT_PASSWORD_URL, 'Invalid email address.', 'invalid_email');
        }
        
        // Find user by email with employee details
        $user = Database::fetch(
            "SELECT ua.user_id, ua.username, ua.email, ua.emp_id,
                    e.first_name, e.middle_name, e.last_name
             FROM user_accounts ua
             LEFT JOIN employees e ON ua.emp_id = e.emp_id
             WHERE ua.email = :email 
             AND ua.status = 'active' AND ua.deleted_at IS NULL 
             LIMIT 1",
            ['email' => $email]
        );
        
        if (!$user) {
            $_SESSION['error'] = 'The email address you entered is not registered in our system.';
            header('Location: ' . BASE_URL . '/forgot-password');
            exit;
        }
        
        // Format employee name
        $employeeName = trim(($user['first_name'] ?? '') . ' ' . 
                           ($user['middle_name'] ? substr($user['middle_name'], 0, 1) . '. ' : '') . 
                           ($user['last_name'] ?? ''));
        if (empty($employeeName)) {
            $employeeName = $user['username'];
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Delete any existing tokens for this user
        Database::execute(
            "DELETE FROM password_reset_tokens WHERE user_id = :user_id",
            ['user_id' => $user['user_id']]
        );
        
        // Store new token
        Database::execute(
            "INSERT INTO password_reset_tokens 
             (user_id, token, email, expires_at, ip_address, user_agent, created_at)
             VALUES 
             (:user_id, :token, :email, :expires_at, :ip, :ua, NOW())",
            [
                'user_id' => $user['user_id'],
                'token' => $token,
                'email' => $email,
                'expires_at' => $expiresAt,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]
        );
        
        // Send email using EmailService
        try {
            $emailService = new EmailService();
            $emailService->sendPasswordResetEmail($email, $token, $employeeName);
        } catch (Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
            // Continue anyway - don't reveal error to user
        }
        
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Show success
        $_SESSION['success'] = 'A password reset link has been sent to your email.';
        $_SESSION['reset_email'] = $email;
        header('Location: ' . BASE_URL . '/confirm-mail');
        exit;
    }

    /* =========================================================
       LOGOUT
       ========================================================= */
    public function logout(): void
    {
        Auth::logout();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['success'] = 'You have been logged out successfully.';
        header('Location: ' . LOGIN_URL);
        exit;
    }

    /* =========================================================
       Helpers
       ========================================================= */
    public function getCSRFToken(): string
    {
        return SecurityHelper::generateCSRFToken();
    }

    public function isLoggedIn(): bool
    {
        return Auth::check();
    }

    public function requireLogin(): void
    {
        Auth::requireLogin();
    }

    private function redirect(string $url): void
    {
        session_write_close();
        header('Location: ' . $url);
        exit;
    }

    private function fail(string $url, string $msg, string $code): void
    {
        $_SESSION['error'] = $msg;
        header('Location: ' . $url . '?error=' . $code);
        exit;
    }
}
