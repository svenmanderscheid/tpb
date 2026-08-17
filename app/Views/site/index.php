<?php
/** @var array<int,array<string,mixed>> $products */
$shirt = '<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M42 20 L30 28 L20 42 L30 52 L38 46 L38 96 A4 4 0 0 0 42 100 L78 100 A4 4 0 0 0 82 96 L82 46 L90 52 L100 42 L90 28 L78 20 C74 30 46 30 42 20 Z" fill="#e9e8e4" stroke="#c9c8c3" stroke-width="2" stroke-linejoin="round"/><circle cx="60" cy="60" r="12" fill="#ff5a3c" opacity="0.9"/></svg>';
?>
<section class="section">
    <div class="wrap">
        <h1>Produkte konfigurieren</h1>
        <p class="muted">Wähle ein Produkt und gestalte es – der Preis wird live berechnet.</p>
        <?php if (empty($products)): ?>
            <div class="card mt"><p class="muted">Aktuell sind keine Produkte verfügbar.</p></div>
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
