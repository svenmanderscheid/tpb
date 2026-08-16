<?php
declare(strict_types=1);

namespace Tpb\Domain\Sequence;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Fortlaufende, lückenfreie Geschäftsnummern (§7, §9.3). Referenzimplementierung:
 * INSERT IGNORE + SELECT ... FOR UPDATE + UPDATE in derselben Transaktion.
 *
 * WICHTIG: next() MUSS innerhalb einer aktiven Db::tx() aufgerufen werden – die
 * Nummernvergabe ist Teil derselben Mutation (Order, Angebot, Rechnung), damit
 * bei Rollback keine Nummer verbrannt wird und FOR UPDATE korrekt sperrt.
 * Fortlaufende Nummern sind nie Zugriffsschutz (§11) – öffentliche URLs nutzen public_id/Token.
 */
final class NumberSequence
{
    /** Formate §9.3: ORD-2026-000123, JOB-2026-000456, Q-2026-000123, ZA-2026-000001. */
    public static function next(string $type, string $prefix, int $pad = 6): string
    {
        [$year, $n] = self::allocate($type);
        return sprintf('%s-%d-%0' . $pad . 'd', $prefix, $year, $n);
    }

    /** Rechnungsnummer §9.3: `2026-000001` (eigene Sequenz `invoice`, ohne Präfix). */
    public static function nextInvoice(int $pad = 6): string
    {
        [$year, $n] = self::allocate('invoice');
        return sprintf('%d-%0' . $pad . 'd', $year, $n);
    }

    /**
     * Zieht den nächsten Zählwert lückenfrei (INSERT IGNORE + SELECT … FOR UPDATE + UPDATE).
     * MUSS in einer aktiven Db::tx() laufen (Nummernvergabe ist Teil derselben Mutation).
     * @return array{0:int,1:int} [fiscal_year, next_value]
     */
    private static function allocate(string $type): array
    {
        $year = (int) Clock::nowUtc()->format('Y');
        $pdo = Db::pdo();

        $pdo->prepare(
            'INSERT IGNORE INTO number_sequences (seq_type, fiscal_year, next_value, updated_at)
             VALUES (?, ?, 1, UTC_TIMESTAMP())'
        )->execute([$type, $year]);

        $stmt = $pdo->prepare(
            'SELECT next_value FROM number_sequences
             WHERE seq_type = ? AND fiscal_year = ? FOR UPDATE'
        );
        $stmt->execute([$type, $year]);
        $n = (int) $stmt->fetchColumn();

        $pdo->prepare(
            'UPDATE number_sequences SET next_value = next_value + 1, updated_at = UTC_TIMESTAMP()
             WHERE seq_type = ? AND fiscal_year = ?'
        )->execute([$type, $year]);

        return [$year, $n];
    }
}
