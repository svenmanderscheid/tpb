<?php
declare(strict_types=1);

namespace Tpb\Domain\Finance;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;

/**
 * Kapitaleinlagen der Gründer (§DECISIONS #17). amount_cents als int Cents.
 */
final class ContributionRepo
{
    public static function create(int $partnerId, string $date, string $kind, int $amountCents, ?string $note, ?int $userId): string
    {
        $publicId = Ulid::generate();
        Db::run(
            'INSERT INTO capital_contributions (public_id, partner_id, contributed_on, kind, amount_cents, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$publicId, $partnerId, $date, $kind, $amountCents, $note, $userId, Clock::nowUtcSeconds()]
        );
        return $publicId;
    }

    public static function delete(string $publicId): void
    {
        Db::run('DELETE FROM capital_contributions WHERE public_id = ?', [$publicId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function listRecent(int $limit = 100): array
    {
        return Db::run(
            'SELECT c.public_id, c.contributed_on, c.kind, c.amount_cents, c.note, p.name AS partner_name
             FROM capital_contributions c
             JOIN finance_partners p ON p.id = c.partner_id
             ORDER BY c.contributed_on DESC, c.id DESC
             LIMIT ' . (int) $limit
        )->fetchAll();
    }

    /** @return array<int,int> partner_id => Summe Cents */
    public static function totalsByPartner(): array
    {
        $rows = Db::run(
            'SELECT partner_id, COALESCE(SUM(amount_cents), 0) AS total FROM capital_contributions GROUP BY partner_id'
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['partner_id']] = (int) $r['total'];
        }
        return $out;
    }

    public static function grandTotal(): int
    {
        return (int) Db::run('SELECT COALESCE(SUM(amount_cents), 0) FROM capital_contributions')->fetchColumn();
    }
}
