<?php
/**
 * Small Pusher Channels adapter. Pusher is optional in local environments;
 * transaction processing must not fail when realtime credentials are absent.
 */
final class PusherService
{
    private static ?\Pusher\Pusher $client = null;

    public static function isConfigured(): bool
    {
        return self::credential('PUSHER_APP_ID') !== ''
            && self::credential('PUSHER_KEY') !== ''
            && self::credential('PUSHER_SECRET') !== ''
            && self::credential('PUSHER_CLUSTER') !== ''
            && !self::hasPlaceholder();
    }

    public static function triggerBranch(int $branchId, string $event, array $data): void
    {
        if ($branchId < 1 || !self::isConfigured()) {
            return;
        }

        self::trigger('private-pos-branch-' . $branchId, $event, $data);
    }

    public static function triggerUser(int $userId, string $event, array $data): void
    {
        if ($userId < 1 || !self::isConfigured()) {
            return;
        }

        self::trigger('private-user-' . $userId, $event, $data);
    }

    public static function triggerGlobalNotification(string $event, array $data): void
    {
        if (!self::isConfigured()) {
            return;
        }

        self::trigger('private-notifications-global', $event, $data);
    }

    public static function authorize(string $channelName, string $socketId): string
    {
        return self::client()->socket_auth($channelName, $socketId);
    }

    private static function trigger(string $channelName, string $event, array $data): void
    {
        try {
            self::client()->trigger($channelName, $event, $data);
        } catch (\Throwable $e) {
            // Realtime delivery is best-effort and must never roll back a transaction.
            error_log('[Pusher] Event delivery failed: ' . $e->getMessage());
        }
    }

    private static function client(): \Pusher\Pusher
    {
        if (self::$client instanceof \Pusher\Pusher) {
            return self::$client;
        }

        $autoload = TMS_ROOT . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new \RuntimeException('Composer autoload file is missing.');
        }
        require_once $autoload;

        self::$client = new \Pusher\Pusher(
            self::credential('PUSHER_KEY'),
            self::credential('PUSHER_SECRET'),
            self::credential('PUSHER_APP_ID'),
            [
                'cluster' => self::credential('PUSHER_CLUSTER'),
                'useTLS' => true,
                'timeout' => 5,
            ]
        );

        return self::$client;
    }

    private static function credential(string $key): string
    {
        return trim((string) env($key, ''));
    }

    private static function hasPlaceholder(): bool
    {
        foreach (['PUSHER_APP_ID', 'PUSHER_KEY', 'PUSHER_SECRET'] as $key) {
            if (stripos(self::credential($key), 'YOUR_') !== false) {
                return true;
            }
        }
        return false;
    }
}
