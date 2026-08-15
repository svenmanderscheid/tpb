<?php
/** @var array<int,array<string,mixed>> $products */
/** @var array{type:string,text:string}|null $flash */

use Tpb\Core\Csrf;
?>
<h1>Katalog</h1>

<div class="toolbar">
    <a class="btn secondary" href="/admin/techniken">Techniken</a>
    <a class="btn secondary" href="/admin/preisbuecher">Preisbücher</a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <div class="card">
        <h2>Neues Produkt</h2>
        <form method="post" action="/admin/katalog">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required maxlength="160">
            </div>
            <div class="field">
                <label for="sku_root">SKU-Wurzel</label>
                <input type="text" id="sku_root" name="sku_root" required maxlength="48" placeholder="z. B. TSH-COT">
            </div>
            <div class="field">
                <label for="product_type">Typ</label>
                <select id="product_type" name="product_type">
                    <option value="configurable">configurable (Konfigurator)</option>
                    <option value="standard">standard (Hausdesign)</option>
                </select>
            </div>
            <button type="submit" class="btn">Anlegen</button>
        </form>
    </div>

    <div class="card">
        <h2>Produkte</h2>
        <?php if (empty($products)): ?>
            <p class="muted">Noch keine Produkte.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Name</th><th>SKU-Wurzel</th><th>Typ</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= e((string) $p['name']) ?></td>
                        <td class="muted"><?= e((string) $p['sku_root']) ?></td>
                        <td><span class="badge"><?= e((string) $p['product_type']) ?></span></td>
                        <td><span class="badge"><?= e((string) $p['status']) ?></span></td>
                        <td><a href="/admin/katalog/produkt/<?= e((string) $p['public_id']) ?>">bearbeiten</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
