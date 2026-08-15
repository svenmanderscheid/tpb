<?php
declare(strict_types=1);

namespace Tpb\Domain\Catalog;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;

/**
 * Produkte (§5.3). Zwei Typen: configurable (Konfigurator) und standard (Hausdesign).
 */
final class ProductRepo
{
    public const TYPES = ['configurable', 'standard'];
    public const STATUSES = ['draft', 'active', 'archived'];

    public static function create(string $type, string $skuRoot, string $name, string $slug, ?string $descriptionMd, ?int $userId): string
    {
        $publicId = Ulid::generate();
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO products (public_id, product_type, sku_root, name, slug, description_md, status, sort, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [$publicId, $type, $skuRoot, $name, $slug, $descriptionMd, 'draft', $now, $now]
        );
        return $publicId;
    }

    public static function update(string $publicId, string $type, string $skuRoot, string $name, string $slug, ?string $descriptionMd, string $status, int $sort): void
    {
        Db::run(
            'UPDATE products SET product_type = ?, sku_root = ?, name = ?, slug = ?, description_md = ?, status = ?, sort = ?, updated_at = ?
             WHERE public_id = ?',
            [$type, $skuRoot, $name, $slug, $descriptionMd, $status, $sort, Clock::nowUtcSeconds(), $publicId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::run(
            'SELECT id, public_id, product_type, sku_root, name, slug, status, sort FROM products ORDER BY sort ASC, name ASC'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function findByPublicId(string $publicId): ?array
    {
        $row = Db::run(
            'SELECT id, public_id, product_type, sku_root, name, slug, description_md, status, sort FROM products WHERE public_id = ? LIMIT 1',
            [$publicId]
        )->fetch();
        return $row ?: null;
    }

    public static function skuRootExists(string $skuRoot, ?int $exceptId = null): bool
    {
        $row = Db::run('SELECT id FROM products WHERE sku_root = ? LIMIT 1', [$skuRoot])->fetch();
        return $row !== false && (int) $row['id'] !== (int) $exceptId;
    }

    public static function slugify(string $name): string
    {
        $s = strtolower(trim($name));
        $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-') ?: 'produkt';
    }
}
