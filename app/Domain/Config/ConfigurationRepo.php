<?php
declare(strict_types=1);

namespace Tpb\Domain\Config;

use Tpb\Core\Clock;
use Tpb\Core\Db;
use Tpb\Core\Ulid;

/**
 * Persistenz von Konfigurations-Entwürfen (§5.4) inkl. price_calculations.
 * Positionsdaten in realen Millimetern. Entwurf über public_id ladbar (M2-DoD).
 */
final class ConfigurationRepo
{
    /**
     * Speichert einen Entwurf (neu oder Update über public_id). Gibt public_id + id.
     *
     * @param array<string,mixed> $resolved  Ergebnis von ConfigMapper::resolve()
     * @param array<string,mixed> $meta       price_book_id, cost_version_id, guest_email, guest_name, note, public_id?
     * @return array{public_id:string,id:int}
     */
    public static function save(array $resolved, array $meta): array
    {
        return Db::tx(function () use ($resolved, $meta): array {
            $now = Clock::nowUtcSeconds();
            $existingPublic = isset($meta['public_id']) ? (string) $meta['public_id'] : '';

            if ($existingPublic !== '') {
                $row = Db::run("SELECT id FROM configurations WHERE public_id = ? AND status = 'draft' LIMIT 1", [$existingPublic])->fetch();
                if ($row === false) {
                    throw new \InvalidArgumentException('Entwurf nicht gefunden oder nicht mehr änderbar.');
                }
                $configId = (int) $row['id'];
                self::deleteChildren($configId);
                Db::run(
                    'UPDATE configurations SET guest_email = ?, guest_name = ?, note = ?, express = ?, price_book_id = ?, cost_version_id = ?, updated_at = ? WHERE id = ?',
                    [$meta['guest_email'] ?? null, $meta['guest_name'] ?? null, $meta['note'] ?? null, !empty($resolved['express']) ? 1 : 0, $meta['price_book_id'] ?? null, $meta['cost_version_id'] ?? null, $now, $configId]
                );
                $publicId = $existingPublic;
            } else {
                $publicId = Ulid::generate();
                Db::run(
                    'INSERT INTO configurations (public_id, guest_email, guest_name, status, price_book_id, cost_version_id, express, note, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$publicId, $meta['guest_email'] ?? null, $meta['guest_name'] ?? null, 'draft', $meta['price_book_id'] ?? null, $meta['cost_version_id'] ?? null, !empty($resolved['express']) ? 1 : 0, $meta['note'] ?? null, $now, $now]
                );
                $configId = (int) Db::pdo()->lastInsertId();
            }

            foreach ($resolved['items'] as $item) {
                Db::run(
                    'INSERT INTO configuration_items (configuration_id, pos_no, item_type, product_id, technique_id, comment, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$configId, $item['pos_no'], $item['type'] === 'standard' ? 'standard' : 'configured', $item['product_id'], $item['technique_id'], $item['comment'], $now, $now]
                );
                $itemId = (int) Db::pdo()->lastInsertId();

                foreach ($item['sizes'] as $s) {
                    Db::run(
                        'INSERT INTO configuration_item_sizes (item_id, variant_id, qty, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                        [$itemId, $s['variant_id'], $s['qty'], $now, $now]
                    );
                }
                foreach ($item['layers'] as $l) {
                    Db::run(
                        'INSERT INTO configuration_layers (item_id, placement_id, layer_no, layer_type, asset_id, text_content, color_name, width_mm, height_mm, offset_x_mm, offset_y_mm, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$itemId, $l['placement_id'], $l['layer_no'], $l['layer_type'], $l['asset_id'], $l['text_content'], $l['color_name'], $l['width_mm'], $l['height_mm'], $l['offset_x_mm'], $l['offset_y_mm'], $now, $now]
                    );
                }
                foreach ($item['units'] as $u) {
                    Db::run(
                        'INSERT INTO configuration_units (item_id, unit_no, variant_id, name, number, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        [$itemId, $u['unit_no'], $u['variant_id'], $u['name'], $u['number'], $u['note'], $now, $now]
                    );
                }
            }

            return ['public_id' => $publicId, 'id' => $configId];
        });
    }

    public static function persistCalculation(int $configId, int $priceBookId, int $costVersionId, string $inputHash, string $breakdownJson, int $totalCents, int $floorCents, bool $belowFloor, string $calcHash): void
    {
        Db::run(
            'INSERT INTO price_calculations (configuration_id, price_book_id, cost_version_id, input_hash, breakdown_json, total_cents, floor_cents, below_floor, calc_hash, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$configId, $priceBookId, $costVersionId, $inputHash, $breakdownJson, $totalCents, $floorCents, $belowFloor ? 1 : 0, $calcHash, Clock::nowUtcSeconds()]
        );
    }

