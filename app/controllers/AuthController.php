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
            $this->redirect(LOGIN_URL);
        }

        $user = User::findByUsername($username);

        // Generic message — never reveal which field was wrong.
        if (!$user) {
            $_SESSION['error'] = 'Invalid username or password.';
            $this->redirect(LOGIN_URL);
        }

        if (User::isLocked($user)) {
            $_SESSION['error'] = 'Your account is locked. Please try again later or contact an administrator.';
            $this->redirect(LOGIN_URL);
        }

        if (!password_verify($password, $user['password_hash'] ?? '')) {
            User::recordFailedLogin((int) $user['user_id']);
            $_SESSION['error'] = 'Invalid username or password.';
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
       FORGOT PASSWORD (placeholder — DB-aware, email TBD)
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
        // Always show success — never disclose whether the email exists.
        $_SESSION['success'] = 'If that email exists, a reset link has been sent.';
        header('Location: ' . CONFIRM_MAIL_URL);
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
