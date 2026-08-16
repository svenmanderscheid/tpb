<?php
/**
 * @var array<string,mixed> $quote
 * @var array<int,array<string,mixed>> $items
 * @var array<string,mixed> $snapshot
 * @var string $token
 * @var array<string,mixed>|null $order
 */
use Tpb\Core\Csrf;
use Tpb\Core\Money;

$status = (string) $quote['status'];
$currency = (string) $quote['currency'];
$statusLabels = ['DRAFT' => 'Entwurf', 'SENT' => 'Offen', 'ACCEPTED' => 'Angenommen', 'DECLINED' => 'Abgelehnt', 'EXPIRED' => 'Abgelaufen', 'CANCELLED' => 'Storniert'];
$publicId = (string) $quote['public_id'];
$tokenQ = rawurlencode($token);
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Angebot <?= e((string) ($quote['quote_number'] ?? '')) ?> – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <h1>Angebot <?= e((string) ($quote['quote_number'] ?? '')) ?>
            <span class="status-badge <?= e(strtolower($status)) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
        </h1>
        <?php if ($quote['valid_until'] !== null): ?>
            <p class="muted">Gültig bis <?= e((string) $quote['valid_until']) ?></p>
        <?php endif; ?>

        <?php if ($status === 'ACCEPTED' && $order !== null): ?>
            <div class="alert ok">Sie haben dieses Angebot angenommen. Ihr Auftrag: <strong><?= e((string) $order['order_number']) ?></strong>.</div>
        <?php elseif ($status === 'DECLINED'): ?>
            <div class="alert error">Dieses Angebot wurde abgelehnt.</div>
        <?php elseif ($status === 'EXPIRED'): ?>
            <div class="alert error">Dieses Angebot ist abgelaufen. Bitte fordern Sie ein neues an.</div>
        <?php endif; ?>

        <div class="card">
            <table>
                <thead><tr><th>Pos</th><th>Beschreibung</th><th class="num">Menge</th><th class="num">Einzel</th><th class="num">Gesamt</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $l): ?>
                        <tr>
                            <td><?= e((string) $l['pos_no']) ?></td>
                            <td><?= e((string) $l['description']) ?></td>
                            <td class="num"><?= e((string) $l['qty']) ?></td>
                            <td class="num"><?= e(Money::format((int) $l['unit_cents'], $currency)) ?></td>
                            <td class="num"><?= e(Money::format((int) $l['line_cents'], $currency)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="num"><strong>Gesamt</strong></td><td class="num"><strong><?= e(Money::format((int) $quote['total_cents'], $currency)) ?></strong></td></tr>
                </tfoot>
            </table>
            <p class="mt"><a class="btn secondary" href="/angebot/<?= e($publicId) ?>/pdf?t=<?= $tokenQ ?>">Angebot als PDF</a></p>
        </div>

        <?php if ($status === 'SENT'): ?>
            <div class="actions-row">
                <form method="post" action="/angebot/<?= e($publicId) ?>/annehmen">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="t" value="<?= e($token) ?>">
                    <button type="submit" class="btn">Angebot annehmen</button>
                </form>
                <form method="post" action="/angebot/<?= e($publicId) ?>/ablehnen">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="t" value="<?= e($token) ?>">
                    <button type="submit" class="btn secondary">Ablehnen</button>
                </form>
            </div>
            <p class="muted mt">Mit der Annahme kommt ein verbindlicher Auftrag zustande.</p>
        <?php endif; ?>
    </main>
</body>
</html>
