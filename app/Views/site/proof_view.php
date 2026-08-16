<?php
/**
 * @var array<string,mixed> $order
 * @var array<string,mixed> $proof
 * @var array<int,array<string,mixed>> $items
 * @var string $token
 */
use Tpb\Core\Csrf;

$publicId = (string) $order['public_id'];
$tokenQ = rawurlencode($token);
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proof zu Auftrag <?= e((string) $order['order_number']) ?> – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <h1>Druckfreigabe – Auftrag <?= e((string) $order['order_number']) ?>
            <span class="status-badge sent">Proof v<?= e((string) $proof['version_no']) ?></span>
        </h1>
        <p class="muted">Bitte prüfen Sie Motivposition und Maße. Mit der Freigabe bestätigen Sie die Produktionsvorgaben verbindlich.</p>

        <?php foreach ($items as $it): ?>
            <?php $config = $it['config_snapshot_json'] !== null ? json_decode((string) $it['config_snapshot_json'], true) : null; $layers = is_array($config) ? ($config['layers'] ?? []) : []; ?>
            <div class="card">
                <h2><?= e((string) $it['description']) ?> — <?= e((string) $it['qty']) ?> Stück</h2>
                <?php if (empty($layers)): ?>
                    <p class="muted">Keine Druckposition hinterlegt.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Position</th><th>Art</th><th>Inhalt</th><th class="num">B mm</th><th class="num">H mm</th><th class="num">X mm</th><th class="num">Y mm</th></tr></thead>
                        <tbody>
                            <?php foreach ($layers as $l): ?>
                                <tr>
                                    <td><?= e((string) ($l['placement_code'] ?? '')) ?></td>
                                    <td><?= e((string) ($l['layer_type'] ?? '')) ?></td>
                                    <td><?= e((string) ($l['text_content'] ?? ($l['color_name'] ?? '—'))) ?></td>
                                    <td class="num"><?= e((string) ($l['width_mm'] ?? '')) ?></td>
                                    <td class="num"><?= e((string) ($l['height_mm'] ?? '')) ?></td>
                                    <td class="num"><?= e((string) ($l['offset_x_mm'] ?? '')) ?></td>
                                    <td class="num"><?= e((string) ($l['offset_y_mm'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <p><a class="btn secondary" href="/proof/<?= e($publicId) ?>/pdf?t=<?= $tokenQ ?>">Proof als PDF</a></p>

        <div class="card">
            <h2>Ihre Entscheidung</h2>
            <div class="actions-row">
                <form method="post" action="/proof/<?= e($publicId) ?>/freigeben">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="t" value="<?= e($token) ?>">
                    <button type="submit" class="btn">Freigeben</button>
                </form>
            </div>
            <form method="post" action="/proof/<?= e($publicId) ?>/aenderung" class="mt">
                <?= Csrf::field() ?>
                <input type="hidden" name="t" value="<?= e($token) ?>">
                <div class="field">
                    <label for="comment">Änderungswunsch (optional)</label>
                    <input type="text" id="comment" name="comment">
                </div>
                <button type="submit" class="btn secondary">Änderung anfordern</button>
            </form>
        </div>
    </main>
</body>
</html>
