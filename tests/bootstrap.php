<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tpb\Core\Db;
use Tpb\Core\Env;
use Tpb\Core\View;

// .env laden, dann fest auf die Test-DB umbiegen (§13).
Env::load(dirname(__DIR__) . '/.env');
Env::set('APP_ENV', 'test');
Env::set('DB_NAME', 'tpb_test');
View::base(dirname(__DIR__) . '/app/Views');
Db::reset();

// Migrationen in tpb_test anwenden (idempotent).
$pdo = Db::pdo();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(8) PRIMARY KEY,
        checksum CHAR(64) NOT NULL,
        applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip(array_map('strval', $applied));

$files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
sort($files);
foreach ($files as $path) {
    if (!preg_match('/(\d{3,8})_/', basename($path), $m)) {
        continue;
    }
    $version = $m[1];
    if (isset($applied[$version])) {
        continue;
    }
    $sql = (string) file_get_contents($path);
    foreach (tpb_test_split($sql) as $stmt) {
        $pdo->exec($stmt);
    }
    $ins = $pdo->prepare('INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (?, ?, UTC_TIMESTAMP())');
    $ins->execute([$version, hash('sha256', $sql)]);
}

/** @return string[] */
function tpb_test_split(string $sql): array
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
