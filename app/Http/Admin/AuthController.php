<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\RateLimit;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Auth\MfaService;

final class AuthController
{
    /** @param array<string,string> $params */
    public function showLogin(array $params): void
    {
        if (Auth::check()) {
            Response::redirect('/admin');
            return;
        }
        Response::html(View::render('admin/login', ['error' => null], null));
    }

    /** @param array<string,string> $params */
    public function login(array $params): void
    {
        $email = strtolower(trim((string) Request::post('email', '')));
        $password = (string) Request::post('password', '');
        $ip = Request::ip();

        if (RateLimit::loginBlocked($email, $ip)) {
            Response::html(
                View::render('admin/login', [
                    'error' => 'Zu viele Fehlversuche. Bitte in einigen Minuten erneut versuchen.',
                ], null),
                429
            );
            return;
        }

        $user = ($email !== '' && $password !== '') ? Auth::attempt($email, $password) : null;

        if ($user === null) {
            RateLimit::recordLogin($email, $ip, false);
            Response::html(
                View::render('admin/login', [
                    'error' => 'E-Mail oder Passwort ist falsch.',
                ], null),
                401
            );
            return;
        }

        RateLimit::recordLogin($email, $ip, true);

        // MFA aktiv ⇒ Passwort reicht nicht: Zwischenzustand + zweiter Faktor.
        if (MfaService::isEnabled((int) $user['id'])) {
            Auth::loginPending((int) $user['id']);
            Response::html(View::render('admin/login_mfa', ['error' => null], null));
            return;
        }

        $this->completeLogin($user);
    }

    /** @param array<string,string> $params */
    public function mfaVerify(array $params): void
    {
        $uid = Auth::pendingUid();
        if ($uid === null) {
            Response::redirect('/admin/login');
            return;
        }
        $code = (string) Request::post('code', '');
        if (!MfaService::verifyLogin($uid, $code)) {
            Audit::log('user', (string) $uid, 'auth.mfa_failed', ['actor_user_id' => $uid]);
            Response::html(View::render('admin/login_mfa', ['error' => 'Code ist ungültig oder abgelaufen.'], null), 401);
            return;
        }
        $user = Db::run('SELECT id, email, display_name, role, status FROM users WHERE id = ? AND status = ? LIMIT 1', [$uid, 'active'])->fetch();
        if ($user === false) {
            Auth::clearPending();
            Response::redirect('/admin/login');
            return;
        }
        Auth::clearPending();
        $this->completeLogin($user);
    }

    /** @param array<string,mixed> $user */
    private function completeLogin(array $user): void
    {
        Auth::login($user);
        Db::run('UPDATE users SET last_login_at = ? WHERE id = ?', [Clock::nowUtcSeconds(), (int) $user['id']]);
        Audit::log('user', (string) $user['id'], 'auth.login', [
            'actor_user_id' => (int) $user['id'],
            'actor_label'   => $user['email'] ?? null,
        ]);

        // Pflicht-MFA (Nicht-Lokal, privilegierte Rolle) noch nicht eingerichtet ⇒ zur Einrichtung.
        if (MfaService::required((string) $user['role']) && !MfaService::isEnabled((int) $user['id'])) {
            Response::redirect('/admin/mfa');
            return;
        }
        Response::redirect('/admin');
    }

    /** @param array<string,string> $params */
    public function logout(array $params): void
    {
        $uid = Auth::id();
        if ($uid !== null) {
            Audit::log('user', (string) $uid, 'auth.logout', ['actor_user_id' => $uid]);
        }
        Auth::logout();
        Response::redirect('/admin/login');
    }
}
