<?php
/**
 * @var array<string,mixed> $job
 * @var array<string,mixed>|false $order
 * @var array<string,mixed>|false $item
 */
$labels = ['BLOCKED' => 'Blockiert', 'READY' => 'Freigegeben', 'IN_PROGRESS' => 'In Arbeit', 'QUALITY_CHECK' => 'Qualität', 'REWORK' => 'Nacharbeit', 'DONE' => 'Fertig', 'SCRAPPED' => 'Ausschuss'];
$status = (string) $job['status'];
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Job <?= e((string) $job['job_number']) ?> – The Printing Brothers</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <main class="container">
        <div class="card">
            <h1>Job <?= e((string) $job['job_number']) ?>
                <span class="status-badge <?= e(strtolower($status)) ?>"><?= e($labels[$status] ?? $status) ?></span>
            </h1>
            <p>Auftrag: <strong><?= $order !== false ? e((string) $order['order_number']) : '' ?></strong></p>
            <p><?= $item !== false ? e((string) $item['description']) : '' ?> · Menge <?= $item !== false ? e((string) $item['qty']) : '' ?></p>
            <p class="muted">Route: <?= e((string) $job['route']) ?></p>
            <p class="muted">Zur Bearbeitung im Backoffice anmelden.</p>
        </div>
    </main>
</body>
</html>
