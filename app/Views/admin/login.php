<?php
/** @var string|null $error */

use Tpb\Core\Csrf;
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anmelden – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="login-wrap">
        <div class="brand">The Printing Brothers</div>
        <div class="card">
            <h1>Anmelden</h1>
            <?php if (!empty($error)): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" action="/admin/login" autocomplete="off">
                <?= Csrf::field() ?>
                <div class="field">
                    <label for="email">E-Mail</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Anmelden</button>
            </form>
        </div>
    </div>
</body>
</html>
