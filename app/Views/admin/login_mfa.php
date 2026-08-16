<?php
/** @var string|null $error */
use Tpb\Core\Csrf;
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bestätigung – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="login-wrap">
        <div class="brand">The Printing Brothers</div>
        <div class="card">
            <h1>Zwei-Faktor-Bestätigung</h1>
            <p class="muted">Bitte den 6-stelligen Code aus deiner Authenticator-App eingeben (oder einen Backup-Code).</p>
            <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="/admin/login/mfa" autocomplete="off">
                <?= Csrf::field() ?>
                <div class="field">
                    <label for="code">Code</label>
                    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>
                </div>
                <button type="submit" class="btn">Bestätigen</button>
            </form>
        </div>
    </div>
</body>
</html>
