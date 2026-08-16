<?php
declare(strict_types=1);

namespace Tpb\Domain\Order;

use Tpb\Core\Db;
use Tpb\Domain\Pricing\Breakdown;
use Tpb\Domain\Pricing\PriceBook;

/**
 * Baut den unveränderlichen Auftrags-/Angebots-Snapshot (Kunde + Positionen + Summen).
 * Gemeinsame Quelle für Angebotsannahme (Pfad A) und Shop-Checkout (Pfad B): dieselben
 * Positions- und Summenwerte, immer serverseitig aus dem Breakdown (§6).
 */
final class OrderSnapshot
{
    /**
     * @param array<string,mixed> $payload   ConfigurationRepo::loadByPublicId (externe Schlüssel)
     * @param array<string,mixed> $resolved  ConfigMapper::resolve (interne IDs)
     * @param array<string,mixed> $customer
     * @return array<string,mixed>
     */
    public static function build(array $payload, array $resolved, Breakdown $breakdown, PriceBook $pb, array $customer): array
    {
        $names = self::productNames(array_map(static fn ($it) => (int) $it['product_id'], $resolved['items']));

        $lines = [];
        $orderItems = [];
        $pos = 0;
        foreach ($resolved['items'] as $i => $rit) {
            $pit = $payload['items'][$i];
            $b = $breakdown->items[$i];
            $prod = $names[(int) $rit['product_id']] ?? ['name' => 'Position', 'sku_root' => null];

            $qty = (int) $b['qty'];
            $unitPiece = (int) $b['unit_base_cents'] + (int) $b['piece_surcharge_cents'];
            $productLine = $qty * $unitPiece;

            $desc = (string) $prod['name'];
            if (($rit['type'] ?? 'configured') === 'configured') {
                $extra = [];
                if (!empty($rit['technique_code'])) {
                    $extra[] = (string) $rit['technique_code'];
                }
                $placements = [];
                foreach ($pit['layers'] ?? [] as $l) {
                    $placements[(string) $l['placement_code']] = true;
                }
                if ($placements !== []) {
                    $extra[] = count($placements) . ' Position(en)';
                }
                if ($extra !== []) {
                    $desc .= ' (' . implode(', ', $extra) . ')';
                }
            }

            $lines[] = ['pos_no' => ++$pos, 'sku' => $prod['sku_root'], 'description' => $desc, 'qty' => $qty, 'unit_cents' => $unitPiece, 'line_cents' => $productLine];

            $orderItems[] = [
                'type'        => (string) ($rit['type'] ?? 'configured'),
                'product_id'  => (int) $rit['product_id'],
                'sku'         => $prod['sku_root'],
                'description' => $desc,
                'qty'         => $qty,
                'unit_cents'  => $unitPiece,
                'line_cents'  => $productLine,
                'config'      => $pit,
                'units'       => array_map(static fn ($u) => [
                    'variant_sku' => (string) $u['variant_sku'],
                    'name'        => $u['name'] ?? null,
                    'number'      => $u['number'] ?? null,
                ], $pit['units'] ?? []),
            ];

            $perso = (int) $b['personalisation_cents'];
            if ($perso > 0) {
                $persoCount = 0;
                foreach ($pit['units'] ?? [] as $u) {
                    if (trim((string) ($u['name'] ?? '')) !== '' || trim((string) ($u['number'] ?? '')) !== '') {
                        $persoCount++;
                    }
                }
                $unit = $persoCount > 0 ? intdiv($perso, $persoCount) : $perso;
                $lines[] = ['pos_no' => ++$pos, 'sku' => null, 'description' => 'Personalisierung (Name/Nummer) – ' . (string) $prod['name'], 'qty' => max(1, $persoCount), 'unit_cents' => $unit, 'line_cents' => $perso];
            }
        }

        if ($breakdown->setupsCents > 0) {
            $lines[] = ['pos_no' => ++$pos, 'sku' => null, 'description' => 'Einrichtung (Setup je Motiv)', 'qty' => 1, 'unit_cents' => $breakdown->setupsCents, 'line_cents' => $breakdown->setupsCents];
        }
        if ($breakdown->filePrepCents > 0) {
            $lines[] = ['pos_no' => ++$pos, 'sku' => null, 'description' => 'Dateiaufbereitung', 'qty' => 1, 'unit_cents' => $breakdown->filePrepCents, 'line_cents' => $breakdown->filePrepCents];
        }
        if ($breakdown->expressCents > 0) {
            $lines[] = ['pos_no' => ++$pos, 'sku' => null, 'description' => 'Express-Zuschlag', 'qty' => 1, 'unit_cents' => $breakdown->expressCents, 'line_cents' => $breakdown->expressCents];
        }

        return [
            'customer' => [
                'public_id'    => (string) $customer['public_id'],
                'type'         => (string) $customer['type'],
                'company_name' => $customer['company_name'] ?? null,
                'first_name'   => (string) $customer['first_name'],
                'last_name'    => (string) $customer['last_name'],
                'email'        => (string) $customer['email'],
                'phone'        => $customer['phone'] ?? null,
                'lang'         => (string) $customer['lang'],
                'billing'      => [
                    'street'  => $customer['billing_street'] ?? null,
                    'zip'     => $customer['billing_zip'] ?? null,
                    'city'    => $customer['billing_city'] ?? null,
                    'country' => $customer['billing_country'] ?? null,
                ],
            ],
            'currency'           => $pb->currency,
            'price_book_version' => $pb->version,
            'lines'              => $lines,
            'items'              => $orderItems,
            'totals'             => [
                'subtotal_cents'  => $breakdown->subtotalCents,
                'setups_cents'    => $breakdown->setupsCents,
                'fileprep_cents'  => $breakdown->filePrepCents,
                'express_cents'   => $breakdown->expressCents,
                'shipping_cents'  => $breakdown->shippingCents,
                'total_cents'     => $breakdown->totalCents,
                'floor_cents'     => $breakdown->floorCents,
                'below_floor'     => $breakdown->belowFloor,
                'below_min_order' => $breakdown->belowMinOrder,
                'calc_hash'       => $breakdown->calcHash,
            ],
        ];
    }

    /**
     * @param array<int,int> $productIds
     * @return array<int,array{name:string,sku_root:?string}>
     */
    private static function productNames(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter($productIds)));
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach (Db::run("SELECT id, name, sku_root FROM products WHERE id IN ({$in})", $ids)->fetchAll() as $r) {
            $out[(int) $r['id']] = ['name' => (string) $r['name'], 'sku_root' => (string) $r['sku_root']];
        }
        return $out;
    }
}
