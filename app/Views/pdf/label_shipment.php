<?php
declare(strict_types=1);
/**
 * Hausetikett Versand 100 × 150 mm (DECISIONS #28). Empfänger + Auftrags-Barcode/QR.
 * Kein Carrier-Tracking-Barcode (folgt mit echter Carrier-Anbindung). Werte über e().
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
  .lbl { width: 100mm; padding: 5mm; box-sizing: border-box; color: #000; }
  .sender { font-size: 8pt; color: #333; border-bottom: 0.3mm solid #000; padding-bottom: 2mm; }
  .method { margin-top: 3mm; font-size: 10pt; font-weight: bold; }
  .to-label { margin-top: 6mm; font-size: 8pt; color: #333; text-transform: uppercase; letter-spacing: 0.05em; }
  .to { font-size: 15pt; line-height: 1.35; margin-top: 1mm; }
  .to .name { font-weight: bold; }
  .codes { margin-top: 8mm; text-align: center; }
  .codes img.bc { width: 80mm; height: 16mm; }
  .codes img.qr { width: 28mm; height: 28mm; margin-top: 2mm; }
  .ordno { margin-top: 2mm; font-size: 9pt; }
</style>
</head>
<body>
<div class="lbl">
  <div class="sender">The Printing Brothers · Luxembourg &nbsp; <span style="color:#b00">[Absenderadresse – Platzhalter]</span></div>
  <div class="method"><?= e((string) $l['method']) ?><?php if (!empty($l['carrier'])): ?> · <?= e((string) $l['carrier']) ?><?php endif; ?></div>

  <div class="to-label">Empfänger</div>
  <div class="to">
    <?php if (!empty($l['recipient_company'])): ?><span class="name"><?= e((string) $l['recipient_company']) ?></span><br><?php endif; ?>
    <span class="name"><?= e((string) $l['recipient_name']) ?></span><br>
    <?php if (!empty($l['street'])): ?><?= e((string) $l['street']) ?><br><?php endif; ?>
    <?php if (!empty($l['zip']) || !empty($l['city'])): ?><?= e(trim((string) ($l['zip'] ?? '') . ' ' . (string) ($l['city'] ?? ''))) ?><br><?php endif; ?>
    <?php if (!empty($l['country'])): ?><?= e((string) $l['country']) ?><?php endif; ?>
  </div>

  <?php if (!empty($l['tracking_ref'])): ?>
    <div class="ordno">Tracking: <?= e((string) $l['tracking_ref']) ?></div>
  <?php endif; ?>

  <div class="codes">
    <img class="bc" src="<?= e((string) $l['barcode']) ?>" alt="Code128">
    <div class="ordno">Auftrag <?= e((string) $l['order_number']) ?></div>
    <img class="qr" src="<?= e((string) $l['qr']) ?>" alt="QR">
  </div>
</div>
</body>
</html>
