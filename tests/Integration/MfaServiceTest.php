<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Domain\Auth\MfaService;
use Tpb\Domain\Auth\Totp;

/**
 * MFA-Aktivierung, Login-Verifikation (TOTP + Backup-Code einmalig), Deaktivierung.
 */
final class MfaServiceTest extends TestCase
{
    private int $userId;

    private function cleanAll(): void
    {
        Db::run('DELETE FROM mfa_backup_codes');
        Db::run("DELETE FROM users WHERE email = 'mfa@example.com'");
    }

    protected function tearDown(): void
    {
        $this->cleanAll();
    }

    protected function setUp(): void
    {
        $this->cleanAll();
        $now = Clock::nowUtcSeconds();
        Db::run("INSERT INTO users (email, pass_hash, display_name, role, status, created_at, updated_at) VALUES ('mfa@example.com', ?, 'MFA', 'owner', 'active', ?, ?)", [password_hash('x', PASSWORD_DEFAULT), $now, $now]);
        $this->userId = (int) Db::run("SELECT id FROM users WHERE email = 'mfa@example.com'")->fetchColumn();
    }

    public function testEnableThenVerifyTotp(): void
    {
        $secret = Totp::generateSecret();
        self::assertFalse(MfaService::isEnabled($this->userId));

        $codes = MfaService::enable($this->userId, $secret, Totp::code($secret));
        self::assertCount(8, $codes);
        self::assertTrue(MfaService::isEnabled($this->userId));

        self::assertTrue(MfaService::verifyLogin($this->userId, Totp::code($secret)));
        self::assertFalse(MfaService::verifyLogin($this->userId, '000000'));
    }

    public function testEnableRejectsWrongCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MfaService::enable($this->userId, Totp::generateSecret(), '000000');
    }

    public function testBackupCodeWorksOnce(): void
    {
        $secret = Totp::generateSecret();
        $codes = MfaService::enable($this->userId, $secret, Totp::code($secret));
        $backup = $codes[0];

        self::assertSame(8, MfaService::unusedBackupCount($this->userId));
        self::assertTrue(MfaService::verifyLogin($this->userId, $backup));   // erste Nutzung ok
        self::assertSame(7, MfaService::unusedBackupCount($this->userId));
        self::assertFalse(MfaService::verifyLogin($this->userId, $backup));  // zweite Nutzung abgewiesen
    }

    public function testDisableClearsSecretAndCodes(): void
    {
        $secret = Totp::generateSecret();
        MfaService::enable($this->userId, $secret, Totp::code($secret));
        MfaService::disable($this->userId, $this->userId);

        self::assertFalse(MfaService::isEnabled($this->userId));
        self::assertNull(MfaService::secretFor($this->userId));
        self::assertSame(0, MfaService::unusedBackupCount($this->userId));
    }
}
