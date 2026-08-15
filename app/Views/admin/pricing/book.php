<?php
/** @var array<string,mixed> $book */
/** @var array<int,array<string,mixed>> $tiers */
/** @var array<int,array<string,mixed>> $params */
/** @var array<int,array<string,mixed>> $products */
/** @var bool $isDraft */
/** @var array{type:string,text:string}|null $flash */

use Tpb\Core\Csrf;
use Tpb\Core\Money;

$v = (string) $book['version'];
?>
<h1>Preisbuch v<?= e($v) ?> <span class="badge"><?= e((string) $book['status']) ?></span></h1>
<div class="toolbar">
    <a class="btn secondary" href="/admin/preisbuecher">← Alle Preisbücher</a>
    <?php if ($isDraft): ?>
        <form method="post" action="/admin/preisbuch/<?= e($v) ?>/publish" class="inline-form" data-confirm="Preisbuch veröffentlichen? Danach unveränderlich.">
            <?= Csrf::field() ?><button type="submit" class="btn">Veröffentlichen</button>
        </form>
    <?php elseif ($book['status'] === 'published'): ?>
        <form method="post" action="/admin/preisbuch/<?= e($v) ?>/retire" class="inline-form" data-confirm="Preisbuch stilllegen?">
            <?= Csrf::field() ?><button type="submit" class="btn secondary">Stilllegen</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<?php if (!$isDraft): ?>
    <div class="alert ok">Dieses Preisbuch ist <strong><?= e((string) $book['status']) ?></strong> – Staffeln und Parameter sind unveränderlich (§6.2).</div>
<?php endif; ?>

<div class="card">
    <h2>Staffelpreise</h2>
    <?php if (empty($tiers)): ?>
        <p class="muted">Noch keine Staffeln.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Produkt</th><th class="num">ab Menge</th><th class="num">bis Menge</th><th class="num">Stückpreis</th><?php if ($isDraft): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($tiers as $t): ?>
                <tr>
                    <td><?= e((string) $t['product_name']) ?></td>
                    <td class="num"><?= e((string) $t['qty_from']) ?></td>
                    <td class="num"><?= $t['qty_to'] === null ? '∞' : e((string) $t['qty_to']) ?></td>
                    <td class="num"><?= e(Money::format((int) $t['unit_cents'])) ?></td>
                    <?php if ($isDraft): ?>
                        <td>
                            <form method="post" action="/admin/preisbuch/<?= e($v) ?>/tier/loeschen" class="inline-form" data-confirm="Staffel löschen?">
                                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) $t['id']) ?>">
                                <button type="submit" class="link-danger">löschen</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($isDraft): ?>
        <h2 class="mt">Staffel hinzufügen</h2>
        <form method="post" action="/admin/preisbuch/<?= e($v) ?>/tier" class="field-row">
            <?= Csrf::field() ?>
            <div class="field">
                <label>Produkt</label>
                <select name="product_id" required>
                    <option value="">– wählen –</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= e((string) $p['id']) ?>"><?= e((string) $p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>ab Menge</label><input type="number" name="qty_from" min="1" required></div>
            <div class="field"><label>bis Menge (leer = ∞)</label><input type="number" name="qty_to" min="1"></div>
            <div class="field"><label>Stückpreis (€)</label><input type="text" name="unit_price" inputmode="decimal" required placeholder="0,00"></div>
            <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Hinzufügen</button></div>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Parameter</h2>
    <p class="muted">Werte roh je Schlüssel: Cents (…_CENTS), Basispunkte (…_BPS) oder Menge (…_QTY).</p>
    <?php if (empty($params)): ?>
        <p class="muted">Noch keine Parameter.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Schlüssel</th><th class="num">Wert</th><th>Notiz</th><?php if ($isDraft): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($params as $pr): ?>
                <tr>
                    <td><?= e((string) $pr['param_key']) ?></td>
                    <td class="num"><?= e((string) $pr['value_int']) ?></td>
                    <td class="muted"><?= e((string) ($pr['note'] ?? '')) ?></td>
                    <?php if ($isDraft): ?>
                        <td>
                            <form method="post" action="/admin/preisbuch/<?= e($v) ?>/param/loeschen" class="inline-form" data-confirm="Parameter löschen?">
                                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) $pr['id']) ?>">
                                <button type="submit" class="link-danger">löschen</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($isDraft): ?>
        <h2 class="mt">Parameter setzen</h2>
        <form method="post" action="/admin/preisbuch/<?= e($v) ?>/param" class="field-row">
            <?= Csrf::field() ?>
            <div class="field">
                <label>Schlüssel</label>
                <input type="text" name="param_key" list="param-keys" required placeholder="MIN_ORDER_CENTS">
                <datalist id="param-keys">
                    <option value="SETUP_FEE_CENTS_PER_MOTIF"></option>
                    <option value="SETUP_FEE_WAIVER_QTY"></option>
                    <option value="EXTRA_POSITION_CENTS"></option>
                    <option value="EXTRA_COLOR_CENTS"></option>
                    <option value="NAME_NUMBER_CENTS"></option>
                    <option value="FILEPREP_CENTS"></option>
                    <option value="EXPRESS_BPS"></option>
                    <option value="MIN_ORDER_CENTS"></option>
                    <option value="SHIPPING_FLAT_CENTS"></option>
                    <option value="TECH_SURCHARGE_FLEX_CENTS"></option>
                </datalist>
            </div>
            <div class="field"><label>Wert (Ganzzahl)</label><input type="number" name="value_int" required></div>
            <div class="field"><label>Notiz</label><input type="text" name="note" maxlength="255"></div>
            <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Speichern</button></div>
        </form>
    <?php endif; ?>
</div>
