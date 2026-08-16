<?php
declare(strict_types=1);

/**
 * Jobetikett-Testrender (§9.2, M5-DoD): rendert das Etikett eines Jobs als PNG in
 * 203 und 300 dpi (Sichtprüfung: QR/Code128 mit Smartphone scanbar) sowie als PDF.
 *
 * Aufruf: php cli/label_test.php --job=<job_public_id> [--out=<verzeichnis>]
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\View;
use Tpb\Domain\Label\LabelService;
use Tpb\Domain\Production\JobRepo;

View::base(dirname(__DIR__) . '/app/Views');

$argv = $_SERVER['argv'] ?? [];
$jobPublic = cli_opt($argv, 'job');
$out = cli_opt($argv, 'out') ?: (dirname(__DIR__) . '/private/tpb/tmp/labels');

if ($jobPublic === null || $jobPublic === '') {
    fwrite(STDERR, "Bitte --job=<job_public_id> angeben.\n");
    exit(1);
}
if (JobRepo::findByPublicId($jobPublic) === null) {
    fwrite(STDERR, "Job nicht gefunden: {$jobPublic}\n");
    exit(1);
}
if (!is_dir($out)) {
    mkdir($out, 0770, true);
}

foreach ([203, 300] as $dpi) {
    $png = LabelService::renderPng($jobPublic, $dpi);
    $path = $out . "/label_{$jobPublic}_{$dpi}dpi.png";
    file_put_contents($path, $png);
    $info = getimagesizefromstring($png);
    echo "PNG {$dpi} dpi: {$path} ({$info[0]}x{$info[1]} px)\n";
}

// Zusätzlich das Druck-PDF (identisch zum Handoff) erzeugen.
$res = LabelService::render($jobPublic, null);
echo "PDF: print_job #{$res['print_job_id']}, Asset {$res['asset_public_id']}\n";
echo "Fertig.\n";
