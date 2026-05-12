<?php
/**
 * User model — encapsulates all queries against `user_accounts`.
 * Keeps SQL out of controllers and is easy to reuse from REST API.
 */
final class User
{
    /** Find an active (not soft-deleted) user by email. */
    public static function findByEmail(string $email): ?array
    {
        return Database::fetch(
            "SELECT u.*, r.role_code, r.role_name, r.default_dashboard
               FROM user_accounts u
               LEFT JOIN user_roles r ON r.role_id = u.role_id
              WHERE u.email = :email
                AND u.deleted_at IS NULL
              LIMIT 1",
            ['email' => $email]
        );
    }

    /** Find an active (not soft-deleted) user by username. */
    public static function findByUsername(string $username): ?array
    {
        return Database::fetch(
            "SELECT u.*, r.role_code, r.role_name, r.default_dashboard
               FROM user_accounts u
               LEFT JOIN user_roles r ON r.role_id = u.role_id
              WHERE u.username = :username
                AND u.deleted_at IS NULL
              LIMIT 1",
            ['username' => $username]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch(
            "SELECT u.*, r.role_code, r.role_name
               FROM user_accounts u
               LEFT JOIN user_roles r ON r.role_id = u.role_id
              WHERE u.user_id = :id
                AND u.deleted_at IS NULL
              LIMIT 1",
            ['id' => $id]
        );
    }

    public static function isLocked(array $user): bool
    {
        if ($user['status'] !== 'active') return true;
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return true;
        }
        return false;
    }

    public static function recordFailedLogin(int $userId): void
    {
        // Lock the account for 15 min after 5 consecutive failed attempts.
        Database::execute(
            "UPDATE user_accounts
                SET failed_login_attempts = failed_login_attempts + 1,
                    locked_until = CASE
                        WHEN failed_login_attempts + 1 >= 5
                        THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        ELSE locked_until
                    END
              WHERE user_id = :id",
            ['id' => $userId]
        );
    }

    public static function recordSuccessfulLogin(int $userId): void
    {
        Database::execute(
            "UPDATE user_accounts
                SET failed_login_attempts = 0,
                    locked_until = NULL,
                    last_login_at = NOW()
              WHERE user_id = :id",
            ['id' => $userId]
        );
    }
}
