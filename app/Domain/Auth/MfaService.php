<?php
declare(strict_types=1);

namespace Tpb\Domain\Auth;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Domain\Audit\Audit;

/**
 * MFA-Verwaltung (TOTP + Backup-Codes, §4/§11). Für owner/admin/finance ab dem ersten
 * Nicht-Lokal-Deployment verpflichtend, lokal optional. Backup-Codes werden nur als
 * SHA-256-Hash gespeichert (§3) und dem Nutzer genau einmal im Klartext gezeigt.
 */
final class MfaService
{
    private const PRIVILEGED = ['owner', 'admin', 'finance'];
    private const BACKUP_COUNT = 8;

    public static function isEnabled(int $userId): bool
    {
        $row = Db::run('SELECT mfa_enabled_at FROM users WHERE id = ? LIMIT 1', [$userId])->fetch();
        return $row !== false && $row['mfa_enabled_at'] !== null;
    }

    /** MFA ist für diese Rolle im aktuellen Deployment Pflicht? (lokal optional) */
    public static function required(string $role): bool
    {
        $isLocal = (string) (Env::get('APP_ENV', 'local') ?? 'local') === 'local';
        return !$isLocal && in_array($role, self::PRIVILEGED, true);
    }

    public static function secretFor(int $userId): ?string
    {
        $s = Db::run('SELECT mfa_secret FROM users WHERE id = ? LIMIT 1', [$userId])->fetchColumn();
        return $s === false || $s === null ? null : (string) $s;
    }

    /**
     * Aktiviert MFA nach erfolgreicher Code-Bestätigung; erzeugt Backup-Codes (Klartext
     * nur hier zurückgegeben). Wirft InvalidArgumentException bei falschem Code.
     *
     * @return array<int,string> Klartext-Backup-Codes (einmalig anzeigen)
     */
    public static function enable(int $userId, string $secretBase32, string $code): array
    {
        if (!Totp::verify($secretBase32, $code)) {
            throw new \InvalidArgumentException('Der Code ist ungültig. Bitte erneut aus der App eingeben.');
        }
        return Db::tx(function () use ($userId, $secretBase32): array {
            $now = Clock::nowUtcSeconds();
            Db::run('UPDATE users SET mfa_secret = ?, mfa_enabled_at = ?, updated_at = ? WHERE id = ?', [$secretBase32, $now, $now, $userId]);
            Db::run('DELETE FROM mfa_backup_codes WHERE user_id = ?', [$userId]);

            $codes = [];
            foreach (range(1, self::BACKUP_COUNT) as $i) {
                $code = self::randomBackupCode();
                $codes[] = $code;
                Db::run('INSERT INTO mfa_backup_codes (user_id, code_hash, created_at) VALUES (?, ?, ?)', [$userId, hash('sha256', $code), $now]);
            }
            Audit::log('user', (string) $userId, 'mfa.enabled', ['actor_user_id' => $userId]);
            return $codes;
        });
    }

    public static function disable(int $userId, ?int $actorUserId): void
    {
        Db::tx(function () use ($userId, $actorUserId): void {
            Db::run('UPDATE users SET mfa_secret = NULL, mfa_enabled_at = NULL, updated_at = ? WHERE id = ?', [Clock::nowUtcSeconds(), $userId]);
            Db::run('DELETE FROM mfa_backup_codes WHERE user_id = ?', [$userId]);
            Audit::log('user', (string) $userId, 'mfa.disabled', ['actor_user_id' => $actorUserId ?? $userId]);
        });
    }

    /** Prüft beim Login einen TOTP-Code ODER einen ungenutzten Backup-Code (einmalig). */
    public static function verifyLogin(int $userId, string $code): bool
    {
        $secret = self::secretFor($userId);
        if ($secret !== null && Totp::verify($secret, $code)) {
            return true;
        }
        // Backup-Code (Klartext eingegeben) gegen ungenutzte Hashes prüfen.
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if ($normalized === '') {
            return false;
        }
        $hash = hash('sha256', $normalized);
        $row = Db::run('SELECT id FROM mfa_backup_codes WHERE user_id = ? AND code_hash = ? AND used_at IS NULL LIMIT 1', [$userId, $hash])->fetch();
        if ($row === false) {
            return false;
        }
        Db::run('UPDATE mfa_backup_codes SET used_at = ? WHERE id = ?', [Clock::nowUtcSeconds(), (int) $row['id']]);
        Audit::log('user', (string) $userId, 'mfa.backup_used', ['actor_user_id' => $userId]);
        return true;
    }

    public static function unusedBackupCount(int $userId): int
    {
        return (int) Db::run('SELECT COUNT(*) FROM mfa_backup_codes WHERE user_id = ? AND used_at IS NULL', [$userId])->fetchColumn();
    }

    private static function randomBackupCode(): string
    {
        // 10 Zeichen aus einem Zeichensatz ohne verwechselbare Zeichen (0/O, 1/I).
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }
}
