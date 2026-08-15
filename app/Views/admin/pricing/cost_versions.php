<?php
/** @var array<int,array<string,mixed>> $versions */
/** @var int $nextVersion */
/** @var array{type:string,text:string}|null $flash */

use Tpb\Core\Csrf;
use Tpb\Core\Money;
?>
<h1>Kostenversionen</h1>
<div class="toolbar">
    <a class="btn secondary" href="/admin/preisbuecher">Preisbücher</a>
    <a class="btn secondary" href="/admin/katalog">Katalog</a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <div class="card">
        <h2>Neue Kostenversion (Entwurf)</h2>
        <p class="muted">Nächste Version: <strong>v<?= e((string) $nextVersion) ?></strong></p>
        <form method="post" action="/admin/kostenversionen">
            <?= Csrf::field() ?>
            <div class="field-row">
                <div class="field"><label>Lohnsatz (€/h)</label><input type="text" name="labor_rate" inputmode="decimal" required placeholder="35,00"></div>
                <div class="field"><label>Maschinensatz (€/h)</label><input type="text" name="machine_rate" inputmode="decimal" required placeholder="12,00"></div>
            </div>
            <div class="field-row">
                <div class="field"><label>Ausschuss (%)</label><input type="text" name="scrap" inputmode="decimal" placeholder="3"></div>
                <div class="field"><label>Zielmarge (%)</label><input type="text" name="margin" inputmode="decimal" placeholder="50"></div>
            </div>
            <button type="submit" class="btn">Anlegen</button>
        </form>
    </div>
    <div class="card">
        <h2>Vorhandene Kostenversionen</h2>
        <?php if (empty($versions)): ?>
            <p class="muted">Noch keine Kostenversionen.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Version</th><th>Status</th><th class="num">Lohn/h</th><th class="num">Maschine/h</th><th class="num">Ausschuss</th><th class="num">Marge</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($versions as $c): ?>
                    <tr>
                        <td>v<?= e((string) $c['version']) ?></td>
                        <td><span class="badge"><?= e((string) $c['status']) ?></span></td>
                        <td class="num"><?= e(Money::format((int) $c['labor_rate_cents_h'])) ?></td>
                        <td class="num"><?= e(Money::format((int) $c['machine_rate_cents_h'])) ?></td>
                        <td class="num"><?= e(number_format((int) $c['scrap_bps'] / 100, 2, ',', '.')) ?> %</td>
                        <td class="num"><?= e(number_format((int) $c['target_margin_bps'] / 100, 2, ',', '.')) ?> %</td>
                        <td><a href="/admin/kostenversion/<?= e((string) $c['version']) ?>">öffnen</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
