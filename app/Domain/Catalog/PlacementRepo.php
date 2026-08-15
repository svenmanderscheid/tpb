<?php
declare(strict_types=1);

namespace Tpb\Domain\Catalog;

use Tpb\Core\Clock;
use Tpb\Core\Db;

/**
 * Druckpositionen je Produkt in realen Millimetern (§5.3/§5.4).
 */
final class PlacementRepo
{
    public const SIDES = ['front', 'back', 'sleeve_l', 'sleeve_r', 'neck'];

    /**
     * @param array<string,mixed> $dims  optionale Maße: area_x_mm, area_y_mm, max_w_mm, max_h_mm, preset_x_mm, preset_y_mm
     */
    public static function create(int $productId, string $side, string $code, string $name, array $dims, bool $isPreset): int
    {
        $now = Clock::nowUtcSeconds();
        Db::run(
            'INSERT INTO placements
                (product_id, side, code, name, area_x_mm, area_y_mm, max_w_mm, max_h_mm, preset_x_mm, preset_y_mm, is_preset, sort, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [
                $productId, $side, $code, $name,
                $dims['area_x_mm'] ?? null, $dims['area_y_mm'] ?? null,
                $dims['max_w_mm'] ?? null, $dims['max_h_mm'] ?? null,
                $dims['preset_x_mm'] ?? null, $dims['preset_y_mm'] ?? null,
                $isPreset ? 1 : 0, $now, $now,
            ]
        );
        return (int) Db::pdo()->lastInsertId();
    }

    public static function delete(int $id, int $productId): void
    {
        Db::run('DELETE FROM placements WHERE id = ? AND product_id = ?', [$id, $productId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function listByProduct(int $productId): array
    {
        return Db::run(
            'SELECT id, side, code, name, max_w_mm, max_h_mm, is_preset, sort FROM placements WHERE product_id = ? ORDER BY sort ASC, id ASC',
            [$productId]
        )->fetchAll();
    }

    public static function codeExistsForProduct(int $productId, string $code): bool
    {
        return Db::run(
            'SELECT id FROM placements WHERE product_id = ? AND code = ? LIMIT 1',
            [$productId, $code]
        )->fetch() !== false;
    }
}
