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
        $now = Clock::nowUtcSeconds();
        $publicId = Ulid::generate();
        $orderNumber = NumberSequence::next('order', 'ORD');

        $totals = $snapshot['totals'] ?? [];
        $totalCents = (int) ($totals['total_cents'] ?? 0);
        $currency = (string) ($quote['currency'] ?? 'EUR');
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
                $publicId, $orderNumber, (int) $quote['id'], (int) $quote['customer_id'], $customerSnapshot,
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

        // Auftragsachse: (Start) -> CONFIRMED (Pfad A, Annahme des Angebots).
        Status::transition('order', $orderId, 'order', null, 'CONFIRMED', [
            'actor_user_id' => $actorUserId,
            'actor_label'   => $actorUserId === null ? 'customer' : null,
            'reason'        => 'quote_accepted',
        ]);

        Audit::log('order', $publicId, 'order.created', [
            'actor_user_id' => $actorUserId,
            'actor_label'   => $actorUserId === null ? 'customer' : null,
            'to_state'      => 'CONFIRMED',
            'metadata'      => ['order_number' => $orderNumber, 'total_cents' => $totalCents, 'quote_id' => (int) $quote['id']],
        ]);

        return ['id' => $orderId, 'public_id' => $publicId, 'order_number' => $orderNumber];
    }
}
