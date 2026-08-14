<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Login/Logout, Session-Härtung (§4, §11). password_hash/verify,
 * Session-Regeneration nach Login, Idle-/Absolut-Timeout.
 */
final class Auth
{
    private const IDLE_TIMEOUT = 1800;      // 30 min
    private const ABSOLUTE_TIMEOUT = 43200; // 12 h

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            return;
        }
        $secure = Env::get('APP_ENV', 'local') !== 'local';
        session_name('tpb_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * Prüft Credentials, gibt den User-Datensatz zurück oder null.
     * @return array<string,mixed>|null
     */
    public static function attempt(string $email, string $password): ?array
    {
        $row = Db::run(
            'SELECT id, email, pass_hash, display_name, role, status FROM users WHERE email = ? LIMIT 1',
            [strtolower(trim($email))]
        )->fetch();

        if (!$row || $row['status'] !== 'active') {
            return null;
        }
        if (!password_verify($password, (string) $row['pass_hash'])) {
            return null;
        }
        return $row;
    }

    /** @param array<string,mixed> $user */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['display_name'] = (string) $user['display_name'];
        $now = time();
        $_SESSION['login_at'] = $now;
        $_SESSION['last_seen'] = $now;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function check(): bool
    {
        return !empty($_SESSION['uid']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
    }

    public static function role(): ?string
    {
        return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
    }

    public static function displayName(): ?string
    {
        return isset($_SESSION['display_name']) ? (string) $_SESSION['display_name'] : null;
    }

    /**
     * Erzwingt Idle- und Absolut-Timeout; verwirft die Session bei Ablauf.
     */
    public static function enforceTimeouts(): void
    {
        if (!self::check()) {
            return;
        }
        $now = time();
        $loginAt = (int) ($_SESSION['login_at'] ?? $now);
        $lastSeen = (int) ($_SESSION['last_seen'] ?? $now);
        if ($now - $loginAt > self::ABSOLUTE_TIMEOUT || $now - $lastSeen > self::IDLE_TIMEOUT) {
            self::logout();
            return;
        }
        $_SESSION['last_seen'] = $now;
    }
}
