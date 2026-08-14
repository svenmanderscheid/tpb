<?php
declare(strict_types=1);

namespace Tpb\Domain\Finance;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;

/**
 * Gesellschafter/Gründer mit Gewinnanteil (Basispunkte). Teil des Finanzmoduls
 * (Abweichung, siehe docs/DECISIONS.md #14-17).
 */
final class PartnerRepo
{
    public static function create(string $name, int $shareBps, ?int $userId): string
    {
        $publicId = Ulid::generate();
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO finance_partners (public_id, name, profit_share_bps, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$publicId, $name, $shareBps, 'active', $userId, $now, $now]
        );
        return $publicId;
    }

    public static function update(string $publicId, string $name, int $shareBps, string $status): void
    {
        Db::run(
            'UPDATE finance_partners SET name = ?, profit_share_bps = ?, status = ?, updated_at = ? WHERE public_id = ?',
            [$name, $shareBps, $status, Clock::nowUtcSeconds(), $publicId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::run(
            'SELECT id, public_id, name, profit_share_bps, status FROM finance_partners ORDER BY name ASC'
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public static function allActive(): array
    {
        return Db::run(
            "SELECT id, public_id, name, profit_share_bps, status
             FROM finance_partners WHERE status = 'active' ORDER BY name ASC"
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run(
            'SELECT id, public_id, name, profit_share_bps, status FROM finance_partners WHERE public_id = ? LIMIT 1',
            [$publicId]
        )->fetch();
        return $row ?: null;
    }

    public static function totalShareBps(): int
    {
        return (int) Db::run(
            "SELECT COALESCE(SUM(profit_share_bps), 0) FROM finance_partners WHERE status = 'active'"
        )->fetchColumn();
    }
}
