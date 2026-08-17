<?php
/**
 * @var array<int,array<string,mixed>> $products
 * @var array<int,array<string,mixed>> $legalDocs
 */
$shirt = '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M42 20 L30 28 L20 42 L30 52 L38 46 L38 96 A4 4 0 0 0 42 100 L78 100 A4 4 0 0 0 82 96 L82 46 L90 52 L100 42 L90 28 L78 20 C74 30 46 30 42 20 Z" fill="#e9e8e4" stroke="#c9c8c3" stroke-width="2" stroke-linejoin="round"/><circle cx="60" cy="60" r="12" fill="#ff5a3c" opacity="0.9"/></svg>';
?>
<section class="hero">
    <div class="wrap">
        <div class="hero-grid">
            <div>
                <span class="eyebrow">Textildruck aus Luxemburg</span>
                <h1>Deine Idee, auf Stoff gebracht.</h1>
                <p class="lead">Individuell bedruckte T-Shirts und Hoodies – vom Einzelstück bis zur kompletten Vereinsausstattung. Online gestalten, Preis sofort sehen, bestellen.</p>
                <div class="hero-actions">
                    <a class="btn lg" href="/produkte">Jetzt gestalten</a>
                    <a class="btn secondary lg" href="/anfrage">Angebot für Vereine</a>
                </div>
                <div class="trust">
                    <span><span class="dot">●</span> Preis in Echtzeit</span>
                    <span><span class="dot">●</span> Druckdaten-Freigabe inklusive</span>
                    <span><span class="dot">●</span> Versand LU &amp; international</span>
                </div>
            </div>
            <div class="hero-art"><?= $shirt ?></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <h2>Unsere Produkte</h2>
        <p class="muted">Wähle ein Produkt und gestalte es im Konfigurator.</p>
        <?php if (empty($products)): ?>
            <div class="card"><p class="muted">Aktuell sind keine Produkte verfügbar.</p></div>
        <?php else: ?>
            <div class="grid products mt">
                <?php foreach ($products as $p): ?>
                    <div class="card product-card">
                        <div class="product-thumb"><?= $shirt ?></div>
                        <div class="product-body">
                            <h3><?= e((string) $p['name']) ?></h3>
                            <span class="product-type"><?= e((string) $p['product_type']) ?></span>
                            <a class="btn block" href="/konfigurator/<?= e((string) $p['public_id']) ?>">Konfigurieren</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
