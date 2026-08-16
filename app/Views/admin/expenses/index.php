<?php
/**
 * @var array<int,array<string,mixed>> $expenses
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Authz;
use Tpb\Core\Csrf;
use Tpb\Core\Money;
?>
<h1>Ausgaben</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <?php if (Authz::can('tpb_manage_finance')): ?>
    <div class="card">
        <h2>Ausgabe erfassen</h2>
        <form method="post" action="/admin/ausgaben" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <div class="field"><label for="expense_date">Datum</label><input type="date" id="expense_date" name="expense_date"></div>
            <div class="field"><label for="vendor">Lieferant</label><input type="text" id="vendor" name="vendor"></div>
            <div class="field"><label for="category">Kategorie</label><input type="text" id="category" name="category" placeholder="z. B. Material, Miete, Software"></div>
            <div class="field"><label for="amount">Betrag €</label><input type="text" id="amount" name="amount"></div>
            <div class="field"><label for="description">Beschreibung</label><input type="text" id="description" name="description"></div>
            <div class="field"><label for="order_number">Auftragsnr. (optional)</label><input type="text" id="order_number" name="order_number"></div>
            <div class="field"><label for="receipt">Beleg (optional)</label><input type="file" id="receipt" name="receipt"></div>
            <button type="submit" class="btn">Ausgabe speichern</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Letzte Ausgaben</h2>
        <?php if (empty($expenses)): ?>
            <p class="muted">Noch keine Ausgaben.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Datum</th><th>Lieferant</th><th>Kategorie</th><th>Auftrag</th><th class="num">Betrag</th><th>Beleg</th></tr></thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td><?= e((string) $e['expense_date']) ?></td>
                            <td><?= e((string) $e['vendor']) ?></td>
                            <td><?= e((string) $e['category']) ?></td>
                            <td><?= e((string) ($e['order_number'] ?? '')) ?></td>
                            <td class="num"><?= e(Money::format((int) $e['amount_cents'], (string) $e['currency'])) ?></td>
                            <td><?php if (!empty($e['receipt_public_id'])): ?><a href="/files/<?= e((string) $e['receipt_public_id']) ?>">Beleg</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
