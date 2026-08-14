<?php
/** @var string $content */
/** @var string $title */

use Tpb\Core\Auth;
use Tpb\Core\Csrf;

$pageTitle = isset($title) ? (string) $title : 'Admin';
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <header class="topbar">
        <div class="brand">The Printing Brothers</div>
        <div>
            <span class="user"><?= e(Auth::displayName() ?? '') ?> · <?= e(Auth::role() ?? '') ?></span>
            <form method="post" action="/admin/logout">
                <?= Csrf::field() ?>
                <button type="submit" class="btn secondary">Abmelden</button>
            </form>
        </div>
    </header>
    <main class="container">
        <?= $content ?>
    </main>
</body>
</html>
