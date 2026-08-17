<?php
/**
 * @var array<string,mixed> $job
 * @var array<string,mixed>|false $order
 * @var array<string,mixed>|false $item
 * @var array<int,array<string,mixed>> $events
 * @var string $idem_key
 * @var int|null $last_print
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Csrf;

$status = (string) $job['status'];
$labels = ['BLOCKED' => 'Blockiert', 'READY' => 'Freigegeben', 'IN_PROGRESS' => 'In Arbeit', 'QUALITY_CHECK' => 'Qualität', 'REWORK' => 'Nacharbeit', 'DONE' => 'Fertig', 'SCRAPPED' => 'Ausschuss'];
$act = static fn (string $publicId, string $action, string $label, string $cls = 'btn') => sprintf(
    '<form class="inline-form" method="post" action="/admin/job/%s/aktion">%s<input type="hidden" name="action" value="%s"><button class="%s" type="submit">%s</button></form>',
    e($publicId), Csrf::field(), e($action), e($cls), e($label)
);
$pid = (string) $job['public_id'];
?>
<p><a href="/admin/produktion">← Produktion</a></p>
<h1>Job <?= e((string) $job['job_number']) ?>
    <span class="status-badge <?= e(strtolower($status)) ?>"><?= e($labels[$status] ?? $status) ?></span>
</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <div class="card">
        <h2>Ablauf</h2>
        <p class="muted">
            Auftrag: <?= $order !== false ? e((string) $order['order_number']) : '' ?> ·
            Position: <?= $item !== false ? e((string) $item['description']) : '' ?> ·
            Menge: <?= $item !== false ? e((string) $item['qty']) : '' ?> ·
            Route: <?= e((string) $job['route']) ?>
        </p>

        <div class="actions-row">
            <?php if ($status === 'BLOCKED'): ?>
                <form class="inline-form" method="post" action="/admin/job/<?= e($pid) ?>/freigeben"><?= Csrf::field() ?><button class="btn" type="submit">Freigeben (Gate prüfen)</button></form>
            <?php elseif ($status === 'READY'): ?>
                <?= $act($pid, 'start', 'Start') ?>
            <?php elseif ($status === 'IN_PROGRESS'): ?>
                <?= $act($pid, 'qc', 'Zur Qualitätsprüfung') ?>
                <?= $act($pid, 'scrap', 'Ausschuss (Job)', 'btn secondary') ?>
            <?php elseif ($status === 'QUALITY_CHECK'): ?>
                <?= $act($pid, 'done', 'Fertig') ?>
                <?= $act($pid, 'rework', 'Nacharbeit', 'btn secondary') ?>
            <?php elseif ($status === 'REWORK'): ?>
                <?= $act($pid, 'resume', 'Weiter bearbeiten') ?>
            <?php else: ?>
                <p class="muted">Job ist abgeschlossen.</p>
            <?php endif; ?>
        </div>

        <?php if ($status === 'IN_PROGRESS'): ?>
            <h2 class="mt">Menge erfassen</h2>
            <form method="post" action="/admin/job/<?= e($pid) ?>/menge">
                <?= Csrf::field() ?>
                <input type="hidden" name="idem_key" value="<?= e($idem_key) ?>">
                <div class="field-row">
                    <div class="field">
                        <label for="type">Art</label>
                        <select id="type" name="type"><option value="qty_good">Gutmenge</option><option value="scrap">Ausschuss</option></select>
                    </div>
                    <div class="field"><label for="qty">Menge</label><input type="number" id="qty" name="qty" min="1" value="1"></div>
                </div>
                <button type="submit" class="btn secondary">Erfassen</button>
            </form>
        <?php endif; ?>
    </div>

    <aside>
        <div class="card">
            <h2>Termin &amp; Kapazität</h2>
            <p class="muted">Geplant: <?= $job['planned_min'] !== null ? (int) $job['planned_min'] . ' min' : '– (kein Satz)' ?></p>
            <form method="post" action="/admin/job/<?= e($pid) ?>/termin">
                <?= Csrf::field() ?>
                <div class="field"><label for="due_date">Fälligkeitstermin</label><input type="date" id="due_date" name="due_date" value="<?= e((string) ($job['due_date'] ?? '')) ?>"></div>
                <button type="submit" class="btn secondary">Termin setzen</button>
            </form>
            <p class="muted mt"><a href="/admin/kapazitaet">Wochenkapazität ansehen →</a></p>
        </div>

        <div class="card">
            <h2>Etikett</h2>
            <form method="post" action="/admin/job/<?= e($pid) ?>/etikett"><?= Csrf::field() ?><button class="btn" type="submit">Etikett rendern</button></form>
            <?php if ($last_print !== null): ?>
                <form method="post" action="/admin/job/<?= e($pid) ?>/etikett/neu" class="mt">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="reprint_of" value="<?= e((string) $last_print) ?>">
                    <div class="field"><label for="reason">Grund für Neudruck</label><input type="text" id="reason" name="reason"></div>
                    <button type="submit" class="btn secondary">Neu drucken</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Ereignisse</h2>
            <?php if (empty($events)): ?>
                <p class="muted">Noch keine Ereignisse.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Typ</th><th class="num">Menge</th><th>Zeit</th></tr></thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr><td><?= e((string) $ev['event_type']) ?></td><td class="num"><?= e((string) ($ev['qty'] ?? '')) ?></td><td><?= e((string) substr((string) $ev['occurred_at'], 0, 16)) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </aside>
</div>
