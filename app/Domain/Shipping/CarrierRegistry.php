<?php
declare(strict_types=1);

namespace Tpb\Domain\Shipping;

/**
 * Registry der verfügbaren Carrier (DECISIONS #28). „Günstigster Anbieter" für den
 * Standardversand = Adapter mit dem niedrigsten quote(). Derzeit nur der HouseCarrier;
 * echte Carrier (Post LU/DHL/GLS) werden hier registriert, sobald API-Zugang besteht.
 */
final class CarrierRegistry
{
    /** @return array<string,CarrierAdapter> code => adapter */
    public static function all(): array
    {
        $adapters = [new HouseCarrier()];
        $out = [];
        foreach ($adapters as $a) {
            $out[$a->code()] = $a;
        }
        return $out;
    }

    public static function byCode(string $code): ?CarrierAdapter
    {
        return self::all()[$code] ?? null;
    }

    /**
     * Wählt den günstigsten Carrier für die Sendung (Standardversand).
     * @param array<string,mixed> $shipment
     */
    public static function cheapest(array $shipment): CarrierAdapter
    {
        $best = null;
        $bestQuote = PHP_INT_MAX;
        foreach (self::all() as $adapter) {
            $q = $adapter->quote($shipment);
            if ($q < $bestQuote) {
                $bestQuote = $q;
                $best = $adapter;
            }
        }
        return $best ?? new HouseCarrier();
    }
}
