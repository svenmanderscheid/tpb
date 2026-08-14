<?php
declare(strict_types=1);

namespace Tpb\Domain\Finance;

/**
 * Parst Nutzereingaben in int Cents bzw. Basispunkte – ohne Floats (§3.1).
 * Akzeptiert deutsche und englische Schreibweise (1.234,56 / 1,234.56 / 1234.5).
 */
final class Amount
{
    /** Positiver Geldbetrag -> Cents. Wirft bei ungültig/<= 0. */
    public static function toCents(string $raw): int
    {
        $cents = self::parseHundredths($raw);
        if ($cents === null || $cents <= 0) {
            throw new \InvalidArgumentException('Bitte einen gültigen Betrag größer als 0 eingeben.');
        }
        return $cents;
    }

    /** Prozentangabe -> Basispunkte (50 -> 5000, 33,33 -> 3333). 0..100 %. */
    public static function percentToBps(string $raw): int
    {
        $bps = self::parseHundredths($raw);
        if ($bps === null || $bps < 0 || $bps > 10000) {
            throw new \InvalidArgumentException('Bitte einen Anteil zwischen 0 und 100 % eingeben.');
        }
        return $bps;
    }

    /**
     * Zerlegt eine Dezimalzahl in Hundertstel (2 Nachkommastellen) als int.
     * Rechnet rein ganzzahlig, keine Floats.
     */
    private static function parseHundredths(string $raw): ?int
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        $s = str_replace([' ', "\xC2\xA0", '€', 'EUR', '%'], '', $s);

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        $decPos = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);

        if ($decPos >= 0) {
            $intPart = substr($s, 0, $decPos);
            $fracPart = substr($s, $decPos + 1);
        } else {
            $intPart = $s;
            $fracPart = '';
        }

        $negative = str_starts_with(ltrim($intPart), '-');
        $intDigits = preg_replace('/\D/', '', $intPart) ?? '';
        $fracDigits = preg_replace('/\D/', '', $fracPart) ?? '';

        if ($intDigits === '' && $fracDigits === '') {
            return null;
        }
        // Genau 2 Nachkommastellen (überzählige abschneiden).
        $fracDigits = substr(str_pad($fracDigits, 2, '0'), 0, 2);

        $value = (int) ($intDigits === '' ? '0' : $intDigits) * 100
            + (int) ($fracDigits === '' ? '0' : $fracDigits);

        return $negative ? -$value : $value;
    }
}
