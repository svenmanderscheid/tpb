<?php
/** @var array<int,array<string,mixed>> $products */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konfigurator – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <h1>Produkte konfigurieren</h1>
        <?php if (empty($products)): ?>
            <div class="card"><p class="muted">Aktuell sind keine Produkte verfügbar.</p></div>
        <?php else: ?>
            <div class="charts">
                <?php foreach ($products as $p): ?>
                    <div class="card">
                        <h2><?= e((string) $p['name']) ?></h2>
                        <p class="muted"><?= e((string) $p['product_type']) ?></p>
                        <a class="btn" href="/konfigurator/<?= e((string) $p['public_id']) ?>">Konfigurieren</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
