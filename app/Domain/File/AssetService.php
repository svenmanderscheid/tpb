<?php
declare(strict_types=1);

namespace Tpb\Domain\File;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;
use Tpb\Domain\Audit\Audit;

/**
 * Upload-Pipeline (§5.4/§8):
 * Whitelist -> finfo -> Limits -> SHA-256 -> Speicherung unter
 * artwork/{owner}/{assetUlid}/original -> security_status=quarantine ->
 * Preflight -> Thumbnail (PNG, GD) getrennt vom Original -> clean.
 */
final class AssetService
{
    /**
     * @param array{name:string,type?:string,tmp_name:string,error:int,size:int} $file
     * @return array<string,mixed>  Der angelegte Asset-Datensatz (inkl. public_id).
     */
    public static function storeUpload(
        array $file,
        int $userId,
        string $kind = 'artwork',
        string $retentionClass = 'artwork_short'
    ): array {
        self::assertUploadOk($file['error']);

        $tmp = $file['tmp_name'];
        $originalName = self::sanitizeName($file['name']);
        $sizeBytes = (int) ($file['size'] ?: (is_file($tmp) ? filesize($tmp) : 0));

        // 1) Preflight auf der temporären Datei (wirft UploadRejected).
        $meta = Preflight::inspect($tmp, $originalName, $sizeBytes);

        // 2) SHA-256 vor dem Verschieben.
        $sha256 = (string) hash_file('sha256', $tmp);

        // 3) Speicherung.
        $ulid = Ulid::generate();
        $ownerSeg = 'user-' . $userId;
        $storageKey = "artwork/{$ownerSeg}/{$ulid}/original";
        PrivateStorage::move($tmp, $storageKey, is_uploaded_file($tmp));

        // 4) Asset-Zeile in Quarantäne.
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO assets
                (public_id, owner_type, owner_id, kind, original_name, mime, size_bytes,
                 sha256, storage_key, security_status, retention_class, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $ulid, 'user', $userId, $kind, $originalName, $meta['mime'], $sizeBytes,
                $sha256, $storageKey, 'quarantine', $retentionClass, $userId, $now,
            ]
        );
        $id = (int) Db::pdo()->lastInsertId();

        // 5) Thumbnail (nur Raster) getrennt vom Original.
        $thumbKey = null;
        if ($meta['type'] === 'raster') {
            $thumbKey = "artwork/{$ownerSeg}/{$ulid}/thumb.png";
            try {
                self::makeThumbnail(PrivateStorage::path($storageKey), $meta['mime'], PrivateStorage::path($thumbKey));
            } catch (\Throwable) {
                $thumbKey = null; // Thumbnail ist optional; Original bleibt gültig.
            }
        }

        // 6) Quarantäne -> clean.
        Db::run('UPDATE assets SET security_status = ? WHERE id = ?', ['clean', $id]);

        Audit::log('asset', $ulid, 'asset.uploaded', [
            'actor_user_id' => $userId,
            'metadata'      => [
                'mime'   => $meta['mime'],
                'size'   => $sizeBytes,
                'type'   => $meta['type'],
                'width'  => $meta['width'],
                'height' => $meta['height'],
                'sha256' => $sha256,
            ],
        ]);

        return [
            'id'              => $id,
            'public_id'       => $ulid,
            'original_name'   => $originalName,
            'mime'            => $meta['mime'],
            'size_bytes'      => $sizeBytes,
            'security_status' => 'clean',
            'thumb_key'       => $thumbKey,
            'preflight'       => $meta,
        ];
    }

    private static function assertUploadOk(int $error): void
    {
        if ($error === UPLOAD_ERR_OK) {
            return;
        }
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist zu groß.',
            UPLOAD_ERR_PARTIAL   => 'Der Upload wurde unterbrochen.',
            UPLOAD_ERR_NO_FILE   => 'Es wurde keine Datei ausgewählt.',
            default              => 'Upload fehlgeschlagen.',
        };
        throw new UploadRejected($message);
    }

    private static function sanitizeName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? 'datei';
        $base = trim($base, '._-');
        return $base === '' ? 'datei' : mb_substr($base, 0, 200);
    }

    private static function makeThumbnail(string $srcPath, string $mime, string $destPath, int $max = 400): void
    {
        $src = match ($mime) {
            'image/png'  => imagecreatefrompng($srcPath),
            'image/jpeg' => imagecreatefromjpeg($srcPath),
            default      => null,
        };
        if (!$src instanceof \GdImage) {
            throw new \RuntimeException('Kein rasterbares Bild für Thumbnail.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $max / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $tw, $th, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        imagepng($dst, $destPath);
        imagedestroy($dst);
        imagedestroy($src);
    }
}
