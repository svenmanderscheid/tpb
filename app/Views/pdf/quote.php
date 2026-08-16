<?php
declare(strict_types=1);
/**
 * Angebots-PDF (§9). Rendert AUSSCHLIESSLICH aus dem eingefrorenen Snapshot.
 * Alle dynamischen Werte über e(); keine externen Ressourcen (dompdf-Härtung §9).
 *
 * @var array<string,mixed> $quote
 * @var string $number
 * @var array<string,mixed> $snapshot
 */
use Tpb\Core\Money;

$c = $snapshot['customer'] ?? [];
$currency = (string) ($snapshot['currency'] ?? 'EUR');
$lines = $snapshot['lines'] ?? [];
$totals = $snapshot['totals'] ?? [];
$validUntil = $quote['valid_until'] ?? null;

$customerName = trim((string) ($c['first_name'] ?? '') . ' ' . (string) ($c['last_name'] ?? ''));
$company = (string) ($c['company_name'] ?? '');
$billing = $c['billing'] ?? [];
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { font-size: 11px; color: #1a1a1a; margin: 0; }
  .wrap { padding: 6px 4px; }
  .seller { color: #555; font-size: 10px; }
  .placeholder { color: #b00; font-size: 9px; }
  h1 { font-size: 18px; margin: 14px 0 2px; }
  .meta { margin: 2px 0 14px; color: #444; }
  .addr { margin: 10px 0 18px; }
  table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  th, td { padding: 5px 6px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
  th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
  td.num, th.num { text-align: right; white-space: nowrap; }
  tfoot td { border-bottom: none; }
  .tot { font-size: 14px; font-weight: bold; }
  .foot { margin-top: 24px; color: #555; font-size: 10px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="seller">
    The Printing Brothers · Luxembourg<br>
    <span class="placeholder">[Platzhalter – vollständige Verkäuferangaben (Adresse, Autorisation, USt-Id) ausstehend, docs/OFFENE-FRAGEN.md]</span>
  </div>

  <h1>Angebot <?= e($number) ?></h1>
  <div class="meta">
    Datum: <?= e((string) substr((string) ($quote['sent_at'] ?? $quote['created_at'] ?? ''), 0, 10)) ?>
    <?php if ($validUntil !== null): ?> · Gültig bis: <?= e((string) $validUntil) ?><?php endif; ?>
  </div>

  <div class="addr">
    <?php if ($company !== ''): ?><strong><?= e($company) ?></strong><br><?php endif; ?>
    <?= e($customerName) ?><br>
    <?php if (!empty($billing['street'])): ?><?= e((string) $billing['street']) ?><br><?php endif; ?>
    <?php if (!empty($billing['zip']) || !empty($billing['city'])): ?><?= e(trim((string) ($billing['zip'] ?? '') . ' ' . (string) ($billing['city'] ?? ''))) ?><br><?php endif; ?>
    <?php if (!empty($c['email'])): ?><?= e((string) $c['email']) ?><?php endif; ?>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:32px">Pos</th>
        <th>Beschreibung</th>
        <th class="num">Menge</th>
        <th class="num">Einzel</th>
        <th class="num">Gesamt</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $l): ?>
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
      <?php if ((int) ($totals['express_cents'] ?? 0) > 0): ?>
      <tr><td colspan="4" class="num">Zwischensumme</td><td class="num"><?= e(Money::format((int) $totals['subtotal_cents'], $currency)) ?></td></tr>
      <?php endif; ?>
      <tr><td colspan="4" class="num tot">Gesamt</td><td class="num tot"><?= e(Money::format((int) ($totals['total_cents'] ?? 0), $currency)) ?></td></tr>
    </tfoot>
  </table>

  <div class="foot">
    Alle Preise verstehen sich gemäß der aktuell gültigen Steuerregelung.
    <span class="placeholder">[Platzhalter – Steuerlegende/AGB-Hinweis, juristische Fassung ausstehend]</span><br>
    Dieses Angebot wurde maschinell erstellt und ist ohne Unterschrift gültig.
  </div>
</div>
</body>
</html>
