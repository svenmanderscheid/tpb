<?php
declare(strict_types=1);

namespace Tpb\Domain\Pricing;

use Tpb\Core\Canonical;
use Tpb\Core\Money;

/**
 * Preis-Engine (§6). Deterministisch, reine Int-Arithmetik, keine DB-Zugriffe.
 * Eingabe: normalisierte Konfiguration + PriceBook + CostVersion (Value Objects).
 *
 * Config-Shape:
 *   [
 *     'express' => bool, 'fileprep' => bool,
 *     'items' => [ [
 *        'product_id' => int, 'type' => 'configured'|'standard',
 *        'technique_code' => ?string, 'technique_id' => ?int,
 *        'positions' => int, 'extra_colors' => int,
 *        'sizes' => [ ['variant_id'=>int,'qty'=>int], ... ],
 *        'units' => [ ['name'=>?string,'number'=>?string], ... ],
 *        'motifs' => [ sha256, ... ],   // nur Logo-Layer; Textlayer zählen nicht
 *     ], ... ],
 *   ]
 */
final class PriceEngine
{
    /**
     * @param array<string,mixed> $config
     */
    public static function calculate(array $config, PriceBook $pb, CostVersion $cv): Breakdown
    {
        $items = $config['items'] ?? [];
        $express = (bool) ($config['express'] ?? false);
        $fileprep = (bool) ($config['fileprep'] ?? false);

        $totalQtyAll = 0;
        $motifs = [];
        foreach ($items as $item) {
            $totalQtyAll += self::qtyTotal($item);
            if (($item['type'] ?? 'configured') !== 'standard') {
                foreach ($item['motifs'] ?? [] as $hash) {
                    $motifs[$hash] = true;
                }
            }
        }

        $itemsOut = [];
        $sumLines = 0;
        foreach ($items as $idx => $item) {
            $line = self::lineForItem($item, $pb, $itemsOut, $idx);
            $sumLines += $line;
        }

        // Einrichtung je distinktem Motiv, Waiver ab Gesamtmenge (§6.6).
        $motifCount = count($motifs);
        $waiverQty = $pb->param('SETUP_FEE_WAIVER_QTY');
        $waived = $waiverQty !== null && $totalQtyAll >= $waiverQty;
        $setups = $waived ? 0 : $motifCount * $pb->paramOr('SETUP_FEE_CENTS_PER_MOTIF');

        $filePrep = $fileprep ? $pb->paramOr('FILEPREP_CENTS') : 0;
        $subtotal = $sumLines + $setups + $filePrep;
        $expressCents = $express ? Money::bp($subtotal, $pb->paramOr('EXPRESS_BPS')) : 0;
        $total = $subtotal + $expressCents; // Versand im MVP: Abholung = 0

        $minOrder = $pb->paramOr('MIN_ORDER_CENTS');
        $belowMinOrder = $total < $minOrder;

        $floor = self::floorTotal($items, $cv);
        $belowFloor = $total < $floor;

        $calcHash = Canonical::hash([
            'config'            => $config,
            'price_book_version' => $pb->version,
            'cost_version'      => $cv->version,
        ]);

        return new Breakdown(
            items: $itemsOut,
            setupsCents: $setups,
            filePrepCents: $filePrep,
            subtotalCents: $subtotal,
            expressCents: $expressCents,
            shippingCents: 0,
            totalCents: $total,
            floorCents: $floor,
            belowMinOrder: $belowMinOrder,
            belowFloor: $belowFloor,
            calcHash: $calcHash,
        );
    }

