<?php
declare(strict_types=1);

namespace Tpb\Domain\Bank;

use Tpb\Core\Db;

/**
 * Matching-Vorschläge (§5.6): (1) exakte Rechnungs-/Auftragsnummer im Verwendungszweck
 * oder in der EndToEndId ⇒ hoher Vorschlag; (2) Betrag exakt + Namensähnlichkeit ⇒
 * niedriger Vorschlag. Erzeugt NIE eine Buchung – nur einen Vorschlag zur Bestätigung.
 */
final class Matcher
{
    /**
     * @param array<string,mixed> $line
     * @return array<string,mixed>|null {invoice_id,invoice_number,order_public,confidence,reason}
     */
    public static function suggest(array $line): ?array
    {
        if ((int) $line['amount_cents'] <= 0) {
            return null; // Ausgangszeilen ⇒ Ausgaben-Vorschlag (kein Rechnungsmatch)
        }
        $text = strtoupper(trim((string) ($line['remittance_info'] ?? '') . ' ' . (string) ($line['end_to_end_id'] ?? '')));
        $amount = (int) $line['amount_cents'];

        // (1) Rechnungsnummer im Text.
        foreach (Db::run("SELECT id, invoice_number, order_id, gross_cents FROM invoices WHERE invoice_number IS NOT NULL AND status IN ('ISSUED','SENT')")->fetchAll() as $inv) {
            if ($text !== '' && str_contains($text, strtoupper((string) $inv['invoice_number']))) {
                return self::candidate($inv, 'high', 'Rechnungsnummer im Verwendungszweck');
            }
        }

        // (1b) Auftragsnummer im Text ⇒ Primärrechnung des Auftrags.
        foreach (Db::run('SELECT id, order_number, public_id FROM orders')->fetchAll() as $o) {
            if ($text !== '' && str_contains($text, strtoupper((string) $o['order_number']))) {
                $inv = Db::run("SELECT id, invoice_number, order_id, gross_cents FROM invoices WHERE order_id = ? AND doc_type='invoice' AND status IN ('ISSUED','SENT') ORDER BY id ASC LIMIT 1", [(int) $o['id']])->fetch();
                if ($inv !== false) {
                    return self::candidate($inv, 'high', 'Auftragsnummer im Verwendungszweck');
                }
            }
        }

        // (2) Betrag exakt + Namensähnlichkeit gegen den Kunden der Rechnung.
        $name = strtolower(trim((string) ($line['counterparty_name'] ?? '')));
        foreach (Db::run("SELECT id, invoice_number, order_id, gross_cents FROM invoices WHERE gross_cents = ? AND status IN ('ISSUED','SENT')", [$amount])->fetchAll() as $inv) {
            $open = (int) $inv['gross_cents'] - (int) Db::run('SELECT COALESCE(SUM(amount_cents),0) FROM payment_allocations WHERE invoice_id = ?', [(int) $inv['id']])->fetchColumn();
            if ($open <= 0) {
                continue;
            }
            if ($name !== '' && self::nameSimilar($name, (int) $inv['order_id'])) {
                return self::candidate($inv, 'low', 'Betrag + Namensähnlichkeit');
            }
            return self::candidate($inv, 'low', 'Betrag stimmt überein');
        }

        return null;
    }

    /** @param array<string,mixed> $inv @return array<string,mixed> */
    private static function candidate(array $inv, string $confidence, string $reason): array
    {
        $orderPublic = null;
        if ($inv['order_id'] !== null) {
            $orderPublic = Db::run('SELECT public_id FROM orders WHERE id = ? LIMIT 1', [(int) $inv['order_id']])->fetchColumn() ?: null;
        }
        return [
            'invoice_id'     => (int) $inv['id'],
            'invoice_number' => (string) $inv['invoice_number'],
            'order_public'   => $orderPublic !== null ? (string) $orderPublic : null,
            'confidence'     => $confidence,
            'reason'         => $reason,
        ];
    }

    private static function nameSimilar(string $counterparty, int $orderId): bool
    {
        $cust = Db::run(
            'SELECT c.company_name, c.first_name, c.last_name FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ? LIMIT 1',
            [$orderId]
        )->fetch();
        if ($cust === false) {
            return false;
        }
        foreach ([(string) ($cust['company_name'] ?? ''), trim((string) $cust['first_name'] . ' ' . (string) $cust['last_name'])] as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ($candidate !== '' && (str_contains($counterparty, $candidate) || str_contains($candidate, $counterparty))) {
                return true;
            }
        }
        return false;
    }
}
