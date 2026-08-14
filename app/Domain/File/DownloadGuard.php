<?php
declare(strict_types=1);

namespace Tpb\Domain\File;

use Tpb\Core\Auth;
use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Zugriffskontrolle für private Downloads (§8): angemeldeter Nutzer mit
 * Objektberechtigung ODER gültiges kurzlebiges Token (access_tokens,
 * purpose file_download). Fortlaufende Nummern sind nie Zugriffsschutz (§11).
 */
final class DownloadGuard
{
    /**
     * @param array<string,mixed> $asset
     */
    public static function authorize(array $asset, ?string $token): bool
    {
        // 1) Angemeldeter Backoffice-Nutzer (M0: jede aktive Rolle darf lesen).
        if (Auth::check()) {
            return true;
        }

        // 2) Kurzlebiges Download-Token.
        if ($token !== null && $token !== '') {
            return self::tokenValid($token, (int) $asset['id']);
        }

        return false;
    }

    private static function tokenValid(string $token, int $assetId): bool
    {
        $hash = hash('sha256', $token);
        $row = Db::run(
            'SELECT id, expires_at, revoked_at FROM access_tokens
             WHERE token_hash = ? AND purpose = ? AND ref_type = ? AND ref_id = ? LIMIT 1',
            [$hash, 'file_download', 'asset', $assetId]
        )->fetch();

        if (!$row) {
            return false;
        }
        if ($row['revoked_at'] !== null) {
            return false;
        }
        if ((string) $row['expires_at'] < Clock::nowUtcSeconds()) {
            return false;
        }

        Db::run('UPDATE access_tokens SET last_used_at = ? WHERE id = ?', [Clock::nowUtcSeconds(), (int) $row['id']]);
        return true;
    }
}
