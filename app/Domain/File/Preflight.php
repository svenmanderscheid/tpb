<?php
declare(strict_types=1);

namespace Tpb\Domain\File;

use Tpb\Core\Env;

/**
 * Upload-Preflight (§5.4/§8): Endungs-Whitelist -> finfo-MIME -> Größen-/Pixellimit
 * -> SVG-Sanitätsprüfung. Wirft UploadRejected bei Verstoß; liefert sonst Metadaten.
 */
final class Preflight
{
    /** Erlaubte Endungen -> akzeptierte finfo-MIME-Typen. */
    private const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'pdf'  => ['application/pdf'],
        // SVG: finfo ist bei SVG unzuverlässig -> zusätzlich Content-Prüfung unten.
        'svg'  => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'],
    ];

    private const MAX_PIXELS = 100_000_000; // 100 MP Schutzgrenze für Raster

    /**
     * @return array{ext:string,mime:string,type:string,width:?int,height:?int,has_alpha:?bool}
     */
    public static function inspect(string $tmpPath, string $originalName, int $sizeBytes): array
    {
        $maxBytes = Env::int('UPLOAD_MAX_BYTES', 26_214_400);
        if ($sizeBytes <= 0) {
            throw new UploadRejected('Die Datei ist leer.');
        }
        if ($sizeBytes > $maxBytes) {
            throw new UploadRejected('Die Datei ist zu groß (max. ' . intdiv($maxBytes, 1_048_576) . ' MB).');
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            throw new UploadRejected('Dateityp nicht erlaubt (nur SVG, PDF, PNG, JPG).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpPath);

        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            throw new UploadRejected('Dateiinhalt passt nicht zur Endung.');
        }

        return match ($ext) {
            'png'         => self::inspectRaster($tmpPath, 'image/png'),
            'jpg', 'jpeg' => self::inspectRaster($tmpPath, 'image/jpeg'),
            'pdf'         => ['ext' => 'pdf', 'mime' => 'application/pdf', 'type' => 'pdf', 'width' => null, 'height' => null, 'has_alpha' => null],
            'svg'         => self::inspectSvg($tmpPath),
            default       => throw new UploadRejected('Dateityp nicht erlaubt.'),
        };
    }

    /**
     * @return array{ext:string,mime:string,type:string,width:?int,height:?int,has_alpha:?bool}
     */
    private static function inspectRaster(string $tmpPath, string $mime): array
    {
        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new UploadRejected('Bilddatei konnte nicht gelesen werden.');
        }
        [$w, $h] = $info;
        if ($w * $h > self::MAX_PIXELS) {
            throw new UploadRejected('Bildauflösung zu hoch.');
        }
        $hasAlpha = null;
        if ($mime === 'image/png') {
            $hasAlpha = in_array($info['channels'] ?? 0, [2, 4], true)
                || (($info['bits'] ?? 0) === 8 && self::pngHasAlpha($tmpPath));
        }
        return [
            'ext'       => $mime === 'image/png' ? 'png' : 'jpg',
            'mime'      => $mime,
            'type'      => 'raster',
            'width'     => (int) $w,
            'height'    => (int) $h,
            'has_alpha' => $hasAlpha,
        ];
    }

    /**
     * @return array{ext:string,mime:string,type:string,width:?int,height:?int,has_alpha:?bool}
     */
    private static function inspectSvg(string $tmpPath): array
    {
        $content = (string) file_get_contents($tmpPath);
        if (!preg_match('/<svg[\s>]/i', $content)) {
            throw new UploadRejected('Keine gültige SVG-Datei.');
        }
        // Aktive Inhalte blockieren (§8: SVG niemals inline; keine Skriptausführung).
        if (preg_match('/<script[\s>]/i', $content)
            || preg_match('/\son\w+\s*=/i', $content)
            || preg_match('/javascript:/i', $content)
            || preg_match('/<foreignObject[\s>]/i', $content)
        ) {
            throw new UploadRejected('SVG enthält aktive Inhalte und wurde abgelehnt.');
        }
        return ['ext' => 'svg', 'mime' => 'image/svg+xml', 'type' => 'vector', 'width' => null, 'height' => null, 'has_alpha' => null];
    }

    private static function pngHasAlpha(string $path): bool
    {
        $data = file_get_contents($path, false, null, 24, 2);
        if ($data === false || strlen($data) < 2) {
            return false;
        }
        $colorType = ord($data[1]);
        return in_array($colorType, [4, 6], true); // 4=grau+alpha, 6=RGBA
    }
}
