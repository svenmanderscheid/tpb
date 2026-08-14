<?php
/** @var string $displayName */
/** @var string $role */
?>
<h1>Dashboard</h1>
<div class="card">
    <p>Willkommen, <strong><?= e($displayName) ?></strong>.</p>
    <p class="muted">Angemeldet als Rolle <span class="badge"><?= e($role) ?></span>.</p>
</div>
<div class="card">
    <h2>Fundament M0</h2>
    <p class="muted">
        Dies ist das leere Backoffice-Dashboard aus Meilenstein M0. Katalog, Preise,
        Angebote und Produktion folgen in den nächsten Meilensteinen.
    </p>
    <p><a href="/admin/assets">Asset-Upload (Quarantäne &amp; Preflight)</a></p>
</div>
