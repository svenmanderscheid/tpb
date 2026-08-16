<?php
declare(strict_types=1);

namespace Tpb\Http\Admin;

use Tpb\Core\Auth;
use Tpb\Core\Db;
use Tpb\Core\Request;
use Tpb\Core\Response;
use Tpb\Core\View;
use Tpb\Domain\Auth\MfaService;
use Tpb\Domain\Auth\Totp;
use Tpb\Domain\Label\Barcode;

/**
 * Selbstverwaltung der Zwei-Faktor-Authentifizierung (§4/§11). Jeder angemeldete
 * Nutzer richtet seine eigene MFA ein/ab. QR aus otpauth-URI (lokal gerendert, CSP-konform).
 */
final class MfaController
{
    use FlashTrait;

    /** @param array<string,string> $params */
    public function setup(array $params): void
    {
        $uid = (int) Auth::id();
        $user = Db::run('SELECT email, role FROM users WHERE id = ? LIMIT 1', [$uid])->fetch();
        $enabled = MfaService::isEnabled($uid);

        $secret = null;
        if (!$enabled) {
            $secret = (string) ($_SESSION['mfa_setup_secret'] ?? '');
            if ($secret === '') {
                $secret = Totp::generateSecret();
                $_SESSION['mfa_setup_secret'] = $secret;
            }
        }

        Response::html(View::render('admin/mfa/setup', [
            'title'    => 'Zwei-Faktor-Authentifizierung',
            'nav'      => '',
            'enabled'  => $enabled,
            'required' => MfaService::required((string) $user['role']),
            'secret'   => $secret,
            'backup_left' => $enabled ? MfaService::unusedBackupCount($uid) : 0,
            'flash'    => $this->takeFlash(),
        ]));
    }

    /** Streamt den QR-Code des laufenden MFA-Setups als PNG (CSP-konform, kein data:). */
    public function qr(array $params): void
    {
        $uid = (int) Auth::id();
        $secret = (string) ($_SESSION['mfa_setup_secret'] ?? '');
        if ($secret === '' || MfaService::isEnabled($uid)) {
            Response::error(404);
            return;
        }
        $email = (string) Db::run('SELECT email FROM users WHERE id = ? LIMIT 1', [$uid])->fetchColumn();
        $png = Barcode::qrPngRaw(Totp::otpauthUri($secret, $email, 'The Printing Brothers'), 5);
        Response::sendSecurityHeaders();
        http_response_code(200);
        header('Content-Type: image/png');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $png;
    }

    /** @param array<string,string> $params */
    public function enable(array $params): void
    {
        $uid = (int) Auth::id();
        $secret = (string) ($_SESSION['mfa_setup_secret'] ?? '');
        if ($secret === '') {
            $this->flash('error', 'Einrichtung abgelaufen. Bitte erneut starten.');
            Response::redirect('/admin/mfa');
            return;
        }
        try {
            $codes = MfaService::enable($uid, $secret, (string) Request::post('code', ''));
            unset($_SESSION['mfa_setup_secret']);
            Response::html(View::render('admin/mfa/backup_codes', [
                'title' => 'Backup-Codes',
                'nav'   => '',
                'codes' => $codes,
            ]));
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
            Response::redirect('/admin/mfa');
        }
    }

    /** @param array<string,string> $params */
    public function disable(array $params): void
    {
        $uid = (int) Auth::id();
        MfaService::disable($uid, $uid);
        $this->flash('ok', 'Zwei-Faktor-Authentifizierung deaktiviert.');
        Response::redirect('/admin/mfa');
    }
}
