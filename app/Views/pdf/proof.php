<?php
declare(strict_types=1);
/**
 * Proof-PDF (§9, M4). Rendert die Produktionsspezifikation (Positionen + Maße in mm)
 * aus dem Order-/Config-Snapshot. Alle dynamischen Werte über e(); keine externen
 * Ressourcen (dompdf-Härtung §9).
 *
 * @var array<string,mixed> $order
 * @var int $version_no
 * @var array<string,mixed> $customer
 * @var array<int,array<string,mixed>> $items
 */
$name = trim((string) ($customer['first_name'] ?? '') . ' ' . (string) ($customer['last_name'] ?? ''));
$company = (string) ($customer['company_name'] ?? '');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { font-size: 11px; color: #1a1a1a; margin: 0; }
  .wrap { padding: 6px 4px; }
  h1 { font-size: 18px; margin: 10px 0 2px; }
  .meta { color: #444; margin-bottom: 14px; }
  .item { border: 1px solid #ccc; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; }
  .item h2 { font-size: 13px; margin: 0 0 6px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 4px 6px; border-bottom: 1px solid #e0e0e0; text-align: left; }
  th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; }
  td.num, th.num { text-align: right; }
  .note { margin-top: 18px; color: #555; font-size: 10px; }
  .placeholder { color: #b00; font-size: 9px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Proof – Auftrag <?= e((string) $order['order_number']) ?></h1>
  <div class="meta">
    Version <?= e((string) $version_no) ?> ·
    <?php if ($company !== ''): ?><?= e($company) ?> · <?php endif; ?><?= e($name) ?>
  </div>

  <?php foreach ($items as $it): ?>
    <div class="item">
      <h2><?= e((string) $it['description']) ?> — <?= e((string) $it['qty']) ?> Stück</h2>
      <?php $layers = $it['layers'] ?? []; ?>
      <?php if (empty($layers)): ?>
        <p class="muted">Keine Druckposition hinterlegt.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>Position</th><th>Art</th><th>Inhalt</th><th class="num">Breite mm</th><th class="num">Höhe mm</th><th class="num">X mm</th><th class="num">Y mm</th></tr></thead>
          <tbody>
            <?php foreach ($layers as $l): ?>
              <tr>
                <td><?= e((string) ($l['placement_code'] ?? '')) ?></td>
                <td><?= e((string) ($l['layer_type'] ?? '')) ?></td>
                <td><?= e((string) ($l['text_content'] ?? ($l['color_name'] ?? '—'))) ?></td>
                <td class="num"><?= e((string) ($l['width_mm'] ?? '')) ?></td>
                <td class="num"><?= e((string) ($l['height_mm'] ?? '')) ?></td>
                <td class="num"><?= e((string) ($l['offset_x_mm'] ?? '')) ?></td>
                <td class="num"><?= e((string) ($l['offset_y_mm'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="note">
    Bitte prüfen Sie Motiv, Position und Maße sorgfältig. Mit Ihrer Freigabe bestätigen Sie diese Produktionsvorgaben verbindlich.
    <span class="placeholder">[Platzhalter – gerasterte Motivvorschau folgt; derzeit Maßangaben aus der Konfiguration]</span>
  </div>
</div>
</body>
</html>
