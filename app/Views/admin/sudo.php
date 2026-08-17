<?php
/**
 * sudo-Modus-Bestätigung (§11). Sendet die ursprüngliche Aktion erneut an dieselbe URL,
 * ergänzt um Passwort/TOTP. Die übrigen POST-Felder werden als Hidden-Felder gespiegelt.
 *
 * @var string $action
 * @var array<string,string> $fields
 * @var string|null $error
 */
use Tpb\Core\Csrf;
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bestätigung erforderlich – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="login-wrap">
        <div class="brand">The Printing Brothers</div>
        <div class="card">
            <h1>Kritische Aktion bestätigen</h1>
            <p class="muted">Diese Aktion ist unumkehrbar oder sicherheitsrelevant. Bitte bestätige mit deinem Passwort<?php /* oder MFA-Code */ ?> (oder MFA-Code, falls aktiv). Die Bestätigung gilt 5 Minuten.</p>
            <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="<?= e($action) ?>" autocomplete="off">
                <?= Csrf::field() ?>
                <?php foreach ($fields as $k => $v): ?>
                    <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                <?php endforeach; ?>
                <div class="field">
                    <label for="_sudo_password">Passwort oder MFA-Code</label>
                    <input type="password" id="_sudo_password" name="_sudo_password" required autofocus>
                </div>
                <button type="submit" class="btn">Bestätigen &amp; fortfahren</button>
            </form>
        </div>
    </div>
</body>
</html>
