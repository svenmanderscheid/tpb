<?php
/** @var int $year */
/** @var int[] $years */
/** @var array<string,mixed> $report */
/** @var string $chartJson */

use Tpb\Core\Money;

$profit = (int) $report['profit_cents'];
?>
<h1>Finanzen <?= e((string) $year) ?></h1>

<form method="get" action="/admin/finanzen" class="toolbar">
    <label for="year">Jahr</label>
    <select id="year" name="year" data-autosubmit>
        <?php foreach ($years as $y): ?>
            <option value="<?= e((string) $y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= e((string) $y) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn secondary">Anzeigen</button>
    <a class="btn secondary" href="/admin/finanzen/buchungen?year=<?= e((string) $year) ?>">Buchungen</a>
    <a class="btn secondary" href="/admin/finanzen/gesellschafter">Gesellschafter</a>
</form>

<div class="kpis">
    <div class="kpi income">
        <div class="label">Einnahmen</div>
        <div class="value"><?= e(Money::format((int) $report['income_cents'])) ?></div>
    </div>
    <div class="kpi expense">
        <div class="label">Ausgaben</div>
        <div class="value"><?= e(Money::format((int) $report['expense_cents'])) ?></div>
    </div>
    <div class="kpi profit">
        <div class="label">Gewinn</div>
        <div class="value <?= $profit >= 0 ? 'pos' : 'neg' ?>"><?= e(Money::format($profit)) ?></div>
    </div>
    <div class="kpi">
        <div class="label">Kapitaleinlagen gesamt</div>
        <div class="value"><?= e(Money::format((int) $report['contributions_total_cents'])) ?></div>
    </div>
</div>

<div class="charts">
    <div class="chart">
        <h2>Einnahmen &amp; Ausgaben pro Monat</h2>
        <div id="chart-monthly"><p class="empty">Wird geladen …</p></div>
        <div class="legend">
            <span><i class="sw-income"></i> Einnahmen</span>
            <span><i class="sw-expense"></i> Ausgaben</span>
        </div>
    </div>
    <div class="chart">
        <h2>Ausgaben nach Kategorie</h2>
        <div id="chart-expense-cat"><p class="empty">Wird geladen …</p></div>
    </div>
</div>

<div class="card">
    <h2>Gewinnanteil &amp; Kapitaleinlagen je Gründer</h2>
    <?php if (empty($report['partners'])): ?>
        <p class="muted">Noch keine Gesellschafter angelegt. <a href="/admin/finanzen/gesellschafter">Jetzt anlegen</a>.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Gründer</th><th class="num">Anteil</th><th class="num">Gewinnanteil</th><th class="num">Kapitaleinlage</th></tr>
            </thead>
            <tbody>
            <?php foreach ($report['partners'] as $p): ?>
                <?php $share = (int) $p['share_bps']; $ps = (int) $p['profit_share_cents']; ?>
                <tr>
                    <td><?= e((string) $p['name']) ?></td>
                    <td class="num"><?= e(number_format($share / 100, 2, ',', '.')) ?> %</td>
                    <td class="num <?= $ps >= 0 ? 'pos' : 'neg' ?>"><?= e(Money::format($ps)) ?></td>
                    <td class="num"><?= e(Money::format((int) $p['contributed_cents'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php $shareTotal = (int) $report['share_bps_total']; ?>
        <?php if ($shareTotal !== 10000): ?>
            <p class="muted mt">Hinweis: Die Summe der Anteile beträgt <?= e(number_format($shareTotal / 100, 2, ',', '.')) ?> % (nicht 100 %). Gewinnanteile werden trotzdem je Anteil berechnet.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script type="application/json" id="finance-data"><?= $chartJson ?></script>
<script type="module" src="/assets/js/finance-charts.js"></script>
