<?php
/** @var array<string,mixed> $product */
/** @var string $dataJson */
?>
<section class="section">
    <div class="wrap">
        <div class="cfg-head">
            <div>
                <a class="back-link" href="/produkte">← Alle Produkte</a>
                <h1><?= e((string) $product['name']) ?> gestalten</h1>
            </div>
        </div>

        <div class="cfg-grid">
            <div id="cfg-form" class="cfg-form-cards"><div class="card"><p class="muted">Konfigurator wird geladen …</p></div></div>
            <aside class="cfg-side">
                <div class="card">
                    <h2>Vorschau</h2>
                    <div id="cfg-preview" class="preview-stage" aria-live="polite"></div>
                    <div id="cfg-preview-meta" class="preview-meta"></div>
                </div>
                <div class="card">
                    <h2>Preis</h2>
                    <div id="cfg-price"><p class="muted">Bitte Mengen wählen.</p></div>
                    <button id="cfg-save" class="btn block mt" type="button">Entwurf speichern</button>
                    <p class="muted small mt">Speichern erzeugt einen Link, mit dem du später weitermachst.</p>
                    <div id="cfg-link"></div>
                </div>
            </aside>
        </div>
    </div>
</section>

<script type="application/json" id="cfg-data"><?= $dataJson ?></script>
<script type="module" src="/assets/js/configurator.js"></script>
