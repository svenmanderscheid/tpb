<?php
/**
 * @var array<string,mixed> $invoice
 * @var array<int,array<string,mixed>> $lines
 * @var array<int,array<string,mixed>> $credits
 * @var string|null $pdf_public
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Authz;
use Tpb\Core\Csrf;
use Tpb\Core\Money;

$inv = $invoice;
$isCredit = (string) $inv['doc_type'] === 'credit_note';
$currency = (string) $inv['currency'];
$status = (string) $inv['status'];
$statusLabels = ['DRAFT' => 'Entwurf', 'ISSUED' => 'Ausgestellt', 'SENT' => 'Versendet', 'PARTIALLY_CREDITED' => 'Teilw. gutgeschrieben', 'FULLY_CREDITED' => 'Gutgeschrieben'];
?>
<p><a href="/admin/rechnungen">← Rechnungen</a></p>
<h1><?= $isCredit ? 'Gutschrift' : 'Rechnung' ?> <?= e((string) ($inv['invoice_number'] ?? '(Entwurf)')) ?>
    <span class="status-badge <?= e(strtolower($status)) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <div class="card">
        <table>
            <thead><tr><th>Pos</th><th>Beschreibung</th><th class="num">Menge</th><th class="num">Einzel</th><th class="num">Betrag</th></tr></thead>
            <tbody>
                <?php foreach ($lines as $l): ?>
                    <tr>
                        <td><?= e((string) $l['pos_no']) ?></td>
                        <td><?= e((string) $l['description']) ?></td>
                        <td class="num"><?= e((string) $l['qty']) ?></td>
                        <td class="num"><?= e(Money::format((int) $l['unit_cents'], $currency)) ?></td>
                        <td class="num"><?= e(Money::format((int) $l['line_cents'], $currency)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="num">Netto</td><td class="num"><?= e(Money::format((int) $inv['net_cents'], $currency)) ?></td></tr>
                <tr><td colspan="4" class="num">USt</td><td class="num"><?= e(Money::format((int) $inv['tax_cents'], $currency)) ?></td></tr>
                <tr><td colspan="4" class="num"><strong>Gesamt</strong></td><td class="num"><strong><?= e(Money::format((int) $inv['gross_cents'], $currency)) ?></strong></td></tr>
            </tfoot>
        </table>
        <?php if (!empty($inv['tax_legend'])): ?><p class="muted mt"><?= e((string) $inv['tax_legend']) ?></p><?php endif; ?>
    </div>

    <aside>
        <div class="card">
            <h2>Aktionen</h2>
            <?php if ($status === 'DRAFT' && Authz::can('tpb_issue_invoices')): ?>
                <form method="post" action="/admin/rechnung/<?= e((string) $inv['public_id']) ?>/ausstellen"><?= Csrf::field() ?><button type="submit" class="btn">Ausstellen (unumkehrbar)</button></form>
                <p class="muted mt">Zieht die Rechnungsnummer, friert Snapshot + PDF/JSON ein.</p>
            <?php elseif ($status !== 'DRAFT'): ?>
                <?php if ($pdf_public !== null): ?><p><a class="btn secondary" href="/files/<?= e((string) $pdf_public) ?>">PDF</a></p><?php endif; ?>
                <?php if (!$isCredit && $status === 'ISSUED' && Authz::can('tpb_issue_invoices')): ?>
                    <form method="post" action="/admin/rechnung/<?= e((string) $inv['public_id']) ?>/gutschrift" class="mt"><?= Csrf::field() ?><button type="submit" class="btn secondary">Vollständige Gutschrift</button></form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($credits)): ?>
        <div class="card">
            <h2>Gutschriften</h2>
            <ul>
                <?php foreach ($credits as $cn): ?>
                    <li><a href="/admin/rechnung/<?= e((string) $cn['public_id']) ?>"><?= e((string) ($cn['invoice_number'] ?? 'Entwurf')) ?></a> · <?= e(Money::format((int) $cn['gross_cents'], $currency)) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </aside>
</div>
