<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Geld ausschließlich als int Cents; Prozente als Basispunkte (§3.1/§3.2).
 * Keine Floats im Geldpfad.
 */
final class Money
{
    /**
     * Basispunkt-Anteil von $cents: cents * bps / 10000, Round-half-up.
     * Vorzeichensicher.
     */
    public static function bp(int $cents, int $bps): int
    {
        $negative = ($cents < 0) !== ($bps < 0);
        $numerator = abs($cents) * abs($bps);
        $denominator = 10000;
        $result = intdiv($numerator + intdiv($denominator, 2), $denominator);
        return $negative ? -$result : $result;
    }

    /**
     * ceil_div(a, b) für positive Ganzzahlen (Untergrenzen-Rechnung §6).
     */
    public static function ceilDiv(int $a, int $b): int
    {
        if ($b === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        return intdiv($a + $b - 1, $b);
    }

    /**
     * Anzeigeformatierung – NUR für Views. z. B. 12345 -> "123,45".
     */
    public static function format(int $cents, string $currency = 'EUR'): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $euros = intdiv($abs, 100);
        $rest = $abs % 100;
        $formatted = number_format($euros, 0, ',', '.');
        return sprintf('%s%s,%02d %s', $sign, $formatted, $rest, $currency);
    }
}
