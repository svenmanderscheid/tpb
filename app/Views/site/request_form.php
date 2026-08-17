<?php
/**
 * @var array<string,mixed> $config
 * @var array<int,array<string,mixed>> $legalDocs
 * @var array<int,string> $required
 * @var string|null $error
 * @var array<string,mixed> $old
 */
use Tpb\Core\Csrf;

$old = $old ?? [];
$val = static fn (string $k): string => e((string) ($old[$k] ?? ''));
?>
<section class="section">
    <div class="wrap">
        <a class="back-link" href="/produkte">← Zurück</a>
        <h1>Angebot anfragen</h1>
        <p class="muted">Für Vereine und größere Bestellungen erstellen wir Ihnen ein persönliches Angebot.</p>

        <?php if ($error !== null): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div class="card">
                <form method="post" action="/anfrage">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="config" value="<?= e((string) $config['public_id']) ?>">

                    <div class="field">
                        <label>Kundenart</label>
                        <label class="check"><input type="radio" name="type" value="private" <?= ($old['type'] ?? 'private') !== 'business' ? 'checked' : '' ?>> Privat</label>
                        &nbsp;&nbsp;
                        <label class="check"><input type="radio" name="type" value="business" <?= ($old['type'] ?? '') === 'business' ? 'checked' : '' ?>> Verein / Firma</label>
                    </div>
                    <div class="field">
                        <label for="company_name">Verein / Firma (falls zutreffend)</label>
                        <input type="text" id="company_name" name="company_name" value="<?= $val('company_name') ?>">
                    </div>
                    <div class="field-row">
                        <div class="field"><label for="first_name">Vorname *</label><input type="text" id="first_name" name="first_name" value="<?= $val('first_name') ?>" required></div>
                        <div class="field"><label for="last_name">Nachname *</label><input type="text" id="last_name" name="last_name" value="<?= $val('last_name') ?>" required></div>
                    </div>
                    <div class="field-row">
                        <div class="field"><label for="email">E-Mail *</label><input type="email" id="email" name="email" value="<?= $val('email') ?>" required></div>
                        <div class="field"><label for="phone">Telefon</label><input type="tel" id="phone" name="phone" value="<?= $val('phone') ?>"></div>
                    </div>
                    <div class="field">
                        <label for="message">Nachricht (optional)</label>
                        <input type="text" id="message" name="message" value="<?= $val('message') ?>">
                    </div>

                    <?php if (!empty($legalDocs)): ?>
                    <div class="checks">
                        <?php foreach ($legalDocs as $d): $t = (string) $d['doc_type']; $req = in_array($t, $required, true); ?>
                            <label class="check">
                                <input type="checkbox" name="consent_<?= e($t) ?>" value="1">
                                <span>Ich akzeptiere <a href="/rechtliches/<?= e($t) ?>" target="_blank" rel="noopener"><?= e($t) ?></a><?= $req ? ' *' : '' ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn block lg">Anfrage absenden</button>
                </form>
            </div>

            <aside>
                <div class="card summary">
                    <h2>Ihre Konfiguration</h2>
                    <?php foreach (($config['items'] ?? []) as $it): ?>
                        <div class="layer">
                            <strong><?= e((string) $it['product']) ?></strong>
                            <div class="muted small"><?= e((string) $it['type']) ?><?= !empty($it['technique_code']) ? ' · ' . e((string) $it['technique_code']) : '' ?></div>
                            <?php foreach (($it['sizes'] ?? []) as $s): ?>
                                <div class="summary-row"><span><?= e((string) $s['variant_sku']) ?></span><span><?= e((string) $s['qty']) ?>×</span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($config['calculation'])): ?>
                        <p class="muted small mt">Vorläufiger Richtpreis: <?= e(\Tpb\Core\Money::format((int) $config['calculation']['total_cents'])) ?> (unverbindlich)</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</section>
