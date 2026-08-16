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

    /** @param array<string,mixed> $p */
    public static function proofSent(array $p): array
    {
        $url = self::base() . '/proof/' . rawurlencode((string) $p['order_public_id']) . '?t=' . rawurlencode((string) $p['token']);
        $number = (string) $p['order_number'];
        $name = trim((string) ($p['to_name'] ?? '')) ?: 'Kundin/Kunde';
        $version = (string) ($p['proof_version'] ?? '');

        $subject = "Druckfreigabe (Proof v{$version}) zu Auftrag {$number}";
        $text = "Hallo {$name},\n\n"
            . "zu Ihrem Auftrag {$number} liegt der Proof (Version {$version}) zur Freigabe bereit.\n\n"
            . "Bitte prüfen und freigeben oder Änderungen anfordern:\n{$url}\n\n"
            . "Erst nach Ihrer Freigabe geht der Auftrag in die Produktion.\n\n"
            . "Herzliche Grüße\nThe Printing Brothers";
        $html = '<p>Hallo ' . e($name) . ',</p>'
            . '<p>zu Ihrem Auftrag <strong>' . e($number) . '</strong> liegt der Proof (Version ' . e($version) . ') zur Freigabe bereit.</p>'
            . '<p><a href="' . e($url) . '">Proof prüfen, freigeben oder Änderungen anfordern</a></p>'
            . '<p>Erst nach Ihrer Freigabe geht der Auftrag in die Produktion.</p>'
            . '<p>Herzliche Grüße<br>The Printing Brothers</p>';

        return ['to' => (string) $p['to_email'], 'subject' => $subject, 'text' => $text, 'html' => $html];
    }

    /** @param array<string,mixed> $p */
    public static function invoiceIssued(array $p): array
    {
        $isCredit = ($p['doc_type'] ?? 'invoice') === 'credit_note';
        $number = (string) $p['invoice_number'];
        $name = trim((string) ($p['to_name'] ?? '')) ?: 'Kundin/Kunde';
        $amount = Money::format(abs((int) $p['gross_cents']), (string) ($p['currency'] ?? 'EUR'));
        $doc = $isCredit ? 'Gutschrift' : 'Rechnung';

        $subject = "{$doc} {$number}";
        $text = "Hallo {$name},\n\n"
            . "anbei Ihre {$doc} {$number} über {$amount}.\n\n"
            . "Herzliche Grüße\nThe Printing Brothers";
        $html = '<p>Hallo ' . e($name) . ',</p>'
            . '<p>anbei Ihre ' . e($doc) . ' <strong>' . e($number) . '</strong> über <strong>' . e($amount) . '</strong>.</p>'
            . '<p>Herzliche Grüße<br>The Printing Brothers</p>';

        return ['to' => (string) $p['to_email'], 'subject' => $subject, 'text' => $text, 'html' => $html];
    }

    /** @param array<string,mixed> $p Zahlungserinnerung/Mahnung Stufe 1 an den Kunden. */
    public static function paymentReminder(array $p): array
    {
        $number = (string) $p['invoice_number'];
        $name = trim((string) ($p['to_name'] ?? '')) ?: 'Kundin/Kunde';
        $amount = Money::format((int) $p['gross_cents'], (string) ($p['currency'] ?? 'EUR'));
        $due = (string) ($p['due_date'] ?? '');

        $subject = "Zahlungserinnerung zu Rechnung {$number}";
        $text = "Hallo {$name},\n\n"
            . "unsere Rechnung {$number} über {$amount} (fällig am {$due}) ist noch offen.\n"
            . "Falls sich Ihre Zahlung überschnitten hat, betrachten Sie diese Erinnerung bitte als gegenstandslos.\n\n"
            . "Herzliche Grüße\nThe Printing Brothers";
        $html = '<p>Hallo ' . e($name) . ',</p>'
            . '<p>unsere Rechnung <strong>' . e($number) . '</strong> über <strong>' . e($amount) . '</strong> (fällig am ' . e($due) . ') ist noch offen.</p>'
            . '<p>Falls sich Ihre Zahlung überschnitten hat, betrachten Sie diese Erinnerung bitte als gegenstandslos.</p>'
            . '<p>Herzliche Grüße<br>The Printing Brothers</p>';

        return ['to' => (string) $p['to_email'], 'subject' => $subject, 'text' => $text, 'html' => $html];
    }

    /** @param array<string,mixed> $p Meldebestand-Alarm (intern an den Owner). */
    public static function stockLow(array $p): array
    {
        $levels = ['reorder' => 'Meldebestand erreicht', 'critical' => 'kritisch niedrig', 'empty' => 'ausverkauft'];
        $levelText = $levels[(string) ($p['level'] ?? '')] ?? (string) ($p['level'] ?? '');
        $sku = (string) $p['sku'];
        $product = (string) ($p['product'] ?? '');
        $stock = (int) ($p['stock'] ?? 0);

        $subject = "Lager: {$sku} {$levelText} ({$stock} Stück)";
        $text = "Bestandshinweis\n\n"
            . "Artikel: {$product} ({$sku})\n"
            . "Status: {$levelText}\n"
            . "Aktueller Bestand: {$stock} Stück\n\n"
            . "Bitte im Lager prüfen und ggf. nachbestellen.";
        $html = '<p><strong>Bestandshinweis</strong></p>'
            . '<p>Artikel: ' . e($product) . ' (' . e($sku) . ')<br>'
            . 'Status: <strong>' . e($levelText) . '</strong><br>'
            . 'Aktueller Bestand: ' . e((string) $stock) . ' Stück</p>'
            . '<p>Bitte im Lager prüfen und ggf. nachbestellen.</p>';

        return ['to' => (string) $p['to_email'], 'subject' => $subject, 'text' => $text, 'html' => $html];
    }

    private static function base(): string
    {
        return rtrim((string) (Env::get('APP_URL', 'http://tpb.local') ?? 'http://tpb.local'), '/');
    }
}
