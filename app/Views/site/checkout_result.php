<?php
/**
 * @var string $kind  paid|cancelled
 * @var string|null $order_number
 */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $kind === 'paid' ? 'Bestellung bestätigt' : 'Zahlung abgebrochen' ?> – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <div class="card">
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
    </main>
</body>
</html>
