<?php
/**
 * @var array<int,array<string,mixed>> $imports
 * @var array<int,array<string,mixed>> $lines
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Csrf;
use Tpb\Core\Money;
?>
<h1>Bankabgleich</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Kontoauszug importieren</h2>
    <form method="post" action="/admin/bank/import" enctype="multipart/form-data" class="field-row">
        <?= Csrf::field() ?>
        <div class="field"><label for="account_label">Konto</label><input type="text" id="account_label" name="account_label" value="Hausbank"></div>
        <div class="field"><label for="format">Format</label><select id="format" name="format"><option value="csv">CSV</option><option value="camt053">CAMT.053 (XML)</option></select></div>
        <div class="field"><label for="statement">Datei</label><input type="file" id="statement" name="statement"></div>
        <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Importieren</button></div>
    </form>
    <p class="muted">Doppelter Schutz: identische Datei und identische Zeile werden nicht erneut übernommen. Es wird nie automatisch gebucht.</p>
</div>

<div class="card">
    <h2>Offene Bankzeilen</h2>
    <?php if (empty($lines)): ?>
        <p class="muted">Keine offenen Zeilen.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Datum</th><th class="num">Betrag</th><th>Gegenpartei</th><th>Verwendungszweck</th><th>Vorschlag / Aktion</th></tr></thead>
            <tbody>
                <?php foreach ($lines as $l): $incoming = (int) $l['amount_cents'] > 0; $s = $l['suggestion'] ?? null; ?>
                    <tr>
                        <td><?= e((string) $l['booking_date']) ?></td>
                        <td class="num <?= $incoming ? 'pos' : 'neg' ?>"><?= e(Money::format((int) $l['amount_cents'], (string) $l['currency'])) ?></td>
                        <td><?= e((string) ($l['counterparty_name'] ?? '')) ?></td>
                        <td><?= e((string) ($l['remittance_info'] ?? $l['end_to_end_id'] ?? '')) ?></td>
                        <td>
                            <div class="actions-row">
                                <?php if ($incoming): ?>
                                    <?php if ($s !== null): ?>
                                        <form class="inline-form" method="post" action="/admin/bank/<?= (int) $l['id'] ?>/zahlung">
                                            <?= Csrf::field() ?><input type="hidden" name="invoice_id" value="<?= (int) $s['invoice_id'] ?>">
                                            <button type="submit" class="btn">Zahlung buchen: <?= e((string) $s['invoice_number']) ?> <span class="badge"><?= e((string) $s['confidence']) ?></span></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">kein Vorschlag</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form class="inline-form" method="post" action="/admin/bank/<?= (int) $l['id'] ?>/ausgabe">
                                        <?= Csrf::field() ?>
                                        <input type="text" name="category" placeholder="Kategorie" class="stock-input">
                                        <button type="submit" class="btn secondary">Als Ausgabe erfassen</button>
                                    </form>
                                <?php endif; ?>
                                <form class="inline-form" method="post" action="/admin/bank/<?= (int) $l['id'] ?>/ignorieren">
                                    <?= Csrf::field() ?><button type="submit" class="link-danger">ignorieren</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Letzte Importe</h2>
    <?php if (empty($imports)): ?>
        <p class="muted">Noch keine Importe.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Konto</th><th>Format</th><th class="num">Zeilen</th><th>Zeitpunkt</th></tr></thead>
            <tbody>
                <?php foreach ($imports as $im): ?>
                    <tr><td><?= e((string) $im['account_label']) ?></td><td><?= e((string) $im['format']) ?></td><td class="num"><?= (int) $im['line_count'] ?></td><td><?= e((string) substr((string) $im['imported_at'], 0, 16)) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
