<?php
declare(strict_types=1);

namespace Tpb\Domain\Order;

use Tpb\Core\Canonical;
use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;
use Tpb\Domain\Audit\Audit;
use Tpb\Domain\Sequence\NumberSequence;
use Tpb\Domain\Status\Status;

/**
 * Aufträge (§5.5). Erzeugung ausschließlich aus einem unveränderlichen Quote-Snapshot
 * (§1 Punkt 6, §7). MUSS innerhalb der Annahme-Transaktion aufgerufen werden.
 */
final class OrderRepo
{
    /** @return array<string,mixed>|null */
    public static function findByQuoteId(int $quoteId): ?array
    {
        $row = Db::run('SELECT * FROM orders WHERE quote_id = ? LIMIT 1', [$quoteId])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run('SELECT * FROM orders WHERE public_id = ? LIMIT 1', [$publicId])->fetch();
        return $row === false ? null : $row;
    }

    /** Aktueller Zustand der Order-Achse (§7) aus status_events (keine Cache-Spalte). */
    public static function orderState(int $orderId): string
    {
        $s = Db::run(
            "SELECT to_state FROM status_events WHERE aggregate_type = 'order' AND aggregate_id = ? AND axis = 'order' ORDER BY id DESC LIMIT 1",
            [$orderId]
        )->fetchColumn();
        return $s === false ? '' : (string) $s;
    }

