<?php
/**
 * Öffentliches Storefront-Layout (Kundenseite). Getrennt vom Admin-Layout.
 * @var string $content
 * @var string $title
 */
$pageTitle = isset($title) ? (string) $title : 'The Printing Brothers';
$footerYear = (int) (\Tpb\Core\Clock::nowUtc()->format('Y'));
// PLATZHALTER-Firmenzeile (Impressum/Rechtsform folgen aus business_settings, siehe OFFENE-FRAGEN.md).
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> – The Printing Brothers</title>
    <meta name="description" content="Individuell bedruckte Textilien aus Luxemburg – vom Einzelstück bis zur Vereinsausstattung.">
    <link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
    <header class="site-header">
        <div class="bar">
            <a class="site-logo" href="/" aria-label="The Printing Brothers – Startseite">
                <img src="/assets/img/logo.svg" alt="The Printing Brothers">
            </a>
            <nav class="site-nav">
                <a href="/produkte">Produkte</a>
                <a href="/anfrage">Vereine &amp; Großbestellung</a>
                <a class="cta" href="/produkte">Jetzt gestalten</a>
            </nav>
        </div>
    </header>

    <main class="site">
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <div>
                <div class="brandline">The Printing Brothers</div>
                <p class="muted small">Individuell bedruckte Textilien aus Luxemburg – vom Einzelstück bis zur Vereinsausstattung.</p>
            </div>
            <div>
                <h3>Rechtliches</h3>
                <nav class="footer-links">
                    <a href="/rechtliches/agb">AGB</a>
                    <a href="/rechtliches/widerruf">Widerruf</a>
                    <a href="/rechtliches/datenschutz">Datenschutz</a>
                    <a href="/rechtliches/datei">Dateispezifikation</a>
                </nav>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="wrap">
                <span>© <?= e((string) $footerYear) ?> The Printing Brothers</span>
                <span class="muted">Preise inkl. gesetzl. Steuern</span>
            </div>
        </div>
    </footer>
</body>
</html>
