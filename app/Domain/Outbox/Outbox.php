<?php
declare(strict_types=1);

namespace Tpb\Domain\Outbox;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Outbox-Enqueue (§10). Fachlogik erzeugt nie direkt Seiteneffekte, sondern
 * schreibt in derselben Transaktion eine outbox_events-Zeile; die Verarbeitung
 * übernimmt cli/outbox_worker.php.
 */
final class Outbox
{
    /**
     * @param array<string,mixed> $payload
     * @return int  Die outbox_events.id (bei bekanntem Idempotency-Key die bestehende).
     */
    public static function enqueue(string $eventType, array $payload, ?string $idempotencyKey = null): int
    {
        $now = Clock::nowUtcSeconds();
        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if ($idempotencyKey !== null) {
            // Doppelte Einreihung verhindern (UNIQUE idempotency_key).
            Db::run(
                'INSERT INTO outbox_events
                    (event_type, payload_json, status, attempts, next_attempt_at, idempotency_key, created_at)
                 VALUES (?, ?, ?, 0, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
                [$eventType, $payloadJson, 'queued', $now, $idempotencyKey, $now]
            );
            return (int) Db::pdo()->lastInsertId();
        }

        Db::run(
            'INSERT INTO outbox_events
                (event_type, payload_json, status, attempts, next_attempt_at, created_at)
             VALUES (?, ?, ?, 0, ?, ?)',
            [$eventType, $payloadJson, 'queued', $now, $now]
        );
        return (int) Db::pdo()->lastInsertId();
    }
}
