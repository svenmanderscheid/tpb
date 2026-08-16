<?php
/**
 * @var array<int,array<string,mixed>> $variants
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Csrf;

$miniForm = static function (int $id, string $action, string $label, int $current) {
    ob_start(); ?>
    <form class="inline-form" method="post" action="/admin/lager/<?= (int) $id ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="<?= e($action) ?>">
        <input type="number" name="value" value="<?= (int) $current ?>" class="stock-input" min="0">
        <button type="submit" class="btn secondary"><?= e($label) ?></button>
    </form>
    <?php return ob_get_clean();
};
?>
<h1>Lager</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Meldebestand für alle setzen</h2>
    <form method="post" action="/admin/lager/alle/meldebestand" class="field-row">
        <?= Csrf::field() ?>
        <div class="field"><label for="allthr">Schwellwert für alle Varianten</label><input type="number" id="allthr" name="value" min="0" value="5"></div>
        <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Für alle setzen</button></div>
    </form>
</div>

<div class="card">
    <h2>Bestände</h2>
    <?php if (empty($variants)): ?>
        <p class="muted">Keine Varianten angelegt.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Produkt</th><th>SKU</th><th>Farbe/Größe</th><th class="num">Bestand</th><th class="num">Reserve</th><th class="num">Reserviert</th><th class="num">Verfügbar</th><th class="num">Meldebest.</th><th>Aktionen</th></tr></thead>
            <tbody>
                <?php foreach ($variants as $v): $id = (int) $v['id']; $avail = (int) $v['available']; ?>
                    <tr>
                        <td><?= e((string) $v['product_name']) ?></td>
                        <td><?= e((string) $v['sku']) ?></td>
                        <td><?= e(trim((string) ($v['color_name'] ?? '') . ' ' . (string) ($v['size'] ?? ''))) ?></td>
                        <td class="num"><?= (int) $v['stock_qty'] ?></td>
                        <td class="num"><?= (int) $v['reserve_qty'] ?></td>
                        <td class="num"><?= (int) $v['reserved'] ?></td>
                        <td class="num <?= $avail <= (int) $v['reorder_threshold'] ? 'stock-low' : '' ?>"><strong><?= $avail ?></strong></td>
                        <td class="num"><?= (int) $v['reorder_threshold'] ?></td>
                        <td>
                            <div class="stock-actions">
                                <?= $miniForm($id, 'wareneingang', 'Wareneingang +', 0) ?>
                                <?= $miniForm($id, 'bestand', 'Bestand =', (int) $v['stock_qty']) ?>
                                <?= $miniForm($id, 'reserve', 'Reserve =', (int) $v['reserve_qty']) ?>
                                <?= $miniForm($id, 'meldebestand', 'Meldebest. =', (int) $v['reorder_threshold']) ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
