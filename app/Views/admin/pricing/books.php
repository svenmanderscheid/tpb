<?php
/** @var array<int,array<string,mixed>> $books */
/** @var int $nextVersion */
/** @var array{type:string,text:string}|null $flash */

use Tpb\Core\Csrf;
?>
<h1>Preisbücher</h1>
<div class="toolbar">
    <a class="btn secondary" href="/admin/katalog">Katalog</a>
    <a class="btn secondary" href="/admin/kostenversionen">Kostenversionen</a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <div class="card">
        <h2>Neues Preisbuch (Entwurf)</h2>
        <p class="muted">Nächste Version: <strong>v<?= e((string) $nextVersion) ?></strong></p>
        <form method="post" action="/admin/preisbuecher" class="field-row">
            <?= Csrf::field() ?>
            <div class="field"><label>Währung</label><input type="text" name="currency" value="EUR" maxlength="3"></div>
            <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Anlegen</button></div>
        </form>
    </div>
    <div class="card">
        <h2>Vorhandene Preisbücher</h2>
        <?php if (empty($books)): ?>
            <p class="muted">Noch keine Preisbücher.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Version</th><th>Währung</th><th>Status</th><th>Veröffentlicht</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($books as $b): ?>
                    <tr>
                        <td>v<?= e((string) $b['version']) ?></td>
                        <td><?= e((string) $b['currency']) ?></td>
                        <td><span class="badge"><?= e((string) $b['status']) ?></span></td>
                        <td class="muted"><?= e((string) ($b['published_at'] ?? '–')) ?></td>
                        <td><a href="/admin/preisbuch/<?= e((string) $b['version']) ?>">öffnen</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
