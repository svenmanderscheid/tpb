<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Auth\Sudo;

/**
 * sudo-Modus (§11): Bestätigung mit Passwort, 5-Minuten-Fenster, Ablauf.
 */
final class SudoTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        $_SESSION = [];
        Db::run("DELETE FROM users WHERE email = 'sudo@example.com'");
        $now = Clock::nowUtcSeconds();
        Db::run("INSERT INTO users (email, pass_hash, display_name, role, status, created_at, updated_at) VALUES ('sudo@example.com', ?, 'S', 'owner', 'active', ?, ?)", [password_hash('geheim123', PASSWORD_DEFAULT), $now, $now]);
        $this->userId = (int) Db::run("SELECT id FROM users WHERE email = 'sudo@example.com'")->fetchColumn();
    }

    protected function tearDown(): void
    {
        Db::run("DELETE FROM users WHERE email = 'sudo@example.com'");
        $_SESSION = [];
    }

    public function testNotFreshInitially(): void
    {
        self::assertFalse(Sudo::isFresh());
    }

    public function testWrongPasswordRejected(): void
    {
        self::assertFalse(Sudo::confirm($this->userId, 'falsch'));
        self::assertFalse(Sudo::isFresh());
    }

    public function testCorrectPasswordEstablishesSudo(): void
    {
        self::assertTrue(Sudo::confirm($this->userId, 'geheim123'));
        self::assertTrue(Sudo::isFresh());
    }

    public function testSudoExpiresAfterWindow(): void
    {
        Sudo::confirm($this->userId, 'geheim123');
        self::assertTrue(Sudo::isFresh());
        $_SESSION['sudo_at'] = time() - 400; // > 5 min
        self::assertFalse(Sudo::isFresh());
    }
}
