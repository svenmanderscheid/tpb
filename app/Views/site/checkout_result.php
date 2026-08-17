<?php
/**
 * @var string $kind  paid|cancelled
 * @var string|null $order_number
 */
?>
<section class="section">
    <div class="wrap">
        <div class="card narrow center">
            <?php if ($kind === 'paid'): ?>
                <h1>Vielen Dank für Ihre Bestellung!</h1>
                <div class="alert ok">Ihre Zahlung war erfolgreich. Auftrag <strong><?= e((string) ($order_number ?? '')) ?></strong> ist bestätigt.</div>
                <p>Sie erhalten eine Bestätigung und die Rechnung per E-Mail. Wir starten die Produktion.</p>
            <?php else: ?>
                <h1>Zahlung abgebrochen</h1>
                <p>Die Zahlung wurde abgebrochen. Ihre Konfiguration bleibt gespeichert – Sie können den Kauf jederzeit erneut starten.</p>
            <?php endif; ?>
            <p><a class="btn" href="/">Zur Startseite</a></p>
        </div>
    </div>
</section>
