<?php
declare(strict_types=1);

namespace Tpb\Core;

/**
 * Parst .env (KEY=VALUE, `#` Kommentar). Keine externe Lib (§4).
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $val = trim(substr($line, $pos + 1));
                $len = strlen($val);
                if ($len >= 2
                    && ($val[0] === '"' || $val[0] === "'")
                    && $val[$len - 1] === $val[0]
                ) {
                    $val = substr($val, 1, -1);
                }
                self::$vars[$key] = $val;
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }
        return $default;
    }

    public static function require(string $key): string
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required env var: {$key}");
        }
        return $v;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return ($v === null || $v === '') ? $default : (int) $v;
    }

    public static function bool(string $key, bool $default): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** Nur für Tests/Bootstrap – überschreibt einen Wert im Speicher. */
    public static function set(string $key, string $value): void
    {
        self::$vars[$key] = $value;
        self::$loaded = true;
    }
}
