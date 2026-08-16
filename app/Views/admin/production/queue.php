<?php
/**
 * @var array<int,array<string,mixed>> $jobs
 * @var array{type:string,text:string}|null $flash
 */
$labels = ['BLOCKED' => 'Blockiert', 'READY' => 'Freigegeben', 'IN_PROGRESS' => 'In Arbeit', 'QUALITY_CHECK' => 'Qualität', 'REWORK' => 'Nacharbeit', 'DONE' => 'Fertig', 'SCRAPPED' => 'Ausschuss'];
?>
<h1>Produktion</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <?php if (empty($jobs)): ?>
        <p class="muted">Keine Jobs in der Warteschlange.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Job</th><th>Auftrag</th><th>Position</th><th class="num">Menge</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($jobs as $j): ?>
                    <tr>
                        <td><?= e((string) $j['job_number']) ?></td>
                        <td><a href="/admin/auftrag/<?= e((string) $j['order_public_id']) ?>"><?= e((string) $j['order_number']) ?></a></td>
                        <td><?= e((string) $j['description']) ?></td>
                        <td class="num"><?= e((string) $j['qty']) ?></td>
                        <td><span class="status-badge <?= e(strtolower((string) $j['status'])) ?>"><?= e($labels[(string) $j['status']] ?? (string) $j['status']) ?></span></td>
                        <td class="num"><a class="btn secondary" href="/admin/job/<?= e((string) $j['public_id']) ?>">Öffnen</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
