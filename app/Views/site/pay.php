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
?>
<section class="section">
    <div class="wrap">
        <div class="card pay-box">
            <span class="tag">Testmodus</span>
            <h1>Zahlung</h1>
            <p class="muted">Simulierte Anbieterseite – hier steht später die echte Bezahlseite des Zahlungsdienstleisters.</p>
            <div class="pay-amount"><?= e(Money::format((int) $intent['amount_cents'], (string) $intent['currency'])) ?></div>

            <?php if ($status === 'created'): ?>
                <div class="stack">
                    <form method="post" action="/pay/<?= e((string) $intent['public_id']) ?>/simulieren">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn block lg">Zahlung simulieren (erfolgreich)</button>
                    </form>
                    <form method="post" action="/pay/<?= e((string) $intent['public_id']) ?>/abbrechen">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn secondary block">Abbrechen</button>
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
    </div>
</section>
