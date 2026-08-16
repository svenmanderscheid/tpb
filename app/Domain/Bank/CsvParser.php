<?php
declare(strict_types=1);

namespace Tpb\Domain\Bank;

/**
 * CSV-Kontoauszug (§5.6). Erwartete Spalten (mit Kopfzeile, Trenner ';'):
 * booking_date; value_date; amount; currency; counterparty_name; counterparty_iban;
 * remittance_info; end_to_end_id. Betrag signiert (Eingang +, Ausgang −), EU- oder
 * US-Dezimalschreibweise. Das konkrete Hausbank-Mapping ist offen (docs/OFFENE-FRAGEN);
 * bis dahin dieses dokumentierte Standardformat.
 *
 * @return array<int,array<string,mixed>>
 */
final class CsvParser
{
    /** @return array<int,array<string,mixed>> */
    public static function parse(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // BOM entfernen
        $rows = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $out = [];
        $lineNo = 0;
        foreach ($rows as $i => $row) {
            if ($i === 0 || trim($row) === '') {
                continue; // Kopfzeile / Leerzeilen
            }
            $cols = str_getcsv($row, ';');
            if (count($cols) < 4) {
                continue;
            }
            $lineNo++;
            $out[] = [
                'line_no'           => $lineNo,
                'booking_date'      => self::date($cols[0] ?? ''),
                'value_date'        => self::date($cols[1] ?? '') ?: null,
                'amount_cents'      => self::amountCents($cols[2] ?? '0'),
                'currency'          => strtoupper(trim($cols[3] ?? 'EUR')) ?: 'EUR',
                'counterparty_name' => self::str($cols[4] ?? '', 160),
                'counterparty_iban' => self::str($cols[5] ?? '', 40),
                'remittance_info'   => self::str($cols[6] ?? '', 500),
                'end_to_end_id'     => self::str($cols[7] ?? '', 80),
            ];
        }
        return $out;
    }

    private static function amountCents(string $raw): int
    {
        $t = trim($raw);
        $neg = str_contains($t, '-');
        $t = preg_replace('/[^0-9.,]/', '', $t) ?? '';
        if (str_contains($t, '.') && str_contains($t, ',')) {
            // 1.234,56 → 1234.56 (EU) bzw. 1,234.56 → 1234.56 (US): letztes Trennzeichen = Dezimalpunkt.
            $t = strrpos($t, ',') > strrpos($t, '.')
                ? str_replace(['.', ','], ['', '.'], $t)
                : str_replace(',', '', $t);
        } elseif (str_contains($t, ',')) {
            $t = str_replace(',', '.', $t);
        }
        $cents = (int) round((float) $t * 100);
        return $neg ? -abs($cents) : $cents;
    }

    private static function date(string $raw): ?string
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        // ISO (YYYY-MM-DD) oder DD.MM.YYYY.
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t)) {
            return $t;
        }
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $t, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }

    private static function str(string $raw, int $max): ?string
    {
        $t = trim($raw);
        return $t === '' ? null : mb_substr($t, 0, $max);
    }
}
