<?php
declare(strict_types=1);

namespace Tpb\Domain\Status;

use Tpb\Core\Auth;
use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Schreibt einen validierten Statuswechsel in status_events (§7). Wahrheit ist
 * status_events; Cache-Spalten (z. B. orders.cur_*) aktualisiert der Aufrufer.
 * Innerhalb der Mutations-Transaktion aufrufen (nutzt die gemeinsame Verbindung).
 *
 * @param array{actor_user_id?:int|null,actor_label?:string|null,reason?:string|null,correlation_id?:string|null} $opts
 */
final class Status
{
    /** @throws IllegalTransitionException */
    public static function transition(
        string $aggregateType,
        int $aggregateId,
        string $axis,
        ?string $from,
        string $to,
        array $opts = []
    ): void {
        States::assert($axis, $from, $to);

        $actorId = $opts['actor_user_id'] ?? Auth::id();

        Db::run(
            'INSERT INTO status_events
                (aggregate_type, aggregate_id, axis, from_state, to_state,
                 actor_user_id, actor_label, reason, correlation_id, occurred_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $aggregateType,
                $aggregateId,
                $axis,
                $from,
                $to,
                $actorId !== null ? (int) $actorId : null,
                $opts['actor_label'] ?? null,
                $opts['reason'] ?? null,
                $opts['correlation_id'] ?? null,
                Clock::nowUtcMs(),
            ]
        );
    }
}
