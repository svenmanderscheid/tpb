<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * ULID-Generator (Crockford-Base32, monoton innerhalb einer Millisekunde, §4).
 * 48-Bit Zeit (10 Zeichen) + 80-Bit Zufall (16 Zeichen) = 26 Zeichen.
 */
final class Ulid
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private static int $lastMs = -1;
    /** @var int[] 16 Werte je 0..31 */
    private static array $lastRand = [];

    public static function generate(?int $timeMs = null): string
    {
        $ms = $timeMs ?? (int) floor(microtime(true) * 1000);

        if ($ms === self::$lastMs && self::$lastRand !== []) {
            self::incrementRand();
        } else {
            self::$lastMs = $ms;
            self::$lastRand = self::randomComponent();
        }

        return self::encodeTime($ms) . self::encodeRand(self::$lastRand);
    }

    public static function isValid(string $ulid): bool
    {
        if (strlen($ulid) !== 26) {
            return false;
        }
        return strspn(strtoupper($ulid), self::ENCODING) === 26;
    }

    private static function encodeTime(int $ms): string
    {
        $out = '';
        for ($shift = 45; $shift >= 0; $shift -= 5) {
            $out .= self::ENCODING[($ms >> $shift) & 31];
        }
        return $out;
    }

    /** @param int[] $rand */
    private static function encodeRand(array $rand): string
    {
        $out = '';
        foreach ($rand as $v) {
            $out .= self::ENCODING[$v & 31];
        }
        return $out;
    }

    /** @return int[] */
    private static function randomComponent(): array
    {
        $rand = [];
        for ($i = 0; $i < 16; $i++) {
            $rand[$i] = random_int(0, 31);
        }
        return $rand;
    }

    private static function incrementRand(): void
    {
        for ($i = 15; $i >= 0; $i--) {
            if (self::$lastRand[$i] < 31) {
                self::$lastRand[$i]++;
                return;
            }
            self::$lastRand[$i] = 0;
        }
        // Überlauf (praktisch unmöglich): neue Zufallskomponente.
        self::$lastRand = self::randomComponent();
    }
}
