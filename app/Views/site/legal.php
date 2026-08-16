<?php
/**
 * @var array<int,array<string,mixed>> $docs
 * @var array<string,mixed>|null $doc
 */
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rechtliches – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <p><a href="/">← Startseite</a></p>
        <h1>Rechtliches</h1>

        <div class="toolbar">
            <?php foreach ($docs as $d): ?>
                <a class="btn secondary" href="/rechtliches/<?= e((string) $d['doc_type']) ?>"><?= e((string) $d['doc_type']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($doc !== null): ?>
            <div class="card">
                <h2><?= e((string) $doc['doc_type']) ?> <span class="badge quarantine">Version <?= e((string) $doc['version']) ?></span></h2>
                <pre class="legal-text"><?= e((string) $doc['content']) ?></pre>
            </div>
        <?php elseif (empty($docs)): ?>
            <div class="card"><p class="muted">Es sind derzeit keine Rechtstexte veröffentlicht.</p></div>
        <?php else: ?>
            <div class="card"><p class="muted">Bitte oben einen Rechtstext auswählen.</p></div>
        <?php endif; ?>
    </main>
</body>
</html>