    /**
     * Lädt einen Entwurf als UI-Payload (externe Schlüssel) + jüngste Berechnung.
     * @return array<string,mixed>|null
     */
    public static function loadByPublicId(string $publicId): ?array
    {
        $config = Db::run(
            'SELECT id, public_id, status, express, guest_email, guest_name, note FROM configurations WHERE public_id = ? LIMIT 1',
            [$publicId]
        )->fetch();
        if (!$config) {
            return null;
        }
        $configId = (int) $config['id'];

        $items = [];
        foreach (Db::run(
            'SELECT ci.id, ci.pos_no, ci.item_type, ci.comment, p.public_id AS product_public, t.code AS technique_code
             FROM configuration_items ci
             JOIN products p ON p.id = ci.product_id
             LEFT JOIN techniques t ON t.id = ci.technique_id
             WHERE ci.configuration_id = ? ORDER BY ci.pos_no ASC',
            [$configId]
        )->fetchAll() as $it) {
            $itemId = (int) $it['id'];
            $items[] = [
                'product'        => (string) $it['product_public'],
                'type'           => (string) $it['item_type'],
                'technique_code' => $it['technique_code'] !== null ? (string) $it['technique_code'] : null,
                'comment'        => $it['comment'] !== null ? (string) $it['comment'] : null,
                'sizes'          => self::loadSizes($itemId),
                'layers'         => self::loadLayers($itemId),
                'units'          => self::loadUnits($itemId),
            ];
        }

        return [
            'public_id'  => (string) $config['public_id'],
            'status'     => (string) $config['status'],
            'express'    => ((int) $config['express']) === 1,
            'guest_email' => $config['guest_email'] !== null ? (string) $config['guest_email'] : null,
            'guest_name' => $config['guest_name'] !== null ? (string) $config['guest_name'] : null,
            'note'       => $config['note'] !== null ? (string) $config['note'] : null,
            'items'      => $items,
            'calculation' => self::latestCalculation($configId),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function latestCalculation(int $configId): ?array
    {
        $row = Db::run(
            'SELECT total_cents, floor_cents, below_floor, calc_hash, breakdown_json FROM price_calculations WHERE configuration_id = ? ORDER BY id DESC LIMIT 1',
            [$configId]
        )->fetch();
        if (!$row) {
            return null;
        }
        return [
            'total_cents' => (int) $row['total_cents'],
            'floor_cents' => (int) $row['floor_cents'],
            'below_floor' => ((int) $row['below_floor']) === 1,
            'calc_hash'   => (string) $row['calc_hash'],
            'breakdown'   => json_decode((string) $row['breakdown_json'], true),
        ];
    }

    private static function deleteChildren(int $configId): void
    {
        $itemIds = Db::run('SELECT id FROM configuration_items WHERE configuration_id = ?', [$configId])->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($itemIds as $itemId) {
            Db::run('DELETE FROM configuration_units WHERE item_id = ?', [(int) $itemId]);
            Db::run('DELETE FROM configuration_layers WHERE item_id = ?', [(int) $itemId]);
            Db::run('DELETE FROM configuration_item_sizes WHERE item_id = ?', [(int) $itemId]);
        }
        Db::run('DELETE FROM configuration_items WHERE configuration_id = ?', [$configId]);
    }

    /** @return array<int,array<string,mixed>> */
    private static function loadSizes(int $itemId): array
    {
        $out = [];
        foreach (Db::run(
            'SELECT v.sku AS variant_sku, s.qty FROM configuration_item_sizes s JOIN product_variants v ON v.id = s.variant_id WHERE s.item_id = ? ORDER BY s.id ASC',
            [$itemId]
        )->fetchAll() as $r) {
            $out[] = ['variant_sku' => (string) $r['variant_sku'], 'qty' => (int) $r['qty']];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private static function loadLayers(int $itemId): array
    {
        $out = [];
        foreach (Db::run(
            'SELECT pl.code AS placement_code, l.layer_type, a.public_id AS asset_public_id, l.text_content, l.color_name, l.width_mm, l.height_mm, l.offset_x_mm, l.offset_y_mm
             FROM configuration_layers l
             JOIN placements pl ON pl.id = l.placement_id
             LEFT JOIN assets a ON a.id = l.asset_id
             WHERE l.item_id = ? ORDER BY l.layer_no ASC',
            [$itemId]
        )->fetchAll() as $r) {
            $out[] = [
                'placement_code'  => (string) $r['placement_code'],
                'layer_type'      => (string) $r['layer_type'],
                'asset_public_id' => $r['asset_public_id'] !== null ? (string) $r['asset_public_id'] : null,
                'text_content'    => $r['text_content'] !== null ? (string) $r['text_content'] : null,
                'color_name'      => $r['color_name'] !== null ? (string) $r['color_name'] : null,
                'width_mm'        => $r['width_mm'],
                'height_mm'       => $r['height_mm'],
                'offset_x_mm'     => $r['offset_x_mm'],
                'offset_y_mm'     => $r['offset_y_mm'],
            ];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private static function loadUnits(int $itemId): array
    {
        $out = [];
        foreach (Db::run(
            'SELECT v.sku AS variant_sku, u.name, u.number, u.note FROM configuration_units u JOIN product_variants v ON v.id = u.variant_id WHERE u.item_id = ? ORDER BY u.unit_no ASC',
            [$itemId]
        )->fetchAll() as $r) {
            $out[] = [
                'variant_sku' => (string) $r['variant_sku'],
                'name'        => $r['name'] !== null ? (string) $r['name'] : null,
                'number'      => $r['number'] !== null ? (string) $r['number'] : null,
                'note'        => $r['note'] !== null ? (string) $r['note'] : null,
            ];
        }
        return $out;
    }
}
