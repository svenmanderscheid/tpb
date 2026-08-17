<?php
declare(strict_types=1);

namespace Tpb\Domain\Production;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Status\Status;

/**
 * Produktionssteuerung (§7, §7.3, M5). Freigabe-Gate serverseitig; Statusfluss über
 * die Produktions-Achse; Mengen-/Ausschusserfassung idempotent über idem_key.
 */
final class ProductionService
{
    /** Aktion → [erwarteter Von-Zustand, Ziel-Zustand, event_type|null]. */
    private const ACTIONS = [
        'start'  => ['READY', 'IN_PROGRESS', 'start'],
        'qc'     => ['IN_PROGRESS', 'QUALITY_CHECK', 'stop'],
        'done'   => ['QUALITY_CHECK', 'DONE', 'qc_pass'],
        'rework' => ['QUALITY_CHECK', 'REWORK', 'qc_fail'],
        'resume' => ['REWORK', 'IN_PROGRESS', 'start'],
        'scrap'  => ['IN_PROGRESS', 'SCRAPPED', 'scrap'],
    ];

    /** Setzt den Fälligkeitstermin eines Jobs (Kapazitätsplanung, M9). */
    public static function setDueDate(string $jobPublicId, ?string $dueDate, ?int $actorUserId): void
    {
        $due = $dueDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) ? $dueDate : null;
        $n = Db::run('UPDATE production_jobs SET due_date = ?, updated_at = ? WHERE public_id = ?', [$due, Clock::nowUtcSeconds(), $jobPublicId])->rowCount();
        if ($n > 0) {
            Audit::log('production_job', $jobPublicId, 'production.due_date_set', ['actor_user_id' => $actorUserId, 'metadata' => ['due_date' => $due]]);
        }
    }

    /** Legt Jobs für einen Auftrag an (Order muss aktiv/CONFIRMED sein). */
    public static function createJobs(int $orderId, ?int $actorUserId): array
    {
        return Db::tx(function () use ($orderId, $actorUserId): array {
            $order = Db::run('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId])->fetch();
            if ($order === false) {
                throw new \InvalidArgumentException('Auftrag nicht gefunden.');
            }
            if ($order['cancelled_at'] !== null) {
                throw new ProductionGateException('Auftrag ist storniert.');
            }
            $jobs = JobRepo::createForOrder($orderId, $actorUserId);
            if ($jobs !== []) {
                Audit::log('order', (string) $order['public_id'], 'production.jobs_created', [
                    'actor_user_id' => $actorUserId, 'metadata' => ['count' => count($jobs)],
                ]);
            }
            return $jobs;
        });
    }

    /**
     * Freigabe-Gate (§7.3): BLOCKED → READY nur wenn Order aktiv, Zahlung nicht offen
     * und – für konfigurierte Positionen – Artwork LOCKED. Standardpositionen gelten
     * mit dem Hausdesign als vorab freigegeben (v1.4).
     */
    public static function release(string $jobPublicId, ?int $actorUserId): void
    {
        Db::tx(function () use ($jobPublicId, $actorUserId): void {
            $job = Db::run('SELECT * FROM production_jobs WHERE public_id = ? FOR UPDATE', [$jobPublicId])->fetch();
            if ($job === false) {
                throw new \InvalidArgumentException('Job nicht gefunden.');
            }
            if ((string) $job['status'] !== 'BLOCKED') {
                throw new ProductionStateException('Der Job ist nicht im Zustand BLOCKED.');
            }
            $order = Db::run('SELECT * FROM orders WHERE id = ? LIMIT 1', [(int) $job['order_id']])->fetch();
            $item = Db::run('SELECT config_snapshot_json FROM order_items WHERE id = ? LIMIT 1', [(int) $job['order_item_id']])->fetch();

            [$ok, $reason] = self::gate($order, $item);
            if (!$ok) {
                throw new ProductionGateException($reason);
            }

            self::applyTransition((int) $job['id'], (int) $job['order_id'], 'BLOCKED', 'READY', null, $actorUserId);
            Audit::log('production_job', (string) $job['public_id'], 'production.released', ['actor_user_id' => $actorUserId, 'to_state' => 'READY']);
        });
    }

    /** Führt eine Statusaktion (§7-Achse) aus. */
    public static function advance(string $jobPublicId, string $action, ?int $actorUserId): void
    {
        if (!isset(self::ACTIONS[$action])) {
            throw new ProductionStateException('Unbekannte Aktion.');
        }
        [$from, $to, $eventType] = self::ACTIONS[$action];

        Db::tx(function () use ($jobPublicId, $from, $to, $eventType, $actorUserId): void {
            $job = Db::run('SELECT * FROM production_jobs WHERE public_id = ? FOR UPDATE', [$jobPublicId])->fetch();
            if ($job === false) {
                throw new \InvalidArgumentException('Job nicht gefunden.');
            }
            if ((string) $job['status'] !== $from) {
                throw new ProductionStateException('Aktion im aktuellen Status nicht erlaubt (Status: ' . (string) $job['status'] . ').');
            }
            self::applyTransition((int) $job['id'], (int) $job['order_id'], $from, $to, $eventType, $actorUserId);
            Audit::log('production_job', (string) $job['public_id'], 'production.' . $to, ['actor_user_id' => $actorUserId, 'from_state' => $from, 'to_state' => $to]);
        });
    }

    /**
     * Erfasst eine Gut-/Ausschussmenge als production_event. Idempotent über idem_key:
     * ein zweiter POST mit demselben Key bucht nicht erneut (DoD M5).
     */
    public static function recordQuantity(string $jobPublicId, string $type, int $qty, ?string $idemKey, ?int $actorUserId): void
    {
        if (!in_array($type, ['qty_good', 'scrap', 'rework'], true)) {
            throw new ProductionStateException('Ungültiger Mengentyp.');
        }
        if ($qty <= 0) {
            throw new ProductionStateException('Menge muss größer als 0 sein.');
        }
        Db::tx(function () use ($jobPublicId, $type, $qty, $idemKey, $actorUserId): void {
            $job = Db::run('SELECT id, public_id FROM production_jobs WHERE public_id = ? FOR UPDATE', [$jobPublicId])->fetch();
            if ($job === false) {
                throw new \InvalidArgumentException('Job nicht gefunden.');
            }
            if ($idemKey !== null && $idemKey !== '') {
                $dup = Db::run('SELECT 1 FROM production_events WHERE idem_key = ? LIMIT 1', [$idemKey])->fetchColumn();
                if ($dup !== false) {
                    return; // idempotent: bereits gebucht
                }
            }
            Db::run(
                'INSERT INTO production_events (job_id, event_type, actor_user_id, qty, idem_key, occurred_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [(int) $job['id'], $type, $actorUserId, $qty, $idemKey !== '' ? $idemKey : null, Clock::nowUtcSeconds()]
            );
            Audit::log('production_job', (string) $job['public_id'], 'production.qty.' . $type, ['actor_user_id' => $actorUserId, 'metadata' => ['qty' => $qty, 'idem_key' => $idemKey]]);
        });
    }

    /**
     * @param array<string,mixed> $order
     * @param array<string,mixed>|false $item
     * @return array{0:bool,1:string}
     */
    private static function gate(array $order, array|false $item): array
    {
        if ($order['cancelled_at'] !== null || $order['completed_at'] !== null) {
            return [false, 'Auftrag ist nicht aktiv (storniert oder abgeschlossen).'];
        }
        if ((int) $order['deposit_required_cents'] > 0 && (string) $order['cur_payment'] !== 'PAID') {
            return [false, 'Anzahlung/Zahlung steht noch aus.'];
        }
        $isConfigured = $item !== false && $item['config_snapshot_json'] !== null;
        if ($isConfigured && (string) $order['cur_artwork'] !== 'LOCKED') {
            return [false, 'Artwork ist noch nicht freigegeben (LOCKED erforderlich).'];
        }
        return [true, ''];
    }

    private static function applyTransition(int $jobId, int $orderId, string $from, string $to, ?string $eventType, ?int $actorUserId): void
    {
        $now = Clock::nowUtcSeconds();
        Status::transition('production_job', $jobId, 'production', $from, $to, ['actor_user_id' => $actorUserId]);
        Db::run('UPDATE production_jobs SET status = ?, updated_at = ? WHERE id = ?', [$to, $now, $jobId]);
        if ($eventType !== null) {
            Db::run(
                'INSERT INTO production_events (job_id, event_type, actor_user_id, occurred_at) VALUES (?, ?, ?, ?)',
                [$jobId, $eventType, $actorUserId, $now]
            );
        }
        self::refreshOrderProductionCache($orderId, $now);
    }

    /** Rollup der Produktionsachse auf Auftragsebene (Cache orders.cur_production). */
    private static function refreshOrderProductionCache(int $orderId, string $now): void
    {
        $statuses = Db::run('SELECT status FROM production_jobs WHERE order_id = ?', [$orderId])->fetchAll(\PDO::FETCH_COLUMN);
        if ($statuses === []) {
            return;
        }
        $active = array_filter($statuses, static fn ($s) => $s !== 'SCRAPPED');
        if ($active === []) {
            $roll = 'SCRAPPED';
        } elseif (count(array_filter($active, static fn ($s) => $s === 'DONE')) === count($active)) {
            $roll = 'DONE';
        } elseif (in_array('IN_PROGRESS', $active, true) || in_array('QUALITY_CHECK', $active, true) || in_array('REWORK', $active, true)) {
            $roll = 'IN_PROGRESS';
        } elseif (in_array('READY', $active, true)) {
            $roll = 'READY';
        } else {
            $roll = 'BLOCKED';
        }
        Db::run('UPDATE orders SET cur_production = ?, updated_at = ? WHERE id = ?', [$roll, $now, $orderId]);
    }
}
