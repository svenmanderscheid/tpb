<?php
declare(strict_types=1);

namespace Tpb\Domain\Shipping;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;

/**
 * Sendungen je Auftrag (Migration 051). Eine Sendung pro Auftrag; Lieferadresse wird
 * beim Anlegen aus dem Kundenstamm (Liefer- vor Rechnungsadresse) übernommen.
 */
final class ShipmentRepo
{
    /** @return array<string,mixed>|null */
    public static function findByOrderId(int $orderId): ?array
    {
        $row = Db::run('SELECT * FROM shipments WHERE order_id = ? LIMIT 1', [$orderId])->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run('SELECT * FROM shipments WHERE public_id = ? LIMIT 1', [$publicId])->fetch();
        return $row === false ? null : $row;
    }

    /** Legt bei Bedarf eine Sendung an (Adresse aus dem Kundenstamm). @return array<string,mixed> */
    public static function ensureForOrder(int $orderId, ?int $actorUserId): array
    {
        $existing = self::findByOrderId($orderId);
        if ($existing !== null) {
            return $existing;
        }
        $order = Db::run('SELECT customer_id FROM orders WHERE id = ? LIMIT 1', [$orderId])->fetch();
        $c = $order !== false ? Db::run('SELECT * FROM customers WHERE id = ? LIMIT 1', [(int) $order['customer_id']])->fetch() : false;

        $useDelivery = $c !== false && !empty($c['delivery_street']);
        $now = Clock::nowUtcSeconds();
        $publicId = Ulid::generate();
        Db::run(
            'INSERT INTO shipments
                (public_id, order_id, method, recipient_name, recipient_company, street, zip, city, country,
                 shipping_cost_cents, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $publicId, $orderId, 'pickup',
                $c !== false ? trim((string) $c['first_name'] . ' ' . (string) $c['last_name']) : '',
                $c !== false ? ($c['company_name'] ?? null) : null,
                $c === false ? null : ($useDelivery ? $c['delivery_street'] : $c['billing_street']),
                $c === false ? null : ($useDelivery ? $c['delivery_zip'] : $c['billing_zip']),
                $c === false ? null : ($useDelivery ? $c['delivery_city'] : $c['billing_city']),
                $c === false ? null : ($useDelivery ? $c['delivery_country'] : $c['billing_country']),
                0, $actorUserId, $now, $now,
            ]
        );
        return self::findByOrderId($orderId) ?? [];
    }

    /** @param array<string,mixed> $fields */
    public static function update(int $shipmentId, array $fields): void
    {
        $allowed = ['method', 'carrier', 'recipient_name', 'recipient_company', 'street', 'zip', 'city', 'country', 'shipping_cost_cents', 'tracking_ref', 'label_asset_id', 'packed_at', 'shipped_at', 'delivered_at'];
        $set = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $set[] = "{$k} = ?";
                $vals[] = $v;
            }
        }
        if ($set === []) {
            return;
        }
        $set[] = 'updated_at = ?';
        $vals[] = Clock::nowUtcSeconds();
        $vals[] = $shipmentId;
        Db::run('UPDATE shipments SET ' . implode(', ', $set) . ' WHERE id = ?', $vals);
    }
}
