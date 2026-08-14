<?php
declare(strict_types=1);

namespace Tpb\Domain\Outbox;

use PHPMailer\PHPMailer\PHPMailer;
use Tpb\Core\Clock;
use Tpb\Core\Env;
use Tpb\Core\Ulid;

/**
 * Mailversand über PHPMailer (§10). Lokal MAIL_DRIVER=file: schreibt .eml nach
 * private/tpb/outbox-mails. Sonst SMTP aus der .env.
 */
final class Mailer
{
    /**
     * @param array<string,mixed> $payload  Keys: to, subject, text, html?
     */
    public static function sendFromPayload(array $payload): void
    {
        $to = (string) ($payload['to'] ?? '');
        $subject = (string) ($payload['subject'] ?? '');
        $text = (string) ($payload['text'] ?? '');
        $html = isset($payload['html']) ? (string) $payload['html'] : null;

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Ungültige Empfängeradresse in Mail-Payload.');
        }
        self::send($to, $subject, $text, $html);
    }

    public static function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $from = Env::get('MAIL_FROM');
        if ($from === null || $from === '') {
            $from = 'no-reply@tpb.local'; // TODO(§15): echte Absenderadresse nach Gründung
        }
        $mail->setFrom($from, 'The Printing Brothers');
        $mail->addAddress($to);
        $mail->Subject = $subject;

        if ($html !== null && $html !== '') {
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
        } else {
            $mail->isHTML(false);
            $mail->Body = $text;
        }

        $driver = Env::get('MAIL_DRIVER', 'file');
        if ($driver === 'file') {
            self::writeEml($mail);
            return;
        }

        // SMTP (ab M3-Abnahme; Zugang aus .env)
        $mail->isSMTP();
        $mail->Host = (string) Env::get('SMTP_HOST', '');
        $mail->Port = Env::int('SMTP_PORT', 587);
        $user = (string) Env::get('SMTP_USER', '');
        if ($user !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = (string) Env::get('SMTP_PASS', '');
        }
        $mail->send();
    }

    private static function writeEml(PHPMailer $mail): void
    {
        $mail->preSend();
        $mime = $mail->getSentMIMEMessage();

        $dir = TPB_ROOT . '/private/tpb/outbox-mails';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $name = Clock::nowUtc()->format('Ymd-His') . '-' . Ulid::generate() . '.eml';
        file_put_contents($dir . '/' . $name, $mime);
    }
}
