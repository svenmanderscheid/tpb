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
        Auth::login($user);
        Db::run(
            'UPDATE users SET last_login_at = ? WHERE id = ?',
            [Clock::nowUtcSeconds(), (int) $user['id']]
        );
        Audit::log('user', (string) $user['id'], 'auth.login', [
            'actor_user_id' => (int) $user['id'],
            'actor_label'   => $user['email'],
        ]);

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
