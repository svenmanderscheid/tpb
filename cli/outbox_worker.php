<?php
declare(strict_types=1);

/**
 * Outbox-Worker (§10). Lock via GET_LOCK, claimt fällige Events (LIMIT 20),
 * Backoff 1/5/15/60 min, nach 5 Versuchen 'failed' + Audit-Alarm.
 * Lokal per geplantem Task jede Minute aufrufen.
 *
 * Aufruf: php cli/outbox_worker.php [--once]
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Outbox\OutboxHandlers;

const BATCH = 20;
const MAX_ATTEMPTS = 5;
/** Backoff-Minuten nach Fehlversuch (Versuch 1..4). */
const BACKOFF_MIN = [1 => 1, 2 => 5, 3 => 15, 4 => 60];

$pdo = Db::pdo();
$workerId = gethostname() . '-' . getmypid();

// Exklusiver Lock; kein zweiter Worker parallel.
$locked = (int) $pdo->query("SELECT GET_LOCK('tpb_outbox', 0)")->fetchColumn();
if ($locked !== 1) {
    fwrite(STDERR, "Ein anderer Worker hält den Lock – Abbruch.\n");
    exit(0);
}

$processed = 0;
$failed = 0;

try {
    while (true) {
        $due = claimDue($workerId);
        if ($due === []) {
            break;
        }
        foreach ($due as $event) {
            [$ok, $isFailed] = handleEvent($event);
            $processed += $ok ? 1 : 0;
            $failed += $isFailed ? 1 : 0;
        }
    }
} finally {
    $pdo->query("SELECT RELEASE_LOCK('tpb_outbox')");
}

echo "Outbox-Worker fertig: {$processed} verarbeitet, {$failed} endgültig fehlgeschlagen.\n";
exit(0);

// ---------------------------------------------------------------------------

/**
 * @return array<int,array<string,mixed>>
 */
function claimDue(string $workerId): array
{
    $now = Clock::nowUtcSeconds();
    return Db::run(
        "SELECT id, event_type, payload_json, attempts
         FROM outbox_events
         WHERE status = 'queued' AND next_attempt_at <= ?
         ORDER BY id ASC
         LIMIT " . BATCH,
        [$now]
    )->fetchAll();
}

/**
 * @param array<string,mixed> $event
 * @return array{0:bool,1:bool}  [erfolgreich, endgültig-fehlgeschlagen]
 */
function handleEvent(array $event): array
{
    $id = (int) $event['id'];
    $now = Clock::nowUtcSeconds();

    Db::run(
        "UPDATE outbox_events SET locked_at = ?, locked_by = ? WHERE id = ?",
        [$now, gethostname() . '-' . getmypid(), $id]
    );

    try {
        $payload = json_decode((string) $event['payload_json'], true, 512, JSON_THROW_ON_ERROR);
        OutboxHandlers::dispatch((string) $event['event_type'], is_array($payload) ? $payload : []);

        Db::run(
            "UPDATE outbox_events SET status = 'done', processed_at = ?, locked_at = NULL, locked_by = NULL, last_error = NULL WHERE id = ?",
            [Clock::nowUtcSeconds(), $id]
        );
        return [true, false];
    } catch (\Throwable $e) {
        return [false, markFailure($event, $e)];
    }
}

/**
 * @param array<string,mixed> $event
 * @return bool  true, wenn das Event endgültig auf 'failed' gesetzt wurde
 */
function markFailure(array $event, \Throwable $e): bool
{
    $id = (int) $event['id'];
    $attempts = (int) $event['attempts'] + 1;
    $error = substr($e->getMessage(), 0, 1000);

    if ($attempts >= MAX_ATTEMPTS) {
        Db::run(
            "UPDATE outbox_events SET status = 'failed', attempts = ?, last_error = ?, locked_at = NULL, locked_by = NULL WHERE id = ?",
            [$attempts, $error, $id]
        );
        Audit::log('outbox', (string) $id, 'outbox.failed', [
            'actor_label' => 'worker',
            'reason_code' => 'max_attempts',
            'metadata'    => ['event_type' => $event['event_type'], 'attempts' => $attempts],
        ]);
        return true;
    }

    $delayMin = BACKOFF_MIN[$attempts] ?? 60;
    $next = Clock::nowUtc()->add(new \DateInterval('PT' . $delayMin . 'M'))->format('Y-m-d H:i:s');
    Db::run(
        "UPDATE outbox_events SET attempts = ?, next_attempt_at = ?, last_error = ?, locked_at = NULL, locked_by = NULL WHERE id = ?",
        [$attempts, $next, $error, $id]
    );
    return false;
}
