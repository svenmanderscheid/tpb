<?php
declare(strict_types=1);

namespace Tpb\Domain\Invoice;

use Tpb\Core\Db;

/**
 * Lesezugriffe auf Rechnungen/Gutschriften (§5.5). Schreibpfad in InvoiceService
 * (Issue-Flow §11.2 – Nummer, Snapshot, PDF/JSON, ISSUED unumkehrbar).
 */
final class InvoiceRepo
{
    /** @return array<string,mixed>|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run('SELECT * FROM invoices WHERE public_id = ? LIMIT 1', [$publicId])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $row = Db::run('SELECT * FROM invoices WHERE id = ? LIMIT 1', [$id])->fetch();
        return $row === false ? null : $row;
    }

    /** Primärrechnung (kein Gutschrift-Beleg) eines Auftrags. @return array<string,mixed>|null */
    public static function primaryForOrder(int $orderId): ?array
    {
        $row = Db::run("SELECT * FROM invoices WHERE order_id = ? AND doc_type = 'invoice' ORDER BY id ASC LIMIT 1", [$orderId])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function lines(int $invoiceId): array
    {
        return Db::run('SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY pos_no ASC', [$invoiceId])->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public static function listForAdmin(): array
    {
        return Db::run(
            "SELECT i.id, i.public_id, i.doc_type, i.invoice_number, i.status, i.gross_cents, i.currency, i.issued_at,
                    o.order_number, o.public_id AS order_public_id
             FROM invoices i LEFT JOIN orders o ON o.id = i.order_id
             ORDER BY i.id DESC LIMIT 300"
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Gutschriften zu einer Rechnung. */
    public static function creditNotesFor(int $invoiceId): array
    {
        return Db::run('SELECT * FROM invoices WHERE credited_invoice_id = ? ORDER BY id ASC', [$invoiceId])->fetchAll();
    }
}
