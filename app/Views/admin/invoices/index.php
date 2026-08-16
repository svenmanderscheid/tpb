<?php
/**
 * @var array<int,array<string,mixed>> $invoices
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Money;

$docLabels = ['invoice' => 'Rechnung', 'credit_note' => 'Gutschrift'];
$statusLabels = ['DRAFT' => 'Entwurf', 'ISSUED' => 'Ausgestellt', 'SENT' => 'Versendet', 'PARTIALLY_CREDITED' => 'Teilw. gutgeschrieben', 'FULLY_CREDITED' => 'Gutgeschrieben'];
?>
<h1>Rechnungen</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <?php if (empty($invoices)): ?>
        <p class="muted">Noch keine Rechnungen.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Nummer</th><th>Typ</th><th>Auftrag</th><th>Status</th><th class="num">Betrag</th><th>Datum</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td><?= e((string) ($i['invoice_number'] ?? '(Entwurf)')) ?></td>
                        <td><?= e($docLabels[(string) $i['doc_type']] ?? (string) $i['doc_type']) ?></td>
                        <td><?php if (!empty($i['order_number'])): ?><a href="/admin/auftrag/<?= e((string) $i['order_public_id']) ?>"><?= e((string) $i['order_number']) ?></a><?php endif; ?></td>
                        <td><span class="status-badge <?= e(strtolower((string) $i['status'])) ?>"><?= e($statusLabels[(string) $i['status']] ?? (string) $i['status']) ?></span></td>
                        <td class="num"><?= e(Money::format((int) $i['gross_cents'], (string) $i['currency'])) ?></td>
                        <td><?= e((string) substr((string) ($i['issued_at'] ?? ''), 0, 10)) ?></td>
                        <td class="num"><a class="btn secondary" href="/admin/rechnung/<?= e((string) $i['public_id']) ?>">Öffnen</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
