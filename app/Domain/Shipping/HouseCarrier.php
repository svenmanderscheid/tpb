<?php
declare(strict_types=1);

namespace Tpb\Domain\Shipping;

/**
 * Eigenlieferung / Hausversand (DECISIONS #28): kein externer Dienstleister, kein
 * Tracking, Kosten = manuell erfasster Betrag der Sendung. Liefert kein eigenes
 * Label – ShippingService rendert das Hausetikett. Platzhalter, bis echte Carrier
 * (günstigster Anbieter) angebunden werden.
 */
final class HouseCarrier implements CarrierAdapter
{
    public function code(): string
    {
        return 'house';
    }

    public function name(): string
    {
        return 'Eigenlieferung';
    }

    /** @param array<string,mixed> $shipment */
    public function quote(array $shipment): int
    {
        return (int) ($shipment['shipping_cost_cents'] ?? 0);
    }

    public function providesLabel(): bool
    {
        return false;
    }

    /** @param array<string,mixed> $shipment */
    public function createLabel(array $shipment): array
    {
        return ['tracking_ref' => null, 'pdf' => null];
    }
}
