<?php
declare(strict_types=1);

// Gemeinsamer Bootstrap für alle CLI-Skripte.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';
\Tpb\Core\Env::load(dirname(__DIR__) . '/.env');

/**
 * Liest einen benannten CLI-Schalter (--key=value oder --flag).
 * @param array<int,string> $argv
 */
function cli_opt(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if ($arg === '--' . $name) {
            return '';
        }
        if (str_starts_with($arg, '--' . $name . '=')) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return $default;
}

/** @param array<int,string> $argv */
function cli_has(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true)
        || cli_opt($argv, $name) !== null;
}
