<?php
declare(strict_types=1);

namespace Tpb\Domain\Label;

use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Erzeugt QR- und Code-128-Grafiken als PNG-Data-URIs (§9.2/§9.3). Data-URIs sind
 * lokal (kein Netzzugriff) und in dompdf mit `isRemoteEnabled=false` einbettbar.
 * Whitelist-Bibliotheken (§1.1): chillerlan/php-qrcode, picqer/php-barcode-generator.
 */
final class Barcode
{
    /** Code 128 (z. B. Jobnummer) als PNG-Data-URI. */
    public static function code128(string $text, int $widthFactor = 2, int $height = 44): string
    {
        $png = (new BarcodeGeneratorPNG())->getBarcode($text, BarcodeGeneratorPNG::TYPE_CODE_128, $widthFactor, $height);
        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** QR-Code (interne Scan-URL) als PNG-Data-URI. */
    public static function qrPng(string $data, int $scale = 4): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64'    => true,
            'scale'           => $scale,
        ]);
        return (new QRCode($options))->render($data);
    }

    /** QR-Code als rohe PNG-Bytes (für einen Bild-Endpunkt, CSP-konform ohne data:). */
    public static function qrPngRaw(string $data, int $scale = 5): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64'    => false,
            'scale'           => $scale,
        ]);
        return (new QRCode($options))->render($data);
    }
}
