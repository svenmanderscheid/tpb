<?php
declare(strict_types=1);

/**
 * Backup vor jeder Migration (§5.1, §14.3): mysqldump der DB (gzip) +
 * tar.gz von private/. Kein Down-Migrations-Mechanismus – Rollback = Restore.
 *
 * Aufruf: php cli/backup.php
 * Restore (Beispiel):
 *   gzip -dc backup.sql.gz | mysql -u root tpb_dev
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Env;

$root = dirname(__DIR__);
$backupDir = $root . '/private/tpb/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0770, true);
}

$stamp = gmdate('Ymd-His');
$dbName = Env::require('DB_NAME');

// --- 1) DB-Dump -----------------------------------------------------------
$dumpBin = locate_mysqldump();
$sqlFile = "{$backupDir}/{$dbName}-{$stamp}.sql";

$cmd = sprintf(
    '%s --host=%s --user=%s %s --single-transaction --routines --events --default-character-set=utf8mb4 %s',
    escapeshellarg($dumpBin),
    escapeshellarg(Env::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1'),
    escapeshellarg(Env::get('DB_USER', 'root') ?? 'root'),
    password_arg(Env::get('DB_PASS', '') ?? ''),
    escapeshellarg($dbName)
);

$fh = fopen($sqlFile, 'wb');
if ($fh === false) {
    fwrite(STDERR, "Kann Dump-Datei nicht schreiben: {$sqlFile}\n");
    exit(1);
}
$proc = proc_open($cmd, [1 => $fh, 2 => ['pipe', 'w']], $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "mysqldump konnte nicht gestartet werden.\n");
    exit(1);
}
$err = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$code = proc_close($proc);
fclose($fh);

if ($code !== 0) {
    fwrite(STDERR, "mysqldump fehlgeschlagen (Code {$code}): {$err}\n");
    @unlink($sqlFile);
    exit(1);
}

// gzip über PHP (kein externes gzip nötig).
$gzFile = $sqlFile . '.gz';
file_put_contents($gzFile, gzencode((string) file_get_contents($sqlFile), 9));
unlink($sqlFile);
printf("DB-Dump: %s (%d Bytes)\n", basename($gzFile), filesize($gzFile));

// --- 2) private/-Archiv (ohne backups/) -----------------------------------
$tarBase = "{$backupDir}/private-{$stamp}.tar";
@unlink($tarBase);
@unlink($tarBase . '.gz');

$phar = new PharData($tarBase);
$privateDir = $root . '/private';
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($privateDir, FilesystemIterator::SKIP_DOTS)
);
$count = 0;
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $full = $file->getPathname();
    $local = ltrim(str_replace('\\', '/', substr($full, strlen($privateDir))), '/');
    if (str_starts_with($local, 'tpb/backups/')) {
        continue; // eigene Backups nicht mitsichern
    }
    $phar->addFile($full, $local);
    $count++;
}
if ($count === 0) {
    // PharData verlangt mindestens einen Eintrag.
    $phar->addFromString('.backup-manifest', "private/ war leer am {$stamp}\n");
}
$phar->compress(Phar::GZ);
unset($phar);
@unlink($tarBase);
printf("private-Archiv: %s (%d Dateien)\n", basename($tarBase . '.gz'), $count);

echo "Backup abgeschlossen.\n";
exit(0);

// ---------------------------------------------------------------------------

function locate_mysqldump(): string
{
    $env = getenv('MYSQLDUMP');
    if (is_string($env) && $env !== '' && is_file($env)) {
        return $env;
    }
    $candidates = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
    ];
    foreach ($candidates as $c) {
        if (is_file($c)) {
            return $c;
        }
    }
    return 'mysqldump'; // aus PATH
}

function password_arg(string $pass): string
{
    // Leeres Passwort: kein --password-Argument (XAMPP-Standard).
    return $pass === '' ? '' : '--password=' . escapeshellarg($pass);
}
