<?php
declare(strict_types=1);

namespace Tpb\Domain\Auth;

use Tpb\Core\Db;

/**
 * „sudo-Modus" (§11/§13.1): erneute Bestätigung (Passwort ODER TOTP) für kritische
 * Aktionen (Rechnungsausstellung, Gutschrift, Preisbuch-Publish, Benutzerverwaltung).
 * Die Bestätigung gilt 5 Minuten pro Session.
 */
final class Sudo
{
    private const TTL = 300;

    public static function isFresh(): bool
    {
        $at = (int) ($_SESSION['sudo_at'] ?? 0);
        return $at > 0 && (time() - $at) <= self::TTL;
    }

    public static function touch(): void
    {
        $_SESSION['sudo_at'] = time();
    }

    /** Bestätigt mit Passwort oder – falls MFA aktiv – mit einem TOTP/Backup-Code. */
    public static function confirm(int $userId, string $input): bool
    {
        if ($input === '') {
            return false;
        }
        $hash = Db::run('SELECT pass_hash FROM users WHERE id = ? LIMIT 1', [$userId])->fetchColumn();
        if ($hash !== false && password_verify($input, (string) $hash)) {
            self::touch();
            return true;
        }
        if (MfaService::isEnabled($userId) && MfaService::verifyLogin($userId, $input)) {
            self::touch();
            return true;
        }
        return false;
    }
}
