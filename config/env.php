<?php
/**
 * Lightweight .env loader (no Composer required).
 * Loads key=value pairs from <project root>/.env into $_ENV / getenv()
 * and exposes a global env() helper with default fallback.
 *
 * Usage:
 *   require_once __DIR__ . '/env.php';
 *   $host = env('DB_HOST', '127.0.0.1');
 */

if (!function_exists('env_load')) {
    function env_load(string $path): void {
        static $loaded = [];
        if (isset($loaded[$path]) || !is_file($path)) {
            return;
        }
        $loaded[$path] = true;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip inline comments (only when value is unquoted)
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = rtrim(substr($value, 0, $hash));
                }
            }

            // Strip surrounding quotes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Only set if not already defined in real environment
            if (getenv($key) === false) {
                putenv("$key=$value");
            }
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        // Cast common literal values
        $lower = strtolower($value);
        switch ($lower) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
            default:
                return $value;
        }
    }
}

// Auto-load the project's .env on first include
env_load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
