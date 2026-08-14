<?php
/** @var array<int,array<string,mixed>> $partners */
/** @var array<int,array<string,mixed>> $contributions */
/** @var array<string,int> $totalsByName */
/** @var int $shareTotalBps */
/** @var array{type:string,text:string}|null $flash */
/** @var string $today */

use Tpb\Core\Csrf;
use Tpb\Core\Money;

$fmtShare = static fn (int $bps): string => number_format($bps / 100, 2, ',', '');
?>
<h1>Gesellschafter &amp; Kapitaleinlagen</h1>

<div class="toolbar">
    <a class="btn secondary" href="/admin/finanzen">Zur Übersicht</a>
    <a class="btn secondary" href="/admin/finanzen/buchungen">Buchungen</a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Gesellschafter</h2>
    <p class="muted">Gewinnanteil in Prozent. Summe aller aktiven Anteile aktuell:
        <strong><?= e($fmtShare($shareTotalBps)) ?> %</strong>.</p>

    <?php foreach ($partners as $p): ?>
        <form method="post" action="/admin/finanzen/gesellschafter" class="field-row">
            <?= Csrf::field() ?>
            <input type="hidden" name="public_id" value="<?= e((string) $p['public_id']) ?>">
            <div class="field">
                <label>Name</label>
                <input type="text" name="name" value="<?= e((string) $p['name']) ?>" maxlength="120" required>
            </div>
            <div class="field">
                <label>Anteil (%)</label>
                <input type="text" name="share" inputmode="decimal" value="<?= e($fmtShare((int) $p['profit_share_bps'])) ?>">
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>aktiv</option>
                    <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : '' ?>>inaktiv</option>
                </select>
            </div>
            <div class="field">
                <label>Kapitaleinlage</label>
                <input type="text" value="<?= e(Money::format($totalsByName[(string) $p['name']] ?? 0)) ?>" readonly>
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <button type="submit" class="btn secondary">Speichern</button>
            </div>
        </form>
    <?php endforeach; ?>

    <h2 class="mt">Neuen Gesellschafter anlegen</h2>
    <form method="post" action="/admin/finanzen/gesellschafter" class="field-row">
        <?= Csrf::field() ?>
        <div class="field">
            <label for="new-name">Name</label>
            <input type="text" id="new-name" name="name" maxlength="120" required>
        </div>
        <div class="field">
            <label for="new-share">Anteil (%)</label>
            <input type="text" id="new-share" name="share" inputmode="decimal" placeholder="z. B. 50">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="submit" class="btn">Anlegen</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Kapitaleinlage erfassen</h2>
    <?php if (empty($partners)): ?>
        <p class="muted">Bitte zuerst einen Gesellschafter anlegen.</p>
    <?php else: ?>
        <form method="post" action="/admin/finanzen/einlagen" class="field-row">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="c-partner">Gründer</label>
                <select id="c-partner" name="partner" required>
                    <?php foreach ($partners as $p): ?>
                        <?php if ($p['status'] === 'active'): ?>
                            <option value="<?= e((string) $p['public_id']) ?>"><?= e((string) $p['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="c-date">Datum</label>
                <input type="date" id="c-date" name="contributed_on" value="<?= e($today) ?>" required>
            </div>
            <div class="field">
                <label for="c-kind">Art</label>
                <select id="c-kind" name="kind">
                    <option value="cash">Geld</option>
                    <option value="asset">Sachwert</option>
                </select>
            </div>
            <div class="field">
                <label for="c-amount">Betrag (€)</label>
                <input type="text" id="c-amount" name="amount" inputmode="decimal" placeholder="0,00" required>
            </div>
            <div class="field">
                <label for="c-note">Notiz</label>
                <input type="text" id="c-note" name="note" maxlength="255">
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <button type="submit" class="btn">Speichern</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if (!empty($contributions)): ?>
        <table class="mt">
            <thead>
                <tr><th>Datum</th><th>Gründer</th><th>Art</th><th class="num">Betrag</th><th>Notiz</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($contributions as $c): ?>
                <tr>
                    <td><?= e((string) $c['contributed_on']) ?></td>
                    <td><?= e((string) $c['partner_name']) ?></td>
                    <td><span class="badge"><?= $c['kind'] === 'asset' ? 'Sachwert' : 'Geld' ?></span></td>
                    <td class="num"><?= e(Money::format((int) $c['amount_cents'])) ?></td>
                    <td class="muted"><?= e((string) ($c['note'] ?? '')) ?></td>
                    <td>
                        <form method="post" action="/admin/finanzen/einlagen/loeschen" class="inline-form" data-confirm="Einlage wirklich löschen?">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="public_id" value="<?= e((string) $c['public_id']) ?>">
                            <button type="submit" class="link-danger">löschen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
