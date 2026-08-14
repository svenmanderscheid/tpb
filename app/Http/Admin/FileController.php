<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Db;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Domain\File\DownloadGuard;
use Tpb\Domain\File\PrivateStorage;

/**
 * Gesicherter Download /files/{publicId} (§8). Streaming mit attachment-Header,
 * nosniff. SVG/PDF werden nie inline ausgeliefert.
 */
final class FileController
{
    /** @param array<string,string> $params */
    public function download(array $params): void
    {
        $publicId = $params['publicId'] ?? '';
        $asset = Db::run(
            'SELECT id, public_id, original_name, mime, storage_key, security_status
             FROM assets WHERE public_id = ? LIMIT 1',
            [$publicId]
        )->fetch();

        if (!$asset) {
            Response::error(404);
            return;
        }

        $token = Request::query('t');
        if (!DownloadGuard::authorize($asset, $token)) {
            Response::error(403, 'Kein Zugriff auf diese Datei.');
            return;
        }

        // Quarantäne-Objekte werden nicht ausgeliefert (§8).
        if ($asset['security_status'] !== 'clean') {
            Response::error(403, 'Datei ist noch in Quarantäne.');
            return;
        }

        if (!PrivateStorage::exists($asset['storage_key'])) {
            Response::error(404);
            return;
        }

        $path = PrivateStorage::path($asset['storage_key']);
        $filename = (string) $asset['original_name'];

        Response::sendSecurityHeaders();
        http_response_code(200);
        // Immer als Download, nie inline (kein aktives SVG/PDF im Browser-Kontext).
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . self::asciiFilename($filename) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($path);
    }

    private static function asciiFilename(string $name): string
    {
        // Auf ASCII reduzieren, um Header-Injection/Encoding-Probleme zu vermeiden.
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'download';
        return $ascii === '' ? 'download' : $ascii;
    }
}
