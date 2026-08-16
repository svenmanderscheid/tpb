<?php
declare(strict_types=1);

namespace Tpb\Domain\Label;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\File\AssetService;
use Tpb\Domain\Pdf\PdfService;
use Tpb\Domain\Production\JobRepo;
use Tpb\Domain\Proof\ArtworkRepo;
use Tpb\Domain\Token\AccessTokenService;

/**
 * Jobetikett (§9.2, M5). Rendert das 62×100-mm-PDF (Stufe-1-Handoff §9.6: nur
 * rendern, Nutzer druckt über den Systemdialog) und protokolliert print_jobs.
 * Neudruck nur mit Grund (DoD M5). Zusätzlich GD-PNG-Testrender in 203/300 dpi.
 */
final class LabelService
{
    public const SCAN_TOKEN_PURPOSE = 'job_scan';
    private const TEMPLATE_VERSION = 'v1';
    private const W_MM = 62.0;
    private const H_MM = 100.0;

    /**
     * Rendert das Jobetikett-PDF, speichert es als Asset und legt eine print_jobs-Zeile
     * an (status=rendered). Für einen Neudruck sind reprintOf + Grund verpflichtend.
     *
     * @return array{print_job_id:int,asset_public_id:string}
     */
    public static function render(string $jobPublicId, ?int $actorUserId, ?int $reprintOfId = null, ?string $reprintReason = null): array
    {
        return Db::tx(function () use ($jobPublicId, $actorUserId, $reprintOfId, $reprintReason): array {
            $job = JobRepo::findByPublicId($jobPublicId);
            if ($job === null) {
                throw new \InvalidArgumentException('Job nicht gefunden.');
            }
            if ($reprintOfId !== null && ($reprintReason === null || trim($reprintReason) === '')) {
                throw new \InvalidArgumentException('Ein Neudruck erfordert einen Grund.');
            }

            $scanToken = AccessTokenService::issue(self::SCAN_TOKEN_PURPOSE, 'production_job', (int) $job['id']);
            $base = rtrim((string) (Env::get('APP_URL', 'http://tpb.local') ?? 'http://tpb.local'), '/');
            $scanUrl = $base . '/scan/' . $jobPublicId . '?t=' . $scanToken;

            $label = self::buildLabel($job, $scanUrl);
            $pdf = PdfService::renderLabel('label_job', ['label' => $label], self::W_MM, self::H_MM);

            $asset = AssetService::storeGenerated(
                $pdf, 'Etikett_' . (string) $job['job_number'] . '.pdf', 'application/pdf',
                'label_pdf', 'temp_30d', $actorUserId, 'production_job', (int) $job['id']
            );

            $n = (int) Db::run("SELECT COUNT(*) FROM print_jobs WHERE entity_type = 'production_job' AND entity_id = ?", [(int) $job['id']])->fetchColumn();
            $idemKey = 'label_job:' . (int) $job['id'] . ':' . $n;

            Db::run(
                'INSERT INTO print_jobs
                    (label_type, entity_type, entity_id, template_version, copies, idempotency_key, payload_json,
                     render_format, rendered_asset_id, rendered_sha256, status, requested_by, requested_at, reprint_of_id, reprint_reason)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'job', 'production_job', (int) $job['id'], self::TEMPLATE_VERSION, 1, $idemKey,
                    json_encode($label, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'pdf', $asset['id'], $asset['sha256'], 'rendered', $actorUserId, Clock::nowUtcSeconds(),
                    $reprintOfId, $reprintReason !== null && trim($reprintReason) !== '' ? mb_substr($reprintReason, 0, 255) : null,
                ]
            );
            $printJobId = (int) Db::pdo()->lastInsertId();

            Audit::log('production_job', $jobPublicId, $reprintOfId !== null ? 'label.reprinted' : 'label.rendered', [
                'actor_user_id' => $actorUserId,
                'metadata'      => ['print_job_id' => $printJobId, 'sha256' => $asset['sha256'], 'reprint_of' => $reprintOfId],
            ]);

            return ['print_job_id' => $printJobId, 'asset_public_id' => (string) $asset['public_id']];
        });
    }