    /**
     * @param array<string,mixed> $item
     * @param array<int,array<string,mixed>> $itemsOut
     */
    private static function lineForItem(array $item, PriceBook $pb, array &$itemsOut, int $idx): int
    {
        $productId = (int) $item['product_id'];
        $qty = self::qtyTotal($item);
        $type = $item['type'] ?? 'configured';

        if ($qty <= 0) {
            throw new PricingException("Position {$idx} hat keine Menge.");
        }

        $unitBase = $pb->unitTier($productId, $qty);

        if ($type === 'standard') {
            $line = $qty * $unitBase;
            $itemsOut[] = [
                'type' => 'standard', 'product_id' => $productId, 'qty' => $qty,
                'unit_base_cents' => $unitBase, 'piece_surcharge_cents' => 0,
                'personalisation_cents' => 0, 'line_cents' => $line,
            ];
            return $line;
        }

        // Konfigurierter Artikel: Stückzuschläge (§6.3)
        $techCode = $item['technique_code'] ?? null;
        $tech = $techCode !== null && $techCode !== ''
            ? $pb->paramOr('TECH_SURCHARGE_' . strtoupper((string) $techCode) . '_CENTS')
            : 0;
        $positions = max(0, (int) ($item['positions'] ?? 0));
        $extraPos = max(0, $positions - 1) * $pb->paramOr('EXTRA_POSITION_CENTS');
        $extraColor = max(0, (int) ($item['extra_colors'] ?? 0)) * $pb->paramOr('EXTRA_COLOR_CENTS');
        $pieceSurcharge = $tech + $extraPos + $extraColor;

        // Personalisierung: je Unit mit Name ODER Nummer einmal (§6.4)
        $persoUnits = 0;
        foreach ($item['units'] ?? [] as $u) {
            $name = trim((string) ($u['name'] ?? ''));
            $number = trim((string) ($u['number'] ?? ''));
            if ($name !== '' || $number !== '') {
                $persoUnits++;
            }
        }
        $perso = $persoUnits * $pb->paramOr('NAME_NUMBER_CENTS');

        $line = $qty * ($unitBase + $pieceSurcharge) + $perso;
        $itemsOut[] = [
            'type' => 'configured', 'product_id' => $productId, 'qty' => $qty,
            'unit_base_cents' => $unitBase, 'piece_surcharge_cents' => $pieceSurcharge,
            'personalisation_cents' => $perso, 'line_cents' => $line,
        ];
        return $line;
    }

    /**
     * Interne Untergrenze (§6). Annahmen zur ref_type-Zuordnung siehe CostVersion.
     * @param array<int,array<string,mixed>> $items
     */
    private static function floorTotal(array $items, CostVersion $cv): int
    {
        $selbstGesamt = 0;
        foreach ($items as $item) {
            $qty = self::qtyTotal($item);
            if ($qty <= 0) {
                continue;
            }
            $productId = (int) $item['product_id'];
            $techId = $item['technique_id'] ?? null;
            $techCandidates = [['technique', $techId], ['product', $productId]];

            $setupMin = $cv->itemAny($techCandidates, 'SETUP_MIN');
            $unitMin = $cv->itemAny($techCandidates, 'UNIT_MIN');
            $machineMin = $cv->itemAny($techCandidates, 'MACHINE_MIN');

            // Arbeitskosten je Stück (Setup über Menge amortisiert), round-half-up.
            $laborCost = self::roundDiv($cv->laborRateCentsH * ($setupMin + $unitMin * $qty), 60 * $qty);
            $machineCost = self::roundDiv($cv->machineRateCentsH * $machineMin, 60);

            foreach ($item['sizes'] ?? [] as $s) {
                $variantId = (int) $s['variant_id'];
                $vqty = (int) $s['qty'];
                if ($vqty <= 0) {
                    continue;
                }
                $varCandidates = [['variant', $variantId], ['product', $productId]];
                $blank = $cv->itemAny($varCandidates, 'BLANK_CENTS');
                $material = $cv->itemAny($varCandidates, 'MATERIAL_CENTS');

                $selbstPiece = $blank + $material + $laborCost + $machineCost;
                $selbstPiece += Money::bp($selbstPiece, $cv->scrapBps);
                $selbstGesamt += $selbstPiece * $vqty;
            }
        }

        $den = 10000 - $cv->targetMarginBps;
        if ($den <= 0) {
            // Zielmarge >= 100 % ist unzulässig; ohne sinnvolle Untergrenze -> 0.
            return 0;
        }
        return Money::ceilDiv($selbstGesamt * 10000, $den);
    }

    /** @param array<string,mixed> $item */
    private static function qtyTotal(array $item): int
    {
        $sum = 0;
        foreach ($item['sizes'] ?? [] as $s) {
            $sum += (int) ($s['qty'] ?? 0);
        }
        return $sum;
    }

    /** Ganzzahlige Division mit Round-half-up (nur nicht-negative Werte). */
    private static function roundDiv(int $num, int $den): int
    {
        if ($den <= 0) {
            throw new PricingException('Division durch 0 in der Untergrenzen-Rechnung.');
        }
        return intdiv(2 * $num + $den, 2 * $den);
    }
}
