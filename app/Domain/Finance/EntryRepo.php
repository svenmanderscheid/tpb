<?php
declare(strict_types=1);

namespace Tpb\Domain\Finance;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;

/**
 * Einnahmen-/Ausgaben-Buchungen (Ledger). direction = income|expense,
 * amount_cents immer positiv, Vorzeichen ergibt sich aus direction.
 */
final class EntryRepo
{
    public const DIRECTIONS = ['income', 'expense'];

    public static function create(
        string $date,
        string $direction,
        string $category,
        int $amountCents,
        ?string $description,
        ?int $partnerId,
        ?int $userId
    ): string {
        $publicId = Ulid::generate();
        Db::run(
            'INSERT INTO finance_entries (public_id, entry_date, direction, category, description, amount_cents, partner_id, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$publicId, $date, $direction, $category, $description, $amountCents, $partnerId, $userId, Clock::nowUtcSeconds()]
        );
        return $publicId;
    }

    public static function delete(string $publicId): void
    {
        Db::run('DELETE FROM finance_entries WHERE public_id = ?', [$publicId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function listByYear(int $year, int $limit = 200): array
    {
        return Db::run(
            'SELECT e.public_id, e.entry_date, e.direction, e.category, e.description, e.amount_cents, p.name AS partner_name
             FROM finance_entries e
             LEFT JOIN finance_partners p ON p.id = e.partner_id
             WHERE YEAR(e.entry_date) = ?
             ORDER BY e.entry_date DESC, e.id DESC
             LIMIT ' . (int) $limit,
            [$year]
        )->fetchAll();
    }

    /** @return array{income:int,expense:int} */
    public static function sumByDirectionYear(int $year): array
    {
        $rows = Db::run(
            'SELECT direction, COALESCE(SUM(amount_cents), 0) AS total
             FROM finance_entries WHERE YEAR(entry_date) = ? GROUP BY direction',
            [$year]
        )->fetchAll();
        $out = ['income' => 0, 'expense' => 0];
        foreach ($rows as $r) {
            $out[(string) $r['direction']] = (int) $r['total'];
        }
        return $out;
    }

    /** @return array<int,array{month:int,income:int,expense:int}> 12 Monate */
    public static function monthlyByYear(int $year): array
    {
        $rows = Db::run(
            'SELECT MONTH(entry_date) AS m, direction, COALESCE(SUM(amount_cents), 0) AS total
             FROM finance_entries WHERE YEAR(entry_date) = ?
             GROUP BY MONTH(entry_date), direction',
            [$year]
        )->fetchAll();

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = ['month' => $m, 'income' => 0, 'expense' => 0];
        }
        foreach ($rows as $r) {
            $m = (int) $r['m'];
            $months[$m][(string) $r['direction']] = (int) $r['total'];
        }
        return array_values($months);
    }

    /** @return array<int,array{category:string,total:int}> */
    public static function byCategoryYear(int $year, string $direction): array
    {
        return Db::run(
            'SELECT category, COALESCE(SUM(amount_cents), 0) AS total
             FROM finance_entries WHERE YEAR(entry_date) = ? AND direction = ?
             GROUP BY category ORDER BY total DESC',
            [$year, $direction]
        )->fetchAll();
    }

    /** @return int[] absteigend sortierte Jahre mit Buchungen */
    public static function availableYears(): array
    {
        $rows = Db::run(
            'SELECT DISTINCT YEAR(entry_date) AS y FROM finance_entries ORDER BY y DESC'
        )->fetchAll();
        return array_map(static fn ($r) => (int) $r['y'], $rows);
    }
}
