<?php
/**
 * Database.php
 * Singleton PDO wrapper for the whole application.
 *
 * Why a singleton:
 *  - One connection per request -> efficient & avoids connection storms.
 *  - Easy to include anywhere: `db()` global helper.
 *  - Future-ready: the same class is used by web pages, AJAX, REST API.
 *
 * Security defaults:
 *  - PDO::ERRMODE_EXCEPTION       (real errors, not silent failures)
 *  - PDO::FETCH_ASSOC             (clean associative arrays)
 *  - EMULATE_PREPARES = false     (true server-side prepared statements)
 *  - utf8mb4 charset              (full Unicode incl. emoji)
 *  - persistent = false           (safer with sessions / locks)
 */

require_once __DIR__ . '/env.php';

final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host    = env('DB_HOST', '127.0.0.1');
        $port    = (int) env('DB_PORT', 3306);
        $name    = env('DB_DATABASE', 'tms_db');
        $user    = env('DB_USERNAME', 'root');
        $pass    = env('DB_PASSWORD', '');
        $charset = env('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // In debug mode, show the real error; otherwise generic message.
            if (env('APP_DEBUG', false) === true) {
                http_response_code(500);
                die('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
            error_log('[DB] ' . $e->getMessage());
            http_response_code(500);
            die('Service temporarily unavailable. Please try again later.');
        }

        return self::$instance;
    }

    /* ----- Convenience helpers (used by models & controllers) ----- */

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }
}

/* Global short helper */
if (!function_exists('db')) {
    function db(): PDO {
        return Database::connection();
    }
}
