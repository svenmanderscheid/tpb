<?php
/** @var array<int,array<string,mixed>> $assets */
/** @var array{type:string,text:string}|null $flash */

use Tpb\Core\Csrf;
?>
<h1>Assets</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Datei hochladen</h2>
    <p class="muted">Erlaubt: SVG, PDF, PNG, JPG. Uploads durchlaufen Preflight und Quarantäne (§8).</p>
    <form method="post" action="/admin/assets" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <div class="field">
            <label for="file">Datei</label>
            <input type="file" id="file" name="file" accept=".svg,.pdf,.png,.jpg,.jpeg" required>
        </div>
        <button type="submit" class="btn">Hochladen</button>
    </form>
</div>

<div class="card">
    <h2>Letzte Assets</h2>
    <?php if (empty($assets)): ?>
        <p class="muted">Noch keine Assets vorhanden.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Datei</th><th>MIME</th><th>Größe</th><th>Status</th><th>Download</th></tr>
            </thead>
            <tbody>
            <?php foreach ($assets as $a): ?>
                <tr>
                    <td><?= e($a['original_name']) ?></td>
                    <td class="muted"><?= e($a['mime']) ?></td>
                    <td><?= e(number_format((int) $a['size_bytes'] / 1024, 1, ',', '.')) ?> KB</td>
                    <td>
                        <?php $st = (string) $a['security_status']; ?>
                        <span class="badge <?= $st === 'quarantine' ? 'quarantine' : '' ?>"><?= e($st) ?></span>
                    </td>
                    <td><a href="/files/<?= e($a['public_id']) ?>">herunterladen</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
