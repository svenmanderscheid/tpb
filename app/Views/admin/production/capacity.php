<?php
/**
 * @var array<int,array<string,mixed>> $weeks
 * @var int $capacity
 * @var array{type:string,text:string}|null $flash
 */
$hours = static fn (int $min): string => number_format($min / 60, 1, ',', '') . ' h';
?>
<h1>Kapazität</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <p class="muted">Wochenkapazität: <strong><?= e($hours($capacity)) ?></strong> (<?= (int) $capacity ?> min). Ampel: grün &lt; 80 %, gelb 80–100 %, rot &gt; 100 %. Kein Auto-Block – nur Hinweis.</p>
    <?php if (empty($weeks)): ?>
        <p class="muted">Keine terminierten Jobs. Setze in der Job-Ansicht einen Fälligkeitstermin.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Woche ab</th><th class="num">Jobs</th><th class="num">Geplant</th><th class="num">Auslastung</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($weeks as $w): $ratio = (int) $w['ratio_bp'] / 100; ?>
                    <tr>
                        <td><?= e((string) $w['week_start']) ?></td>
                        <td class="num"><?= (int) $w['jobs'] ?></td>
                        <td class="num"><?= e($hours((int) $w['planned_min'])) ?></td>
                        <td class="num"><?= number_format($ratio, 0, ',', '') ?> %</td>
                        <td><span class="status-badge cap-<?= e((string) $w['light']) ?>"><?= $w['light'] === 'red' ? 'überlastet' : ($w['light'] === 'yellow' ? 'eng' : 'ok') ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
