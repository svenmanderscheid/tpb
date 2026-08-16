<?php
/** @var string $content */
/** @var string $title */

use Tpb\Core\Auth;
use Tpb\Core\Authz;
use Tpb\Core\Csrf;

$pageTitle = isset($title) ? (string) $title : 'Admin';
$activeNav = $nav ?? '';
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
        <nav class="mainnav">
            <a href="/admin" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <?php if (Authz::can('tpb_manage_quotes')): ?>
                <a href="/admin/anfragen" class="<?= $activeNav === 'quotes' ? 'active' : '' ?>">Angebote</a>
            <?php endif; ?>
            <?php if (Authz::can('tpb_manage_pricing')): ?>
                <a href="/admin/katalog" class="<?= $activeNav === 'catalog' ? 'active' : '' ?>">Katalog</a>
            <?php endif; ?>
            <?php if (Authz::can('tpb_manage_pricing')): ?>
                <a href="/admin/preisbuecher" class="<?= $activeNav === 'pricing' ? 'active' : '' ?>">Preise</a>
            <?php endif; ?>
            <?php if (Authz::can('tpb_manage_artwork')): ?>
                <a href="/admin/assets" class="<?= $activeNav === 'assets' ? 'active' : '' ?>">Assets</a>
            <?php endif; ?>
            <?php if (Authz::can('tpb_view_costs')): ?>
                <a href="/admin/finanzen" class="<?= $activeNav === 'finance' ? 'active' : '' ?>">Finanzen</a>
            <?php endif; ?>
        </nav>
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
    <script type="module" src="/assets/js/admin.js"></script>
</body>
</html>