    /**
     * Noch nicht bezahlte Shop-Orders (Order-Achse = PENDING_PAYMENT), die vor dem
     * Stichtag angelegt wurden – für cli/expire.php.
     * @return array<int,array<string,mixed>>
     */
    public static function pendingPaymentOlderThan(string $cutoffUtc): array
    {
        return Db::run(
            "SELECT o.id, o.public_id, o.order_number FROM orders o
             WHERE o.ordered_at < ?
               AND (SELECT se.to_state FROM status_events se
                    WHERE se.aggregate_type = 'order' AND se.aggregate_id = o.id AND se.axis = 'order'
                    ORDER BY se.id DESC LIMIT 1) = 'PENDING_PAYMENT'",
            [$cutoffUtc]
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Alle Positionen eines Auftrags. */
    public static function items(int $orderId): array
    {
        return Db::run('SELECT * FROM order_items WHERE order_id = ? ORDER BY pos_no ASC', [$orderId])->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Nur konfigurierte Positionen (mit config_snapshot, M4-Proof). */
    public static function configuredItems(int $orderId): array
    {
        return Db::run(
            'SELECT * FROM order_items WHERE order_id = ? AND config_snapshot_json IS NOT NULL ORDER BY pos_no ASC',
            [$orderId]
        )->fetchAll();
    }

    /**
     * Auftragsliste fürs Backoffice (neueste zuerst).
     * @return array<int,array<string,mixed>>
     */
    public static function listForAdmin(): array
    {
        return Db::run(
            'SELECT o.id, o.public_id, o.order_number, o.total_cents, o.currency, o.cur_artwork, o.cur_production, o.ordered_at,
                    c.company_name, c.first_name, c.last_name
             FROM orders o JOIN customers c ON c.id = o.customer_id
             ORDER BY o.id DESC LIMIT 200'
        )->fetchAll();
    }

    /**
     * Erzeugt Order + Items + Units aus dem Quote-Snapshot. Order-Achse startet
     * bei CONFIRMED (Pfad A, §7). Artwork-Cache: MISSING falls konfigurierte Position
     * vorhanden, sonst LOCKED (v1.4). Gibt id + public_id + order_number.
     *
     * @param array<string,mixed> $quote     Quote-Zeile (id, customer_id, currency, ...)
     * @param array<string,mixed> $snapshot  Eingefrorener Snapshot (customer, items, totals)
     * @return array{id:int,public_id:string,order_number:string}
     */
    public static function createFromQuote(array $quote, array $snapshot, ?int $actorUserId): array
    {
        return self::createFromSnapshot(
            (int) $quote['customer_id'], (int) $quote['id'], (string) ($quote['currency'] ?? 'EUR'),
            $snapshot, 'CONFIRMED', $actorUserId, 'quote_accepted'
        );
    }

    /**
     * Erzeugt Order + Items + Units aus einem Snapshot. Startzustand der Order-Achse:
     * CONFIRMED (Pfad A, Angebotsannahme) oder PENDING_PAYMENT (Pfad B, Shop-Checkout).
     * Artwork-Cache: MISSING falls konfigurierte Position vorhanden, sonst LOCKED (v1.4).
     *
     * @param array<string,mixed> $snapshot
     * @return array{id:int,public_id:string,order_number:string}
     */
    public static function createFromSnapshot(int $customerId, ?int $quoteId, string $currency, array $snapshot, string $initialState, ?int $actorUserId, string $reason): array
    {
        $now = Clock::nowUtcSeconds();
        $publicId = Ulid::generate();
        $orderNumber = NumberSequence::next('order', 'ORD');

        $totalCents = (int) ($snapshot['totals']['total_cents'] ?? 0);
        $customerSnapshot = Canonical::json($snapshot['customer'] ?? []);

        $hasConfigured = false;
        foreach ($snapshot['items'] ?? [] as $it) {
            if (($it['type'] ?? 'configured') === 'configured') {
                $hasConfigured = true;
                break;
            }
        }
        $curArtwork = $hasConfigured ? 'MISSING' : 'LOCKED';

        Db::run(
            'INSERT INTO orders
                (public_id, order_number, quote_id, customer_id, customer_snapshot_json, currency,
                 total_cents, deposit_required_cents, ordered_at, cur_payment, cur_artwork,
                 cur_production, cur_fulfillment, cur_invoice, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $publicId, $orderNumber, $quoteId, $customerId, $customerSnapshot,
                $currency, $totalCents, 0, $now, 'NOT_DUE', $curArtwork,
                'BLOCKED', 'UNFULFILLED', 'NONE', $now, $now,
            ]
        );
        $orderId = (int) Db::pdo()->lastInsertId();

        $pos = 0;
        foreach ($snapshot['items'] ?? [] as $it) {
            $pos++;
            Db::run(
                'INSERT INTO order_items
                    (order_id, pos_no, product_id, sku, description, qty, unit_cents, line_cents, config_snapshot_json, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orderId, $pos, (int) $it['product_id'], $it['sku'] ?? null, (string) $it['description'],
                    (int) $it['qty'], (int) $it['unit_cents'], (int) $it['line_cents'],
                    isset($it['config']) ? Canonical::json($it['config']) : null, $now, $now,
                ]
            );
            $orderItemId = (int) Db::pdo()->lastInsertId();

            $unitNo = 0;
            foreach ($it['units'] ?? [] as $u) {
                $unitNo++;
                Db::run(
                    'INSERT INTO order_item_units (order_item_id, unit_no, variant_sku, name, number, created_at)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$orderItemId, $unitNo, (string) $u['variant_sku'], $u['name'] ?? null, $u['number'] ?? null, $now]
                );
            }
        }

        Status::transition('order', $orderId, 'order', null, $initialState, [
            'actor_user_id' => $actorUserId,
            'actor_label'   => $actorUserId === null ? 'customer' : null,
            'reason'        => $reason,
        ]);

        Audit::log('order', $publicId, 'order.created', [
            'actor_user_id' => $actorUserId,
            'actor_label'   => $actorUserId === null ? 'customer' : null,
            'to_state'      => $initialState,
            'metadata'      => ['order_number' => $orderNumber, 'total_cents' => $totalCents, 'quote_id' => $quoteId],
        ]);

        return ['id' => $orderId, 'public_id' => $publicId, 'order_number' => $orderNumber];
    }
}
