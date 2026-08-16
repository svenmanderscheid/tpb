<?php
/**
 * @var array<int,array<string,mixed>> $requests
 * @var array<int,array<string,mixed>> $quotes
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Csrf;
use Tpb\Core\Money;

$name = static fn (array $r): string => trim((string) ($r['company_name'] ?? '') !== ''
    ? (string) $r['company_name']
    : ((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? '')));
$statusLabels = ['DRAFT' => 'Entwurf', 'SENT' => 'Versendet', 'ACCEPTED' => 'Angenommen', 'DECLINED' => 'Abgelehnt', 'EXPIRED' => 'Abgelaufen', 'CANCELLED' => 'Storniert'];
?>
<h1>Angebote</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Offene Anfragen</h2>
    <?php if (empty($requests)): ?>
        <p class="muted">Keine offenen Anfragen.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Kunde</th><th>E-Mail</th><th class="num">Richtpreis</th><th>Eingegangen</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><?= e($name($r)) ?></td>
                        <td><?= e((string) $r['email']) ?></td>
                        <td class="num"><?= $r['total_cents'] !== null ? e(Money::format((int) $r['total_cents'])) : '–' ?></td>
                        <td><?= e((string) substr((string) $r['created_at'], 0, 10)) ?></td>
                        <td class="num">
                            <form class="inline-form" method="post" action="/admin/anfragen/<?= e((string) $r['public_id']) ?>/angebot">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn">Angebot erstellen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Angebote</h2>
    <?php if (empty($quotes)): ?>
        <p class="muted">Noch keine Angebote.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Nummer</th><th>Kunde</th><th>Status</th><th class="num">Summe</th><th>Gültig bis</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($quotes as $q): ?>
                    <tr>
                        <td><?= e((string) ($q['quote_number'] ?? '(Entwurf)')) ?></td>
                        <td><?= e($name($q)) ?></td>
                        <td><span class="status-badge <?= e(strtolower((string) $q['status'])) ?>"><?= e($statusLabels[(string) $q['status']] ?? (string) $q['status']) ?></span></td>
                        <td class="num"><?= e(Money::format((int) $q['total_cents'], (string) $q['currency'])) ?></td>
                        <td><?= e((string) ($q['valid_until'] ?? '–')) ?></td>
                        <td class="num"><a class="btn secondary" href="/admin/angebot/<?= e((string) $q['public_id']) ?>">Öffnen</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
