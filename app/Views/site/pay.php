<?php
/**
 * Simulierte gehostete Bezahlseite (Testmodus). Ersetzt den echten Anbieter, bis
 * ein Zahlungs-SDK integriert ist. „Zahlung simulieren" löst denselben signierten
 * Webhook aus, den der echte Anbieter server-to-server schicken würde.
 *
 * @var array<string,mixed> $intent
 */
use Tpb\Core\Csrf;
use Tpb\Core\Money;

$status = (string) $intent['status'];
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zahlung – Testmodus</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <div class="card">
            <h1>Zahlung <span class="badge quarantine">TESTMODUS</span></h1>
            <p class="muted">Simulierte Anbieterseite – hier steht später die echte Bezahlseite des Zahlungsdienstleisters.</p>
            <p class="price-total"><?= e(Money::format((int) $intent['amount_cents'], (string) $intent['currency'])) ?></p>

            <?php if ($status === 'created'): ?>
                <div class="actions-row">
                    <form method="post" action="/pay/<?= e((string) $intent['public_id']) ?>/simulieren">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn">Zahlung simulieren (erfolgreich)</button>
                    </form>
                    <form method="post" action="/pay/<?= e((string) $intent['public_id']) ?>/abbrechen">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn secondary">Abbrechen</button>
                    </form>
                </div>
            <?php elseif ($status === 'succeeded'): ?>
                <div class="alert ok">Diese Zahlung wurde bereits erfolgreich verarbeitet.</div>
                <p><a class="btn" href="/">Zur Startseite</a></p>
            <?php else: ?>
                <div class="alert error">Diese Zahlung ist nicht mehr offen (Status: <?= e($status) ?>).</div>
                <p><a class="btn" href="/">Zur Startseite</a></p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
