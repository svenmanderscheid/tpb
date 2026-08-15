<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * DB-basierte Rate-Limits über `login_attempts` (§4, §13.1).
 * Login: max 5 Fehlversuche / 15 min pro E-Mail und pro IP.
 * Generische Buckets (Upload, Statuslink, Angebotsanfrage) mit eigener Grenze.
 */
final class RateLimit
{
    /** bucket => [maxHits, windowSeconds] */
    private const BUCKETS = [
        'upload'       => [30, 900],
        'statuslink'   => [60, 900],
        'quote_request' => [10, 3600],
        'price'        => [240, 300],  // Live-Preis (debounced) im Konfigurator
    ];

    private const LOGIN_MAX = 5;
    private const LOGIN_WINDOW = 900;

    public static function loginBlocked(string $email, string $ip): bool
    {
        return self::countRecent('login_email', strtolower(trim($email)), self::LOGIN_WINDOW) >= self::LOGIN_MAX
            || self::countRecent('login_ip', $ip, self::LOGIN_WINDOW) >= self::LOGIN_MAX;
    }

    public static function recordLogin(string $email, string $ip, bool $success): void
    {
        self::insert('login_email', strtolower(trim($email)), $success);
        self::insert('login_ip', $ip, $success);
    }

    /**
     * Generischer Bucket-Check per Router-Middleware (rate:<bucket>).
     * Zählt den Zugriff und wirft 429 bei Überschreitung.
     */
    public static function enforce(string $bucket, string $identifier): void
    {
        if (!isset(self::BUCKETS[$bucket])) {
            return;
        }
        [$max, $window] = self::BUCKETS[$bucket];
        if (self::countRecent($bucket, $identifier, $window) >= $max) {
            throw new HttpException(429, 'Zu viele Anfragen – bitte später erneut versuchen');
        }
        // Treffer als "verbraucht" zählen (success=0, damit countRecent ihn erfasst).
        self::insert($bucket, $identifier, false);
    }

    private static function countRecent(string $bucket, string $identifier, int $windowSeconds): int
    {
        $since = Clock::nowUtc()
            ->sub(new \DateInterval('PT' . $windowSeconds . 'S'))
            ->format('Y-m-d H:i:s');
        $stmt = Db::run(
            'SELECT COUNT(*) FROM login_attempts
             WHERE bucket = ? AND identifier = ? AND success = 0 AND attempted_at >= ?',
            [$bucket, $identifier, $since]
        );
        return (int) $stmt->fetchColumn();
    }

    private static function insert(string $bucket, string $identifier, bool $success): void
    {
        Db::run(
            'INSERT INTO login_attempts (bucket, identifier, success, attempted_at) VALUES (?, ?, ?, ?)',
            [$bucket, $identifier, $success ? 1 : 0, Clock::nowUtcSeconds()]
        );
    }
}
