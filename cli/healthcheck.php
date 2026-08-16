<?php
declare(strict_types=1);

/**
 * Täglicher Health-Check (§ M7). Prüft DB, letztes Backup, fehlgeschlagene Outbox,
 * Speicherplatz und Quarantäne-Stau. Verschickt eine Mail NUR bei Problemen – plus
 * eine Montags-Zusammenfassung. Exit-Code 1 bei Problemen (für Cron-Alerting).
 *
 * Aufruf: php cli/healthcheck.php
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Domain\Ops\HealthCheck;
use Tpb\Domain\Outbox\Outbox;

$result = HealthCheck::full();

$lines = [];
foreach ($result['checks'] as $c) {
    $lines[] = sprintf('[%s] %-10s %s', $c['ok'] ? 'OK ' : '!! ', $c['name'], $c['detail']);
}
$report = implode("\n", $lines);
echo $report . "\n";

$isMonday = Clock::nowUtc()->format('N') === '1';
if (!$result['ok'] || $isMonday) {
    $to = ownerEmail();
    if ($to !== '') {
        $subject = $result['ok'] ? 'TPB Health – Wochenübersicht (alles ok)' : 'TPB Health – Problem festgestellt';
        Outbox::enqueue('mail.health', [
            'to'      => $to,
            'subject' => $subject,
            'text'    => "Health-Check " . Clock::nowUtcSeconds() . " UTC\n\n" . $report . "\n",
        ]);
        echo "Mail eingereiht an {$to}.\n";
    }
}

exit($result['ok'] ? 0 : 1);

function ownerEmail(): string
{
    $row = Db::run("SELECT email FROM users WHERE role = 'owner' AND status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($row !== false) {
        return (string) $row;
    }
    return (string) (Env::get('MAIL_FROM', '') ?? '');
}
