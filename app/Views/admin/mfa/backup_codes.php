<?php
/** @var array<int,string> $codes */
?>
<h1>Backup-Codes</h1>
<div class="alert ok">Zwei-Faktor-Authentifizierung aktiviert.</div>

<div class="card">
    <h2>Deine Backup-Codes</h2>
    <p class="muted">Bewahre diese Codes sicher auf. Jeder Code funktioniert <strong>einmal</strong>, falls du keinen Zugriff auf die App hast. Sie werden <strong>nur jetzt</strong> angezeigt.</p>
    <ul class="backup-codes">
        <?php foreach ($codes as $c): ?>
            <li><code><?= e($c) ?></code></li>
        <?php endforeach; ?>
    </ul>
    <p><a class="btn" href="/admin/mfa">Fertig</a></p>
</div>
