<?php
declare(strict_types=1);

namespace Tpb\Domain\Auth;

/**
 * TOTP (RFC 6238) als Eigenimplementierung (§4): 30-Sekunden-Schritt, 6 Stellen, SHA-1
 * (Standard der Authenticator-Apps). Secret als Base32 (RFC 4648). Kein externes Paket.
 */
final class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Neues Secret (160 Bit) als Base32. */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32encode(random_bytes($bytes));
    }

    /** Aktueller 6-stelliger Code (führende Nullen bleiben erhalten). */
    public static function code(string $secretBase32, ?int $atTime = null): string
    {
        $atTime ??= time();
        return self::hotp(self::base32decode($secretBase32), intdiv($atTime, self::PERIOD));
    }

    /** Prüft einen Code innerhalb eines Zeitfensters (±$window Schritte, Uhr-Drift). */
    public static function verify(string $secretBase32, string $code, int $window = 1, ?int $atTime = null): bool
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $atTime ??= time();
        $key = self::base32decode($secretBase32);
        $counter = intdiv($atTime, self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($key, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /** otpauth-URI für den QR-Code der Authenticator-App. */
    public static function otpauthUri(string $secretBase32, string $accountLabel, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $accountLabel)
            . '?secret=' . $secretBase32
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    private static function hotp(string $key, int $counter): string
    {
        $binCounter = pack('N*', 0, $counter); // 8-Byte-Big-Endian-Zähler
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $bin = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string) ($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32encode(string $data): string
    {
        $out = '';
        $bits = 0;
        $value = 0;
        foreach (str_split($data) as $ch) {
            $value = ($value << 8) | ord($ch);
            $bits += 8;
            while ($bits >= 5) {
                $out .= self::ALPHABET[($value >> ($bits - 5)) & 0x1F];
                $bits -= 5;
            }
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($value << (5 - $bits)) & 0x1F];
        }
        return $out;
    }

    private static function base32decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
        $out = '';
        $bits = 0;
        $value = 0;
        foreach (str_split($b32) as $ch) {
            $value = ($value << 5) | strpos(self::ALPHABET, $ch);
            $bits += 5;
            if ($bits >= 8) {
                $out .= chr(($value >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }
        return $out;
    }
}
