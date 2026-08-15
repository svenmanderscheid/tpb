<?php
/** @var array<int,array<string,mixed>> $techniques */
/** @var array{type:string,text:string}|null $flash */

use Tpb\Core\Csrf;
?>
<h1>Techniken</h1>
<div class="toolbar"><a class="btn secondary" href="/admin/katalog">← Katalog</a></div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e($flash['text']) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Techniken</h2>
    <?php if (empty($techniques)): ?>
        <p class="muted">Noch keine Techniken.</p>
    <?php else: ?>
        <?php foreach ($techniques as $t): ?>
            <form method="post" action="/admin/techniken/<?= e((string) $t['code']) ?>" class="field-row">
                <?= Csrf::field() ?>
                <div class="field"><label>Code</label><input type="text" value="<?= e((string) $t['code']) ?>" readonly></div>
                <div class="field"><label>Name</label><input type="text" name="name" value="<?= e((string) $t['name']) ?>" required maxlength="80"></div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= $t['status'] === 'active' ? 'selected' : '' ?>>aktiv</option>
                        <option value="inactive" <?= $t['status'] === 'inactive' ? 'selected' : '' ?>>inaktiv</option>
                    </select>
                </div>
                <div class="field"><label>&nbsp;</label><button type="submit" class="btn secondary">Speichern</button></div>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2 class="mt">Neue Technik</h2>
    <form method="post" action="/admin/techniken" class="field-row">
        <?= Csrf::field() ?>
        <div class="field"><label>Code</label><input type="text" name="code" required placeholder="FLEX" maxlength="32"></div>
        <div class="field"><label>Name</label><input type="text" name="name" required maxlength="80"></div>
        <div class="field"><label>&nbsp;</label><button type="submit" class="btn">Anlegen</button></div>
    </form>
</div>
