<?php
declare(strict_types=1);

namespace Tpb\Http\Api;

use Tpb\Core\Response;
use Tpb\Domain\Catalog\ProductRepo;
use Tpb\Domain\Catalog\TechniqueRepo;
use Tpb\Domain\Catalog\VariantRepo;
use Tpb\Domain\Pricing\PriceEngine;
use Tpb\Domain\Pricing\PricingException;
use Tpb\Domain\Pricing\PricingRepo;

/**
 * Öffentlicher Live-Preis (§6, M2). Der Browser sendet NIE einen Betrag –
 * der Server rechnet immer serverseitig gegen das veröffentlichte Preisbuch.
 * Antwort ist reine Berechnung (keine Persistenz; price_calculations folgt mit
 * den Konfigurations-Tabellen in Migration 003).
 */
final class PriceController
{
    private const MAX_ITEMS = 50;
    private const MAX_SIZES = 60;

    /** @param array<string,string> $params */
    public function quote(array $params): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::json(['ok' => false, 'error' => 'Ungültige Anfrage (JSON erwartet).'], 400);
            return;
        }

        try {
            $config = $this->mapConfig($data);
        } catch (\InvalidArgumentException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
            return;
        }

        $pb = PricingRepo::activePriceBook();
        $cv = PricingRepo::activeCostVersion();
        if ($pb === null || $cv === null) {
            Response::json(['ok' => false, 'error' => 'Kein veröffentlichtes Preisbuch bzw. keine Kostenversion vorhanden.'], 409);
            return;
        }

        try {
            $breakdown = PriceEngine::calculate($config, $pb, $cv);
        } catch (PricingException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        }

        Response::json([
            'ok'              => true,
            'currency'        => $pb->currency,
            'price_book'      => $pb->version,
            'cost_version'    => $cv->version,
            'subtotal_cents'  => $breakdown->subtotalCents,
            'setups_cents'    => $breakdown->setupsCents,
            'express_cents'   => $breakdown->expressCents,
            'total_cents'     => $breakdown->totalCents,
            'floor_cents'     => $breakdown->floorCents,
            'below_min_order' => $breakdown->belowMinOrder,
            'below_floor'     => $breakdown->belowFloor,
            'calc_hash'       => $breakdown->calcHash,
            'items'           => $breakdown->items,
        ]);
    }

    /**
     * Bildet die öffentliche Anfrage (public_id/SKU/Code) auf die interne
     * Engine-Konfiguration (interne IDs) ab.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function mapConfig(array $data): array
    {
        $inItems = $data['items'] ?? null;
        if (!is_array($inItems) || $inItems === []) {
            throw new \InvalidArgumentException('Keine Positionen in der Anfrage.');
        }
        if (count($inItems) > self::MAX_ITEMS) {
            throw new \InvalidArgumentException('Zu viele Positionen.');
        }

        $items = [];
        foreach ($inItems as $in) {
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

            $sizesIn = $in['sizes'] ?? [];
            if (!is_array($sizesIn) || $sizesIn === [] || count($sizesIn) > self::MAX_SIZES) {
                throw new \InvalidArgumentException('Ungültige Größen/Mengen.');
            }
            $sizes = [];
            foreach ($sizesIn as $s) {
                $variant = VariantRepo::findBySku((string) ($s['variant_sku'] ?? ''));
                if ($variant === null || $variant['product_id'] !== $productId) {
                    throw new \InvalidArgumentException('Unbekannte oder produktfremde Variante.');
                }
                $qty = (int) ($s['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new \InvalidArgumentException('Menge muss größer als 0 sein.');
                }
                $sizes[] = ['variant_id' => $variant['id'], 'qty' => $qty];
            }

            $units = [];
            foreach ((array) ($in['units'] ?? []) as $u) {
                $units[] = [
                    'name'   => isset($u['name']) ? (string) $u['name'] : null,
                    'number' => isset($u['number']) ? (string) $u['number'] : null,
                ];
            }

            $motifs = [];
            foreach ((array) ($in['motifs'] ?? []) as $m) {
                if (is_string($m) && $m !== '') {
                    $motifs[] = $m;
                }
            }

            $items[] = [
                'product_id'     => $productId,
                'type'           => $type,
                'technique_code' => $techniqueCode,
                'technique_id'   => $techniqueId,
                'positions'      => max(0, (int) ($in['positions'] ?? 0)),
                'extra_colors'   => max(0, (int) ($in['extra_colors'] ?? 0)),
                'sizes'          => $sizes,
                'units'          => $units,
                'motifs'         => $motifs,
            ];
        }

        return [
            'express'  => (bool) ($data['express'] ?? false),
            'fileprep' => (bool) ($data['fileprep'] ?? false),
            'items'    => $items,
        ];
    }
}
