<?php
declare(strict_types=1);

namespace Tpb\Domain\Catalog;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Produktvarianten (Farbe/Größe/SKU) je Produkt (§5.3).
 */
final class VariantRepo
{
    public static function create(int $productId, string $sku, ?string $colorCode, ?string $colorName, ?string $size): int
    {
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO product_variants (product_id, sku, color_code, color_name, size, status, sort, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [$productId, $sku, $colorCode, $colorName, $size, 'active', $now, $now]
        );
        return (int) Db::pdo()->lastInsertId();
    }

    public static function delete(int $id, int $productId): void
    {
        Db::run('DELETE FROM product_variants WHERE id = ? AND product_id = ?', [$id, $productId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function listByProduct(int $productId): array
    {
        return Db::run(
            'SELECT id, sku, color_code, color_name, size, status, sort FROM product_variants WHERE product_id = ? ORDER BY sort ASC, id ASC',
            [$productId]
        )->fetchAll();
    }

    public static function skuExists(string $sku): bool
    {
        return Db::run('SELECT id FROM product_variants WHERE sku = ? LIMIT 1', [$sku])->fetch() !== false;
    }
}
