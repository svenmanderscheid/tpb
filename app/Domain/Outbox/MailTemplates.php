<?php
declare(strict_types=1);

namespace Tpb\Domain\Outbox;

use Tpb\Core\Env;
use Tpb\Core\Money;

/**
 * Transaktionale Mailtexte (§10, Kundensprache DE). Werden im Web-Kontext beim
 * Einreihen gerendert und als to/subject/text/html in die Outbox-Payload gelegt,
 * damit der Worker ohne View-Abhängigkeit versenden kann (Mailer::sendFromPayload).
 * HTML ist bewusst schlicht; alle dynamischen Werte laufen durch e().
 */
final class MailTemplates
{
    /** @param array<string,mixed> $p */
    public static function quoteSent(array $p): array
    {
        $url = self::base() . '/angebot/' . rawurlencode((string) $p['quote_public_id']) . '?t=' . rawurlencode((string) $p['token']);
        $number = (string) $p['quote_number'];
        $name = trim((string) ($p['to_name'] ?? '')) ?: 'Kundin/Kunde';
        $total = Money::format((int) $p['total_cents'], (string) ($p['currency'] ?? 'EUR'));

        $subject = "Ihr Angebot {$number} von The Printing Brothers";
        $text = "Hallo {$name},\n\n"
            . "vielen Dank für Ihre Anfrage. Ihr persönliches Angebot {$number} über {$total} liegt bereit.\n\n"
            . "Angebot ansehen, annehmen oder ablehnen:\n{$url}\n\n"
            . "Der Link ist persönlich – bitte nicht weitergeben.\n\n"
            . "Herzliche Grüße\nThe Printing Brothers";
        $html = '<p>Hallo ' . e($name) . ',</p>'
            . '<p>vielen Dank für Ihre Anfrage. Ihr persönliches Angebot <strong>' . e($number) . '</strong> über <strong>' . e($total) . '</strong> liegt bereit.</p>'
            . '<p><a href="' . e($url) . '">Angebot ansehen, annehmen oder ablehnen</a></p>'
            . '<p>Der Link ist persönlich – bitte nicht weitergeben.</p>'
            . '<p>Herzliche Grüße<br>The Printing Brothers</p>';

        return ['to' => (string) $p['to_email'], 'subject' => $subject, 'text' => $text, 'html' => $html];
    }

    /** @param array<string,mixed> $p */
    public static function orderConfirmed(array $p): array
    {
        $number = (string) $p['order_number'];
        $name = trim((string) ($p['to_name'] ?? '')) ?: 'Kundin/Kunde';
        $total = Money::format((int) $p['total_cents'], (string) ($p['currency'] ?? 'EUR'));

        $subject = "Auftragsbestätigung {$number}";
        $text = "Hallo {$name},\n\n"
            . "vielen Dank – wir haben Ihre Angebotsannahme erhalten. Ihr Auftrag {$number} über {$total} ist angelegt.\n\n"
            . "Wir melden uns mit den nächsten Schritten (Druckdaten/Freigabe).\n\n"
            . "Herzliche Grüße\nThe Printing Brothers";
        $html = '<p>Hallo ' . e($name) . ',</p>'
            . '<p>vielen Dank – wir haben Ihre Angebotsannahme erhalten. Ihr Auftrag <strong>' . e($number) . '</strong> über <strong>' . e($total) . '</strong> ist angelegt.</p>'
            . '<p>Wir melden uns mit den nächsten Schritten (Druckdaten/Freigabe).</p>'
            . '<p>Herzliche Grüße<br>The Printing Brothers</p>';

        return ['to' => (string) $p['to_email'], 'subject' => $subject, 'text' => $text, 'html' => $html];
    }

    private static function base(): string
    {
        return rtrim((string) (Env::get('APP_URL', 'http://tpb.local') ?? 'http://tpb.local'), '/');
    }
}
