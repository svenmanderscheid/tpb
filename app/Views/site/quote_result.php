<?php
/**
 * @var string $kind  accepted|declined|error
 * @var string|null $order_number
 * @var string|null $message
 */
$titles = ['accepted' => 'Angebot angenommen', 'declined' => 'Angebot abgelehnt', 'error' => 'Aktion nicht möglich'];
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titles[$kind] ?? 'Angebot') ?> – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <div class="card">
            <?php if ($kind === 'accepted'): ?>
                <h1>Vielen Dank – Angebot angenommen!</h1>
                <div class="alert ok">Ihr Auftrag <strong><?= e((string) ($order_number ?? '')) ?></strong> ist angelegt. Sie erhalten eine Bestätigung per E-Mail.</div>
                <p>Wir melden uns mit den nächsten Schritten (z. B. Druckdatenfreigabe).</p>
            <?php elseif ($kind === 'declined'): ?>
                <h1>Angebot abgelehnt</h1>
                <p>Sie haben dieses Angebot abgelehnt. Bei Fragen erreichen Sie uns jederzeit.</p>
            <?php else: ?>
                <h1>Aktion nicht möglich</h1>
                <div class="alert error"><?= e((string) ($message ?? 'Diese Aktion ist nicht mehr möglich.')) ?></div>
            <?php endif; ?>
            <p><a class="btn" href="/">Zur Startseite</a></p>
        </div>
    </main>
</body>
</html>
