<?php
declare(strict_types=1);

/**
 * Seed-Runner (§13). NUR in APP_ENV=local|test.
 * Aufruf: php cli/seed.php --scenario=<n>
 *
 * Die fachlichen Szenarien (§13.2: 1,2,5,6,9, Nachtrag, Shop, gemischt) werden
 * ab M1 befüllt, sobald Katalog/Preis-Engine stehen. M0 liefert nur das
 * Grundgerüst inkl. Umgebungs-Guard und Dispatcher.
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Env;

$env = Env::get('APP_ENV', 'local');
if (!in_array($env, ['local', 'test'], true)) {
    fwrite(STDERR, "Seeds sind nur in APP_ENV=local|test erlaubt (aktuell: {$env}).\n");
    exit(1);
}

$argv = $_SERVER['argv'] ?? [];
$scenario = cli_opt($argv, 'scenario');

/**
 * Registry der geplanten Szenarien (§13.2). Callable folgt ab dem jeweiligen
 * Meilenstein; bis dahin dokumentierter Platzhalter (TODO §15/Meilenstein).
 * @var array<int,string> $scenarios
 */
$scenarios = [
    1 => 'Privatkunde: 20 T-Shirts, ein Logo (ab M1/M2)',
    2 => 'Verein: Größenmatrix, Sponsor hinten, Namen im Nacken (ab M2)',
    5 => 'Proof v1 -> v2 (ab M4)',
    6 => 'Anzahlung/Teilzahlung (ab M3/M6)',
    9 => 'Rechnung + Gutschrift (ab M6)',
];

if ($scenario === null) {
    echo "Verfügbare Szenarien (§13.2):\n";
    foreach ($scenarios as $n => $desc) {
        echo "  --scenario={$n}  {$desc}\n";
    }
    echo "\nHinweis: In M0 sind noch keine Szenarien implementiert (kein Katalog).\n";
    exit(0);
}

$n = (int) $scenario;
if (!isset($scenarios[$n])) {
    fwrite(STDERR, "Unbekanntes Szenario: {$n}\n");
    exit(1);
}

fwrite(STDERR, "Szenario {$n} ({$scenarios[$n]}) ist in M0 noch nicht implementiert.\n");
exit(2);