    /**
     * Baut die Etikettendaten aus Job + Auftrag + Position + Artwork-Version.
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    public static function buildLabel(array $job, string $scanUrl): array
    {
        $order = Db::run('SELECT order_number FROM orders WHERE id = ? LIMIT 1', [(int) $job['order_id']])->fetch();
        $item = Db::run('SELECT description, qty, config_snapshot_json FROM order_items WHERE id = ? LIMIT 1', [(int) $job['order_item_id']])->fetch();
        $config = $item && $item['config_snapshot_json'] !== null ? json_decode((string) $item['config_snapshot_json'], true) : null;

        $sizes = [];
        $qtyTotal = 0;
        if (is_array($config) && !empty($config['sizes'])) {
            foreach ($config['sizes'] as $s) {
                $sizes[] = ['label' => (string) ($s['variant_sku'] ?? ''), 'qty' => (int) ($s['qty'] ?? 0)];
                $qtyTotal += (int) ($s['qty'] ?? 0);
            }
        } else {
            $sizes[] = ['label' => (string) ($item['sku'] ?? 'Standard'), 'qty' => (int) $item['qty']];
            $qtyTotal = (int) $item['qty'];
        }

        $positions = [];
        if (is_array($config)) {
            foreach ($config['layers'] ?? [] as $lay) {
                $positions[(string) ($lay['placement_code'] ?? '')] = true;
            }
        }

        $artwork = ArtworkRepo::latest((int) $job['order_id']);

        return [
            'job_number'      => (string) $job['job_number'],
            'order_number'    => (string) ($order['order_number'] ?? ''),
            'product'         => (string) ($item['description'] ?? ''),
            'design_name'     => is_array($config) ? (string) ($config['design_name'] ?? '') : '',
            'design_version'  => is_array($config) ? (string) ($config['design_version'] ?? '') : '',
            'technique'       => (string) ($job['route'] ?? ''),
            'positions'       => $positions !== [] ? implode(', ', array_keys($positions)) : '—',
            'artwork_version' => $artwork !== null ? (string) $artwork['version_no'] : '—',
            'due_date'        => $job['due_date'] ?? null,
            'sizes'           => $sizes,
            'qty_total'       => $qtyTotal,
            'barcode'         => Barcode::code128((string) $job['job_number']),
            'qr'              => Barcode::qrPng($scanUrl, 4),
        ];
    }

    /**
     * GD-Testrender des Etiketts als PNG in exakter physischer Größe bei gegebener
     * Auflösung (M5-DoD: 203/300 dpi zur Sichtprüfung). Gibt die PNG-Bytes zurück.
     */
    public static function renderPng(string $jobPublicId, int $dpi): string
    {
        $job = JobRepo::findByPublicId($jobPublicId);
        if ($job === null) {
            throw new \InvalidArgumentException('Job nicht gefunden.');
        }
        $base = rtrim((string) (Env::get('APP_URL', 'http://tpb.local') ?? 'http://tpb.local'), '/');
        $label = self::buildLabel($job, $base . '/scan/' . $jobPublicId . '?t=preview');

        $w = (int) round(self::W_MM / 25.4 * $dpi);
        $h = (int) round(self::H_MM / 25.4 * $dpi);
        $img = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $w, $h, $white);

        $pad = (int) round(2.5 / 25.4 * $dpi);
        $font = self::fontPath();
        $y = $pad;

        $line = function (string $text, float $ptSize, bool $bold = false) use (&$y, $img, $black, $font, $pad, $dpi): void {
            $px = (int) round($ptSize / 72 * $dpi);
            $y += $px;
            if ($font !== null) {
                imagettftext($img, $px, 0, $pad, $y, $black, $font, $text);
            } else {
                imagestring($img, 5, $pad, $y - $px, $text, $black);
            }
            $y += (int) round($px * 0.5);
        };

        $line((string) $label['job_number'], 12, true);
        $line('Auftrag ' . (string) $label['order_number'], 8);
        $line((string) $label['product'], 8);
        $line('Technik: ' . (string) $label['technique'] . ' · Pos: ' . (string) $label['positions'], 7);
        $line('Artwork v' . (string) $label['artwork_version'] . ' · Menge ' . (string) $label['qty_total'], 7);

        // Barcode + QR aus den Data-URIs übernehmen.
        $bc = self::pngFromDataUri((string) $label['barcode']);
        if ($bc !== null) {
            $bw = $w - 2 * $pad;
            $bh = (int) round(12 / 25.4 * $dpi);
            imagecopyresampled($img, $bc, $pad, $y + $pad, 0, 0, $bw, $bh, imagesx($bc), imagesy($bc));
            imagedestroy($bc);
            $y += $bh + 2 * $pad;
        }
        $qr = self::pngFromDataUri((string) $label['qr']);
        if ($qr !== null) {
            $qs = (int) round(24 / 25.4 * $dpi);
            imagecopyresampled($img, $qr, $pad, $y, 0, 0, $qs, $qs, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);
        return (string) ob_get_clean();
    }

    private static function pngFromDataUri(string $dataUri): ?\GdImage
    {
        $pos = strpos($dataUri, ',');
        if ($pos === false) {
            return null;
        }
        $raw = base64_decode(substr($dataUri, $pos + 1), true);
        if ($raw === false) {
            return null;
        }
        $img = imagecreatefromstring($raw);
        return $img instanceof \GdImage ? $img : null;
    }

    private static function fontPath(): ?string
    {
        $candidates = [
            TPB_ROOT . '/vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf',
            'C:/Windows/Fonts/arial.ttf',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }
}
