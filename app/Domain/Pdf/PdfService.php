<?php
declare(strict_types=1);

namespace Tpb\Domain\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Tpb\Core\View;

/**
 * PDF-Rendering (§9) mit dompdf-Härtung (verbindlich):
 *  - isRemoteEnabled = false (kein Nachladen aus dem Netz),
 *  - chroot auf Views/pdf + Temp-Verzeichnis,
 *  - nur lokale Bilder aus dem privaten Speicher,
 *  - alle dynamischen Werte laufen über e() in den PDF-Views (nie roh).
 * PDF-Views liegen unter Views/pdf/ und rendern ohne Layout-Wrapper.
 */
final class PdfService
{
    /**
     * Rendert eine PDF-View zu einem PDF-Binärstring.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $view, array $data = [], string $paper = 'A4'): string
    {
        $html = View::render('pdf/' . $view, $data, null);
        return self::fromHtml($html, $paper);
    }

    /**
     * Rendert eine View auf ein Etikett in exakter physischer Größe (§9.2), z. B.
     * Jobetikett 62 × 100 mm. Papiermaß in Millimetern, intern in PostScript-Punkte
     * umgerechnet (1 mm = 72/25.4 pt).
     *
     * @param array<string,mixed> $data
     */
    public static function renderLabel(string $view, array $data, float $widthMm, float $heightMm): string
    {
        $html = View::render('pdf/' . $view, $data, null);
        $wPt = $widthMm * 72.0 / 25.4;
        $hPt = $heightMm * 72.0 / 25.4;
        return self::fromHtml($html, [0, 0, $wPt, $hPt]);
    }

    /** @param string|array<int,float|int> $paper 'A4' o. Ä. oder [x0,y0,x1,y1] in Punkten. */
    public static function fromHtml(string $html, string|array $paper = 'A4'): string
    {
        $tmp = self::tempDir();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('tempDir', $tmp);
        $options->set('fontDir', $tmp . '/fonts');
        $options->set('fontCache', $tmp . '/fonts');
        $options->set('chroot', [TPB_ROOT . '/app/Views/pdf', $tmp]);
        // DejaVu Sans deckt Umlaute/Akzente ab (§9: Umlaute/Akzente testen).
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private static function tempDir(): string
    {
        $dir = TPB_ROOT . '/private/tpb/tmp/dompdf';
        if (!is_dir($dir . '/fonts')) {
            mkdir($dir . '/fonts', 0770, true);
        }
        return $dir;
    }
}
