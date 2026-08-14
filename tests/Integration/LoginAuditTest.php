<?php
declare(strict_types=1);

namespace Tpb\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tpb\Core\Auth;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Http\Admin\AuthController;

final class LoginAuditTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSuccessfulLoginWritesAuditAndSession(): void
    {
        Db::run('DELETE FROM audit_events');
        Db::run('DELETE FROM login_attempts');
        Db::run('DELETE FROM users');

        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO users (email, pass_hash, display_name, role, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['chef@tpb.local', password_hash('SehrGeheim2026!', PASSWORD_DEFAULT), 'Chef', 'owner', 'active', $now, $now]
        );
        $uid = (int) Db::pdo()->lastInsertId();

        $_POST = ['email' => 'chef@tpb.local', 'password' => 'SehrGeheim2026!'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        Auth::startSession();
        (new AuthController())->login([]);

        self::assertSame($uid, Auth::id());
        self::assertTrue(Auth::check());

        $count = (int) Db::run(
            "SELECT COUNT(*) FROM audit_events WHERE aggregate_type='user' AND event_type='auth.login' AND aggregate_id=?",
            [(string) $uid]
        )->fetchColumn();
        self::assertSame(1, $count, 'Login muss ein Audit-Event schreiben (DoD M0).');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWrongPasswordDoesNotLogInNorAudit(): void
    {
        Db::run('DELETE FROM audit_events');
        Db::run('DELETE FROM login_attempts');
        Db::run('DELETE FROM users');

        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO users (email, pass_hash, display_name, role, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['chef@tpb.local', password_hash('SehrGeheim2026!', PASSWORD_DEFAULT), 'Chef', 'owner', 'active', $now, $now]
        );

        $_POST = ['email' => 'chef@tpb.local', 'password' => 'falsch'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        Auth::startSession();
        ob_start();
        (new AuthController())->login([]); // rendert bei Fehlschlag die Login-View
        ob_end_clean();

        self::assertFalse(Auth::check());
        $count = (int) Db::run("SELECT COUNT(*) FROM audit_events WHERE event_type='auth.login'")->fetchColumn();
        self::assertSame(0, $count);
    }
}
