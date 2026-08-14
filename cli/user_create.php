<?php
declare(strict_types=1);

/**
 * Legt einen Benutzer an – der erste Owner wird ausschließlich hierüber erzeugt,
 * kein Web-Bootstrap (§5.2, DoD M0). Passwort wird interaktiv abgefragt.
 *
 * Aufruf: php cli/user_create.php --role=owner --email=... --name="..."
 *   optional --password=... (nicht interaktiv, z. B. Seeds/Tests)
 */

require __DIR__ . '/_bootstrap.php';

use Tpb\Core\Authz;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Audit\Audit;

$argv = $_SERVER['argv'] ?? [];

$role = (string) (cli_opt($argv, 'role') ?? '');
$email = strtolower(trim((string) (cli_opt($argv, 'email') ?? '')));
$name = trim((string) (cli_opt($argv, 'name') ?? ''));
$password = cli_opt($argv, 'password');

if (!in_array($role, Authz::ROLES, true)) {
    fwrite(STDERR, "Ungültige oder fehlende Rolle. Erlaubt: " . implode(', ', Authz::ROLES) . "\n");
    exit(1);
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Ungültige oder fehlende E-Mail (--email=...).\n");
    exit(1);
}
if ($name === '') {
    fwrite(STDERR, "Anzeigename fehlt (--name=\"...\").\n");
    exit(1);
}

$exists = Db::run('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])->fetchColumn();
if ($exists !== false) {
    fwrite(STDERR, "Ein Benutzer mit dieser E-Mail existiert bereits.\n");
    exit(1);
}

if ($password === null) {
    $password = prompt_password();
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Passwort zu kurz (mindestens 12 Zeichen).\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$now = Clock::nowUtcSeconds();

Db::run(
    'INSERT INTO users (email, pass_hash, display_name, role, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)',
    [$email, $hash, $name, $role, 'active', $now, $now]
);
$id = (int) Db::pdo()->lastInsertId();

Audit::log('user', (string) $id, 'user.created', [
    'actor_label' => 'cli',
    'metadata'    => ['role' => $role, 'email' => $email],
]);

echo "Benutzer #{$id} ({$role}) angelegt: {$email}\n";
exit(0);

// ---------------------------------------------------------------------------

function prompt_password(): string
{
    fwrite(STDOUT, "Passwort (min. 12 Zeichen): ");
    // Echo-Unterdrückung ist plattformabhängig; unter Windows lesen wir Klartext.
    if (DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
        @shell_exec('stty -echo 2>/dev/null');
    }
    $line = fgets(STDIN);
    if (DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
        @shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    }
    return rtrim((string) $line, "\r\n");
}
