<?php
declare(strict_types=1);

namespace Tpb\Domain\Stock;

use Tpb\Core\Db;

/**
 * Bestandslesen und -Berechnung (Migration 052). Verfügbarkeit =
 * max(0, stock_qty − reserve_qty − aktive Reservierungen). Reserve wird nie verkauft.
 */
final class StockRepo
{
    /** @return array<string,mixed>|null */
    public static function variant(int $variantId): ?array
    {
        $row = Db::run('SELECT id, sku, stock_qty, reserve_qty, reorder_threshold FROM product_variants WHERE id = ? LIMIT 1', [$variantId])->fetch();
        return $row === false ? null : $row;
    }

    /** Summe aktiver Reservierungen einer Variante (optional einen Auftrag ausklammern). */
    public static function reservedActive(int $variantId, ?int $excludeOrderId = null): int
    {
        $sql = "SELECT COALESCE(SUM(qty),0) FROM stock_reservations WHERE variant_id = ? AND status = 'active'";
        $params = [$variantId];
        if ($excludeOrderId !== null) {
            $sql .= ' AND order_id <> ?';
            $params[] = $excludeOrderId;
        }
        return (int) Db::run($sql, $params)->fetchColumn();
    }

    /** Verkaufbare Verfügbarkeit (angezeigt/verkaufbar). */
    public static function availability(int $variantId, ?int $excludeOrderId = null): int
    {
        $v = self::variant($variantId);
        if ($v === null) {
            return 0;
        }
        $avail = (int) $v['stock_qty'] - (int) $v['reserve_qty'] - self::reservedActive($variantId, $excludeOrderId);
        return max(0, $avail);
    }

    /** Verfügbarkeit je Varianten-SKU (für Konfigurator/Shop-Anzeige). @return array<string,int> */
    public static function availabilityBySkuForProduct(int $productId): array
    {
        $out = [];
        foreach (Db::run('SELECT id, sku FROM product_variants WHERE product_id = ?', [$productId])->fetchAll() as $v) {
            $out[(string) $v['sku']] = self::availability((int) $v['id']);
        }
        return $out;
    }

    /**
     * Bestandsübersicht fürs Backoffice (mit berechneter Verfügbarkeit).
     * @return array<int,array<string,mixed>>
     */
    public static function listForAdmin(): array
    {
        $rows = Db::run(
            'SELECT v.id, v.sku, v.color_name, v.size, v.stock_qty, v.reserve_qty, v.reorder_threshold, p.name AS product_name
             FROM product_variants v JOIN products p ON p.id = v.product_id
             ORDER BY p.name ASC, v.sku ASC'
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['reserved'] = self::reservedActive((int) $r['id']);
            $r['available'] = max(0, (int) $r['stock_qty'] - (int) $r['reserve_qty'] - (int) $r['reserved']);
        }
        return $rows;
    }
}
