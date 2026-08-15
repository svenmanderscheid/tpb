<?php
declare(strict_types=1);

namespace Tpb\Http\Api;

use Tpb\Core\Response;
use Tpb\Domain\Config\ConfigMapper;
use Tpb\Domain\Pricing\PriceEngine;
use Tpb\Domain\Pricing\PricingException;
use Tpb\Domain\Pricing\PricingRepo;

/**
 * Öffentlicher Live-Preis (§6, M2). Der Browser sendet NIE einen Betrag –
 * der Server rechnet immer serverseitig gegen das veröffentlichte Preisbuch.
 * Reine Vorschau-Berechnung ohne Persistenz (Persistenz erfolgt beim Speichern
 * des Entwurfs über /api/config/save).
 */
final class PriceController
{
    /** @param array<string,string> $params */
    public function quote(array $params): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            Response::json(['ok' => false, 'error' => 'Ungültige Anfrage (JSON erwartet).'], 400);
            return;
        }

        try {
            $resolved = ConfigMapper::resolve($data);
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
            $breakdown = PriceEngine::calculate(ConfigMapper::toEngineConfig($resolved), $pb, $cv);
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
}
