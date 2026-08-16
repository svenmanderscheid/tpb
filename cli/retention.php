<?php
declare(strict_types=1);

/**
 * Aufbewahrungs-Job (§8, M7). Löscht fällige Assets je retention_class inkl. Datei und
 * schreibt ein Audit-Event. Referenzierte Assets (FK ON DELETE RESTRICT) werden nie
 * gelöscht – sie werden übersprungen. STANDARD ist Trockenlauf; erst `--apply` löscht.
 *
 * Aufruf: php cli/retention.php [--apply]
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\File\PrivateStorage;

/** Lebensdauer je Aufbewahrungsklasse in Tagen; null = unbegrenzt (nie löschen). */
const RETENTION_DAYS = [
    'temp_30d'       => 30,
    'artwork_short'  => 180,
    'proof_contract' => null,   // Vertragsnachweis – aufbewahren
    'invoice_10y'    => 3650,
];

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$today = Clock::nowUtc()->format('Y-m-d');

$deleted = 0;
$skipped = 0;
$candidates = 0;

foreach (Db::run('SELECT id, public_id, storage_key, retention_class, created_at FROM assets')->fetchAll() as $a) {
    $days = RETENTION_DAYS[$a['retention_class']] ?? null;
    if ($days === null) {
        continue;
    }
    $due = (new DateTimeImmutable((string) $a['created_at'], new DateTimeZone('UTC')))->modify("+{$days} days")->format('Y-m-d');
    if ($due >= $today) {
        continue;
    }
    $candidates++;

    if (!$apply) {
        echo "würde löschen: {$a['public_id']} ({$a['retention_class']}, fällig {$due})\n";
        continue;
    }

    try {
        Db::run('DELETE FROM assets WHERE id = ?', [(int) $a['id']]);
    } catch (\PDOException) {
        $skipped++; // noch referenziert (ON DELETE RESTRICT)
        continue;
    }
    if (PrivateStorage::exists((string) $a['storage_key'])) {
        @unlink(PrivateStorage::path((string) $a['storage_key']));
    }
    Audit::log('asset', (string) $a['public_id'], 'asset.retention_deleted', [
        'actor_label' => 'retention',
        'metadata'    => ['retention_class' => $a['retention_class'], 'due' => $due],
    ]);
    $deleted++;
}

if ($apply) {
    echo "Retention: {$deleted} gelöscht, {$skipped} übersprungen (referenziert).\n";
} else {
    echo "Trockenlauf: {$candidates} fällig. Mit --apply tatsächlich löschen.\n";
}
