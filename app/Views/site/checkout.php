<?php
/**
 * @var array<string,mixed> $config
 * @var array<int,array<string,mixed>> $legalDocs
 * @var array<int,string> $required
 * @var string|null $error
 * @var array<string,mixed> $old
 */
use Tpb\Core\Csrf;
use Tpb\Core\Money;

$old = $old ?? [];
$v = static fn (string $k): string => e((string) ($old[$k] ?? ''));
$calc = $config['calculation'] ?? null;
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kasse – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <p><a href="/konfigurator/">← Zurück</a></p>
        <h1>Kasse</h1>

        <?php if ($error !== null): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <div class="cfg-grid">
            <div class="card">
                <form method="post" action="/checkout">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="config" value="<?= e((string) $config['public_id']) ?>">

                    <h2>Ihre Daten</h2>
                    <div class="field">
                        <label class="check"><input type="radio" name="type" value="private" <?= ($old['type'] ?? 'private') !== 'business' ? 'checked' : '' ?>> Privat</label>
                        <label class="check"><input type="radio" name="type" value="business" <?= ($old['type'] ?? '') === 'business' ? 'checked' : '' ?>> Firma</label>
                    </div>
                    <div class="field"><label for="company_name">Firma (optional)</label><input type="text" id="company_name" name="company_name" value="<?= $v('company_name') ?>"></div>
                    <div class="field-row">
                        <div class="field"><label for="first_name">Vorname *</label><input type="text" id="first_name" name="first_name" value="<?= $v('first_name') ?>" required></div>
                        <div class="field"><label for="last_name">Nachname *</label><input type="text" id="last_name" name="last_name" value="<?= $v('last_name') ?>" required></div>
                    </div>
                    <div class="field-row">
                        <div class="field"><label for="email">E-Mail *</label><input type="email" id="email" name="email" value="<?= $v('email') ?>" required></div>
                        <div class="field"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= $v('phone') ?>"></div>
                    </div>
                    <div class="field"><label for="street">Straße *</label><input type="text" id="street" name="street" value="<?= $v('street') ?>" required></div>
                    <div class="field-row">
                        <div class="field"><label for="zip">PLZ *</label><input type="text" id="zip" name="zip" value="<?= $v('zip') ?>" required></div>
                        <div class="field"><label for="city">Ort *</label><input type="text" id="city" name="city" value="<?= $v('city') ?>" required></div>
                        <div class="field"><label for="country">Land</label><input type="text" id="country" name="country" value="<?= $v('country') ?>"></div>
                    </div>

                    <?php if (!empty($legalDocs)): ?>
                    <div class="checks">
                        <?php foreach ($legalDocs as $d): $t = (string) $d['doc_type']; $req = in_array($t, $required, true); ?>
                            <label>
                                <input type="checkbox" name="consent_<?= e($t) ?>" value="1">
                                <span>Ich akzeptiere <a href="/rechtliches/<?= e($t) ?>" target="_blank"><?= e($t) ?></a><?= $req ? ' *' : '' ?><?php if ($t === 'widerruf'): ?> – mir ist bekannt, dass das Widerrufsrecht bei personalisierter Ware erlischt<?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn">Zahlungspflichtig bestellen</button>
                </form>
            </div>

            <aside class="cfg-side">
                <div class="card">
                    <h2>Bestellübersicht</h2>
                    <?php foreach (($config['items'] ?? []) as $it): ?>
                        <div class="layer card-2">
                            <strong><?= e((string) $it['product']) ?></strong>
                            <?php foreach (($it['sizes'] ?? []) as $s): ?><div><?= e((string) $s['variant_sku']) ?>: <?= e((string) $s['qty']) ?></div><?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($calc !== null): ?>
                        <p class="price-total"><?= e(Money::format((int) $calc['total_cents'])) ?></p>
                    <?php endif; ?>
                    <p class="muted">Produktpreis inkl. aller Zuschläge, serverseitig berechnet.</p>
                    <p class="muted">Versand: national gratis ab 50 € (sonst Post Luxembourg), international per DHL – der Endbetrag inkl. Versand erscheint auf der Bezahlseite.</p>
                </div>
            </aside>
        </div>
    </main>
</body>
</html>
