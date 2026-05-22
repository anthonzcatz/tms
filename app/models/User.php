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
        $lockedUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        Database::execute(
            "UPDATE user_accounts
                SET failed_login_attempts = failed_login_attempts + 1,
                    locked_until = CASE
                        WHEN failed_login_attempts + 1 >= 5
                        THEN :locked_until
                        ELSE locked_until
                    END
              WHERE user_id = :id",
            ['id' => $userId, 'locked_until' => $lockedUntil]
        );
    }

    public static function recordSuccessfulLogin(int $userId): void
    {
        Database::execute(
            "UPDATE user_accounts
                SET failed_login_attempts = 0,
                    locked_until = NULL,
                    last_login_at = :last_login_at
              WHERE user_id = :id",
            ['id' => $userId, 'last_login_at' => date('Y-m-d H:i:s')]
        );
    }

    /**
     * Check whether a user is allowed to log in right now based on
     * is_time_restricted, allowed_login_start, allowed_login_end, allowed_days.
     *
     * Returns null when access is permitted.
     * Returns a human-readable error string when access is denied.
     *
     * @param  array       $user      Full user row (must include time restriction columns).
     * @param  string|null $timezone  PHP timezone identifier, e.g. 'Asia/Manila'.
     *                                Defaults to the server's configured timezone.
     */
    public static function checkTimeRestrictions(array $user, ?string $timezone = null): ?string
    {
        // Feature not enabled for this user — always allow.
        if (empty($user['is_time_restricted'])) {
            return null;
        }

        $tz = new \DateTimeZone($timezone ?? (date_default_timezone_get() ?: 'UTC'));
        $now = new \DateTime('now', $tz);

        // --- Day-of-week check -------------------------------------------------
        if (!empty($user['allowed_days'])) {
            $allowedDays = array_map('trim', explode(',', $user['allowed_days']));

            // Normalise to lowercase for comparison
            $allowedDaysLower = array_map('strtolower', $allowedDays);

            // PHP date('l') returns full English name e.g. "Wednesday"
            $todayName = strtolower($now->format('l'));

            if (!in_array($todayName, $allowedDaysLower, true)) {
                $friendlyDays = implode(', ', $allowedDays);
                return "You cannot log in today. Login is only allowed on {$friendlyDays}.";
            }
        }

        // --- Time-of-day check -------------------------------------------------
        $startStr = $user['allowed_login_start'] ?? null;
        $endStr   = $user['allowed_login_end']   ?? null;

        if ($startStr && $endStr) {
            $currentTime = (int) $now->format('Hi'); // e.g. 1430 for 14:30

            // Strip colons and convert to integer for easy comparison
            $startTime = (int) str_replace(':', '', substr($startStr, 0, 5));
            $endTime   = (int) str_replace(':', '', substr($endStr,   0, 5));

            $allowed = false;
            if ($startTime <= $endTime) {
                // Normal window: e.g. 08:00 – 17:00
                $allowed = ($currentTime >= $startTime && $currentTime <= $endTime);
            } else {
                // Overnight window: e.g. 22:00 – 06:00
                $allowed = ($currentTime >= $startTime || $currentTime <= $endTime);
            }

            if (!$allowed) {
                $friendlyStart = date('g:i A', strtotime($startStr));
                $friendlyEnd   = date('g:i A', strtotime($endStr));
                return "Login is only allowed between {$friendlyStart} and {$friendlyEnd}.";
            }
        }

        return null; // All checks passed.
    }

    /**
     * Log a time-restriction violation for audit purposes.
     *
     * @param int    $userId    The user who attempted to log in.
     * @param string $reason    The denial reason string.
     * @param string $ipAddress Remote IP address.
     */
    public static function logTimeRestrictionViolation(int $userId, string $reason, string $ipAddress): void
    {
        try {
            Database::execute(
                "INSERT INTO time_restriction_logs
                    (user_id, attempted_at, ip_address, denial_reason)
                 VALUES
                    (:user_id, :attempted_at, :ip_address, :denial_reason)",
                [
                    'user_id'       => $userId,
                    'attempted_at'  => date('Y-m-d H:i:s'),
                    'ip_address'    => $ipAddress,
                    'denial_reason' => $reason,
                ]
            );
        } catch (\Exception $e) {
            error_log('Failed to log time restriction violation: ' . $e->getMessage());
        }
    }
}
