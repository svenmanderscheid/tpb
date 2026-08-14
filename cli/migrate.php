<?php
declare(strict_types=1);

/**
 * Migrationsrunner (§5.1).
 * - legt schema_migrations an, falls fehlend
 * - führt migrations/NNN_*.sql in Reihenfolge aus, überspringt angewandte
 * - bricht ab, wenn der Checksum einer angewandten Datei abweicht (§14.5)
 * - --status: listet offen/angewandt · --dry-run: zeigt SQL, ohne auszuführen
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Db;

$argv = $_SERVER['argv'] ?? [];
$status = cli_has($argv, 'status');
$dryRun = cli_has($argv, 'dry-run');

$dir = dirname(__DIR__) . '/migrations';

ensureMigrationsTable();

$applied = loadApplied();          // version => checksum
$files = discoverMigrations($dir); // version => path

// Checksum-Integrität angewandter Migrationen prüfen.
foreach ($applied as $version => $checksum) {
    if (isset($files[$version])) {
        $current = hash_file('sha256', $files[$version]);
        if (!hash_equals($checksum, (string) $current)) {
            fwrite(STDERR, "FEHLER: Checksum von Migration {$version} weicht ab – Migrationen sind unveränderlich (§14.5).\n");
            exit(1);
        }
    }
}

if ($status) {
    printStatus($files, $applied);
    exit(0);
}

$pending = array_diff_key($files, $applied);
if ($pending === []) {
    echo "Keine offenen Migrationen.\n";
    exit(0);
}

foreach ($pending as $version => $path) {
    $sql = (string) file_get_contents($path);
    $checksum = hash('sha256', $sql);
    $statements = splitStatements($sql);

    if ($dryRun) {
        echo "--- {$version} (" . count($statements) . " Statements, dry-run) ---\n";
        foreach ($statements as $s) {
            echo $s . ";\n";
        }
        continue;
    }

    echo "Wende Migration {$version} an ... ";
    $pdo = Db::pdo();
    foreach ($statements as $s) {
        $pdo->exec($s);
    }
    Db::run(
        'INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (?, ?, UTC_TIMESTAMP())',
        [$version, $checksum]
    );
    echo "OK\n";
}

echo "Fertig.\n";
exit(0);

// ---------------------------------------------------------------------------

function ensureMigrationsTable(): void
{
    Db::pdo()->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(8) PRIMARY KEY,
            checksum CHAR(64) NOT NULL,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** @return array<string,string> version => checksum */
function loadApplied(): array
{
    $rows = Db::run('SELECT version, checksum FROM schema_migrations')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(string) $r['version']] = (string) $r['checksum'];
    }
    ksort($out);
    return $out;
}

/** @return array<string,string> version => path */
function discoverMigrations(string $dir): array
{
    $out = [];
    foreach (glob($dir . '/*.sql') ?: [] as $path) {
        $base = basename($path);
        if (preg_match('/^(\d{3,8})_/', $base, $m) === 1) {
            $out[$m[1]] = $path;
        }
    }
    ksort($out);
    return $out;
}

/** @return string[] */
function splitStatements(string $sql): array
{
    $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
    $buffer = '';
    $out = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), ';')) {
            $out[] = rtrim(trim($buffer), ';');
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $out[] = rtrim(trim($buffer), ';');
    }
    return $out;
}

/**
 * @param array<string,string> $files
 * @param array<string,string> $applied
 */
function printStatus(array $files, array $applied): void
{
    echo "Version   Status\n";
    echo "-------   ------\n";
    $all = array_unique(array_merge(array_keys($files), array_keys($applied)));
    sort($all);
    foreach ($all as $version) {
        $isApplied = isset($applied[$version]);
        $missing = !isset($files[$version]);
        $label = $isApplied ? 'angewandt' : 'offen';
        if ($missing) {
            $label .= ' (Datei fehlt!)';
        }
        printf("%-9s %s\n", $version, $label);
    }
}
