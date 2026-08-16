<?php
declare(strict_types=1);

namespace Tpb\Domain\Quote;

use Tpb\Core\Db;

/**
 * Lesezugriffe auf Angebote (§5.4). Schreibpfad liegt in QuoteService (Snapshot,
 * Nummernvergabe, Statuswechsel – alles transaktional).
 */
final class QuoteRepo
{
    /** @return array<string,mixed>|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run('SELECT * FROM quotes WHERE public_id = ? LIMIT 1', [$publicId])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $row = Db::run('SELECT * FROM quotes WHERE id = ? LIMIT 1', [$id])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $quoteId): array
    {
        return Db::run(
            'SELECT pos_no, sku, description, qty, unit_cents, line_cents
             FROM quote_items WHERE quote_id = ? ORDER BY pos_no ASC',
            [$quoteId]
        )->fetchAll();
    }

    /**
     * Angebotsliste fürs Backoffice (neueste zuerst), optional nach Status gefiltert.
     * @return array<int,array<string,mixed>>
     */
    public static function listForAdmin(?string $status = null): array
    {
        $sql = 'SELECT q.id, q.public_id, q.quote_number, q.status, q.total_cents, q.currency,
                       q.valid_until, q.sent_at, q.created_at,
                       c.company_name, c.first_name, c.last_name, c.email
                FROM quotes q JOIN customers c ON c.id = q.customer_id';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE q.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY q.id DESC LIMIT 200';
        return Db::run($sql, $params)->fetchAll();
    }

    /**
     * Offene Angebotsanfragen: eingereichte Konfigurationen ohne Angebot.
     * @return array<int,array<string,mixed>>
     */
    public static function openRequests(): array
    {
        return Db::run(
            "SELECT cfg.id, cfg.public_id, cfg.status, cfg.note, cfg.created_at,
                    c.company_name, c.first_name, c.last_name, c.email, c.phone,
                    (SELECT total_cents FROM price_calculations pc WHERE pc.configuration_id = cfg.id ORDER BY pc.id DESC LIMIT 1) AS total_cents
             FROM configurations cfg
             JOIN customers c ON c.id = cfg.customer_id
             WHERE cfg.status = 'submitted'
               AND NOT EXISTS (SELECT 1 FROM quotes q WHERE q.configuration_id = cfg.id)
             ORDER BY cfg.id DESC LIMIT 200"
        )->fetchAll();
    }
}
