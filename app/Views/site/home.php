<?php
/**
 * @var array<int,array<string,mixed>> $products
 * @var array<int,array<string,mixed>> $legalDocs
 */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Printing Brothers – Textildruck aus Luxemburg</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <div class="card">
            <h1>The Printing Brothers</h1>
            <p class="muted">Individuell bedruckte Textilien – vom Einzelstück bis zur Vereinsausstattung.</p>
            <p>
                <a class="btn" href="/produkte">Jetzt konfigurieren</a>
                <a class="btn secondary" href="/anfrage">Angebot für Verein / Großbestellung</a>
            </p>
        </div>

        <h2>Produkte</h2>
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

        <?php if (!empty($legalDocs)): ?>
        <p class="muted mt">
            <?php foreach ($legalDocs as $d): ?>
                <a href="/rechtliches/<?= e((string) $d['doc_type']) ?>"><?= e((string) $d['doc_type']) ?></a>&nbsp;
            <?php endforeach; ?>
        </p>
        <?php endif; ?>
    </main>
</body>
</html>
