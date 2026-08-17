<?php
/**
 * @var array<int,array<string,mixed>> $docs
 * @var array<string,mixed>|null $doc
 */
$labels = ['agb' => 'AGB', 'widerruf' => 'Widerruf', 'datenschutz' => 'Datenschutz', 'datei' => 'Dateispezifikation'];
?>
<section class="section">
    <div class="wrap">
        <a class="back-link" href="/">← Startseite</a>
        <h1>Rechtliches</h1>

        <div class="hero-actions">
            <?php foreach ($docs as $d): $t = (string) $d['doc_type']; ?>
                <a class="btn secondary" href="/rechtliches/<?= e($t) ?>"><?= e($labels[$t] ?? $t) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($doc !== null): ?>
            <div class="card mt prose">
                <h2><?= e($labels[(string) $doc['doc_type']] ?? (string) $doc['doc_type']) ?> <span class="status-badge">Version <?= e((string) $doc['version']) ?></span></h2>
                <pre class="legal-text"><?= e((string) $doc['content']) ?></pre>
            </div>
        <?php elseif (empty($docs)): ?>
            <div class="card mt"><p class="muted">Es sind derzeit keine Rechtstexte veröffentlicht.</p></div>
        <?php else: ?>
            <div class="card mt"><p class="muted">Bitte oben einen Rechtstext auswählen.</p></div>
        <?php endif; ?>
    </div>
</section>
