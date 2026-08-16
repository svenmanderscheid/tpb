<?php
declare(strict_types=1);

namespace Tpb\Domain\Production;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;
use Tpb\Domain\Sequence\NumberSequence;
use Tpb\Domain\Status\Status;

/**
 * Produktionsjobs je Auftragsposition (§5.5, M5). Ein Job pro order_item; Standard-
 * und konfigurierte Positionen erhalten je einen Job. Innerhalb einer Transaktion
 * aufrufen (Nummernvergabe + status_events).
 */
final class JobRepo
{
    /**
     * Legt Jobs für alle Positionen eines Auftrags an (idempotent: existieren bereits
     * Jobs, passiert nichts). Startzustand BLOCKED.
     *
     * @return array<int,array{id:int,public_id:string,job_number:string}>
     */
    public static function createForOrder(int $orderId, ?int $actorUserId): array
    {
        $exists = (int) Db::run('SELECT COUNT(*) FROM production_jobs WHERE order_id = ?', [$orderId])->fetchColumn();
        if ($exists > 0) {
            return [];
        }

        $created = [];
        $now = Clock::nowUtcSeconds();
        $items = Db::run('SELECT id, config_snapshot_json FROM order_items WHERE order_id = ? ORDER BY pos_no ASC', [$orderId])->fetchAll();
        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            $route = self::routeFor($item['config_snapshot_json']);
            $publicId = Ulid::generate();
            $jobNumber = NumberSequence::next('job', 'JOB');
            Db::run(
                'INSERT INTO production_jobs (public_id, job_number, order_id, order_item_id, status, route, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$publicId, $jobNumber, $orderId, $itemId, 'BLOCKED', $route, $now, $now]
            );
            $jobId = (int) Db::pdo()->lastInsertId();
            Status::transition('production_job', $jobId, 'production', null, 'BLOCKED', ['actor_user_id' => $actorUserId]);
            $created[] = ['id' => $jobId, 'public_id' => $publicId, 'job_number' => $jobNumber];
        }
        return $created;
    }

    /** @return array<string,mixed>|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run('SELECT * FROM production_jobs WHERE public_id = ? LIMIT 1', [$publicId])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $row = Db::run('SELECT * FROM production_jobs WHERE id = ? LIMIT 1', [$id])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForOrder(int $orderId): array
    {
        return Db::run(
            'SELECT j.*, oi.description, oi.qty, oi.sku
             FROM production_jobs j JOIN order_items oi ON oi.id = j.order_item_id
             WHERE j.order_id = ? ORDER BY j.id ASC',
            [$orderId]
        )->fetchAll();
    }

    /**
     * Produktionswarteschlange fürs Backoffice (nicht-terminale zuerst).
     * @return array<int,array<string,mixed>>
     */
    public static function queue(): array
    {
        return Db::run(
            "SELECT j.*, oi.description, oi.qty, o.order_number, o.public_id AS order_public_id
             FROM production_jobs j
             JOIN order_items oi ON oi.id = j.order_item_id
             JOIN orders o ON o.id = j.order_id
             ORDER BY FIELD(j.status,'BLOCKED','READY','IN_PROGRESS','QUALITY_CHECK','REWORK','DONE','SCRAPPED'), j.id DESC
             LIMIT 300"
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public static function events(int $jobId): array
    {
        return Db::run(
            'SELECT event_type, step, qty, minutes, note, occurred_at FROM production_events WHERE job_id = ? ORDER BY id DESC',
            [$jobId]
        )->fetchAll();
    }

    private static function routeFor(mixed $configSnapshotJson): string
    {
        if ($configSnapshotJson === null) {
            return 'standard';
        }
        $cfg = json_decode((string) $configSnapshotJson, true);
        return is_array($cfg) && !empty($cfg['technique_code']) ? (string) $cfg['technique_code'] : 'print';
    }
}
