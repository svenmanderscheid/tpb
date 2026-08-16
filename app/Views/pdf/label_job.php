<?php
declare(strict_types=1);
/**
 * Jobetikett 62 × 100 mm (§9.2). Enthält Jobnummer (Code 128), QR (interne Scan-URL),
 * Produkt, Größenraster/Menge, Technik, Positionen, Artwork-Version, Termin.
 * QR/Barcode als lokale PNG-Data-URIs (dompdf, isRemoteEnabled=false).
 *
 * @var array<string,mixed> $label
 */
$l = $label;
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 0; }
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { margin: 0; }
  .lbl { width: 62mm; padding: 2.5mm; box-sizing: border-box; font-size: 8pt; color: #000; }
  .jobno { font-size: 11pt; font-weight: bold; }
  .row { margin-top: 1.2mm; }
  .k { color: #333; }
  .codes { margin-top: 2mm; text-align: center; }
  .codes img.bc { width: 56mm; height: 12mm; }
  .codes img.qr { width: 24mm; height: 24mm; margin-top: 1.5mm; }
  table { width: 100%; border-collapse: collapse; margin-top: 1mm; }
  td, th { border: 0.2mm solid #999; padding: 0.6mm 1mm; text-align: left; font-size: 7.5pt; }
  td.num, th.num { text-align: right; }
</style>
</head>
<body>
<div class="lbl">
  <div class="jobno"><?= e((string) $l['job_number']) ?></div>
  <div class="row k">Auftrag <?= e((string) $l['order_number']) ?></div>
  <div class="row"><strong><?= e((string) $l['product']) ?></strong></div>
  <?php if (!empty($l['design_name'])): ?>
    <div class="row k">Design: <?= e((string) $l['design_name']) ?><?= !empty($l['design_version']) ? ' (' . e((string) $l['design_version']) . ')' : '' ?></div>
  <?php endif; ?>
  <div class="row k">Technik: <?= e((string) $l['technique']) ?> · Positionen: <?= e((string) $l['positions']) ?></div>
  <div class="row k">Artwork: v<?= e((string) $l['artwork_version']) ?><?php if (!empty($l['due_date'])): ?> · Termin: <?= e((string) $l['due_date']) ?><?php endif; ?></div>

  <table>
    <thead><tr><th>Größe</th><th class="num">Menge</th></tr></thead>
    <tbody>
      <?php foreach (($l['sizes'] ?? []) as $s): ?>
        <tr><td><?= e((string) $s['label']) ?></td><td class="num"><?= e((string) $s['qty']) ?></td></tr>
      <?php endforeach; ?>
      <tr><td><strong>Gesamt</strong></td><td class="num"><strong><?= e((string) $l['qty_total']) ?></strong></td></tr>
    </tbody>
  </table>

  <div class="codes">
    <img class="bc" src="<?= e((string) $l['barcode']) ?>" alt="Code128">
    <br>
    <img class="qr" src="<?= e((string) $l['qr']) ?>" alt="QR">
  </div>
</div>
</body>
</html>
