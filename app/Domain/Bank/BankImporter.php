<?php
declare(strict_types=1);

namespace Tpb\Domain\Bank;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;

/**
 * Bankimport (§5.6). Duplikatschutz doppelt: file_sha256 (ganze Datei) UND dedupe_hash
 * je Zeile = sha256(Konto + Buchungsdatum + Betrag + Verwendungszweck + Zeilennr.).
 * Identische Datei erneut ⇒ 0 neue Zeilen. Es wird NIE automatisch gebucht.
 */
final class BankImporter
{
    /**
     * @return array{import_id:?int,parsed:int,new:int,duplicate_file:bool}
     */
    public static function import(string $accountLabel, string $format, string $content, ?int $actorUserId): array
    {
        $fileSha = hash('sha256', $content);
        $existing = BankRepo::importByFileHash($fileSha);
        if ($existing !== null) {
            return ['import_id' => (int) $existing['id'], 'parsed' => 0, 'new' => 0, 'duplicate_file' => true];
        }

        $lines = $format === 'camt053' ? Camt053Parser::parse($content) : CsvParser::parse($content);

        return Db::tx(function () use ($accountLabel, $format, $fileSha, $lines, $actorUserId): array {
            $now = Clock::nowUtcSeconds();
            Db::run(
                'INSERT INTO bank_imports (account_label, format, file_sha256, line_count, imported_by, imported_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [mb_substr($accountLabel, 0, 64), $format, $fileSha, count($lines), $actorUserId, $now]
            );
            $importId = (int) Db::pdo()->lastInsertId();

            $new = 0;
            foreach ($lines as $l) {
                $dedupe = hash('sha256', implode('|', [
                    $accountLabel, (string) $l['booking_date'], (string) $l['amount_cents'], (string) $l['remittance_info'], (string) $l['line_no'],
                ]));
                $stmt = Db::run(
                    'INSERT IGNORE INTO bank_lines
                        (bank_import_id, line_no, booking_date, value_date, amount_cents, currency, counterparty_name,
                         counterparty_iban, remittance_info, end_to_end_id, dedupe_hash, match_status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $importId, $l['line_no'], $l['booking_date'], $l['value_date'], $l['amount_cents'], $l['currency'],
                        $l['counterparty_name'], $l['counterparty_iban'], $l['remittance_info'], $l['end_to_end_id'],
                        $dedupe, 'open', $now,
                    ]
                );
                $new += $stmt->rowCount();
            }

            Audit::log('bank_import', (string) $importId, 'bank.imported', [
                'actor_user_id' => $actorUserId,
                'metadata'      => ['format' => $format, 'parsed' => count($lines), 'new' => $new],
            ]);

            return ['import_id' => $importId, 'parsed' => count($lines), 'new' => $new, 'duplicate_file' => false];
        });
    }
}
