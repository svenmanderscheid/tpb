<?php
/**
 * @var array<int,array<string,mixed>> $orders
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Money;

$name = static fn (array $r): string => (string) ($r['company_name'] ?? '') !== ''
    ? (string) $r['company_name']
    : trim((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? ''));
$artLabels = ['MISSING' => 'Artwork fehlt', 'UPLOADED' => 'Artwork hochgeladen', 'PREPRESS_REVIEW' => 'Prepress', 'PROOF_SENT' => 'Proof versendet', 'CHANGES_REQUESTED' => 'Änderung gewünscht', 'APPROVED' => 'Freigegeben', 'LOCKED' => 'Freigegeben (gesperrt)'];
?>
<h1>Aufträge</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <?php if (empty($orders)): ?>
        <p class="muted">Noch keine Aufträge.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Nummer</th><th>Kunde</th><th>Artwork</th><th class="num">Summe</th><th>Datum</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><?= e((string) $o['order_number']) ?></td>
                        <td><?= e($name($o)) ?></td>
                        <td><span class="status-badge <?= e(strtolower((string) $o['cur_artwork'])) ?>"><?= e($artLabels[(string) $o['cur_artwork']] ?? (string) $o['cur_artwork']) ?></span></td>
                        <td class="num"><?= e(Money::format((int) $o['total_cents'], (string) $o['currency'])) ?></td>
                        <td><?= e((string) substr((string) $o['ordered_at'], 0, 10)) ?></td>
                        <td class="num"><a class="btn secondary" href="/admin/auftrag/<?= e((string) $o['public_id']) ?>">Öffnen</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
