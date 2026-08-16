<?php
declare(strict_types=1);

namespace Tpb\Domain\Payment;

use Tpb\Core\Db;

/**
 * Zahlungen und Zuordnungen (§5.5). Umsatz (ausgestellte Rechnungen) und
 * Zahlungseingang (payments) bleiben getrennt auswertbar (DoD M6).
 */
final class PaymentRepo
{
    /** Netto-Forderung eines Auftrags: Summe gross ausgestellter Belege (Gutschrift negativ). */
    public static function netInvoiced(int $orderId): int
    {
        return (int) Db::run(
            "SELECT COALESCE(SUM(gross_cents), 0) FROM invoices
             WHERE order_id = ? AND status IN ('ISSUED','SENT','PARTIALLY_CREDITED','FULLY_CREDITED')",
            [$orderId]
        )->fetchColumn();
    }

    /** Zahlungseingang eines Auftrags. */
    public static function receivedForOrder(int $orderId): int
    {
        return (int) Db::run('SELECT COALESCE(SUM(amount_cents), 0) FROM payments WHERE order_id = ?', [$orderId])->fetchColumn();
    }

    public static function allocatedToInvoice(int $invoiceId): int
    {
        return (int) Db::run('SELECT COALESCE(SUM(amount_cents), 0) FROM payment_allocations WHERE invoice_id = ?', [$invoiceId])->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForOrder(int $orderId): array
    {
        return Db::run('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$orderId])->fetchAll();
    }
}
