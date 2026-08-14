<?php
declare(strict_types=1);

namespace Tpb\Domain\File;

/**
 * Physischer privater Speicher unter private/tpb (§8, §10.5). Außerhalb
 * public_html. Die DB speichert nur den relativen storage_key, nie absolute Pfade.
 */
final class PrivateStorage
{
    public static function base(): string
    {
        return TPB_ROOT . '/private/tpb';
    }

    public static function path(string $storageKey): string
    {
        return self::base() . '/' . ltrim($storageKey, '/');
    }

    public static function ensureDirFor(string $storageKey): void
    {
        $dir = dirname(self::path($storageKey));
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }

    public static function put(string $storageKey, string $contents): void
    {
        self::ensureDirFor($storageKey);
        file_put_contents(self::path($storageKey), $contents);
    }

    /** Verschiebt eine (ggf. hochgeladene) Datei in den privaten Speicher. */
    public static function move(string $tmpPath, string $storageKey, bool $isUpload): void
    {
        self::ensureDirFor($storageKey);
        $dest = self::path($storageKey);
        $ok = $isUpload ? move_uploaded_file($tmpPath, $dest) : rename($tmpPath, $dest);
        if (!$ok) {
            // Fallback: kopieren (z. B. wenn rename über Laufwerksgrenzen scheitert).
            if (!copy($tmpPath, $dest)) {
                throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
            }
            @unlink($tmpPath);
        }
    }

    public static function exists(string $storageKey): bool
    {
        return is_file(self::path($storageKey));
    }

    public static function get(string $storageKey): string
    {
        return (string) file_get_contents(self::path($storageKey));
    }

    public static function size(string $storageKey): int
    {
        return (int) filesize(self::path($storageKey));
    }
}
