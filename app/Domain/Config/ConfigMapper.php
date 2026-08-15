<?php
declare(strict_types=1);

namespace Tpb\Domain\Config;

use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\TechniqueRepo;
use Tpb\Domain\Catalog\VariantRepo;
use Tpb\Domain\File\AssetRepo;

/**
 * Bildet die öffentliche Konfigurations-Nutzlast (public_id/SKU/Code) auf eine
 * intern aufgelöste Struktur ab. Positionen und Motive werden serverseitig aus
 * den Layern abgeleitet (§6.3/§6.6) – der Client bestimmt sie nicht.
 */
final class ConfigMapper
{
    private const MAX_ITEMS = 50;
    private const MAX_SIZES = 60;
    private const MAX_LAYERS = 40;
    private const MAX_UNITS = 500;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed> Aufgelöste Konfiguration (interne IDs + abgeleitete Werte)
     */
    public static function resolve(array $payload): array
    {
        $inItems = $payload['items'] ?? null;
        if (!is_array($inItems) || $inItems === []) {
            throw new \InvalidArgumentException('Keine Positionen in der Anfrage.');
        }
        if (count($inItems) > self::MAX_ITEMS) {
            throw new \InvalidArgumentException('Zu viele Positionen.');
        }

        $items = [];
        foreach ($inItems as $pos => $in) {
            if (!is_array($in)) {
                throw new \InvalidArgumentException('Ungültige Position.');
            }
            $product = ProductRepo::findByPublicId((string) ($in['product'] ?? ''));
            if ($product === null) {
                throw new \InvalidArgumentException('Unbekanntes Produkt.');
            }
            $productId = (int) $product['id'];
            $type = ($in['type'] ?? 'configured') === 'standard' ? 'standard' : 'configured';

            $techniqueCode = null;
            $techniqueId = null;
            if (!empty($in['technique_code'])) {
                $techniqueCode = strtoupper((string) $in['technique_code']);
                $techniqueId = TechniqueRepo::idByCode($techniqueCode);
                if ($techniqueId === null) {
                    throw new \InvalidArgumentException('Unbekannte Technik.');
                }
            }

            $items[] = [
                'pos_no'         => $pos + 1,
                'product_id'     => $productId,
                'product_public' => (string) $product['public_id'],
                'type'           => $type,
                'technique_id'   => $techniqueId,
                'technique_code' => $techniqueCode,
                'comment'        => self::nullStr($in['comment'] ?? null, 255),
                'sizes'          => self::resolveSizes($in['sizes'] ?? [], $productId),
                'layers'         => self::resolveLayers($in['layers'] ?? [], $productId),
                'units'          => self::resolveUnits($in['units'] ?? [], $productId),
                'extra_colors'   => max(0, (int) ($in['extra_colors'] ?? 0)),
            ];
        }

        return [
            'express'  => (bool) ($payload['express'] ?? false),
            'fileprep' => (bool) ($payload['fileprep'] ?? false),
            'items'    => $items,
        ];
    }

    /**
     * Erzeugt aus der aufgelösten Struktur die Eingabe für die PriceEngine.
     * @param array<string,mixed> $resolved
     * @return array<string,mixed>
     */
    public static function toEngineConfig(array $resolved): array
    {
        $items = [];
        foreach ($resolved['items'] as $it) {
            // Positionen = distinkte belegte Placements; Motive = distinkte Logo-Asset-Hashes.
            $placements = [];
            $motifs = [];
            foreach ($it['layers'] as $layer) {
                $placements[$layer['placement_id']] = true;
                if ($layer['layer_type'] === 'logo' && $layer['asset_sha256'] !== null) {
                    $motifs[$layer['asset_sha256']] = true;
                }
            }
            $items[] = [
                'product_id'     => $it['product_id'],
                'type'           => $it['type'],
                'technique_code' => $it['technique_code'],
                'technique_id'   => $it['technique_id'],
                'positions'      => count($placements),
                'extra_colors'   => $it['extra_colors'],
                'sizes'          => array_map(static fn ($s) => ['variant_id' => $s['variant_id'], 'qty' => $s['qty']], $it['sizes']),
                'units'          => array_map(static fn ($u) => ['name' => $u['name'], 'number' => $u['number']], $it['units']),
                'motifs'         => array_keys($motifs),
            ];
        }
        return ['express' => $resolved['express'], 'fileprep' => $resolved['fileprep'], 'items' => $items];
    }

