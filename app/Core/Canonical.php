<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Kanonisches JSON für Snapshots/Hashes (§3.6): Keys rekursiv sortiert,
 * keine Floats, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES.
 */
final class Canonical
{
    public static function json(array $data): string
    {
        $normalized = self::normalize($data);
        return json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    public static function hash(array $data): string
    {
        return hash('sha256', self::json($data));
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_float($value)) {
            throw new \InvalidArgumentException('Floats sind im kanonischen JSON verboten (§3.1)');
        }
        if (is_array($value)) {
            $isList = array_is_list($value);
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::normalize($v);
            }
            if (!$isList) {
                ksort($out, SORT_STRING);
            }
            return $out;
        }
        return $value;
    }
}
