<?php
declare(strict_types=1);

namespace Tpb\Domain\Shipping;

/**
 * Abstraktion eines Versanddienstleisters (Owner-Entscheidung 2026-08-16, DECISIONS #28).
 * Standardversand nimmt den GÜNSTIGSTEN Anbieter (Registry::cheapest); Premium ist
 * Eigenlieferung über den HouseCarrier. Echte Carrier (Post LU/DHL/GLS) implementieren
 * dieselbe Schnittstelle, sobald API-Zugang besteht – dann liefern sie Tracking + eigenes
 * Label; bis dahin rendert der Aufrufer das Hausetikett.
 */
interface CarrierAdapter
{
    public function code(): string;

    public function name(): string;

    /**
     * Versandkosten in Cents für diese Sendung.
     * @param array<string,mixed> $shipment
     */
    public function quote(array $shipment): int;

    /** true, wenn der Carrier ein eigenes Label + Tracking liefert (echte Anbindung). */
    public function providesLabel(): bool;

    /**
     * Erzeugt (bei echten Carriern) Tracking + Label-PDF. Der HouseCarrier gibt kein
     * eigenes Label zurück – dann rendert ShippingService das Hausetikett.
     *
     * @param array<string,mixed> $shipment
     * @return array{tracking_ref:?string,pdf:?string}
     */
    public function createLabel(array $shipment): array;
}