    /**
     * @param mixed $sizesIn
     * @return array<int,array<string,mixed>>
     */
    private static function resolveSizes(mixed $sizesIn, int $productId): array
    {
        if (!is_array($sizesIn) || $sizesIn === [] || count($sizesIn) > self::MAX_SIZES) {
            throw new \InvalidArgumentException('Ungültige Größen/Mengen.');
        }
        $out = [];
        foreach ($sizesIn as $s) {
            $variant = VariantRepo::findBySku((string) ($s['variant_sku'] ?? ''));
            if ($variant === null || $variant['product_id'] !== $productId) {
                throw new \InvalidArgumentException('Unbekannte oder produktfremde Variante.');
            }
            $qty = (int) ($s['qty'] ?? 0);
            if ($qty <= 0) {
                throw new \InvalidArgumentException('Menge muss größer als 0 sein.');
            }
            $out[] = ['variant_id' => $variant['id'], 'variant_sku' => (string) $s['variant_sku'], 'qty' => $qty];
        }
        return $out;
    }

    /**
     * @param mixed $layersIn
     * @return array<int,array<string,mixed>>
     */
    private static function resolveLayers(mixed $layersIn, int $productId): array
    {
        if (!is_array($layersIn)) {
            return [];
        }
        if (count($layersIn) > self::MAX_LAYERS) {
            throw new \InvalidArgumentException('Zu viele Ebenen.');
        }
        $out = [];
        $no = 0;
        foreach ($layersIn as $l) {
            $placement = self::placementForProduct($productId, (string) ($l['placement_code'] ?? ''));
            $type = ($l['layer_type'] ?? 'logo') === 'text' ? 'text' : 'logo';

            $assetId = null;
            $assetSha = null;
            if ($type === 'logo' && !empty($l['asset_public_id'])) {
                $asset = AssetRepo::findByPublicId((string) $l['asset_public_id']);
                if ($asset === null || $asset['security_status'] !== 'clean') {
                    throw new \InvalidArgumentException('Unbekanntes oder nicht freigegebenes Asset.');
                }
                $assetId = $asset['id'];
                $assetSha = $asset['sha256'];
            }

            $out[] = [
                'layer_no'     => ++$no,
                'placement_id' => $placement['id'],
                'placement_code' => (string) $placement['code'],
                'layer_type'   => $type,
                'asset_id'     => $assetId,
                'asset_sha256' => $assetSha,
                'text_content' => $type === 'text' ? self::nullStr($l['text_content'] ?? null, 255) : null,
                'color_name'   => self::nullStr($l['color_name'] ?? null, 48),
                'width_mm'     => self::mmOrNull($l['width_mm'] ?? null),
                'height_mm'    => self::mmOrNull($l['height_mm'] ?? null),
                'offset_x_mm'  => self::mmOrNull($l['offset_x_mm'] ?? null),
                'offset_y_mm'  => self::mmOrNull($l['offset_y_mm'] ?? null),
            ];
        }
        return $out;
    }

    /**
     * @param mixed $unitsIn
     * @return array<int,array<string,mixed>>
     */
    private static function resolveUnits(mixed $unitsIn, int $productId): array
    {
        if (!is_array($unitsIn)) {
            return [];
        }
        if (count($unitsIn) > self::MAX_UNITS) {
            throw new \InvalidArgumentException('Zu viele Einheiten.');
        }
        $out = [];
        $no = 0;
        foreach ($unitsIn as $u) {
            $variant = VariantRepo::findBySku((string) ($u['variant_sku'] ?? ''));
            if ($variant === null || $variant['product_id'] !== $productId) {
                throw new \InvalidArgumentException('Unbekannte oder produktfremde Variante (Einheit).');
            }
            $out[] = [
                'unit_no'    => ++$no,
                'variant_id' => $variant['id'],
                'name'       => self::nullStr($u['name'] ?? null, 60),
                'number'     => self::nullStr($u['number'] ?? null, 8),
                'note'       => self::nullStr($u['note'] ?? null, 255),
            ];
        }
        return $out;
    }

    /** @return array{id:int,code:string} */
    private static function placementForProduct(int $productId, string $code): array
    {
        foreach (\Tpb\Domain\Catalog\PlacementRepo::listByProduct($productId) as $p) {
            if ((string) $p['code'] === $code) {
                return ['id' => (int) $p['id'], 'code' => (string) $p['code']];
            }
        }
        throw new \InvalidArgumentException('Unbekannte Druckposition.');
    }

    private static function nullStr(mixed $v, int $max): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $t = trim($v);
        return $t === '' ? null : mb_substr($t, 0, $max);
    }

    private static function mmOrNull(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = str_replace(',', '.', (string) $v);
        if (!is_numeric($s)) {
            return null;
        }
        return number_format(round((float) $s, 1), 1, '.', '');
    }
}
