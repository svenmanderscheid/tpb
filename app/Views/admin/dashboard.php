<?php
/**
 * @var string $displayName
 * @var string $role
 * @var array<string,mixed> $today
 */
use Tpb\Core\Money;

$name = static fn (array $r): string => (string) ($r['company_name'] ?? '') !== ''
    ? (string) $r['company_name']
    : trim((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? ''));
?>
<h1>Dashboard</h1>
<div class="card">
    <p>Willkommen, <strong><?= e($displayName) ?></strong>. <span class="muted">Rolle <span class="badge"><?= e($role) ?></span></span></p>
</div>

<h2>Heute</h2>
<div class="kpis">
    <div class="kpi"><div class="label">Wartende Proofs</div><div class="value"><?= (int) $today['waiting_proofs'] ?></div></div>
    <div class="kpi"><div class="label">Offene Bankzeilen</div><div class="value"><?= (int) $today['open_bank_lines'] ?></div></div>
    <div class="kpi"><div class="label">Blockierte Jobs</div><div class="value"><?= (int) $today['blocked_jobs'] ?></div></div>
    <div class="kpi"><div class="label">Ablaufende Angebote</div><div class="value"><?= count($today['expiring_quotes']) ?></div></div>
    <div class="kpi expense"><div class="label">Überfällige Rechnungen</div><div class="value"><?= count($today['overdue_invoices']) ?></div></div>
</div>

<div class="split">
    <div class="card">
        <h2>Ablaufende Angebote</h2>
        <?php if (empty($today['expiring_quotes'])): ?>
            <p class="muted">Keine.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Nummer</th><th>Kunde</th><th>Gültig bis</th></tr></thead>
                <tbody>
                    <?php foreach ($today['expiring_quotes'] as $q): ?>
                        <tr><td><a href="/admin/angebot/<?= e((string) $q['public_id']) ?>"><?= e((string) $q['quote_number']) ?></a></td><td><?= e($name($q)) ?></td><td><?= e((string) $q['valid_until']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Überfällige Rechnungen</h2>
        <?php if (empty($today['overdue_invoices'])): ?>
            <p class="muted">Keine.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Nummer</th><th class="num">Betrag</th><th>Fällig</th></tr></thead>
                <tbody>
                    <?php foreach ($today['overdue_invoices'] as $i): ?>
                        <tr><td><a href="/admin/rechnung/<?= e((string) $i['public_id']) ?>"><?= e((string) $i['invoice_number']) ?></a></td><td class="num"><?= e(Money::format((int) $i['gross_cents'], (string) $i['currency'])) ?></td><td><?= e((string) $i['due_date']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<p class="muted"><a href="/admin/bank">Zum Bankabgleich →</a></p>
