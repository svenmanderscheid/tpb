<?php
/**
 * @var bool $enabled
 * @var bool $required
 * @var string|null $secret
 * @var int $backup_left
 * @var array{type:string,text:string}|null $flash
 */
use Tpb\Core\Csrf;
?>
<h1>Zwei-Faktor-Authentifizierung</h1>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<?php if ($required && !$enabled): ?>
    <div class="alert error">Für deine Rolle ist die Zwei-Faktor-Authentifizierung verpflichtend. Bitte jetzt einrichten.</div>
<?php endif; ?>

<div class="card">
    <?php if ($enabled): ?>
        <h2>Aktiv</h2>
        <p>Die Zwei-Faktor-Authentifizierung ist für dein Konto aktiviert. Verbleibende Backup-Codes: <strong><?= (int) $backup_left ?></strong>.</p>
        <form method="post" action="/admin/mfa/deaktivieren" data-confirm="Zwei-Faktor-Authentifizierung wirklich deaktivieren?">
            <?= Csrf::field() ?>
            <button type="submit" class="btn secondary">Deaktivieren</button>
        </form>
    <?php else: ?>
        <h2>Einrichten</h2>
        <ol>
            <li>Scanne den QR-Code mit einer Authenticator-App (z. B. Google Authenticator, Aegis, 1Password).</li>
            <li>Oder gib das Secret manuell ein: <code><?= e((string) $secret) ?></code></li>
            <li>Gib den angezeigten 6-stelligen Code zur Bestätigung ein.</li>
        </ol>
        <p><img src="/admin/mfa/qr" alt="QR-Code" width="180" height="180"></p>
        <form method="post" action="/admin/mfa/aktivieren" autocomplete="off">
            <?= Csrf::field() ?>
            <div class="field"><label for="code">6-stelliger Code</label><input type="text" id="code" name="code" inputmode="numeric" required></div>
            <button type="submit" class="btn">Aktivieren</button>
        </form>
    <?php endif; ?>
</div>
