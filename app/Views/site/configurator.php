<?php
/** @var array<string,mixed> $product */
/** @var string $dataJson */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $product['name']) ?> konfigurieren – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <p><a href="/konfigurator">← Alle Produkte</a></p>
        <h1><?= e((string) $product['name']) ?> konfigurieren</h1>

        <div class="cfg-grid">
            <div id="cfg-form"><div class="card"><p class="muted">Konfigurator wird geladen …</p></div></div>
            <aside class="cfg-side">
                <div class="card">
                    <h2>Preis</h2>
                    <div id="cfg-price"><p class="muted">Bitte Mengen wählen.</p></div>
                </div>
                <div class="card">
                    <h2>Entwurf</h2>
                    <p class="muted">Speichern erzeugt einen Link, mit dem du später weitermachst.</p>
                    <button id="cfg-save" class="btn" type="button">Entwurf speichern</button>
                    <div id="cfg-link"></div>
                </div>
            </aside>
        </div>
    </main>

    <script type="application/json" id="cfg-data"><?= $dataJson ?></script>
    <script type="module" src="/assets/js/configurator.js"></script>
</body>
</html>
