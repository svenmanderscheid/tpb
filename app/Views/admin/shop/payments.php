<?php
/**
 * @var array<int,array<string,mixed>> $intents
 * @var array<int,array<string,mixed>> $events
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Money;
?>
<h1>Shop-Zahlungen</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Zahlungsabsichten</h2>
    <?php if (empty($intents)): ?>
        <p class="muted">Noch keine Zahlungsabsichten.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Auftrag</th><th>Anbieter</th><th>Referenz</th><th class="num">Betrag</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($intents as $i): ?>
                    <tr>
                        <td><a href="/admin/auftrag/<?= e((string) $i['order_public_id']) ?>"><?= e((string) $i['order_number']) ?></a></td>
                        <td><?= e((string) $i['provider']) ?></td>
                        <td><?= e((string) ($i['provider_ref'] ?? '')) ?></td>
                        <td class="num"><?= e(Money::format((int) $i['amount_cents'], (string) $i['currency'])) ?></td>
                        <td><span class="status-badge"><?= e((string) $i['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Webhook-Events</h2>
    <?php if (empty($events)): ?>
        <p class="muted">Noch keine Events.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Anbieter</th><th>Event</th><th>Typ</th><th>Signatur</th><th>Status</th><th>Empfangen</th></tr></thead>
            <tbody>
                <?php foreach ($events as $ev): ?>
                    <tr>
                        <td><?= e((string) $ev['provider']) ?></td>
                        <td><?= e((string) $ev['event_ref']) ?></td>
                        <td><?= e((string) $ev['event_type']) ?></td>
                        <td><?= ((int) $ev['signature_valid']) === 1 ? 'gültig' : '<span class="neg">ungültig</span>' ?></td>
                        <td><?= e((string) $ev['process_status']) ?></td>
                        <td><?= e((string) substr((string) $ev['received_at'], 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
