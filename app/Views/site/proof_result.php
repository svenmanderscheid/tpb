<?php
/**
 * @var string $kind  approved|changes|error
 * @var string|null $message
 */
$titles = ['approved' => 'Freigabe erteilt', 'changes' => 'Änderung angefordert', 'error' => 'Aktion nicht möglich'];
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titles[$kind] ?? 'Proof') ?> – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <div class="card">
            <?php if ($kind === 'approved'): ?>
                <h1>Vielen Dank – Freigabe erteilt!</h1>
                <div class="alert ok">Ihr Motiv ist freigegeben. Der Auftrag geht nun in die Produktion.</div>
            <?php elseif ($kind === 'changes'): ?>
                <h1>Änderung angefordert</h1>
                <p>Danke für Ihre Rückmeldung. Wir überarbeiten den Proof und senden Ihnen eine neue Version zur Freigabe.</p>
            <?php else: ?>
                <h1>Aktion nicht möglich</h1>
                <div class="alert error"><?= e((string) ($message ?? 'Diese Aktion ist nicht mehr möglich.')) ?></div>
            <?php endif; ?>
            <p><a class="btn" href="/">Zur Startseite</a></p>
        </div>
    </main>
</body>
</html>
