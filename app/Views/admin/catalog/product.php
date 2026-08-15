<?php
/** @var array<string,mixed> $product */
/** @var array<int,array<string,mixed>> $variants */
/** @var array<int,array<string,mixed>> $placements */
/** @var string[] $sides */
/** @var array{type:string,text:string}|null $flash */

use Tpb\Core\Csrf;

$pid = (string) $product['public_id'];
?>
<h1>Produkt: <?= e((string) $product['name']) ?></h1>
<div class="toolbar"><a class="btn secondary" href="/admin/katalog">← Alle Produkte</a></div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Stammdaten</h2>
    <form method="post" action="/admin/katalog/produkt/<?= e($pid) ?>">
        <?= Csrf::field() ?>
        <div class="field-row">
            <div class="field">
                <label>Name</label>
                <input type="text" name="name" value="<?= e((string) $product['name']) ?>" required maxlength="160">
            </div>
            <div class="field">
                <label>SKU-Wurzel</label>
                <input type="text" name="sku_root" value="<?= e((string) $product['sku_root']) ?>" required maxlength="48">
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label>Typ</label>
                <select name="product_type">
                    <option value="configurable" <?= $product['product_type'] === 'configurable' ? 'selected' : '' ?>>configurable</option>
                    <option value="standard" <?= $product['product_type'] === 'standard' ? 'selected' : '' ?>>standard</option>
                </select>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['draft', 'active', 'archived'] as $st): ?>
                        <option value="<?= $st ?>" <?= $product['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Sortierung</label>
                <input type="number" name="sort" value="<?= e((string) $product['sort']) ?>">
            </div>
        </div>
        <div class="field">
            <label>Beschreibung (Markdown, optional)</label>
            <input type="text" name="description_md" value="<?= e((string) ($product['description_md'] ?? '')) ?>" maxlength="255">
        </div>
        <button type="submit" class="btn">Speichern</button>
    </form>
</div>

<div class="card">
    <h2>Varianten</h2>
    <?php if (!empty($variants)): ?>
        <table>
            <thead><tr><th>SKU</th><th>Farbe</th><th>Größe</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($variants as $v): ?>
                <tr>
                    <td><?= e((string) $v['sku']) ?></td>
                    <td><?= e(trim((string) ($v['color_name'] ?? '') . ' ' . (string) ($v['color_code'] ?? ''))) ?></td>
                    <td><?= e((string) ($v['size'] ?? '')) ?></td>
                    <td><span class="badge"><?= e((string) $v['status']) ?></span></td>
                    <td>
                        <form method="post" action="/admin/katalog/produkt/<?= e($pid) ?>/varianten/loeschen" class="inline-form" data-confirm="Variante löschen?">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $v['id']) ?>">
                            <button type="submit" class="link-danger">löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">Noch keine Varianten.</p>
    <?php endif; ?>

    <h2 class="mt">Variante hinzufügen</h2>
    <form method="post" action="/admin/katalog/produkt/<?= e($pid) ?>/varianten" class="field-row">
        <?= Csrf::field() ?>
        <div class="field"><label>SKU</label><input type="text" name="sku" required maxlength="64"></div>
        <div class="field"><label>Farbname</label><input type="text" name="color_name" maxlength="48"></div>
        <div class="field"><label>Farbcode</label><input type="text" name="color_code" maxlength="24"></div>
        <div class="field"><label>Größe</label><input type="text" name="size" maxlength="24"></div>
        <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Hinzufügen</button></div>
    </form>
</div>

<div class="card">
    <h2>Druckpositionen</h2>
    <?php if (!empty($placements)): ?>
        <table>
            <thead><tr><th>Code</th><th>Name</th><th>Seite</th><th class="num">max B×H (mm)</th><th>Preset</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($placements as $pl): ?>
                <tr>
                    <td><?= e((string) $pl['code']) ?></td>
                    <td><?= e((string) $pl['name']) ?></td>
                    <td><span class="badge"><?= e((string) $pl['side']) ?></span></td>
                    <td class="num"><?= e((string) ($pl['max_w_mm'] ?? '–')) ?> × <?= e((string) ($pl['max_h_mm'] ?? '–')) ?></td>
                    <td><?= ((int) $pl['is_preset']) === 1 ? 'ja' : '–' ?></td>
                    <td>
                        <form method="post" action="/admin/katalog/produkt/<?= e($pid) ?>/placements/loeschen" class="inline-form" data-confirm="Position löschen?">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $pl['id']) ?>">
                            <button type="submit" class="link-danger">löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">Noch keine Positionen.</p>
    <?php endif; ?>

    <h2 class="mt">Position hinzufügen</h2>
    <form method="post" action="/admin/katalog/produkt/<?= e($pid) ?>/placements" class="field-row">
        <?= Csrf::field() ?>
        <div class="field"><label>Code</label><input type="text" name="code" required maxlength="32" placeholder="brust"></div>
        <div class="field"><label>Name</label><input type="text" name="name" required maxlength="80" placeholder="Brust links"></div>
        <div class="field">
            <label>Seite</label>
            <select name="side"><?php foreach ($sides as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>max B (mm)</label><input type="text" name="max_w_mm" inputmode="decimal"></div>
        <div class="field"><label>max H (mm)</label><input type="text" name="max_h_mm" inputmode="decimal"></div>
        <div class="field">
            <label>Preset</label>
            <select name="is_preset"><option value="0">nein</option><option value="1">ja</option></select>
        </div>
        <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Hinzufügen</button></div>
    </form>
</div>
