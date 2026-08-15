<?php
/** @var array<string,mixed> $cv */
/** @var array<int,array<string,mixed>> $items */
/** @var string[] $refTypes */
/** @var string[] $paramKeys */
/** @var bool $isDraft */
/** @var array{type:string,text:string}|null $flash */

use Tpb\Core\Csrf;
use Tpb\Core\Money;

$v = (string) $cv['version'];
?>
<h1>Kostenversion v<?= e($v) ?> <span class="badge"><?= e((string) $cv['status']) ?></span></h1>
<div class="toolbar">
    <a class="btn secondary" href="/admin/kostenversionen">← Alle Kostenversionen</a>
    <?php if ($isDraft): ?>
        <form method="post" action="/admin/kostenversion/<?= e($v) ?>/publish" class="inline-form" data-confirm="Kostenversion veröffentlichen? Danach unveränderlich.">
            <?= Csrf::field() ?><button type="submit" class="btn">Veröffentlichen</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<?php if (!$isDraft): ?>
    <div class="alert ok">Diese Kostenversion ist <strong><?= e((string) $cv['status']) ?></strong> – unveränderlich.</div>
<?php endif; ?>

<div class="card">
    <h2>Sätze</h2>
    <?php if ($isDraft): ?>
        <form method="post" action="/admin/kostenversion/<?= e($v) ?>/saetze">
            <?= Csrf::field() ?>
            <div class="field-row">
                <div class="field"><label>Lohnsatz (€/h)</label><input type="text" name="labor_rate" inputmode="decimal" value="<?= e(number_format((int) $cv['labor_rate_cents_h'] / 100, 2, ',', '')) ?>" required></div>
                <div class="field"><label>Maschinensatz (€/h)</label><input type="text" name="machine_rate" inputmode="decimal" value="<?= e(number_format((int) $cv['machine_rate_cents_h'] / 100, 2, ',', '')) ?>" required></div>
                <div class="field"><label>Ausschuss (%)</label><input type="text" name="scrap" inputmode="decimal" value="<?= e(number_format((int) $cv['scrap_bps'] / 100, 2, ',', '')) ?>"></div>
                <div class="field"><label>Zielmarge (%)</label><input type="text" name="margin" inputmode="decimal" value="<?= e(number_format((int) $cv['target_margin_bps'] / 100, 2, ',', '')) ?>"></div>
                <div class="field"><label>&nbsp;</label><button type="submit" class="btn secondary">Speichern</button></div>
            </div>
        </form>
    <?php else: ?>
        <p>Lohn <?= e(Money::format((int) $cv['labor_rate_cents_h'])) ?>/h · Maschine <?= e(Money::format((int) $cv['machine_rate_cents_h'])) ?>/h ·
           Ausschuss <?= e(number_format((int) $cv['scrap_bps'] / 100, 2, ',', '.')) ?> % · Zielmarge <?= e(number_format((int) $cv['target_margin_bps'] / 100, 2, ',', '.')) ?> %</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Kostenpositionen</h2>
    <p class="muted">Bezug: variant/product/technique + ID. Werte: …_CENTS in Cents, …_MIN in Minuten.</p>
    <?php if (empty($items)): ?>
        <p class="muted">Noch keine Kostenpositionen.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Bezug</th><th>ID</th><th>Schlüssel</th><th class="num">Wert</th><?php if ($isDraft): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><span class="badge"><?= e((string) $it['ref_type']) ?></span></td>
                    <td><?= e((string) $it['ref_id']) ?></td>
                    <td><?= e((string) $it['param_key']) ?></td>
                    <td class="num"><?= e((string) $it['value_int']) ?></td>
                    <?php if ($isDraft): ?>
                        <td>
                            <form method="post" action="/admin/kostenversion/<?= e($v) ?>/item/loeschen" class="inline-form" data-confirm="Position löschen?">
                                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= e((string) $it['id']) ?>">
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
        <h2 class="mt">Kostenposition hinzufügen</h2>
        <form method="post" action="/admin/kostenversion/<?= e($v) ?>/item" class="field-row">
            <?= Csrf::field() ?>
            <div class="field">
                <label>Bezugstyp</label>
                <select name="ref_type"><?php foreach ($refTypes as $rt): ?><option value="<?= $rt ?>"><?= $rt ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label>Bezugs-ID</label><input type="number" name="ref_id" min="1" required></div>
            <div class="field">
                <label>Schlüssel</label>
                <select name="param_key"><?php foreach ($paramKeys as $pk): ?><option value="<?= $pk ?>"><?= $pk ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label>Wert (Ganzzahl)</label><input type="number" name="value_int" required></div>
            <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Speichern</button></div>
        </form>
    <?php endif; ?>
</div>
