<?php
declare(strict_types=1);

namespace Tpb\Domain\Proof;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Artwork-Versionen je Auftrag (§5.5, M4). Die finale/gereinigte Druckdatei wird
 * pro Auftrag versioniert; jede Version kann Grundlage eines Proofs sein.
 * Innerhalb der Mutations-Transaktion aufrufen.
 */
final class ArtworkRepo
{
    /** @return array{id:int,version_no:int} Nächste Version fortlaufend je Auftrag. */
    public static function addVersion(int $orderId, int $assetId, string $kind, ?int $actorUserId): array
    {
        $next = (int) Db::run('SELECT COALESCE(MAX(version_no), 0) + 1 FROM artwork_versions WHERE order_id = ?', [$orderId])->fetchColumn();
        Db::run(
            'INSERT INTO artwork_versions (order_id, asset_id, version_no, kind, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $assetId, $next, $kind === 'cleaned' ? 'cleaned' : 'original', 'uploaded', $actorUserId, Clock::nowUtcSeconds()]
        );
        return ['id' => (int) Db::pdo()->lastInsertId(), 'version_no' => $next];
    }

    /** @return array<string,mixed>|null Jüngste Artwork-Version eines Auftrags. */
    public static function latest(int $orderId): ?array
    {
        $row = Db::run(
            'SELECT * FROM artwork_versions WHERE order_id = ? ORDER BY version_no DESC LIMIT 1',
            [$orderId]
        )->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForOrder(int $orderId): array
    {
        return Db::run(
            'SELECT av.*, a.public_id AS asset_public_id, a.original_name
             FROM artwork_versions av JOIN assets a ON a.id = av.asset_id
             WHERE av.order_id = ? ORDER BY av.version_no DESC',
            [$orderId]
        )->fetchAll();
    }
}
