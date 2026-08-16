<?php
declare(strict_types=1);
/**
 * Rechnungs-/Gutschrift-PDF (§11.2). Rendert AUSSCHLIESSLICH aus dem eingefrorenen
 * Snapshot – niemals aus Live-Daten. Alle Werte über e(); Steuerlegende aus dem
 * Snapshot (tax_regime_versions, §11.5). Keine externen Ressourcen (dompdf-Härtung §9).
 *
 * @var array<string,mixed> $snapshot
 */
use Tpb\Core\Money;

$s = $snapshot;
$isCredit = ($s['doc_type'] ?? 'invoice') === 'credit_note';
$currency = (string) ($s['currency'] ?? 'EUR');
$seller = $s['seller'] ?? [];
$c = $s['customer'] ?? [];
$totals = $s['totals'] ?? [];
$title = $isCredit ? 'Gutschrift' : 'Rechnung';

$custName = trim((string) ($c['first_name'] ?? '') . ' ' . (string) ($c['last_name'] ?? ''));
$billing = $c['billing'] ?? [];
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { font-size: 11px; color: #1a1a1a; margin: 0; }
  .wrap { padding: 8px 6px; }
  .seller { font-size: 10px; color: #555; }
  .placeholder { color: #b00; font-size: 9px; }
  h1 { font-size: 18px; margin: 14px 0 2px; }
  .meta { margin: 2px 0 14px; color: #444; }
  .addr { margin: 8px 0 16px; }
  table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  th, td { padding: 5px 6px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
  th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; }
  td.num, th.num { text-align: right; white-space: nowrap; }
  tfoot td { border-bottom: none; }
  .tot { font-size: 14px; font-weight: bold; }
  .legend { margin-top: 16px; color: #333; font-size: 10px; }
  .foot { margin-top: 20px; color: #666; font-size: 9px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="seller">
    <strong><?= e((string) ($seller['name'] ?? 'The Printing Brothers')) ?></strong><br>
    <?php foreach (($seller['address_lines'] ?? []) as $line): ?><?= e((string) $line) ?><br><?php endforeach; ?>
    <?php if (!empty($seller['placeholder'])): ?><span class="placeholder">[Verkäuferangaben – Platzhalter, docs/OFFENE-FRAGEN.md]</span><?php endif; ?>
  </div>

  <h1><?= e($title) ?> <?= e((string) ($s['invoice_number'] ?? '')) ?></h1>
  <div class="meta">
    Datum: <?= e((string) ($s['issued_at'] ?? '')) ?>
    <?php if (!$isCredit && !empty($s['due_date'])): ?> · Fällig: <?= e((string) $s['due_date']) ?><?php endif; ?>
    <?php if ($isCredit && !empty($s['credited_number'])): ?> · zu Rechnung <?= e((string) $s['credited_number']) ?><?php endif; ?>
  </div>

  <div class="addr">
    <?php if (!empty($c['company_name'])): ?><strong><?= e((string) $c['company_name']) ?></strong><br><?php endif; ?>
    <?= e($custName) ?><br>
    <?php if (!empty($billing['street'])): ?><?= e((string) $billing['street']) ?><br><?php endif; ?>
    <?php if (!empty($billing['zip']) || !empty($billing['city'])): ?><?= e(trim((string) ($billing['zip'] ?? '') . ' ' . (string) ($billing['city'] ?? ''))) ?><br><?php endif; ?>
  </div>

  <table>
    <thead>
      <tr><th style="width:30px">Pos</th><th>Beschreibung</th><th class="num">Menge</th><th class="num">Einzel</th><th class="num">Betrag</th></tr>
    </thead>
    <tbody>
      <?php foreach (($s['lines'] ?? []) as $l): ?>
      <tr>
        <td><?= e((string) $l['pos_no']) ?></td>
        <td><?= e((string) $l['description']) ?><?php if (!empty($l['sku'])): ?><br><span class="seller"><?= e((string) $l['sku']) ?></span><?php endif; ?></td>
        <td class="num"><?= e((string) $l['qty']) ?></td>
        <td class="num"><?= e(Money::format((int) $l['unit_cents'], $currency)) ?></td>
        <td class="num"><?= e(Money::format((int) $l['line_cents'], $currency)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="4" class="num">Netto</td><td class="num"><?= e(Money::format((int) ($totals['net_cents'] ?? 0), $currency)) ?></td></tr>
      <tr><td colspan="4" class="num">USt</td><td class="num"><?= e(Money::format((int) ($totals['tax_cents'] ?? 0), $currency)) ?></td></tr>
      <tr><td colspan="4" class="num tot"><?= $isCredit ? 'Gutschriftbetrag' : 'Gesamt' ?></td><td class="num tot"><?= e(Money::format((int) ($totals['gross_cents'] ?? 0), $currency)) ?></td></tr>
    </tfoot>
  </table>

  <div class="legend"><?= e((string) ($s['tax']['legend'] ?? '')) ?></div>
  <div class="foot">Maschinell erstellt und ohne Unterschrift gültig.</div>
</div>
</body>
</html>
