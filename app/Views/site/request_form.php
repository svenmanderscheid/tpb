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
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Angebot anfragen – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <p><a href="/konfigurator/">← Zurück</a></p>
        <h1>Angebot anfragen</h1>
        <p class="muted">Für Vereine und größere Bestellungen erstellen wir Ihnen ein persönliches Angebot.</p>

        <?php if ($error !== null): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="cfg-grid">
            <div class="card">
                <form method="post" action="/anfrage">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="config" value="<?= e((string) $config['public_id']) ?>">

                    <div class="field">
                        <label>Kundenart</label>
                        <label class="check"><input type="radio" name="type" value="private" <?= ($old['type'] ?? 'private') !== 'business' ? 'checked' : '' ?>> Privat</label>
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
                        <div class="field"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= $val('phone') ?>"></div>
                    </div>
                    <div class="field">
                        <label for="message">Nachricht (optional)</label>
                        <input type="text" id="message" name="message" value="<?= $val('message') ?>">
                    </div>

                    <?php if (!empty($legalDocs)): ?>
                    <div class="checks">
                        <?php foreach ($legalDocs as $d): $t = (string) $d['doc_type']; $req = in_array($t, $required, true); ?>
                            <label>
                                <input type="checkbox" name="consent_<?= e($t) ?>" value="1">
                                <span>Ich akzeptiere <a href="/rechtliches/<?= e($t) ?>" target="_blank"><?= e($t) ?></a><?= $req ? ' *' : '' ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn">Anfrage absenden</button>
                </form>
            </div>

            <aside class="cfg-side">
                <div class="card">
                    <h2>Ihre Konfiguration</h2>
                    <?php foreach (($config['items'] ?? []) as $it): ?>
                        <div class="layer card-2">
                            <strong><?= e((string) $it['product']) ?></strong>
                            <div class="muted"><?= e((string) $it['type']) ?><?= !empty($it['technique_code']) ? ' · ' . e((string) $it['technique_code']) : '' ?></div>
                            <?php foreach (($it['sizes'] ?? []) as $s): ?>
                                <div><?= e((string) $s['variant_sku']) ?>: <?= e((string) $s['qty']) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($config['calculation'])): ?>
                        <p class="muted">Vorläufiger Richtpreis: <?= e(\Tpb\Core\Money::format((int) $config['calculation']['total_cents'])) ?> (unverbindlich)</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </main>
</body>
</html>
