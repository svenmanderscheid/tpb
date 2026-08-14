<?php
/** @var int $year */
/** @var int[] $years */
/** @var array<int,array<string,mixed>> $entries */
/** @var array<int,array<string,mixed>> $partners */
/** @var array{type:string,text:string}|null $flash */
/** @var string $today */

use Tpb\Core\Csrf;
use Tpb\Core\Money;
?>
<h1>Buchungen <?= e((string) $year) ?></h1>

<form method="get" action="/admin/finanzen/buchungen" class="toolbar">
    <label for="year">Jahr</label>
    <select id="year" name="year" data-autosubmit>
        <?php foreach ($years as $y): ?>
            <option value="<?= e((string) $y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= e((string) $y) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn secondary">Anzeigen</button>
    <a class="btn secondary" href="/admin/finanzen?year=<?= e((string) $year) ?>">Zur Übersicht</a>
</form>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="split">
    <div class="card">
        <h2>Neue Buchung</h2>
        <form method="post" action="/admin/finanzen/buchungen">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="direction">Art</label>
                <select id="direction" name="direction" required>
                    <option value="income">Einnahme</option>
                    <option value="expense">Ausgabe</option>
                </select>
            </div>
            <div class="field">
                <label for="entry_date">Datum</label>
                <input type="date" id="entry_date" name="entry_date" value="<?= e($today) ?>" required>
            </div>
            <div class="field">
                <label for="category">Kategorie</label>
                <input type="text" id="category" name="category" list="cat-list" required maxlength="48" placeholder="z. B. Material">
                <datalist id="cat-list">
                    <option value="Material"></option>
                    <option value="Geräte"></option>
                    <option value="Miete"></option>
                    <option value="Marketing"></option>
                    <option value="Versand"></option>
                    <option value="Verkauf"></option>
                    <option value="Dienstleistung"></option>
                    <option value="Sonstiges"></option>
                </datalist>
            </div>
            <div class="field">
                <label for="amount">Betrag (€)</label>
                <input type="text" id="amount" name="amount" inputmode="decimal" required placeholder="0,00">
            </div>
            <div class="field">
                <label for="description">Beschreibung (optional)</label>
                <input type="text" id="description" name="description" maxlength="255">
            </div>
            <div class="field">
                <label for="partner">Gründer (optional)</label>
                <select id="partner" name="partner">
                    <option value="">—</option>
                    <?php foreach ($partners as $p): ?>
                        <option value="<?= e((string) $p['public_id']) ?>"><?= e((string) $p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Speichern</button>
        </form>
    </div>

    <div class="card">
        <h2>Buchungen <?= e((string) $year) ?></h2>
        <?php if (empty($entries)): ?>
            <p class="muted">Für <?= e((string) $year) ?> sind noch keine Buchungen erfasst.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Datum</th><th>Art</th><th>Kategorie</th><th>Beschreibung</th><th class="num">Betrag</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $en): ?>
                    <?php $isIncome = $en['direction'] === 'income'; $amt = (int) $en['amount_cents']; ?>
                    <tr>
                        <td><?= e((string) $en['entry_date']) ?></td>
                        <td><span class="badge"><?= $isIncome ? 'Einnahme' : 'Ausgabe' ?></span></td>
                        <td><?= e((string) $en['category']) ?></td>
                        <td class="muted">
                            <?= e((string) ($en['description'] ?? '')) ?>
                            <?php if (!empty($en['partner_name'])): ?>
                                <span class="badge"><?= e((string) $en['partner_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num <?= $isIncome ? 'pos' : 'neg' ?>"><?= ($isIncome ? '+' : '−') . e(Money::format($amt)) ?></td>
                        <td>
                            <form method="post" action="/admin/finanzen/buchungen/loeschen" class="inline-form" data-confirm="Buchung wirklich löschen?">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="public_id" value="<?= e((string) $en['public_id']) ?>">
                                <button type="submit" class="link-danger">löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
