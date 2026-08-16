<?php
declare(strict_types=1);

namespace Tpb\Domain\Proof;

use Tpb\Core\Db;

/**
 * Lesezugriffe auf Proofs (§5.5, M4). Schreibpfad in ProofService (transaktional,
 * inkl. Statuswechsel der Artwork-Achse und Supersede-Logik).
 */
final class ProofRepo
{
    /** @return array<string,mixed>|null Aktueller (nicht abgelöster) Proof eines Auftrags. */
    public static function activeForOrder(int $orderId): ?array
    {
        $row = Db::run(
            "SELECT * FROM proofs WHERE order_id = ? AND status <> 'superseded' ORDER BY version_no DESC LIMIT 1",
            [$orderId]
        )->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $row = Db::run('SELECT * FROM proofs WHERE id = ? LIMIT 1', [$id])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForOrder(int $orderId): array
    {
        return Db::run(
            'SELECT p.*, a.public_id AS pdf_public_id
             FROM proofs p LEFT JOIN assets a ON a.id = p.pdf_asset_id
             WHERE p.order_id = ? ORDER BY p.version_no DESC',
            [$orderId]
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public static function approvals(int $proofId): array
    {
        return Db::run(
            'SELECT decision, comment, actor_label, decided_at FROM proof_approvals WHERE proof_id = ? ORDER BY id DESC',
            [$proofId]
        )->fetchAll();
    }
}
