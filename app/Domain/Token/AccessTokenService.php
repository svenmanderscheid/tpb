<?php
declare(strict_types=1);

namespace Tpb\Domain\Token;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Kurzlebige, widerrufbare Zugriffstoken (§8, §11). Nur der SHA-256-Hash wird
 * gespeichert – der Rohwert existiert genau einmal (in der versendeten URL).
 * Fortlaufende Nummern sind nie Zugriffsschutz; öffentliche Kundenlinks tragen
 * public_id + dieses Token.
 */
final class AccessTokenService
{
    /**
     * Erzeugt ein Token für (purpose, refType, refId) und gibt den ROHWERT zurück.
     * TTL in Sekunden; Default 30 Tage (Angebotslink überlebt die Gültigkeitsdauer).
     */
    public static function issue(string $purpose, string $refType, int $refId, int $ttlSeconds = 2592000): string
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expiresAt = Clock::nowUtc()->modify("+{$ttlSeconds} seconds")->format('Y-m-d H:i:s');

        Db::run(
            'INSERT INTO access_tokens (token_hash, purpose, ref_type, ref_id, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$hash, $purpose, $refType, $refId, $expiresAt, Clock::nowUtcSeconds()]
        );

        return $raw;
    }

    /**
     * Prüft ein Roh-Token. Gibt die Token-Zeile zurück oder null (unbekannt,
     * widerrufen oder abgelaufen). Bei Erfolg wird last_used_at gesetzt.
     *
     * @return array<string,mixed>|null
     */
    public static function verify(string $raw, string $purpose, string $refType, int $refId): ?array
    {
        if ($raw === '') {
            return null;
        }
        $hash = hash('sha256', $raw);
        $row = Db::run(
            'SELECT id, expires_at, revoked_at FROM access_tokens
             WHERE token_hash = ? AND purpose = ? AND ref_type = ? AND ref_id = ? LIMIT 1',
            [$hash, $purpose, $refType, $refId]
        )->fetch();

        if ($row === false) {
            return null;
        }
        if ($row['revoked_at'] !== null) {
            return null;
        }
        if ((string) $row['expires_at'] < Clock::nowUtcSeconds()) {
            return null;
        }

        Db::run('UPDATE access_tokens SET last_used_at = ? WHERE id = ?', [Clock::nowUtcSeconds(), (int) $row['id']]);
        return $row;
    }

    public static function revoke(string $purpose, string $refType, int $refId): void
    {
        Db::run(
            'UPDATE access_tokens SET revoked_at = ?
             WHERE purpose = ? AND ref_type = ? AND ref_id = ? AND revoked_at IS NULL',
            [Clock::nowUtcSeconds(), $purpose, $refType, $refId]
        );
    }
}
