<?php
declare(strict_types=1);

namespace Tpb\Domain\Audit;

use Tpb\Core\Auth;
use Tpb\Core\Canonical;
use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Append-only Audit-Log (§3.7, §13.5). Jede fachliche Mutation ruft log().
 * Kein UPDATE/DELETE auf audit_events im Code.
 */
final class Audit
{
    /**
     * @param array<string,mixed> $opts  Optionale Felder:
     *   actor_user_id, actor_label, from_state, to_state, reason_code,
     *   correlation_id, before_hash, after_hash, metadata (array)
     */
    public static function log(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $opts = []
    ): void {
        $actorId = $opts['actor_user_id'] ?? Auth::id();
        $metadata = $opts['metadata'] ?? null;
        $metadataJson = is_array($metadata) ? Canonical::json($metadata) : null;

        Db::run(
            'INSERT INTO audit_events
                (occurred_at, actor_user_id, actor_label, aggregate_type, aggregate_id,
                 event_type, from_state, to_state, reason_code, correlation_id,
                 before_hash, after_hash, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Clock::nowUtcMs(),
                $actorId !== null ? (int) $actorId : null,
                $opts['actor_label'] ?? null,
                $aggregateType,
                $aggregateId,
                $eventType,
                $opts['from_state'] ?? null,
                $opts['to_state'] ?? null,
                $opts['reason_code'] ?? null,
                $opts['correlation_id'] ?? null,
                $opts['before_hash'] ?? null,
                $opts['after_hash'] ?? null,
                $metadataJson,
            ]
        );
    }
}
