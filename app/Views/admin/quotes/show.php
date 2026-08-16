<?php
/**
 * @var array<string,mixed> $quote
 * @var array<string,mixed>|false $customer
 * @var array<int,array<string,mixed>> $items
 * @var string|null $pdf_public
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Csrf;
use Tpb\Core\Money;

$status = (string) $quote['status'];
$currency = (string) $quote['currency'];
$statusLabels = ['DRAFT' => 'Entwurf', 'SENT' => 'Versendet', 'ACCEPTED' => 'Angenommen', 'DECLINED' => 'Abgelehnt', 'EXPIRED' => 'Abgelaufen', 'CANCELLED' => 'Storniert'];
?>
<p><a href="/admin/anfragen">← Übersicht</a></p>
<h1>Angebot <?= e((string) ($quote['quote_number'] ?? '(Entwurf)')) ?>
    <span class="status-badge <?= e(strtolower($status)) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <div class="card">
        <h2>Positionen</h2>
        <table>
            <thead><tr><th>Pos</th><th>Beschreibung</th><th class="num">Menge</th><th class="num">Einzel</th><th class="num">Gesamt</th></tr></thead>
            <tbody>
                <?php foreach ($items as $l): ?>
                    <tr>
                        <td><?= e((string) $l['pos_no']) ?></td>
                        <td><?= e((string) $l['description']) ?></td>
                        <td class="num"><?= e((string) $l['qty']) ?></td>
                        <td class="num"><?= e(Money::format((int) $l['unit_cents'], $currency)) ?></td>
                        <td class="num"><?= e(Money::format((int) $l['line_cents'], $currency)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="4" class="num"><strong>Gesamt</strong></td><td class="num"><strong><?= e(Money::format((int) $quote['total_cents'], $currency)) ?></strong></td></tr></tfoot>
        </table>
    </div>

    <aside>
        <div class="card">
            <h2>Kunde</h2>
            <?php if ($customer !== false): ?>
                <?php if (!empty($customer['company_name'])): ?><p><strong><?= e((string) $customer['company_name']) ?></strong></p><?php endif; ?>
                <p><?= e(trim((string) $customer['first_name'] . ' ' . (string) $customer['last_name'])) ?><br>
                   <?= e((string) $customer['email']) ?><?php if (!empty($customer['phone'])): ?><br><?= e((string) $customer['phone']) ?><?php endif; ?></p>
            <?php endif; ?>
            <p class="muted">Gültig bis: <?= e((string) ($quote['valid_until'] ?? '–')) ?></p>
        </div>

        <div class="card">
            <h2>Aktionen</h2>
            <?php if ($status === 'DRAFT'): ?>
                <form method="post" action="/admin/angebot/<?= e((string) $quote['public_id']) ?>/versenden">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn">Angebot versenden</button>
                </form>
                <p class="muted mt">Vergibt die Q-Nummer, erzeugt das PDF und die Kundenmail (Outbox).</p>
            <?php else: ?>
                <p class="muted">Versendet am <?= e((string) substr((string) ($quote['sent_at'] ?? ''), 0, 16)) ?>.</p>
            <?php endif; ?>

            <?php if ($pdf_public !== null): ?>
                <p class="mt"><a class="btn secondary" href="/files/<?= e((string) $pdf_public) ?>">Angebots-PDF</a></p>
            <?php endif; ?>
        </div>
    </aside>
</div>
